import { apiRequest } from './client';
import type { CurrentUser, DataEnvelope } from './types';

export async function signIn(email: string, password: string): Promise<CurrentUser> {
    const body = await apiRequest<DataEnvelope<CurrentUser>>('/api/v1/auth/sign-in', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
    });

    return body.data;
}

export async function signOut(): Promise<void> {
    await apiRequest<unknown>('/api/v1/auth/sign-out', { method: 'POST' });
}

export async function requestPasswordReset(email: string): Promise<{ ok: true }> {
    return apiRequest<{ ok: true }>('/api/v1/auth/forgot-password', {
        method: 'POST',
        body: JSON.stringify({ email }),
    });
}

export async function resetPassword(
    token: string,
    email: string,
    password: string,
): Promise<{ ok: true }> {
    return apiRequest<{ ok: true }>('/api/v1/auth/reset-password', {
        method: 'POST',
        body: JSON.stringify({ token, email, password }),
    });
}

export async function fetchCurrentUser(): Promise<CurrentUser> {
    const body = await apiRequest<DataEnvelope<CurrentUser>>('/api/v1/me');

    return body.data;
}
