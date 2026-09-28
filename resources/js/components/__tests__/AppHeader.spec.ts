import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import type { CurrentUser } from '../../api/types';
import { t } from '../../utils/i18n';

const { signOut } = vi.hoisted(() => ({
    signOut: vi.fn(async () => undefined),
}));

vi.mock('../../api/auth', () => ({
    signOut,
    signIn: vi.fn(),
    fetchCurrentUser: vi.fn(),
}));

const admin: CurrentUser = {
    id: 2,
    email: 'admin@acme.test',
    role: 'company_admin',
    company_id: 3,
};

afterEach(() => {
    vi.clearAllMocks();
    document.documentElement.lang = 'en';
});

describe('AppHeader', () => {
    it('shows the signed-in email and signs out', async () => {
        const { useCurrentUser } = await import('../../composables/useCurrentUser');
        const { default: AppHeader } = await import('../AppHeader.vue');
        const { currentUser } = useCurrentUser();
        currentUser.value = admin;

        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                { path: '/', component: { template: '<div />' } },
                { path: '/sign-in', component: { template: '<div />' } },
            ],
        });
        const wrapper = mount(AppHeader, { global: { plugins: [router] } });
        await router.isReady();

        expect(wrapper.get('[data-testid="current-email"]').text()).toBe(admin.email);
        expect(wrapper.get('[data-testid="sign-out"]').text()).toBe(t('auth.signOut'));

        await wrapper.get('[data-testid="sign-out"]').trigger('click');
        await flushPromises();

        expect(signOut).toHaveBeenCalledOnce();
        expect(currentUser.value).toBeNull();
        expect(router.currentRoute.value.path).toBe('/sign-in');
    });
});
