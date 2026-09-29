import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter, type RouterHistory } from 'vue-router';
import type { Company, PendingInvitation } from '../../api/adminCompanies';
import { ApiError } from '../../api/client';
import { t } from '../../utils/i18n';

const {
    listCompanies,
    createCompany,
    listTimeZones,
    listFirstAdminInvitations,
    inviteFirstAdmin,
    resendFirstAdminInvitation,
    revokeFirstAdminInvitation,
    deactivateCompany,
    reactivateCompany,
    selectCompany,
    clearSelectedCompany,
} = vi.hoisted(() => ({
    listCompanies: vi.fn(),
    createCompany: vi.fn(),
    listTimeZones: vi.fn(),
    listFirstAdminInvitations: vi.fn(),
    inviteFirstAdmin: vi.fn(),
    resendFirstAdminInvitation: vi.fn(),
    revokeFirstAdminInvitation: vi.fn(),
    deactivateCompany: vi.fn(),
    reactivateCompany: vi.fn(),
    selectCompany: vi.fn(),
    clearSelectedCompany: vi.fn(),
}));

vi.mock('../../api/adminCompanies', () => ({
    listCompanies,
    createCompany,
    listTimeZones,
    listFirstAdminInvitations,
    inviteFirstAdmin,
    resendFirstAdminInvitation,
    revokeFirstAdminInvitation,
    deactivateCompany,
    reactivateCompany,
    DEFAULT_COMPANY_TIME_ZONE: 'Asia/Almaty',
}));

vi.mock('../../api/selectedCompany', () => ({
    selectCompany,
    clearSelectedCompany,
}));

const acme: Company = {
    id: 3,
    name: 'Acme',
    bin: '123456789012',
    is_active: true,
    awaiting_first_admin: true,
    created_at: '2026-01-15T12:00:00+00:00',
};

const closed: Company = {
    id: 4,
    name: 'Closed Co',
    bin: null,
    is_active: false,
    awaiting_first_admin: false,
    created_at: '2026-01-15T12:00:00+00:00',
};

const pending: PendingInvitation = {
    id: 9,
    email: 'admin@acme.test',
    role: 'company_admin',
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
        path: '/api/v1/admin/companies/3/invitations',
        per_page: 15,
        to: null,
        total: 0,
    },
};

const emptyCompanies = {
    data: [] as Company[],
    links: { first: null, last: null, prev: null, next: null },
    meta: {
        current_page: 1,
        from: null,
        last_page: 1,
        links: [],
        path: '/api/v1/admin/companies',
        per_page: 15,
        to: null,
        total: 0,
    },
};

afterEach(() => {
    vi.clearAllMocks();
    document.documentElement.lang = 'en';
    sessionStorage.clear();
});

