import { flushPromises } from '@vue/test-utils';
import { createApp, type App as VueApp } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, type Router, type RouterHistory } from 'vue-router';
import type { CurrentUser } from '../api/types';
import { t } from '../utils/i18n';

const owner: CurrentUser = {
    id: 1,
    email: 'owner@example.com',
    role: 'super_admin',
    company_id: null,
};

const acmePage = {
    data: [
        {
            id: 3,
            name: 'Acme',
            bin: '123456789012',
            is_active: true,
            awaiting_first_admin: false,
            created_at: '2026-01-15T12:00:00+00:00',
        },
    ],
    links: { first: null, last: null, prev: null, next: null },
    meta: {
        current_page: 1,
        from: 1,
        last_page: 1,
        links: [],
        path: '/api/v1/admin/companies',
        per_page: 15,
        to: 1,
        total: 1,
    },
};

const emptyInvitations = {
    data: [],
    links: { first: null, last: null, prev: null, next: null },
    meta: {
        current_page: 1,
        from: null,
        last_page: 1,
        links: [],
        path: '/api/v1/admin/companies/3/invitations',
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
    sessionStorage.clear();
});

describe('select company header', () => {
    it('shows the selected company in the header without a document reload', async () => {
        const { router, root } = await boot(owner, '/companies');
        const mountedRoot = root;

        expect(router.currentRoute.value.path).toBe('/companies');
        expect(root.querySelector('[data-testid="select-company-prompt"]')?.textContent).toBe(
            t('companies.selectPrompt'),
        );
        expect(root.querySelector('[data-testid="company-users-link"]')).toBeNull();
        expect(root.querySelector('[data-testid="company-profile-link"]')).toBeNull();

        const select = root.querySelector('[data-testid="select-company-3"]');
        expect(select).toBeInstanceOf(HTMLButtonElement);
        (select as HTMLButtonElement).click();
        await flushPromises();
        await flushPromises();

        expect(mountedRoot).toBe(root);
        expect(document.body.contains(mountedRoot)).toBe(true);
        expect(router.currentRoute.value.path).toBe('/companies');
        expect(root.querySelector('[data-testid="selected-company-name"]')?.textContent).toBe(
            t('companies.selected', { name: 'Acme' }),
        );
        expect(root.querySelector('[data-testid="company-users-link"]')?.getAttribute('href')).toBe(
            '/company/users',
        );
        expect(
            root.querySelector('[data-testid="company-profile-link"]')?.getAttribute('href'),
        ).toBe('/company');
        expect(root.querySelector('[data-testid="select-company-prompt"]')).toBeNull();

        const clear = root.querySelector('[data-testid="clear-selected-company"]');
        expect(clear).toBeInstanceOf(HTMLButtonElement);
        (clear as HTMLButtonElement).click();
        await flushPromises();
        await flushPromises();

        expect(mountedRoot).toBe(root);
        expect(document.body.contains(mountedRoot)).toBe(true);
        expect(router.currentRoute.value.path).toBe('/companies');
        expect(root.querySelector('[data-testid="select-company-prompt"]')?.textContent).toBe(
            t('companies.selectPrompt'),
        );
        expect(root.querySelector('[data-testid="selected-company-name"]')).toBeNull();
        expect(root.querySelector('[data-testid="company-users-link"]')).toBeNull();
        expect(root.querySelector('[data-testid="company-profile-link"]')).toBeNull();
    });
});

async function boot(
    user: CurrentUser,
    start: string,
): Promise<{ router: Router; root: HTMLElement }> {
    const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = String(input);
        const method = (init?.method ?? 'GET').toUpperCase();

        if (url === '/sanctum/csrf-cookie') {
            return new Response(null, { status: 204 });
        }

        if (url === '/api/v1/me') {
            return jsonResponse({ data: user }, 200);
        }

        if (url === '/api/v1/time-zones') {
            return jsonResponse({ data: { identifiers: ['Africa/Abidjan', 'Asia/Almaty'] } }, 200);
        }

        if (url === '/api/v1/admin/selected-company' && method === 'POST') {
            return jsonResponse({ data: { id: 3, name: 'Acme' } }, 200);
        }

        if (url === '/api/v1/admin/selected-company' && method === 'DELETE') {
            return jsonResponse({ ok: true }, 200);
        }

        if (url.startsWith('/api/v1/admin/companies/3/invitations')) {
            return jsonResponse(emptyInvitations, 200);
        }

        if (url.startsWith('/api/v1/admin/companies')) {
            return jsonResponse(acmePage, 200);
        }

        return jsonResponse({ message: 'Not found.' }, 404);
    });
    vi.stubGlobal('fetch', fetchMock);

    const { createAppRouter } = await import('../router/index');
    const { default: Root } = await import('../App.vue');
    const router = createAppRouter(historyStartingAt(start));
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp(Root);
    app.use(router);
    app.mount(root);
    await router.isReady();
    await flushPromises();

    return { router, root };
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
