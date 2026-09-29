import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter, type RouterHistory } from 'vue-router';
import type { CompanyUser, PendingInvitation } from '../../api/companyUsers';
import { ApiError } from '../../api/client';
import { t } from '../../utils/i18n';

const {
    listCompanyUsers,
    listCompanyInvitations,
    inviteCompanyUser,
    resendCompanyInvitation,
    revokeCompanyInvitation,
    deactivateCompanyUser,
    reactivateCompanyUser,
    changeCompanyUserRole,
} = vi.hoisted(() => ({
    listCompanyUsers: vi.fn(),
    listCompanyInvitations: vi.fn(),
    inviteCompanyUser: vi.fn(),
    resendCompanyInvitation: vi.fn(),
    revokeCompanyInvitation: vi.fn(),
    deactivateCompanyUser: vi.fn(),
    reactivateCompanyUser: vi.fn(),
    changeCompanyUserRole: vi.fn(),
}));

vi.mock('../../api/companyUsers', () => ({
    listCompanyUsers,
    listCompanyInvitations,
    inviteCompanyUser,
    resendCompanyInvitation,
    revokeCompanyInvitation,
    deactivateCompanyUser,
    reactivateCompanyUser,
    changeCompanyUserRole,
}));

const admin: CompanyUser = {
    id: 2,
    email: 'admin@acme.test',
    role: 'company_admin',
    is_active: true,
};

const former: CompanyUser = {
    id: 3,
    email: 'former@acme.test',
    role: 'viewer',
    is_active: false,
};

const pending: PendingInvitation = {
    id: 9,
    email: 'invitee@acme.test',
    role: 'viewer',
    expires_at: '2030-01-22T12:00:00+00:00',
};

const emptyInvitations = {
    data: [] as PendingInvitation[],
    links: { first: null, last: null, prev: null, next: null },
    meta: {
        current_page: 1,
        from: null,
        last_page: 1,
        links: [],
        path: '/api/v1/company/invitations',
        per_page: 15,
        to: null,
        total: 0,
    },
};

afterEach(() => {
    vi.clearAllMocks();
    document.documentElement.lang = 'en';
});

