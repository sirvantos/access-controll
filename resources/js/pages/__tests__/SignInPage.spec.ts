import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import type { CurrentUser } from '../../api/types';
import { t } from '../../utils/i18n';

const admin: CurrentUser = {
    id: 2,
    email: 'admin@acme.test',
    role: 'company_admin',
    company_id: 3,
};

afterEach(() => {
    vi.unstubAllGlobals();
    vi.resetModules();
    document.documentElement.lang = 'en';
});

describe('SignInPage', () => {
    it('prefills the email and signs in', async () => {
        stubSignIn(200, { data: admin });

        const { wrapper, router } = await mountSignIn(admin.email);

        expect(wrapper.get<HTMLInputElement>('[data-testid="email"]').element.value).toBe(
            admin.email,
        );
        expect(wrapper.get('[data-testid="email"]').element).toBeInstanceOf(HTMLInputElement);
        expect(wrapper.get('[data-testid="password"]').element).toBeInstanceOf(HTMLInputElement);
        expect(wrapper.get('[data-testid="sign-in"]').element.tagName).toBe('BUTTON');
        expect(wrapper.get('[data-testid="sign-in"]').text()).toBe(t('auth.signIn'));
        expect(wrapper.get('[data-testid="sign-in"]').classes().join(' ')).toContain('bg-primary');
        expect(wrapper.get('[data-testid="email"]').attributes('data-slot')).toBe('input');
        expect(wrapper.get('[data-testid="forgot-password"]').text()).toBe(
            t('auth.forgotPassword'),
        );
        expect(wrapper.get('[data-testid="forgot-password"]').classes().join(' ')).toContain(
            'text-primary',
        );
        expect(wrapper.get('[data-testid="forgot-password"]').attributes('href')).toBe(
            '/forgot-password',
        );

        await wrapper.get('[data-testid="password"]').setValue('secret');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(router.currentRoute.value.path).toBe('/');
    });

    it('shows the generic failure message for 422', async () => {
        const message = 'sign-in-failed';
        stubSignIn(422, { message: 'Invalid.', errors: { email: [message] } });

        const { wrapper, router } = await mountSignIn();

        await submit(wrapper);
        await flushPromises();

        expect(wrapper.get('[data-testid="sign-in-error"]').text()).toBe(message);
        expect(wrapper.get('[data-testid="sign-in-error"]').classes().join(' ')).toContain(
            'text-destructive',
        );
        expect(router.currentRoute.value.path).toBe('/sign-in');
    });

    it('shows the block message for 429', async () => {
        const message = 'sign-in-blocked';
        stubSignIn(429, { message: 'Blocked.', errors: { email: [message] } });

        const { wrapper, router } = await mountSignIn();

        await submit(wrapper);
        await flushPromises();

        expect(wrapper.get('[data-testid="sign-in-error"]').text()).toBe(message);
        expect(wrapper.get('[data-testid="sign-in-error"]').classes().join(' ')).toContain(
            'text-destructive',
        );
        expect(router.currentRoute.value.path).toBe('/sign-in');
    });
});

function stubSignIn(status: number, body: unknown): void {
    vi.stubGlobal(
        'fetch',
        vi.fn(async (input: RequestInfo | URL) => {
            const url = String(input);

            if (url === '/sanctum/csrf-cookie') {
                return new Response(null, { status: 204 });
            }

            if (url === '/api/v1/auth/sign-in') {
                return jsonResponse(body, status);
            }

            return jsonResponse({ data: admin }, 200);
        }),
    );
}

async function mountSignIn(email?: string): Promise<{
    wrapper: ReturnType<typeof mount>;
    router: ReturnType<typeof createRouter>;
}> {
    const { default: SignInPage } = await import('../SignInPage.vue');
    const query = email === undefined ? '' : `?email=${encodeURIComponent(email)}`;
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/sign-in', component: SignInPage },
            { path: '/forgot-password', component: { template: '<div />' } },
            { path: '/', component: { template: '<div />' } },
        ],
    });
    const wrapper = mount({ template: '<RouterView />' }, { global: { plugins: [router] } });
    await router.push(`/sign-in${query}`);
    await flushPromises();

    return { router, wrapper };
}

async function submit(wrapper: ReturnType<typeof mount>): Promise<void> {
    await wrapper.get('[data-testid="email"]').setValue(admin.email);
    await wrapper.get('[data-testid="password"]').setValue('secret');
    await wrapper.get('form').trigger('submit');
}

function jsonResponse(body: unknown, status: number): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}
