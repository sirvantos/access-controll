import { afterEach, describe, expect, it, vi } from 'vitest';

afterEach(() => {
    vi.unstubAllGlobals();
    vi.resetModules();
});

describe('companyMedia api', () => {
    it('requests media with same-origin credentials and returns bytes', async () => {
        const bytes = new Uint8Array([1, 2, 3]).buffer;
        const fetchMock = vi.fn<typeof fetch>(async () => new Response(bytes, { status: 200 }));
        vi.stubGlobal('fetch', fetchMock);

        const { fetchCompanyMedia } = await import('../companyMedia');

        const result = await fetchCompanyMedia('11111111-1111-1111-1111-111111111111');

        expect(fetchMock).toHaveBeenCalledOnce();
        expect(fetchMock.mock.calls[0]?.[0]).toBe(
            '/api/v1/company/media/11111111-1111-1111-1111-111111111111',
        );
        expect(fetchMock.mock.calls[0]?.[1]).toMatchObject({ credentials: 'same-origin' });
        expect(new Uint8Array(result)).toEqual(new Uint8Array(bytes));
    });

    it('maps 404 responses to ApiError like other company-data endpoints', async () => {
        const fetchMock = vi.fn<typeof fetch>(async () =>
            jsonResponse({ message: 'Not found.' }, 404),
        );
        vi.stubGlobal('fetch', fetchMock);

        const { fetchCompanyMedia } = await import('../companyMedia');
        const { ApiError } = await import('../client');

        await expect(fetchCompanyMedia('22222222-2222-2222-2222-222222222222')).rejects.toEqual(
            new ApiError(404, 'Not found.', null, null),
        );
    });
});

function jsonResponse(body: unknown, status: number): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}
