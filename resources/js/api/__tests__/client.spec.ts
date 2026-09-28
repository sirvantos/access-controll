import { afterEach, describe, expect, it, vi } from 'vitest';

afterEach(() => {
    vi.unstubAllGlobals();
    vi.resetModules();
    document.cookie = 'XSRF-TOKEN=; Max-Age=0';
});

describe('api client', () => {
    it('primes the csrf cookie once and sends the token on state-changing requests', async () => {
        document.cookie = 'XSRF-TOKEN=abc%2Bdef';
        const fetchMock = vi.fn<typeof fetch>(async (input) => {
            if (String(input) === '/sanctum/csrf-cookie') {
                return new Response(null, { status: 204 });
            }

            return jsonResponse({ data: { ok: true } }, 200);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { apiRequest } = await import('../client');

        await apiRequest('/api/v1/auth/sign-in', { method: 'POST', body: '{}' });
        await apiRequest('/api/v1/auth/sign-out', { method: 'POST', body: '{}' });

        expect(fetchMock).toHaveBeenCalledTimes(3);
        expect(String(fetchMock.mock.calls[0]?.[0])).toBe('/sanctum/csrf-cookie');
        expect(fetchMock.mock.calls[0]?.[1]).toMatchObject({ credentials: 'same-origin' });

        const signInInit = fetchMock.mock.calls[1]?.[1];
        const headers = new Headers(signInInit?.headers);

        expect(signInInit?.credentials).toBe('same-origin');
        expect(headers.get('Accept')).toBe('application/json');
        expect(headers.get('Content-Type')).toBe('application/json');
        expect(headers.get('X-XSRF-TOKEN')).toBe('abc+def');
        expect(String(fetchMock.mock.calls[2]?.[0])).toBe('/api/v1/auth/sign-out');
    });

    it('does not prime csrf for a read', async () => {
        const fetchMock = vi.fn<typeof fetch>(async () => jsonResponse({ data: { id: 1 } }, 200));
        vi.stubGlobal('fetch', fetchMock);

        const { apiRequest } = await import('../client');

        await apiRequest('/api/v1/me');

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(String(fetchMock.mock.calls[0]?.[0])).toBe('/api/v1/me');
    });

    it.each([
        [401, { message: 'Unauthenticated.' }, null, null],
        [403, { message: 'Forbidden.' }, null, null],
        [404, { message: 'Not found.' }, null, null],
        [409, { message: 'Conflict.', error_code: 'last_active_admin' }, 'last_active_admin', null],
        [410, { message: 'Gone.', error_code: 'invitation_invalid' }, 'invitation_invalid', null],
        [
            422,
            { message: 'Invalid.', errors: { email: ['invalid'] } },
            null,
            { email: ['invalid'] },
        ],
        [
            429,
            { message: 'Blocked.', errors: { email: ['blocked'] } },
            null,
            { email: ['blocked'] },
        ],
    ] as const)('maps HTTP %s onto a typed error', async (status, body, errorCode, errors) => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => jsonResponse(body, status)),
        );

        const { ApiError, apiRequest } = await import('../client');

        await expect(apiRequest('/api/v1/me')).rejects.toMatchObject({
            name: 'ApiError',
            status,
            message: body.message,
            errorCode,
            errors,
        });

        await expect(apiRequest('/api/v1/me')).rejects.toBeInstanceOf(ApiError);
    });
});

function jsonResponse(body: unknown, status: number): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}
