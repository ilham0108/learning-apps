import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { CountdownTimer } from '@/components/exam-runner/countdown-timer';
import { QuestionNavigator } from '@/components/exam-runner/question-navigator';
import type { AnswerChange } from '@/components/exam-runner/question-view';
import {
    isAnswered,
    isFlagged,
    QuestionView,
} from '@/components/exam-runner/question-view';
import { SaveIndicator } from '@/components/exam-runner/save-indicator';
import { SubmitDialog } from '@/components/exam-runner/submit-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCountdown } from '@/hooks/use-countdown';
import type { SaveStatus } from '@/lib/autosave-queue';
import { AutosaveQueue, readPending } from '@/lib/autosave-queue';
import { jsonRequest } from '@/lib/http';
import { t } from '@/lib/i18n';
import {
    ErrorIcon,
    QuestionListIcon,
    SubmitIcon,
    TimerIcon,
} from '@/lib/icons';
import attempts from '@/routes/attempts';
import type { RunnerAnswer, RunnerState } from '@/types';

type Props = RunnerState & {
    exam: { title: string; assignment: string };
    sessionToken: string;
};

type Phase = 'active' | 'finished' | 'replaced';

type Rejection = { code?: string; result_url?: string };

const SYNC_INTERVAL_MS = 60_000;

const emptyAnswer: RunnerAnswer = {
    selected: [],
    text: null,
    flagged: false,
    revision: 0,
};

/**
 * Jawaban lokal yang belum terkirim (misalnya karena koneksi putus sebelum
 * refresh) menang atas jawaban server jika revisinya lebih baru.
 */
function mergeLocal(
    attemptId: string,
    server: Record<string, RunnerAnswer>,
): Record<string, RunnerAnswer> {
    const merged = { ...server };

    for (const [token, local] of Object.entries(readPending(attemptId))) {
        if (local.revision > (merged[token]?.revision ?? 0)) {
            merged[token] = { ...local };
        }
    }

    return merged;
}

