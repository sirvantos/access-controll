import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { ApiError } from '../../api/client';
import { t } from '../../utils/i18n';

const { resetPassword } = vi.hoisted(() => ({
    resetPassword: vi.fn(),
}));

vi.mock('../../api/auth', () => ({
    resetPassword,
}));

const token = 'reset-token-0123456789abcdef';
const email = 'admin@acme.test';

afterEach(() => {
    vi.clearAllMocks();
    document.documentElement.lang = 'en';
});

describe('ResetPasswordPage', () => {
    it('sets a new password and goes to sign-in with the email', async () => {
        resetPassword.mockResolvedValue({ ok: true });

        const { wrapper, router } = await mountPage();

        expect(wrapper.get('[data-testid="password"]').element).toBeInstanceOf(HTMLInputElement);
        expect(wrapper.get('[data-testid="reset-password"]').element).toBeInstanceOf(
            HTMLButtonElement,
        );
        expect(wrapper.get('[data-testid="reset-password"]').text()).toBe(t('auth.resetPassword'));
        expect(wrapper.get('[data-testid="reset-password"]').classes().join(' ')).toContain(
            'bg-primary',
        );
        expect(wrapper.get('[data-testid="password"]').attributes('data-slot')).toBe('input');

        await wrapper.get('[data-testid="password"]').setValue('new-password');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(resetPassword).toHaveBeenCalledWith(token, email, 'new-password');
        expect(router.currentRoute.value.path).toBe('/sign-in');
        expect(router.currentRoute.value.query.email).toBe(email);
    });

    it('shows a request for a new link when the token is rejected', async () => {
        resetPassword.mockRejectedValue(
            new ApiError(422, 'invalid', null, { token: ['invalid-token'] }),
        );

        const wrapper = await mountPage().then(({ wrapper: page }) => page);

        await wrapper.get('[data-testid="password"]').setValue('new-password');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        const link = wrapper.get('[data-testid="request-new-link"]');

        expect(link.text()).toBe(t('auth.requestNewLink'));
        expect(link.attributes('href')).toBe('/forgot-password');
        expect(wrapper.get('[data-testid="reset-token-error"]').classes().join(' ')).toContain(
            'text-destructive',
        );
        expect(link.classes().join(' ')).toContain('text-primary');
    });

    it('shows a field error for an invalid password', async () => {
        resetPassword.mockRejectedValue(
            new ApiError(422, 'invalid', null, { password: ['too-short'] }),
        );

        const wrapper = await mountPage().then(({ wrapper: page }) => page);

        await wrapper.get('[data-testid="password"]').setValue('short');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[data-testid="password-error"]').text()).toBe('too-short');
        expect(wrapper.get('[data-testid="password-error"]').classes().join(' ')).toContain(
            'text-destructive',
        );
        expect(wrapper.find('[data-testid="reset-token-error"]').exists()).toBe(false);
    });
});

async function mountPage(): Promise<{
    wrapper: ReturnType<typeof mount>;
    router: ReturnType<typeof createRouter>;
}> {
    const { default: ResetPasswordPage } = await import('../ResetPasswordPage.vue');
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/reset-password/:token', component: ResetPasswordPage },
            { path: '/forgot-password', component: { template: '<div />' } },
            { path: '/sign-in', component: { template: '<div />' } },
        ],
    });
    const wrapper = mount({ template: '<RouterView />' }, { global: { plugins: [router] } });
    await router.push(`/reset-password/${token}?email=${encodeURIComponent(email)}`);
    await flushPromises();

    return { wrapper, router };
}
