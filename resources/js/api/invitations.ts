import { apiRequest } from './client';
import type { AssignableRole, DataEnvelope } from './types';

export type InvitationPreview = {
    email: string;
    role: AssignableRole;
    expires_at: string;
};

export async function showInvitation(token: string): Promise<DataEnvelope<InvitationPreview>> {
    return apiRequest<DataEnvelope<InvitationPreview>>(
        `/api/v1/invitations/${encodeURIComponent(token)}`,
    );
}

export async function acceptInvitation(token: string, password: string): Promise<{ ok: true }> {
    return apiRequest<{ ok: true }>(`/api/v1/invitations/${encodeURIComponent(token)}/accept`, {
        method: 'POST',
        body: JSON.stringify({ password }),
    });
}
