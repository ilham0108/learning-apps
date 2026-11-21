import { describe, expect, it } from 'vite-plus/test';
import type { DashboardExam, DashboardMaterial } from '@/lib/next-step';
import { formatDuration, pickNextStep } from '@/lib/next-step';

const exam = (overrides: Partial<DashboardExam>): DashboardExam => ({
    id: 'e1',
    title: 'Tes Produk',
    closes_at: null,
    availability: 'available',
    active_attempt: null,
    progress: 'not_started',
    attempts_used: 0,
    max_attempts: 1,
    ...overrides,
});

const material: DashboardMaterial = {
    id: 'm1',
    title: 'Teknik closing',
    type: 'article',
    program: { id: 'p1', name: 'Onboarding Sales' },
};

describe('pickNextStep', () => {
    it('puts a running exam first', () => {
        const step = pickNextStep(
            [exam({ id: 'a' }), exam({ id: 'b', availability: 'in_progress' })],
            [material],
        );

        expect(step).toMatchObject({
            kind: 'continue_exam',
            exam: { id: 'b' },
        });
    });

    it('asks for a complete profile before any exam can start', () => {
        const incomplete = { complete: false, percent: 40 };

        expect(pickNextStep([exam({})], [material], incomplete)).toEqual({
            kind: 'complete_profile',
            percent: 40,
        });
        // Ujian yang sedang berjalan tetap didahulukan.
        expect(
            pickNextStep(
                [exam({ availability: 'in_progress' })],
                [],
                incomplete,
            ).kind,
        ).toBe('continue_exam');
    });

    it('offers the available exam that closes first', () => {
        const step = pickNextStep(
            [
                exam({ id: 'late', closes_at: '2026-12-01T00:00:00Z' }),
                exam({ id: 'soon', closes_at: '2026-11-12T00:00:00Z' }),
                exam({ id: 'open-ended' }),
            ],
            [],
        );

        expect(step).toMatchObject({
            kind: 'start_exam',
            exam: { id: 'soon' },
        });
    });

    it('points to the material that unlocks a locked exam', () => {
        const step = pickNextStep(
            [exam({ availability: 'prerequisites_incomplete' })],
            [material],
        );

        expect(step).toMatchObject({
            kind: 'unlock_exam',
            material: { id: 'm1' },
        });
    });

    it('falls back to learning, then grading, then done', () => {
        expect(pickNextStep([], [material]).kind).toBe('continue_learning');
        expect(
            pickNextStep(
                [
                    exam({
                        availability: 'no_attempts_left',
                        progress: 'awaiting_grading',
                    }),
                ],
                [],
            ).kind,
        ).toBe('awaiting_grading');
        expect(
            pickNextStep(
                [exam({ availability: 'closed', progress: 'completed' })],
                [],
            ).kind,
        ).toBe('done');
    });
});

describe('formatDuration', () => {
    it('splits milliseconds into days, hours, and minutes', () => {
        expect(formatDuration((2 * 1440 + 4 * 60 + 5) * 60_000)).toEqual({
            days: 2,
            hours: 4,
            minutes: 5,
        });
        expect(formatDuration(-1000)).toEqual({
            days: 0,
            hours: 0,
            minutes: 0,
        });
    });
});
