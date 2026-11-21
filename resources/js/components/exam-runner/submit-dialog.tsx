import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    unanswered: number;
    flagged: number;
    /** Jawaban yang belum terkirim ke server; submit ditahan sampai 0. */
    pendingSaves: number;
    processing: boolean;
    onConfirm: () => void;
};

/**
 * Konfirmasi sebelum mengumpulkan: menampilkan jumlah soal yang belum dijawab
 * dan yang ditandai ragu (rules Bagian 9).
 */
export function SubmitDialog({
    open,
    onOpenChange,
    unanswered,
    flagged,
    pendingSaves,
    processing,
    onConfirm,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('candidate.runner.submitTitle')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('candidate.runner.submitDescription')}
                    </DialogDescription>
                </DialogHeader>

                <ul className="space-y-1 text-sm">
                    {unanswered === 0 ? (
                        <li>{t('candidate.runner.allAnswered')}</li>
                    ) : (
                        <li className="font-medium text-amber-800 dark:text-amber-300">
                            {t('candidate.runner.unansweredCount', {
                                count: unanswered,
                            })}
                        </li>
                    )}
                    {flagged > 0 && (
                        <li>
                            {t('candidate.runner.flaggedCount', {
                                count: flagged,
                            })}
                        </li>
                    )}
                    {pendingSaves > 0 && (
                        <li className="text-red-700 dark:text-red-400">
                            {t('candidate.runner.pendingSaves', {
                                count: pendingSaves,
                            })}
                        </li>
                    )}
                </ul>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="outline" disabled={processing}>
                            {t('common.back')}
                        </Button>
                    </DialogClose>
                    <Button
                        onClick={onConfirm}
                        disabled={processing || pendingSaves > 0}
                    >
                        {processing && <Spinner />}
                        {t('candidate.runner.submitConfirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
