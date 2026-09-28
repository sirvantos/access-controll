import { afterEach, describe, expect, it, vi } from 'vitest';
import type { CurrentUser } from '../../api/types';

const admin: CurrentUser = {
    id: 2,
    email: 'admin@acme.test',
    role: 'company_admin',
    company_id: 3,
};

afterEach(() => {
    vi.unstubAllGlobals();
    vi.resetModules();
});

describe('useCurrentUser', () => {
    it('loads the current user', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => jsonResponse({ data: admin }, 200)),
        );

        const { useCurrentUser } = await import('../useCurrentUser');
        const { currentUser, load } = useCurrentUser();

        await load();

        expect(currentUser.value).toEqual(admin);
    });

    it('treats 401 as signed out', async () => {
        vi.stubGlobal(
            'fetch',
            vi
                .fn()
                .mockResolvedValueOnce(jsonResponse({ data: admin }, 200))
                .mockResolvedValueOnce(jsonResponse({ message: 'Unauthenticated.' }, 401)),
        );

        const { useCurrentUser } = await import('../useCurrentUser');
        const { currentUser, load } = useCurrentUser();

        await load();
        await load();

        expect(currentUser.value).toBeNull();
    });

    it('clears the current user', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => jsonResponse({ data: admin }, 200)),
        );

        const { useCurrentUser } = await import('../useCurrentUser');
        const { currentUser, load, clear } = useCurrentUser();

        await load();
        clear();

        expect(currentUser.value).toBeNull();
    });

    it('rethrows errors other than 401', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => jsonResponse({ message: 'Unavailable.' }, 500)),
        );

        const { useCurrentUser } = await import('../useCurrentUser');
        const { currentUser, load } = useCurrentUser();
        currentUser.value = admin;

        await expect(load()).rejects.toMatchObject({ status: 500 });
        expect(currentUser.value).toEqual(admin);
    });
});

function jsonResponse(body: unknown, status: number): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}
