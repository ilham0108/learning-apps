export type Role = 'super_admin' | 'hr' | 'candidate';

/** Pengguna yang sedang login (CurrentUserResource). `id` adalah public_id. */
export type User = {
    id: string;
    name: string;
    email: string;
    role: Role;
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
