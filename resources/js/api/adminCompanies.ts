import { apiRequest } from './client';
import type { PendingInvitation } from './companyUsers';
import type { DataEnvelope, PaginatedEnvelope } from './types';

export type { PendingInvitation };

export type Company = {
    id: number;
    name: string;
    is_active: boolean;
    awaiting_first_admin: boolean;
    created_at: string;
};

export async function listCompanies(page: number): Promise<PaginatedEnvelope<Company>> {
    const params = new URLSearchParams({ page: String(page) });

    return apiRequest<PaginatedEnvelope<Company>>(`/api/v1/admin/companies?${params.toString()}`);
}

export async function createCompany(
    name: string,
    firstAdminEmail: string,
): Promise<DataEnvelope<Company>> {
    return apiRequest<DataEnvelope<Company>>('/api/v1/admin/companies', {
        method: 'POST',
        body: JSON.stringify({ name, first_admin_email: firstAdminEmail }),
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