export default function AttemptRunner({
    exam,
    sessionToken,
    attempt,
    items,
    answers: serverAnswers,
}: Props) {
    const forwardOnly = attempt.navigation === 'forward_only';
    const headers = useMemo(
        () => ({ 'X-Attempt-Session': sessionToken }),
        [sessionToken],
    );

    const [answers, setAnswers] = useState(() =>
        mergeLocal(attempt.id, serverAnswers),
    );
    const answersRef = useRef(answers);
    const [position, setPosition] = useState(() =>
        Math.min(Math.max(1, attempt.current_position), items.length),
    );
    const [clock, setClock] = useState({
        deadlineAt: attempt.deadline_at,
        serverNow: attempt.server_now,
    });
    const [phase, setPhase] = useState<Phase>('active');
    const [resultUrl, setResultUrl] = useState<string | null>(null);
    const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');
    const [pendingCount, setPendingCount] = useState(0);
    const [submitOpen, setSubmitOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [navigatorOpen, setNavigatorOpen] = useState(false);
    const queueRef = useRef<AutosaveQueue | null>(null);
    const active = phase === 'active';

    const finish = useCallback(
        (url: string | undefined) => {
            queueRef.current?.stop();
            queueRef.current?.clear();
            setResultUrl(url ?? attempts.result(attempt.id).url);
            setPhase('finished');
        },
        [attempt.id],
    );

    const handleRejection = useCallback(
        (data: unknown) => {
            const body = (data ?? {}) as Rejection;

            if (body.code === 'session_replaced') {
                queueRef.current?.stop();
                setPhase('replaced');

                return;
            }

            finish(body.result_url);
        },
        [finish],
    );

    // Antrean autosave per attempt. Jawaban yang tertinggal dari sesi sebelumnya dikirim ulang.
    useEffect(() => {
        const queue = new AutosaveQueue({
            attemptId: attempt.id,
            send: async (token, payload) => {
                const response = await jsonRequest<Rejection>(
                    'PUT',
                    attempts.answers.update([attempt.id, token]).url,
                    payload,
                    headers,
                );

                if (response.ok) {
                    return { kind: 'saved' };
                }

                if (
                    response.status === 422 ||
                    (response.status === 409 &&
                        response.data?.code === 'question_locked')
                ) {
                    return { kind: 'invalid' };
                }

                return response.status === 409
                    ? { kind: 'rejected', data: response.data }
                    : { kind: 'failed' };
            },
            onStatus: (status, count) => {
                setSaveStatus(status);
                setPendingCount(count);
            },
            onRejected: handleRejection,
            onInvalid: () => toast.error(t('candidate.runner.requestFailed')),
        });

        queueRef.current = queue;
        setPendingCount(queue.pendingCount);

        if (queue.pendingCount > 0) {
            void queue.flush();
        }

        const resend = () => void queue.flush();
        window.addEventListener('online', resend);

        return () => {
            window.removeEventListener('online', resend);
            queue.stop();
        };
    }, [attempt.id, headers, handleRejection]);

    const syncClock = useCallback(async () => {
        try {
            const response = await jsonRequest<{
                status: string;
                deadline_at: string;
                server_now: string;
                result_url?: string;
            }>('GET', attempts.clock(attempt.id).url);

            if (!response.ok) {
                return;
            }

            if (response.data.status !== 'in_progress') {
                finish(response.data.result_url);

                return;
            }

            setClock({
                deadlineAt: response.data.deadline_at,
                serverNow: response.data.server_now,
            });
        } catch {
            // Offline: timer tetap berjalan dari sinkronisasi terakhir.
        }
    }, [attempt.id, finish]);

    // Sinkronisasi jam berkala dan saat tab kembali aktif; perpindahan tab dicatat.
    useEffect(() => {
        if (!active) {
            return;
        }

        const interval = setInterval(() => void syncClock(), SYNC_INTERVAL_MS);
        const onVisibility = () => {
            const hidden = document.visibilityState === 'hidden';

            void jsonRequest(
                'POST',
                attempts.events.store(attempt.id).url,
                { type: hidden ? 'tab_hidden' : 'tab_visible' },
                headers,
            ).catch(() => undefined);

            if (hidden) {
                void queueRef.current?.flush();
            } else {
                void syncClock();
            }
        };

        document.addEventListener('visibilitychange', onVisibility);

        return () => {
            clearInterval(interval);
            document.removeEventListener('visibilitychange', onVisibility);
        };
    }, [active, attempt.id, headers, syncClock]);

    // Peringatan saat menutup tab dengan jawaban yang belum terkirim.
    useEffect(() => {
        if (!active || pendingCount === 0) {
            return;
        }

        const warn = (event: BeforeUnloadEvent) => event.preventDefault();
        window.addEventListener('beforeunload', warn);

        return () => window.removeEventListener('beforeunload', warn);
    }, [active, pendingCount]);

    const onExpire = useCallback(async () => {
        await queueRef.current?.flush();

        try {
            const response = await jsonRequest<Rejection>(
                'POST',
                attempts.submit(attempt.id).url,
                {},
                headers,
            );
            finish(response.data?.result_url);
        } catch {
            finish(undefined);
        }
    }, [attempt.id, finish, headers]);

    const remaining = useCountdown(clock.deadlineAt, clock.serverNow, () => {
        void onExpire();
    });

    const changeAnswer = (token: string, change: AnswerChange) => {
        if (!active) {
            return;
        }

        const current = answersRef.current[token] ?? emptyAnswer;
        const next = { ...current, ...change, revision: current.revision + 1 };
        answersRef.current = { ...answersRef.current, [token]: next };
        setAnswers(answersRef.current);
        queueRef.current?.queue(token, {
            revision: next.revision,
            selected: next.selected,
            text: next.text,
            flagged: next.flagged,
        });
    };

    const goTo = async (target: number) => {
        if (
            !active ||
            target === position ||
            target < 1 ||
            target > items.length ||
            (forwardOnly && target < position)
        ) {
            return;
        }

        const queue = queueRef.current;
        await queue?.flush();

        // Maju saja: soal ini terkunci setelah pindah, jadi jawabannya harus sudah tersimpan.
        if (forwardOnly && (queue?.pendingCount ?? 0) > 0) {
            toast.error(t('candidate.runner.requestFailed'));

            return;
        }

        setPosition(target);
        setNavigatorOpen(false);
        window.scrollTo({ top: 0 });

        try {
            const response = await jsonRequest<Rejection>(
                'POST',
                attempts.position(attempt.id).url,
                { position: target },
                headers,
            );

            if (
                response.status === 409 &&
                response.data?.code !== 'question_locked'
            ) {
                handleRejection(response.data);
            }
        } catch {
            // Posisi hanya untuk pemulihan setelah refresh; tidak menghalangi pengerjaan.
        }
    };

    const submit = async () => {
        setSubmitting(true);
        const queue = queueRef.current;
        await queue?.flush();

        if ((queue?.pendingCount ?? 0) > 0) {
            setSubmitting(false);

            return;
        }

        try {
            const response = await jsonRequest<Rejection>(
                'POST',
                attempts.submit(attempt.id).url,
                {},
                headers,
            );

            if (response.ok || response.status === 409) {
                if (response.data?.code === 'session_replaced') {
                    handleRejection(response.data);
                } else {
                    queue?.stop();
                    queue?.clear();
                    router.visit(
                        response.data?.result_url ??
                            attempts.result(attempt.id).url,
                    );
                }
            } else {
                toast.error(t('candidate.runner.requestFailed'));
            }
        } catch {
            toast.error(t('candidate.runner.requestFailed'));
        } finally {
            setSubmitting(false);
        }
    };

    const item = items[position - 1];
    const navigatorItems = items.map((entry) => ({
        position: entry.position,
        answered: isAnswered(entry, answers),
        flagged: isFlagged(entry, answers),
    }));
    const unanswered = navigatorItems.filter((entry) => !entry.answered).length;
    const flagged = navigatorItems.filter((entry) => entry.flagged).length;
    const navigator = (
        <QuestionNavigator
            items={navigatorItems}
            current={position}
            forwardOnly={forwardOnly}
            onSelect={(target) => void goTo(target)}
        />
    );

    return (
        <>
            <Head title={exam.title} />

            <div className="min-h-svh bg-muted/30">
                <header className="sticky top-0 z-20 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
                    <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3">
                        <div className="min-w-0 flex-1">
                            <h1 className="truncate text-sm font-semibold sm:text-base">
                                {exam.title}
                            </h1>
                            <SaveIndicator status={saveStatus} />
                        </div>
                        <CountdownTimer remainingMs={remaining} />
                        <Button
                            onClick={() => setSubmitOpen(true)}
                            disabled={!active}
                        >
                            <SubmitIcon aria-hidden="true" />
                            {t('candidate.runner.submit')}
                        </Button>
                    </div>
                </header>

                <div className="mx-auto grid max-w-6xl gap-6 px-4 py-6 lg:grid-cols-[minmax(0,1fr)_16rem]">
                    <main className="min-w-0">
                        {phase === 'active' ? (
                            <Card>
                                <CardHeader className="flex-row items-center justify-between gap-2">
                                    <CardTitle className="text-base">
                                        {t('candidate.runner.questionOf', {
                                            current: position,
                                            total: items.length,
                                        })}
                                    </CardTitle>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="lg:hidden"
                                        onClick={() => setNavigatorOpen(true)}
                                    >
                                        <QuestionListIcon aria-hidden="true" />
                                        {t('candidate.runner.navigatorTitle')}
                                    </Button>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    {item && (
                                        <QuestionView
                                            key={item.token}
                                            item={item}
                                            answers={answers}
                                            onChange={changeAnswer}
                                            disabled={!active}
                                        />
                                    )}

                                    {forwardOnly && position < items.length && (
                                        <p className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                            {t(
                                                'candidate.runner.forwardOnlyNotice',
                                            )}
                                        </p>
                                    )}

                                    <div className="flex items-center justify-between gap-2 border-t pt-4">
                                        {forwardOnly ? (
                                            <span />
                                        ) : (
                                            <Button
                                                variant="outline"
                                                onClick={() =>
                                                    void goTo(position - 1)
                                                }
                                                disabled={position === 1}
                                            >
                                                <ChevronLeft aria-hidden="true" />
                                                {t('candidate.runner.previous')}
                                            </Button>
                                        )}
                                        {position < items.length ? (
                                            <Button
                                                onClick={() =>
                                                    void goTo(position + 1)
                                                }
                                            >
                                                {t('candidate.runner.next')}
                                                <ChevronRight aria-hidden="true" />
                                            </Button>
                                        ) : (
                                            <Button
                                                onClick={() =>
                                                    setSubmitOpen(true)
                                                }
                                            >
                                                <SubmitIcon aria-hidden="true" />
                                                {t('candidate.runner.submit')}
                                            </Button>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ) : (
                            <StoppedNotice
                                phase={phase}
                                resultUrl={resultUrl}
                            />
                        )}
                    </main>

                    <aside className="hidden lg:block">
                        <Card className="sticky top-24">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('candidate.runner.navigatorTitle')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>{navigator}</CardContent>
                        </Card>
                    </aside>
                </div>
            </div>

            <Sheet open={navigatorOpen} onOpenChange={setNavigatorOpen}>
                <SheetContent
                    side="bottom"
                    className="max-h-[80svh] overflow-y-auto"
                >
                    <SheetHeader>
                        <SheetTitle>
                            {t('candidate.runner.navigatorTitle')}
                        </SheetTitle>
                    </SheetHeader>
                    <div className="px-4 pb-6">{navigator}</div>
                </SheetContent>
            </Sheet>

            <SubmitDialog
                open={submitOpen && active}
                onOpenChange={setSubmitOpen}
                unanswered={unanswered}
                flagged={flagged}
                pendingSaves={pendingCount}
                processing={submitting}
                onConfirm={() => void submit()}
            />
        </>
    );
}

function StoppedNotice({
    phase,
    resultUrl,
}: {
    phase: Exclude<Phase, 'active'>;
    resultUrl: string | null;
}) {
    const replaced = phase === 'replaced';
    const Icon = replaced ? ErrorIcon : TimerIcon;

    return (
        <Card role="alert">
            <CardContent className="flex flex-col items-center gap-4 py-10 text-center">
                <Icon
                    className="size-10 text-muted-foreground"
                    aria-hidden="true"
                />
                <div className="max-w-md space-y-2">
                    <h2 className="text-lg font-semibold">
                        {replaced
                            ? t('candidate.runner.sessionReplacedTitle')
                            : t('candidate.runner.finishedTitle')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {replaced
                            ? t('candidate.runner.sessionReplacedDescription')
                            : t('candidate.runner.finishedDescription')}
                    </p>
                </div>
                {replaced ? (
                    <Button onClick={() => window.location.reload()}>
                        {t('candidate.runner.reload')}
                    </Button>
                ) : (
                    resultUrl && (
                        <Button onClick={() => router.visit(resultUrl)}>
                            {t('candidate.runner.viewResult')}
                        </Button>
                    )
                )}
            </CardContent>
        </Card>
    );
}
