import { apiRequest } from './client';
import type { PendingInvitation } from './companyUsers';
import type { DataEnvelope, PaginatedEnvelope } from './types';

export type { PendingInvitation };

export type Company = {
    id: number;
    name: string;
    bin: string | null;
    is_active: boolean;
    awaiting_first_admin: boolean;
    created_at: string;
};

export type WorkingDaySettings = {
    start_time: string;
    end_time: string;
    working_days: string[];
    break_duration_minutes: number;
    break_deducted: boolean;
    lateness_grace_minutes: number;
};

export type CreatedCompany = {
    id: number;
    name: string;
    time_zone: string;
    bin: string | null;
    contact_person: string | null;
    phone: string | null;
    email: string | null;
    is_active: boolean;
    awaiting_first_admin: boolean;
    created_at: string;
    working_day_settings: WorkingDaySettings;
};

export type CreateCompanyInput = {
    name: string;
    first_admin_email: string;
    time_zone: string;
    bin: string;
    contact_person: string;
    phone: string;
    email: string;
};

export const DEFAULT_COMPANY_TIME_ZONE = 'Asia/Almaty';

export async function listCompanies(
    page: number,
    search?: string,
): Promise<PaginatedEnvelope<Company>> {
    const params = new URLSearchParams({ page: String(page) });

    if (search !== undefined && search !== '') {
        params.set('search', search);
    }

    return apiRequest<PaginatedEnvelope<Company>>(`/api/v1/admin/companies?${params.toString()}`);
}

export async function listTimeZones(): Promise<DataEnvelope<{ identifiers: string[] }>> {
    return apiRequest<DataEnvelope<{ identifiers: string[] }>>('/api/v1/time-zones');
}

export async function createCompany(
    input: CreateCompanyInput,
): Promise<DataEnvelope<CreatedCompany>> {
    return apiRequest<DataEnvelope<CreatedCompany>>('/api/v1/admin/companies', {
        method: 'POST',
        body: JSON.stringify(input),
    });
}

export async function listFirstAdminInvitations(
    companyId: number,
    page: number,
): Promise<PaginatedEnvelope<PendingInvitation>> {
    const params = new URLSearchParams({ page: String(page) });

    return apiRequest<PaginatedEnvelope<PendingInvitation>>(
        `/api/v1/admin/companies/${companyId}/invitations?${params.toString()}`,
    );
}

export async function inviteFirstAdmin(
    companyId: number,
    email: string,
): Promise<DataEnvelope<PendingInvitation>> {
    return apiRequest<DataEnvelope<PendingInvitation>>(
        `/api/v1/admin/companies/${companyId}/invitations`,
        {
            method: 'POST',
            body: JSON.stringify({ email }),
        },
    );
}

export async function resendFirstAdminInvitation(
    companyId: number,
    invitationId: number,
): Promise<DataEnvelope<PendingInvitation>> {
    return apiRequest<DataEnvelope<PendingInvitation>>(
        `/api/v1/admin/companies/${companyId}/invitations/${invitationId}/resend`,
        { method: 'POST' },
    );
}

export async function deactivateCompany(id: number): Promise<DataEnvelope<Company>> {
    return apiRequest<DataEnvelope<Company>>(`/api/v1/admin/companies/${id}/deactivate`, {
        method: 'POST',
    });
}

export async function reactivateCompany(id: number): Promise<DataEnvelope<Company>> {
    return apiRequest<DataEnvelope<Company>>(`/api/v1/admin/companies/${id}/reactivate`, {
        method: 'POST',
    });
}

export async function revokeFirstAdminInvitation(
    companyId: number,
    invitationId: number,
): Promise<{ ok: true }> {
    return apiRequest<{ ok: true }>(
        `/api/v1/admin/companies/${companyId}/invitations/${invitationId}/revoke`,
        { method: 'POST' },
    );
}
