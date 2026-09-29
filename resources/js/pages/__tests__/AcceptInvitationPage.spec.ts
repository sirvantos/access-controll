import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { ApiError } from '../../api/client';
import type { InvitationPreview } from '../../api/invitations';
import { t } from '../../utils/i18n';

const { showInvitation, acceptInvitation } = vi.hoisted(() => ({
    showInvitation: vi.fn(),
    acceptInvitation: vi.fn(),
}));

vi.mock('../../api/invitations', () => ({
    showInvitation,
    acceptInvitation,
}));

const preview: InvitationPreview = {
    email: 'invitee@acme.test',
    role: 'company_admin',
    expires_at: '2030-01-15T12:00:00+00:00',
};

const token = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

afterEach(() => {
    vi.clearAllMocks();
    document.documentElement.lang = 'en';
});

describe('AcceptInvitationPage', () => {
    it('shows the invitation email and role', async () => {
        showInvitation.mockResolvedValue({ data: preview });

        const wrapper = await mountPage();

        expect(showInvitation).toHaveBeenCalledWith(token);
        expect(wrapper.get('[data-testid="invitation-email"]').text()).toBe(preview.email);
        expect(wrapper.get('[data-testid="invitation-role"]').text()).toBe(
            t('roles.company_admin'),
        );
        expect(wrapper.get('[data-testid="password"]').element).toBeInstanceOf(HTMLInputElement);
        expect(wrapper.get('[data-testid="accept-invitation"]').element).toBeInstanceOf(
            HTMLButtonElement,
        );
        expect(wrapper.get('[data-testid="accept-invitation"]').text()).toBe(
            t('invitations.accept'),
        );
        expect(wrapper.get('[data-testid="accept-invitation"]').classes().join(' ')).toContain(
            'bg-primary',
        );
        expect(wrapper.get('[data-testid="password"]').attributes('data-slot')).toBe('input');
    });

    it('shows that the invitation is no longer valid on 410', async () => {
        showInvitation.mockRejectedValue(new ApiError(410, 'gone', 'invitation_invalid', null));

        const wrapper = await mountPage();

        expect(wrapper.get('[data-testid="invitation-invalid"]').text()).toBe(
            t('invitations.noLongerValid'),
        );
        expect(wrapper.get('[data-testid="invitation-invalid"]').classes().join(' ')).toContain(
            'text-destructive',
        );
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('shows the password field error on 422', async () => {
        showInvitation.mockResolvedValue({ data: preview });
        acceptInvitation.mockRejectedValue(
            new ApiError(422, 'invalid', null, { password: ['too-short'] }),
        );

        const wrapper = await mountPage();

        await wrapper.get('[data-testid="password"]').setValue('short');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(acceptInvitation).toHaveBeenCalledWith(token, 'short');
        expect(wrapper.get('[data-testid="password-error"]').text()).toBe('too-short');
        expect(wrapper.get('[data-testid="password-error"]').classes().join(' ')).toContain(
            'text-destructive',
        );
    });

    it('goes to sign-in with the invited email after acceptance', async () => {
        showInvitation.mockResolvedValue({ data: preview });
        acceptInvitation.mockResolvedValue({ ok: true });

        const { wrapper, router } = await mountWithRouter();

        await wrapper.get('[data-testid="password"]').setValue('password');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(router.currentRoute.value.path).toBe('/sign-in');
        expect(router.currentRoute.value.query.email).toBe(preview.email);
    });
});

async function mountPage(): Promise<ReturnType<typeof mount>> {
    const { wrapper } = await mountWithRouter();

    return wrapper;
}

async function mountWithRouter(): Promise<{
    wrapper: ReturnType<typeof mount>;
    router: ReturnType<typeof createRouter>;
}> {
    const { default: AcceptInvitationPage } = await import('../AcceptInvitationPage.vue');
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/invitation/:token', component: AcceptInvitationPage },
            { path: '/sign-in', component: { template: '<div />' } },
        ],
    });
    const wrapper = mount({ template: '<RouterView />' }, { global: { plugins: [router] } });
    await router.push(`/invitation/${token}`);
    await flushPromises();

    return { wrapper, router };
}