const emptyUsers = {
    data: [] as CompanyUser[],
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

describe('CompanyUsersPage', () => {
    beforeEach(() => {
        listCompanyUsers.mockResolvedValue(emptyUsers);
        listCompanyInvitations.mockResolvedValue(emptyInvitations);
        inviteCompanyUser.mockResolvedValue({ data: pending });
        resendCompanyInvitation.mockResolvedValue({ data: pending });
        revokeCompanyInvitation.mockResolvedValue({ ok: true });
        deactivateCompanyUser.mockResolvedValue({ data: { ...admin, is_active: false } });
        reactivateCompanyUser.mockResolvedValue({ data: { ...former, is_active: true } });
        changeCompanyUserRole.mockResolvedValue({ data: { ...admin, role: 'viewer' } });
    });

    it('lists users with their state and has no delete control', async () => {
        listCompanyUsers.mockResolvedValue({
            data: [admin, former],
            links: { first: null, last: null, prev: null, next: null },
            meta: {
                current_page: 1,
                from: 1,
                last_page: 2,
                links: [],
                path: '/api/v1/company/users',
                per_page: 15,
                to: 2,
                total: 16,
            },
        });

        const { default: CompanyUsersPage } = await import('../CompanyUsersPage.vue');
        const router = createRouter({
            history: historyStartingAt('/company/users'),
            routes: [{ path: '/company/users', component: { template: '<div />' } }],
        });
        const wrapper = mount(CompanyUsersPage, { global: { plugins: [router] } });
        await router.isReady();
        await flushPromises();

        expect(listCompanyUsers).toHaveBeenCalledWith(1);
        expect(wrapper.text()).toContain(admin.email);
        expect(wrapper.text()).toContain(former.email);
        expect(wrapper.text()).toContain(t('roles.company_admin'));
        expect(wrapper.text()).toContain(t('roles.viewer'));
        expect(wrapper.text()).toContain(t('companyUsers.active'));
        expect(wrapper.text()).toContain(t('companyUsers.deactivated'));
        expect(wrapper.find('[data-testid="delete-user"]').exists()).toBe(false);
        expect(wrapper.findAll('button').map((button) => button.attributes('data-testid'))).toEqual(
            ['invite-user', 'deactivate-user-2', 'reactivate-user-3', 'previous-page', 'next-page'],
        );
        expect(wrapper.get('[data-testid="invite-role"]').element).toBeInstanceOf(HTMLSelectElement);
        expect(wrapper.get('[data-testid="user-role-2"]').element).toBeInstanceOf(HTMLSelectElement);

        const badges = wrapper.findAll('[data-slot="badge"]');
        const activeBadge = badges.find((badge) => badge.text() === t('companyUsers.active'));
        const deactivatedBadge = badges.find(
            (badge) => badge.text() === t('companyUsers.deactivated'),
        );

        expect(activeBadge).toBeDefined();
        expect(deactivatedBadge).toBeDefined();
        expect(activeBadge?.classes().join(' ')).toContain('bg-success');
        expect(deactivatedBadge?.classes().join(' ')).not.toContain('bg-success');
        expect(wrapper.get('[data-testid="invite-user"]').classes().join(' ')).toContain(
            'bg-primary',
        );
        expect(wrapper.get('[data-testid="deactivate-user-2"]').classes().join(' ')).toContain(
            'bg-destructive',
        );
        expect(wrapper.get('[data-testid="reactivate-user-3"]').classes().join(' ')).toContain(
            'border',
        );
        expect(wrapper.get('[data-testid="reactivate-user-3"]').classes().join(' ')).not.toContain(
            'bg-primary',
        );
        expect(wrapper.get('[data-testid="previous-page"]').classes().join(' ')).toContain('border');
        expect(wrapper.get('[data-testid="next-page"]').classes().join(' ')).not.toContain(
            'bg-primary',
        );

        await wrapper.get('[data-testid="next-page"]').trigger('click');
        await flushPromises();

        expect(listCompanyUsers).toHaveBeenCalledWith(2);
    });

    it('offers only company admin and viewer when inviting', async () => {
        listCompanyUsers.mockResolvedValue({
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
        });

        const { default: CompanyUsersPage } = await import('../CompanyUsersPage.vue');
        const router = createRouter({
            history: historyStartingAt('/company/users'),
            routes: [{ path: '/company/users', component: { template: '<div />' } }],
        });
        const wrapper = mount(CompanyUsersPage, { global: { plugins: [router] } });
        await router.isReady();
        await flushPromises();

        const options = wrapper.get('[data-testid="invite-role"]').findAll('option');

        expect(options.map((option) => option.attributes('value'))).toEqual([
            'company_admin',
            'viewer',
        ]);
        expect(options.map((option) => option.text())).toEqual([
            t('roles.company_admin'),
            t('roles.viewer'),
        ]);

        await wrapper.get('[data-testid="invite-email"]').setValue('viewer@acme.test');
        await wrapper.get('[data-testid="invite-role"]').setValue('viewer');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(inviteCompanyUser).toHaveBeenCalledWith('viewer@acme.test', 'viewer');
        expect(listCompanyInvitations).toHaveBeenCalledWith(1);
    });

    it('shows the registered-email message when the invitation is refused', async () => {
        inviteCompanyUser.mockRejectedValue(
            new ApiError(422, 'registered', null, { email: ['registered'] }),
        );

        const wrapper = await mountPage();

        await wrapper.get('[data-testid="invite-email"]').setValue('admin@acme.test');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[data-testid="invite-error"]').text()).toBe('registered');
        expect(wrapper.get('[data-testid="invite-error"]').classes().join(' ')).toContain(
            'text-destructive',
        );
    });

    it('lists a pending invitation and re-sends or revokes it', async () => {
        listCompanyInvitations.mockResolvedValue({
            ...emptyInvitations,
            data: [pending],
        });

        const wrapper = await mountPage();

        expect(wrapper.get('[data-testid="invitation-row"]').text()).toContain(pending.email);
        expect(wrapper.get('[data-testid="invitation-row"]').text()).toContain(t('roles.viewer'));
        expect(wrapper.get('[data-testid="invitation-row"]').text()).toContain(pending.expires_at);
        expect(
            wrapper.find('[data-testid="invite-role"] option[value="super_admin"]').exists(),
        ).toBe(false);

        await wrapper.get('[data-testid="resend-invitation-9"]').trigger('click');
        await flushPromises();

        expect(resendCompanyInvitation).toHaveBeenCalledWith(pending.id);

        await wrapper.get('[data-testid="revoke-invitation-9"]').trigger('click');
        await flushPromises();

        expect(revokeCompanyInvitation).toHaveBeenCalledWith(pending.id);
        expect(wrapper.get('[data-testid="resend-invitation-9"]').classes().join(' ')).toContain(
            'border',
        );
        expect(wrapper.get('[data-testid="resend-invitation-9"]').classes().join(' ')).not.toContain(
            'bg-primary',
        );
        expect(wrapper.get('[data-testid="revoke-invitation-9"]').classes().join(' ')).toContain(
            'bg-destructive',
        );
    });

    it('shows the not-pending message when re-send is refused', async () => {
        listCompanyInvitations.mockResolvedValue({
            ...emptyInvitations,
            data: [pending],
        });
        resendCompanyInvitation.mockRejectedValue(
            new ApiError(409, 'not-pending', 'invitation_not_pending', null),
        );

        const wrapper = await mountPage();

        await wrapper.get('[data-testid="resend-invitation-9"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-testid="invitation-error"]').text()).toBe('not-pending');
    });

    it('updates the row when a user is deactivated or reactivated', async () => {
        const viewer: CompanyUser = {
            id: 4,
            email: 'viewer@acme.test',
            role: 'viewer',
            is_active: true,
        };

        listCompanyUsers
            .mockResolvedValueOnce(usersPage([viewer, former]))
            .mockResolvedValueOnce(usersPage([{ ...viewer, is_active: false }, former]))
            .mockResolvedValueOnce(
                usersPage([
                    { ...viewer, is_active: false },
                    { ...former, is_active: true },
                ]),
            );
        deactivateCompanyUser.mockResolvedValue({ data: { ...viewer, is_active: false } });
        reactivateCompanyUser.mockResolvedValue({ data: { ...former, is_active: true } });

        const wrapper = await mountPage();

        expect(wrapper.get('[data-testid="user-row"]').text()).toContain(t('companyUsers.active'));
        expect(wrapper.find('[data-testid="delete-user"]').exists()).toBe(false);

        await wrapper.get('[data-testid="deactivate-user-4"]').trigger('click');
        await flushPromises();

        expect(deactivateCompanyUser).toHaveBeenCalledWith(viewer.id);
        expect(wrapper.text()).toContain(t('companyUsers.deactivated'));
        expect(wrapper.find('[data-testid="deactivate-user-4"]').exists()).toBe(false);

        await wrapper.get('[data-testid="reactivate-user-3"]').trigger('click');
        await flushPromises();

        expect(reactivateCompanyUser).toHaveBeenCalledWith(former.id);
        expect(wrapper.text()).toContain(t('companyUsers.active'));
        expect(wrapper.find('[data-testid="reactivate-user-3"]').exists()).toBe(false);
    });

    it('shows the last active admin explanation', async () => {
        listCompanyUsers.mockResolvedValue(usersPage([admin]));
        deactivateCompanyUser.mockRejectedValue(
            new ApiError(409, 'server-message', 'last_active_admin', null),
        );

        const wrapper = await mountPage();

        await wrapper.get('[data-testid="deactivate-user-2"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-testid="user-error"]').text()).toBe(
            t('companyUsers.lastActiveAdmin'),
        );
        expect(wrapper.text()).toContain(t('companyUsers.active'));
    });

    it('changes a role between company admin and viewer and explains the last admin refusal', async () => {
        const viewer: CompanyUser = {
            id: 4,
            email: 'viewer@acme.test',
            role: 'viewer',
            is_active: true,
        };

        listCompanyUsers
            .mockResolvedValueOnce(usersPage([viewer, admin]))
            .mockResolvedValueOnce(usersPage([{ ...viewer, role: 'company_admin' }, admin]))
            .mockResolvedValueOnce(usersPage([{ ...viewer, role: 'company_admin' }, admin]));
        changeCompanyUserRole.mockResolvedValueOnce({
            data: { ...viewer, role: 'company_admin' },
        });
        changeCompanyUserRole.mockRejectedValueOnce(
            new ApiError(409, 'server-message', 'last_active_admin', null),
        );

        const wrapper = await mountPage();
        const roleSelect = wrapper.get('[data-testid="user-role-4"]');

        expect(roleSelect.find('option[value="super_admin"]').exists()).toBe(false);
        expect(roleSelect.findAll('option').map((option) => option.attributes('value'))).toEqual([
            'company_admin',
            'viewer',
        ]);

        await roleSelect.setValue('company_admin');
        await flushPromises();

        expect(changeCompanyUserRole).toHaveBeenCalledWith(viewer.id, 'company_admin');
        expect(
            (wrapper.get('[data-testid="user-role-4"]').element as HTMLSelectElement).value,
        ).toBe('company_admin');

        await wrapper.get('[data-testid="user-role-2"]').setValue('viewer');
        await flushPromises();

        expect(changeCompanyUserRole).toHaveBeenCalledWith(admin.id, 'viewer');
        expect(wrapper.get('[data-testid="user-error"]').text()).toBe(
            t('companyUsers.lastActiveAdmin'),
        );
        expect(
            (wrapper.get('[data-testid="user-role-2"]').element as HTMLSelectElement).value,
        ).toBe('company_admin');
    });
});

async function mountPage(): Promise<ReturnType<typeof mount>> {
    const { default: CompanyUsersPage } = await import('../CompanyUsersPage.vue');
    const router = createRouter({
        history: historyStartingAt('/company/users'),
        routes: [{ path: '/company/users', component: { template: '<div />' } }],
    });
    const wrapper = mount(CompanyUsersPage, { global: { plugins: [router] } });
    await router.isReady();
    await flushPromises();

    return wrapper;
}

function historyStartingAt(path: string): RouterHistory {
    const history = createMemoryHistory();
    history.replace(path);

    return history;
}

function usersPage(data: CompanyUser[]) {
    return {
        data,
        links: { first: null, last: null, prev: null, next: null },
        meta: {
            current_page: 1,
            from: data.length === 0 ? null : 1,
            last_page: 1,
            links: [],
            path: '/api/v1/company/users',
            per_page: 15,
            to: data.length === 0 ? null : data.length,
            total: data.length,
        },
    };
}
