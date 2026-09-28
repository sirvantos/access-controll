import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { ApiError } from '../../api/client';
import { t } from '../../utils/i18n';

const { requestPasswordReset } = vi.hoisted(() => ({
    requestPasswordReset: vi.fn(),
}));

vi.mock('../../api/auth', () => ({
    requestPasswordReset,
}));

afterEach(() => {
    vi.clearAllMocks();
    document.documentElement.lang = 'en';
});

describe('ForgotPasswordPage', () => {
    it('shows the same confirmation after a request', async () => {
        requestPasswordReset.mockResolvedValue({ ok: true });

        const wrapper = await mountPage();

        await wrapper.get('[data-testid="email"]').setValue('admin@acme.test');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(requestPasswordReset).toHaveBeenCalledWith('admin@acme.test');
        expect(wrapper.get('[data-testid="reset-confirmation"]').text()).toBe(
            t('auth.resetConfirmation'),
        );
    });

    it('shows a field error for an invalid email and no confirmation', async () => {
        requestPasswordReset.mockRejectedValue(
            new ApiError(422, 'invalid', null, { email: ['invalid'] }),
        );

        const wrapper = await mountPage();

        await wrapper.get('[data-testid="email"]').setValue('not-an-email');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[data-testid="email-error"]').text()).toBe('invalid');
        expect(wrapper.find('[data-testid="reset-confirmation"]').exists()).toBe(false);
    });
});

async function mountPage(): Promise<ReturnType<typeof mount>> {
    const { default: ForgotPasswordPage } = await import('../ForgotPasswordPage.vue');
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [{ path: '/forgot-password', component: ForgotPasswordPage }],
    });
    const wrapper = mount({ template: '<RouterView />' }, { global: { plugins: [router] } });
    await router.push('/forgot-password');
    await flushPromises();

    return wrapper;
}
