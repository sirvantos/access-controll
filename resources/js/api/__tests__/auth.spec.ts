import { afterEach, describe, expect, it, vi } from 'vitest';
import type { CurrentUser } from '../types';

const viewer: CurrentUser = {
    id: 4,
    email: 'viewer@acme.test',
    role: 'viewer',
    company_id: 8,
};

afterEach(() => {
    vi.unstubAllGlobals();
    vi.resetModules();
});

describe('auth api', () => {
    it('signs in, signs out, and loads the current user', async () => {
        const fetchMock = vi.fn<typeof fetch>(async (input) => {
            const url = String(input);

            if (url === '/sanctum/csrf-cookie') {
                return new Response(null, { status: 204 });
            }

            if (url === '/api/v1/auth/sign-in') {
                return jsonResponse({ data: viewer });
            }

            if (url === '/api/v1/auth/sign-out') {
                return jsonResponse({ ok: true });
            }

            return jsonResponse({ data: viewer });
        });
        vi.stubGlobal('fetch', fetchMock);

        const { signIn, signOut, fetchCurrentUser } = await import('../auth');

        await expect(signIn(viewer.email, 'password')).resolves.toEqual(viewer);
        await expect(signOut()).resolves.toBeUndefined();
        await expect(fetchCurrentUser()).resolves.toEqual(viewer);

        const signInCall = fetchMock.mock.calls.find(
            (call) => String(call[0]) === '/api/v1/auth/sign-in',
        );

        expect(signInCall?.[1]).toMatchObject({
            method: 'POST',
            body: JSON.stringify({ email: viewer.email, password: 'password' }),
            credentials: 'same-origin',
        });
        expect(fetchMock.mock.calls.some((call) => String(call[0]) === '/api/v1/me')).toBe(true);
        expect(
            fetchMock.mock.calls.some((call) => String(call[0]) === '/api/v1/auth/sign-out'),
        ).toBe(true);
    });
});

function jsonResponse(body: unknown): Response {
    return new Response(JSON.stringify(body), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
    });
}
