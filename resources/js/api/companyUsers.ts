import { apiRequest } from './client';
import type { AssignableRole, DataEnvelope, PaginatedEnvelope, Role } from './types';

export type CompanyUser = {
    id: number;
    email: string;
    role: Role;
    is_active: boolean;
};

export type PendingInvitation = {
    id: number;
    email: string;
    role: AssignableRole;
    expires_at: string;
};

export async function listCompanyUsers(page: number): Promise<PaginatedEnvelope<CompanyUser>> {
    const params = new URLSearchParams({ page: String(page) });

    return apiRequest<PaginatedEnvelope<CompanyUser>>(`/api/v1/company/users?${params.toString()}`);
}

export async function listCompanyInvitations(
    page: number,
): Promise<PaginatedEnvelope<PendingInvitation>> {
    const params = new URLSearchParams({ page: String(page) });

    return apiRequest<PaginatedEnvelope<PendingInvitation>>(
        `/api/v1/company/invitations?${params.toString()}`,
    );
}

export async function inviteCompanyUser(
    email: string,
    role: AssignableRole,
): Promise<DataEnvelope<PendingInvitation>> {
    return apiRequest<DataEnvelope<PendingInvitation>>('/api/v1/company/invitations', {
        method: 'POST',
        body: JSON.stringify({ email, role }),
    });
}

export async function resendCompanyInvitation(
    id: number,
): Promise<DataEnvelope<PendingInvitation>> {
    return apiRequest<DataEnvelope<PendingInvitation>>(`/api/v1/company/invitations/${id}/resend`, {
        method: 'POST',
    });
}

export async function revokeCompanyInvitation(id: number): Promise<{ ok: true }> {
    return apiRequest<{ ok: true }>(`/api/v1/company/invitations/${id}/revoke`, {
        method: 'POST',
    });
}

export async function changeCompanyUserRole(
    id: number,
    role: AssignableRole,
): Promise<DataEnvelope<CompanyUser>> {
    return apiRequest<DataEnvelope<CompanyUser>>(`/api/v1/company/users/${id}`, {
        method: 'PATCH',
        body: JSON.stringify({ role }),
    });
}

export async function deactivateCompanyUser(id: number): Promise<DataEnvelope<CompanyUser>> {
    return apiRequest<DataEnvelope<CompanyUser>>(`/api/v1/company/users/${id}/deactivate`, {
        method: 'POST',
    });
}

export async function reactivateCompanyUser(id: number): Promise<DataEnvelope<CompanyUser>> {
    return apiRequest<DataEnvelope<CompanyUser>>(`/api/v1/company/users/${id}/reactivate`, {
        method: 'POST',
    });
}
