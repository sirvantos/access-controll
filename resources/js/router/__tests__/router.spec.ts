import { flushPromises } from '@vue/test-utils';
import { createApp, type App as VueApp } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, type Router, type RouterHistory } from 'vue-router';
import type { CurrentUser } from '../../api/types';
import { t } from '../../utils/i18n';

const viewer: CurrentUser = {
    id: 4,
    email: 'viewer@acme.test',
    role: 'viewer',
    company_id: 8,
};

const admin: CurrentUser = {
    id: 2,
    email: 'admin@acme.test',
    role: 'company_admin',
    company_id: 3,
};

const owner: CurrentUser = {
    id: 1,
    email: 'owner@example.com',
    role: 'super_admin',
    company_id: null,
};

const emptyPage = {
    data: [],
    links: { first: null, last: null, prev: null, next: null },
    meta: {
        current_page: 1,
        from: null,
        last_page: 1,
        links: [],
        path: '/api/v1/company/users',
        per_page: 15,
        to: null,
        total: 0,
    },
};

let app: VueApp<Element> | null = null;

afterEach(() => {
    app?.unmount();
    app = null;
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
    vi.resetModules();
    document.documentElement.lang = 'en';
});

describe('router guards', () => {
    it('sends a signed-out visitor on a protected route to sign-in', async () => {
        const { router, root } = await boot(null, '/');

        expect(router.currentRoute.value.path).toBe('/sign-in');
        expect(root.textContent).not.toContain(viewer.email);
    });

    it('lets a signed-out visitor open password recovery', async () => {
        const { router, root } = await boot(null, '/forgot-password');

        expect(router.currentRoute.value.path).toBe('/forgot-password');
        expect(root.textContent).toContain(t('auth.sendResetLink'));
    });

    it('sends a signed-in user away from password recovery', async () => {
        const { router } = await boot(viewer, '/forgot-password');

        expect(router.currentRoute.value.path).toBe('/');
    });

    it('sends a signed-in user on a guest-only route home', async () => {
        const { router, root } = await boot(viewer, '/sign-in');

        expect(router.currentRoute.value.path).toBe('/');
        expect(root.textContent).toContain(t('home.placeholder'));
        expect(root.textContent).toContain(viewer.email);
    });

    it('sends a viewer away from a company admin route', async () => {
        const { router } = await boot(viewer, '/company/users');

        expect(router.currentRoute.value.path).toBe('/');
    });

    it('sends a company admin home to the company users page', async () => {
        const { router } = await boot(admin, '/');

        expect(router.currentRoute.value.path).toBe('/company/users');
    });

    it('sends a super admin home to the companies page', async () => {
        const { router } = await boot(owner, '/');

        expect(router.currentRoute.value.path).toBe('/companies');
    });

    it('sends a company admin away from the companies page', async () => {
        const { router } = await boot(admin, '/companies');

        expect(router.currentRoute.value.path).toBe('/company/users');
    });

    it('sends a viewer away from the companies page', async () => {
        const { router, root } = await boot(viewer, '/companies');

        expect(router.currentRoute.value.path).toBe('/');
        expect(root.textContent).toContain(t('home.placeholder'));
    });

    it('clears the current user and goes to sign-in after a 401', async () => {
        const { router, fetchMock } = await boot(viewer, '/');

        expect(router.currentRoute.value.path).toBe('/');

        fetchMock.mockImplementation(async () =>
            jsonResponse({ message: 'Unauthenticated.' }, 401),
        );

        const { apiRequest } = await import('../../api/client');
        const { useCurrentUser } = await import('../../composables/useCurrentUser');

        await expect(apiRequest('/api/v1/me')).rejects.toMatchObject({ status: 401 });
        await flushPromises();

        expect(useCurrentUser().currentUser.value).toBeNull();
        expect(router.currentRoute.value.path).toBe('/sign-in');
    });
});

async function boot(
    user: CurrentUser | null,
    start: string,
): Promise<{ router: Router; root: HTMLElement; fetchMock: ReturnType<typeof vi.fn> }> {
    const fetchMock = vi.fn(async (input: RequestInfo | URL) => {
        const url = String(input);

        if (url === '/api/v1/me') {
            return user === null
                ? jsonResponse({ message: 'Unauthenticated.' }, 401)
                : jsonResponse({ data: user }, 200);
        }

        if (
            url.startsWith('/api/v1/company/users') ||
            url.startsWith('/api/v1/company/invitations') ||
            url.startsWith('/api/v1/admin/companies')
        ) {
            return jsonResponse(emptyPage, 200);
        }

        return jsonResponse({ message: 'Unauthenticated.' }, 401);
    });
    vi.stubGlobal('fetch', fetchMock);

    const { createAppRouter } = await import('../index');
    const { default: Root } = await import('../../App.vue');
    const router = createAppRouter(historyStartingAt(start));
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp(Root);
    app.use(router);
    app.mount(root);
    await router.isReady();
    await flushPromises();

    return { router, root, fetchMock };
}

function historyStartingAt(path: string): RouterHistory {
    const history = createMemoryHistory();
    history.replace(path);

    return history;
}

function jsonResponse(body: unknown, status: number): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}
