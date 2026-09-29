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

const viewer: CurrentUser = {
    id: 4,
    email: 'viewer@acme.test',
    role: 'viewer',
    company_id: 3,
};

const owner: CurrentUser = {
    id: 1,
    email: 'owner@example.com',
    role: 'super_admin',
    company_id: null,
};

afterEach(async () => {
    vi.clearAllMocks();
    document.documentElement.lang = 'en';
    sessionStorage.clear();
    const { useCurrentUser } = await import('../../composables/useCurrentUser');
    useCurrentUser().clear();
    const { useSelectedCompany } = await import('../../composables/useSelectedCompany');
    useSelectedCompany().clearSelectedCompany();
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
                { path: '/companies', component: { template: '<div />' } },
                { path: '/company/users', component: { template: '<div />' } },
                { path: '/company', component: { template: '<div />' } },
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

    it('links a super admin to the company list', async () => {
        const wrapper = await mountHeader(owner);

        expect(wrapper.get('[data-testid="companies-link"]').text()).toBe(t('companies.title'));
        expect(wrapper.get('[data-testid="companies-link"]').attributes('href')).toBe('/companies');
        expect(wrapper.get('[data-testid="select-company-prompt"]').text()).toBe(
            t('companies.selectPrompt'),
        );
        expect(wrapper.find('[data-testid="company-users-link"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="company-profile-link"]').exists()).toBe(false);
    });

    it('shows the selected company name through t()', async () => {
        const { useSelectedCompany } = await import('../../composables/useSelectedCompany');
        useSelectedCompany().setSelectedCompany({ id: 3, name: 'Acme' });
        const wrapper = await mountHeader(owner);

        expect(wrapper.get('[data-testid="selected-company-name"]').text()).toBe(
            t('companies.selected', { name: 'Acme' }),
        );
        expect(wrapper.find('[data-testid="select-company-prompt"]').exists()).toBe(false);
        expect(wrapper.get('[data-testid="company-profile-link"]').attributes('href')).toBe(
            '/company',
        );
        expect(wrapper.get('[data-testid="company-users-link"]').attributes('href')).toBe(
            '/company/users',
        );
    });

    it('links a company admin to users and the company profile', async () => {
        const wrapper = await mountHeader(admin);

        expect(wrapper.get('[data-testid="company-users-link"]').text()).toBe(
            t('companyUsers.title'),
        );
        expect(wrapper.get('[data-testid="company-users-link"]').attributes('href')).toBe(
            '/company/users',
        );
        expect(wrapper.get('[data-testid="company-profile-link"]').text()).toBe(
            t('companyProfile.nav'),
        );
        expect(wrapper.get('[data-testid="company-profile-link"]').attributes('href')).toBe(
            '/company',
        );
        expect(wrapper.find('[data-testid="companies-link"]').exists()).toBe(false);
    });

    it('shows the select prompt when this tab still has a stored company the session rejected', async () => {
        const { useSelectedCompany } = await import('../../composables/useSelectedCompany');
        useSelectedCompany().setSelectedCompany({ id: 3, name: 'Acme' });
        useSelectedCompany().markSelectionRejected();
        const wrapper = await mountHeader(owner);

        expect(wrapper.get('[data-testid="select-company-prompt"]').text()).toBe(
            t('companies.selectPrompt'),
        );
        expect(wrapper.find('[data-testid="selected-company-name"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="company-users-link"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="company-profile-link"]').exists()).toBe(false);
    });

    it('links a viewer to the company profile', async () => {
        const wrapper = await mountHeader(viewer);

        expect(wrapper.get('[data-testid="company-profile-link"]').attributes('href')).toBe(
            '/company',
        );
        expect(wrapper.find('[data-testid="companies-link"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="company-users-link"]').exists()).toBe(false);
    });
});

async function mountHeader(user: CurrentUser): Promise<ReturnType<typeof mount>> {
    const { useCurrentUser } = await import('../../composables/useCurrentUser');
    const { default: AppHeader } = await import('../AppHeader.vue');
    useCurrentUser().currentUser.value = user;

    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/', component: { template: '<div />' } },
            { path: '/sign-in', component: { template: '<div />' } },
            { path: '/companies', component: { template: '<div />' } },
            { path: '/company/users', component: { template: '<div />' } },
            { path: '/company', component: { template: '<div />' } },
        ],
    });
    const wrapper = mount(AppHeader, { global: { plugins: [router] } });
    await router.isReady();

    return wrapper;
}
