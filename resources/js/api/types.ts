export type Role = 'super_admin' | 'company_admin' | 'viewer';

export const ASSIGNABLE_ROLES = ['company_admin', 'viewer'] as const satisfies readonly Role[];

export type AssignableRole = (typeof ASSIGNABLE_ROLES)[number];

export type DataEnvelope<T> = {
    data: T;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginationLinks = {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
};

export type PaginationMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    links: PaginationLink[];
    path: string;
    per_page: number;
    to: number | null;
    total: number;
};

export type PaginatedEnvelope<T> = {
    data: T[];
    links: PaginationLinks;
    meta: PaginationMeta;
};

export type CurrentUser = {
    id: number;
    email: string;
    role: Role;
    company_id: number | null;
};

export type FieldErrors = Record<string, string[]>;
