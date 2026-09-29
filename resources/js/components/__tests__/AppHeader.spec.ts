import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
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
    const { resetAppSidebarForTests } = await import('../../composables/useAppSidebar');
    resetAppSidebarForTests();
});

describe('AppHeader', () => {
    it('shows the signed-in email and signs out', async () => {
        const { useCurrentUser } = await import('../../composables/useCurrentUser');
        const { currentUser } = useCurrentUser();
        currentUser.value = admin;

        const { wrapper, router } = await mountShell(admin);

        expect(wrapper.get('[data-testid="current-email"]').text()).toBe(admin.email);
        expect(wrapper.get('[data-testid="sign-out"]').text()).toBe(t('auth.signOut'));
        expect(wrapper.get('[data-testid="sidebar-toggle"]').attributes('aria-label')).toBe(
            t('nav.closeMenu'),
        );

        await wrapper.get('[data-testid="sign-out"]').trigger('click');
        await flushPromises();

        expect(signOut).toHaveBeenCalledOnce();
        expect(currentUser.value).toBeNull();
        expect(router.currentRoute.value.path).toBe('/sign-in');
    });

    it('links a super admin to the company list', async () => {
        const { wrapper } = await mountShell(owner);

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
        const { wrapper } = await mountShell(owner);

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

    it('truncates a long selected company name and sets title to the raw name', async () => {
        const longName =
            'Acme Industrial Holdings International Limited Partnership of Central Asia';
        const { useSelectedCompany } = await import('../../composables/useSelectedCompany');
        useSelectedCompany().setSelectedCompany({ id: 3, name: longName });
        const { wrapper } = await mountShell(owner);
        const name = wrapper.get('[data-testid="selected-company-name"]');
        const className = name.classes().join(' ');

        expect(name.text()).toBe(t('companies.selected', { name: longName }));
        expect(name.attributes('title')).toBe(longName);
        expect(className).toContain('truncate');
        expect(className).toContain('text-slate-900');
    });

    it('links a company admin to users and the company profile', async () => {
        const { wrapper } = await mountShell(admin);

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
        const { wrapper } = await mountShell(owner);

        expect(wrapper.get('[data-testid="select-company-prompt"]').text()).toBe(
            t('companies.selectPrompt'),
        );
        expect(wrapper.find('[data-testid="selected-company-name"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="company-users-link"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="company-profile-link"]').exists()).toBe(false);
    });

    it('links a viewer to the company profile', async () => {
        const { wrapper } = await mountShell(viewer);

        expect(wrapper.get('[data-testid="company-profile-link"]').attributes('href')).toBe(
            '/company',
        );
        expect(wrapper.find('[data-testid="companies-link"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="company-users-link"]').exists()).toBe(false);
    });

    it('toggles the sidebar open and closed', async () => {
        const { wrapper } = await mountShell(admin);

        expect(wrapper.find('[data-testid="app-sidebar"]').exists()).toBe(true);

        await wrapper.get('[data-testid="sidebar-toggle"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="app-sidebar"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="app-sidebar"]').classes().join(' ')).toContain(
            'md:hidden',
        );

        await wrapper.get('[data-testid="sidebar-toggle"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-testid="app-sidebar"]').classes().join(' ')).not.toContain(
            'md:hidden',
        );
    });
});

async function mountShell(
    user: CurrentUser,
): Promise<{ wrapper: ReturnType<typeof mount>; router: ReturnType<typeof createRouter> }> {
    const { useCurrentUser } = await import('../../composables/useCurrentUser');
    const { default: AppHeader } = await import('../AppHeader.vue');
    const { default: AppSidebar } = await import('../AppSidebar.vue');
    const { openSidebar } = await import('../../composables/useAppSidebar');
    useCurrentUser().currentUser.value = user;
    openSidebar();

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

    const Shell = defineComponent({
        components: { AppHeader, AppSidebar },
        template: '<div class="flex"><AppSidebar /><div class="flex-1"><AppHeader /></div></div>',
    });

    const wrapper = mount(Shell, { global: { plugins: [router] } });
    await router.isReady();
    await flushPromises();

    return { wrapper, router };
}
