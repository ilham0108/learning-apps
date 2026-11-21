export type Role = 'super_admin' | 'hr' | 'candidate';

/** App\Domain\Access\Enums\Permission */
export type Permission =
    | 'campaigns.view_all'
    | 'campaigns.manage'
    | 'campaigns.assign_team'
    | 'pipeline.manage_default'
    | 'pipeline.manage_campaign'
    | 'candidates.manage'
    | 'candidates.view_sensitive'
    | 'applications.move_stage'
    | 'applications.decide'
    | 'notes.write'
    | 'evaluations.write'
    | 'learning.manage'
    | 'learning.assign'
    | 'exams.manage'
    | 'exams.assign'
    | 'grading.grade'
    | 'results.release'
    | 'reports.view'
    | 'reports.export'
    | 'staff.manage'
    | 'roles.manage'
    | 'audit.view';

/** Pengguna yang sedang login (CurrentUserResource). `id` adalah public_id. */
export type User = {
    id: string;
    name: string;
    email: string;
    role: Role;
    /** Nama jabatan staf; null untuk Super Admin dan kandidat. */
    job_role: string | null;
    /** Hanya untuk menyesuaikan tampilan; otorisasi tetap di server. */
    permissions: Permission[];
    two_factor_enabled: boolean;
};

export type Auth = {
    user: User;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