describe('CompaniesPage', () => {
    beforeEach(() => {
        listCompanies.mockResolvedValue(emptyCompanies);
        listTimeZones.mockResolvedValue({
            data: { identifiers: ['Africa/Abidjan', 'Asia/Almaty', 'Europe/Moscow'] },
        });
        listFirstAdminInvitations.mockResolvedValue(emptyInvitations);
        createCompany.mockResolvedValue({ data: acme });
        inviteFirstAdmin.mockResolvedValue({ data: pending });
        resendFirstAdminInvitation.mockResolvedValue({ data: pending });
        revokeFirstAdminInvitation.mockResolvedValue({ ok: true });
        deactivateCompany.mockResolvedValue({ data: { ...acme, is_active: false } });
        reactivateCompany.mockResolvedValue({ data: { ...closed, is_active: true } });
        selectCompany.mockResolvedValue({ data: { id: acme.id, name: acme.name } });
        clearSelectedCompany.mockResolvedValue({ ok: true });
    });

    it('lists companies with their state', async () => {
        listCompanies.mockResolvedValue({
            data: [acme, closed],
            links: { first: null, last: null, prev: null, next: null },
            meta: {
                current_page: 1,
                from: 1,
                last_page: 2,
                links: [],
                path: '/api/v1/admin/companies',
                per_page: 15,
                to: 2,
                total: 16,
            },
        });

        const wrapper = await mountPage();

        expect(listCompanies).toHaveBeenCalledWith(1);
        expect(wrapper.text()).toContain(acme.name);
        expect(wrapper.get('[data-testid="company-bin-3"]').text()).toBe('123456789012');
        expect(wrapper.get('[data-testid="company-bin-4"]').text()).toBe('');
        expect(wrapper.text()).toContain(closed.name);
        expect(wrapper.text()).toContain(acme.created_at);
        expect(wrapper.text()).toContain(t('companies.active'));
        expect(wrapper.text()).toContain(t('companies.deactivated'));

        await wrapper.get('[data-testid="next-page"]').trigger('click');
        await flushPromises();

        expect(listCompanies).toHaveBeenCalledWith(2);
    });

    it('selects a company including a deactivated one and clears the selection', async () => {
        listCompanies.mockResolvedValue({
            data: [acme, closed],
            links: { first: null, last: null, prev: null, next: null },
            meta: {
                current_page: 1,
                from: 1,
                last_page: 1,
                links: [],
                path: '/api/v1/admin/companies',
                per_page: 15,
                to: 2,
                total: 2,
            },
        });
        selectCompany.mockResolvedValue({ data: { id: closed.id, name: closed.name } });

        const wrapper = await mountPage();

        await wrapper.get('[data-testid="select-company-4"]').trigger('click');
        await flushPromises();

        expect(selectCompany).toHaveBeenCalledWith(4);

        const { useSelectedCompany } = await import('../../composables/useSelectedCompany');
        expect(useSelectedCompany().selectedCompany.value).toEqual({
            id: closed.id,
            name: closed.name,
        });

        await wrapper.get('[data-testid="clear-selected-company"]').trigger('click');
        await flushPromises();

        expect(clearSelectedCompany).toHaveBeenCalledOnce();
        expect(useSelectedCompany().selectedCompany.value).toBeNull();
    });

    it('creates a company from the name and first admin email', async () => {
        const wrapper = await mountPage();

        await wrapper.get('[data-testid="company-name"]').setValue('Acme');
        await wrapper.get('[data-testid="first-admin-email"]').setValue('admin@acme.test');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(createCompany).toHaveBeenCalledWith({
            name: 'Acme',
            first_admin_email: 'admin@acme.test',
            time_zone: 'Asia/Almaty',
            bin: '',
            contact_person: '',
            phone: '',
            email: '',
        });
    });

    it('creates a company with a time zone and optional details', async () => {
        const wrapper = await mountPage();

        await wrapper.get('[data-testid="company-name"]').setValue('Acme');
        await wrapper.get('[data-testid="company-time-zone"]').setValue('Europe/Moscow');
        await wrapper.get('[data-testid="company-bin"]').setValue('123456789012');
        await wrapper.get('[data-testid="company-contact-person"]').setValue('Acme Contact');
        await wrapper.get('[data-testid="company-phone"]').setValue('+7 (700) 123-45-67');
        await wrapper.get('[data-testid="company-email"]').setValue('office@acme.test');
        await wrapper.get('[data-testid="first-admin-email"]').setValue('admin@acme.test');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(listTimeZones).toHaveBeenCalled();
        expect(wrapper.find('[data-testid="working-day-start"]').exists()).toBe(false);
        expect(createCompany).toHaveBeenCalledWith({
            name: 'Acme',
            first_admin_email: 'admin@acme.test',
            time_zone: 'Europe/Moscow',
            bin: '123456789012',
            contact_person: 'Acme Contact',
            phone: '+7 (700) 123-45-67',
            email: 'office@acme.test',
        });
    });

    it('shows each invalid create field', async () => {
        createCompany.mockRejectedValue(
            new ApiError(422, 'invalid', null, {
                name: ['name'],
                time_zone: ['time zone'],
                bin: ['bin'],
                contact_person: ['contact'],
                phone: ['phone'],
                email: ['email'],
                first_admin_email: ['registered'],
            }),
        );

        const wrapper = await mountPage();

        await wrapper.get('[data-testid="company-name"]').setValue('Acme');
        await wrapper.get('[data-testid="first-admin-email"]').setValue('admin@acme.test');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[data-testid="company-name-error"]').text()).toBe('name');
        expect(wrapper.get('[data-testid="company-time-zone-error"]').text()).toBe('time zone');
        expect(wrapper.get('[data-testid="company-bin-error"]').text()).toBe('bin');
        expect(wrapper.get('[data-testid="company-contact-person-error"]').text()).toBe('contact');
        expect(wrapper.get('[data-testid="company-phone-error"]').text()).toBe('phone');
        expect(wrapper.get('[data-testid="company-email-error"]').text()).toBe('email');
        expect(wrapper.get('[data-testid="first-admin-email-error"]').text()).toBe('registered');
    });

    it('shows a registered first-admin email next to that field', async () => {
        createCompany.mockRejectedValue(
            new ApiError(422, 'registered', null, { first_admin_email: ['registered'] }),
        );

        const wrapper = await mountPage();

        await wrapper.get('[data-testid="company-name"]').setValue('Acme');
        await wrapper.get('[data-testid="first-admin-email"]').setValue('admin@acme.test');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[data-testid="first-admin-email-error"]').text()).toBe('registered');
    });

    it('shows first-admin invitations only while the company is awaiting one', async () => {
        listCompanies.mockResolvedValue({
            ...emptyCompanies,
            data: [acme, closed],
        });
        listFirstAdminInvitations.mockResolvedValue({
            ...emptyInvitations,
            data: [pending],
        });

        const wrapper = await mountPage();

        expect(listFirstAdminInvitations).toHaveBeenCalledWith(acme.id, 1);
        expect(listFirstAdminInvitations).not.toHaveBeenCalledWith(closed.id, 1);
        expect(wrapper.find(`[data-testid="first-admin-invitations-${closed.id}"]`).exists()).toBe(
            false,
        );
        expect(wrapper.get('[data-testid="invitation-row"]').text()).toContain(pending.email);
        expect(wrapper.get('[data-testid="invitation-row"]').text()).toContain(
            t('roles.company_admin'),
        );
        expect(wrapper.get('[data-testid="invitation-row"]').text()).toContain(pending.expires_at);

        await wrapper.get('[data-testid="resend-invitation-9"]').trigger('click');
        await flushPromises();

        expect(resendFirstAdminInvitation).toHaveBeenCalledWith(acme.id, pending.id);

        await wrapper.get('[data-testid="revoke-invitation-9"]').trigger('click');
        await flushPromises();

        expect(revokeFirstAdminInvitation).toHaveBeenCalledWith(acme.id, pending.id);

        await wrapper
            .get(`[data-testid="replacement-email-${acme.id}"]`)
            .setValue('next@acme.test');
        await wrapper.get(`[data-testid="replacement-form-${acme.id}"]`).trigger('submit');
        await flushPromises();

        expect(inviteFirstAdmin).toHaveBeenCalledWith(acme.id, 'next@acme.test');
    });

    it('updates the row when a company is deactivated or reactivated', async () => {
        const page = {
            ...emptyCompanies,
            data: [acme, closed],
        };

        listCompanies
            .mockResolvedValueOnce(page)
            .mockResolvedValueOnce({
                ...page,
                data: [{ ...acme, is_active: false }, closed],
            })
            .mockResolvedValueOnce({
                ...page,
                data: [
                    { ...acme, is_active: false },
                    { ...closed, is_active: true },
                ],
            });

        const wrapper = await mountPage();

        await wrapper.get('[data-testid="deactivate-company-3"]').trigger('click');
        await flushPromises();

        expect(deactivateCompany).toHaveBeenCalledWith(acme.id);
        expect(wrapper.findAll('[data-testid="company-row"]')[0]?.text()).toContain(
            t('companies.deactivated'),
        );
        expect(wrapper.find('[data-testid="deactivate-company-3"]').exists()).toBe(false);

        await wrapper.get('[data-testid="reactivate-company-4"]').trigger('click');
        await flushPromises();

        expect(reactivateCompany).toHaveBeenCalledWith(closed.id);
        expect(wrapper.findAll('[data-testid="company-row"]')[1]?.text()).toContain(
            t('companies.active'),
        );
        expect(wrapper.get('[data-testid="company-bin-3"]').text()).toBe('123456789012');
        expect(wrapper.get('[data-testid="company-bin-4"]').text()).toBe('');
        expect(wrapper.find('[data-testid="reactivate-company-4"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid^="delete-company"]').exists()).toBe(false);
    });

    it('searches from the query string and shows the empty copy when nothing matches', async () => {
        listCompanies.mockResolvedValue({
            ...emptyCompanies,
            data: [acme],
        });

        const wrapper = await mountPage('/companies?search=stroy&page=2');

        expect(listCompanies).toHaveBeenCalledWith(2, 'stroy');
        expect(wrapper.get('[data-testid="company-search"]').element).toHaveProperty(
            'value',
            'stroy',
        );

        listCompanies.mockResolvedValue(emptyCompanies);
        await wrapper.get('[data-testid="company-search"]').setValue('missing');
        await flushPromises();

        expect(listCompanies).toHaveBeenCalledWith(2, 'missing');
        expect(wrapper.get('[data-testid="companies-empty"]').text()).toBe(t('companies.empty'));
    });
});

async function mountPage(path = '/companies'): Promise<ReturnType<typeof mount>> {
    const { default: CompaniesPage } = await import('../CompaniesPage.vue');
    const router = createRouter({
        history: historyStartingAt(path),
        routes: [{ path: '/companies', component: { template: '<div />' } }],
    });
    const wrapper = mount(CompaniesPage, { global: { plugins: [router] } });
    await router.isReady();
    await flushPromises();

    return wrapper;
}

function historyStartingAt(path: string): RouterHistory {
    const history = createMemoryHistory();
    history.replace(path);

    return history;
}
