import type { ExamAvailability, ExamProgress, MaterialType } from '@/types';

export type DashboardExam = {
    id: string;
    title: string;
    closes_at: string | null;
    availability: ExamAvailability;
    active_attempt: string | null;
    progress: ExamProgress;
    attempts_used: number;
    max_attempts: number;
};

export type DashboardMaterial = {
    id: string;
    title: string;
    type: MaterialType;
    program: { id: string; name: string };
};

/**
 * Satu tindakan paling penting untuk kandidat saat ini, berurutan menurut
 * prioritas: ujian yang sedang berjalan, data diri yang belum lengkap, ujian yang bisa dimulai (paling cepat
 * ditutup lebih dulu), materi yang membuka ujian, materi berikutnya, lalu
 * jawaban yang sedang dinilai.
 */
export type NextStep =
    | { kind: 'continue_exam'; exam: DashboardExam }
    | { kind: 'complete_profile'; percent: number }
    | { kind: 'start_exam'; exam: DashboardExam }
    | {
          kind: 'unlock_exam';
          exam: DashboardExam;
          material: DashboardMaterial;
      }
    | { kind: 'continue_learning'; material: DashboardMaterial }
    | { kind: 'awaiting_grading'; exam: DashboardExam }
    | { kind: 'done' };

const byClosing = (a: DashboardExam, b: DashboardExam) =>
    (a.closes_at === null ? Infinity : Date.parse(a.closes_at)) -
    (b.closes_at === null ? Infinity : Date.parse(b.closes_at));

export function pickNextStep(
    exams: DashboardExam[],
    materials: DashboardMaterial[],
    profile: { complete: boolean; percent: number } = {
        complete: true,
        percent: 100,
    },
): NextStep {
    const sorted = [...exams].sort(byClosing);
    const running = sorted.find((exam) => exam.availability === 'in_progress');

    if (running) {
        return { kind: 'continue_exam', exam: running };
    }

    // Ujian tidak bisa dimulai sebelum data diri lengkap.
    if (!profile.complete) {
        return { kind: 'complete_profile', percent: profile.percent };
    }

    const available = sorted.find((exam) => exam.availability === 'available');

    if (available) {
        return { kind: 'start_exam', exam: available };
    }

    const locked = sorted.find(
        (exam) => exam.availability === 'prerequisites_incomplete',
    );

    if (locked && materials[0]) {
        return { kind: 'unlock_exam', exam: locked, material: materials[0] };
    }

    if (materials[0]) {
        return { kind: 'continue_learning', material: materials[0] };
    }

    const grading = sorted.find((exam) => exam.progress === 'awaiting_grading');

    return grading
        ? { kind: 'awaiting_grading', exam: grading }
        : { kind: 'done' };
}

/** Sisa waktu yang mudah dibaca: "2 hari 4 jam", "3 jam 15 menit", "8 menit". */
export function formatDuration(ms: number): {
    days: number;
    hours: number;
    minutes: number;
} {
    const totalMinutes = Math.max(0, Math.floor(ms / 60_000));

    return {
        days: Math.floor(totalMinutes / 1440),
        hours: Math.floor((totalMinutes % 1440) / 60),
        minutes: totalMinutes % 60,
    };
}
