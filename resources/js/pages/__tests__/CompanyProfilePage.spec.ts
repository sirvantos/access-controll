import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import type { WorkingDaySettings } from '../../api/adminCompanies';
import { ApiError } from '../../api/client';
import type { CompanyProfile } from '../../api/companyProfile';
import type { CurrentUser } from '../../api/types';
import { nativeCheckboxClass } from '../../components/ui/checkbox';
import { t } from '../../utils/i18n';

const {
    listTimeZones,
    showCompanyProfile,
    updateCompanyProfile,
    showWorkingDaySettings,
    updateWorkingDaySettings,
} = vi.hoisted(() => ({
    listTimeZones: vi.fn(),
    showCompanyProfile: vi.fn(),
    updateCompanyProfile: vi.fn(),
    showWorkingDaySettings: vi.fn(),
    updateWorkingDaySettings: vi.fn(),
}));

vi.mock('../../api/adminCompanies', () => ({
    listTimeZones,
}));

vi.mock('../../api/companyProfile', () => ({
    showCompanyProfile,
    updateCompanyProfile,
    showWorkingDaySettings,
    updateWorkingDaySettings,
}));

const profile: CompanyProfile = {
    id: 3,
    name: 'Acme',
    time_zone: 'Asia/Almaty',
    bin: '123456789012',
    contact_person: 'Ada Contact',
    phone: '+7 (700) 123-45-67',
    email: 'office@acme.test',
};

const settings: WorkingDaySettings = {
    start_time: '09:00',
    end_time: '18:00',
    working_days: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    break_duration_minutes: 60,
    break_deducted: true,
    lateness_grace_minutes: 0,
};

beforeEach(() => {
    listTimeZones.mockResolvedValue({
        data: { identifiers: ['Asia/Almaty', 'Europe/Moscow'] },
    });
    showCompanyProfile.mockResolvedValue({ data: profile });
    showWorkingDaySettings.mockResolvedValue({ data: settings });
    updateCompanyProfile.mockResolvedValue({
        data: { ...profile, name: 'Alma Stroy', bin: null },
    });
    updateWorkingDaySettings.mockResolvedValue({
        data: { ...settings, start_time: '08:00', break_deducted: false },
    });
});

const viewer: CurrentUser = {
    id: 4,
    email: 'viewer@acme.test',
    role: 'viewer',
    company_id: 3,
};

afterEach(async () => {
    vi.clearAllMocks();
    document.documentElement.lang = 'en';
    const { useCurrentUser } = await import('../../composables/useCurrentUser');
    useCurrentUser().clear();
});

describe('CompanyProfilePage', () => {
    it('saves edited company details', async () => {
        const wrapper = await mountPage();

        expect(wrapper.get('[data-testid="company-name"]').element).toHaveProperty('value', 'Acme');
        expect(wrapper.get('[data-testid="company-time-zone"]').element).toBeInstanceOf(
            HTMLSelectElement,
        );
        expect(wrapper.get('[data-testid="save-company-profile"]').classes().join(' ')).toContain(
            'bg-primary',
        );
        expect(wrapper.get('h1').text()).toBe(t('companyProfile.title'));

        await wrapper.get('[data-testid="company-name"]').setValue('Alma Stroy');
        await wrapper.get('[data-testid="company-time-zone"]').setValue('Europe/Moscow');
        await wrapper.get('[data-testid="company-bin"]').setValue('');
        await wrapper.get('[data-testid="company-contact-person"]').setValue('New Contact');
        await wrapper.get('[data-testid="company-phone"]').setValue('+7 (700) 123-45-67');
        await wrapper.get('[data-testid="company-email"]').setValue('office@acme.test');
        await wrapper.get('[data-testid="company-profile-form"]').trigger('submit');
        await flushPromises();

        expect(updateCompanyProfile).toHaveBeenCalledWith({
            name: 'Alma Stroy',
            time_zone: 'Europe/Moscow',
            bin: '',
            contact_person: 'New Contact',
            phone: '+7 (700) 123-45-67',
            email: 'office@acme.test',
        });
        expect(wrapper.get('[data-testid="company-name"]').element).toHaveProperty(
            'value',
            'Alma Stroy',
        );
        expect(wrapper.get('[data-testid="company-bin"]').element).toHaveProperty('value', '');
    });

    it('shows a validation error on each rejected profile field', async () => {
        updateCompanyProfile.mockRejectedValue(
            new ApiError(422, 'invalid', null, {
                name: ['name'],
                time_zone: ['time zone'],
                bin: ['bin'],
                contact_person: ['contact'],
                phone: ['phone'],
                email: ['email'],
            }),
        );

        const wrapper = await mountPage();

        await wrapper.get('[data-testid="company-profile-form"]').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[data-testid="company-name-error"]').text()).toBe('name');
        expect(wrapper.get('[data-testid="company-time-zone-error"]').text()).toBe('time zone');
        expect(wrapper.get('[data-testid="company-bin-error"]').text()).toBe('bin');
        expect(wrapper.get('[data-testid="company-contact-person-error"]').text()).toBe('contact');
        expect(wrapper.get('[data-testid="company-phone-error"]').text()).toBe('phone');
        expect(wrapper.get('[data-testid="company-email-error"]').text()).toBe('email');
        expect(wrapper.get('[data-testid="company-name-error"]').classes().join(' ')).toContain(
            'text-destructive',
        );
        expect(wrapper.get('[data-testid="company-email-error"]').classes().join(' ')).toContain(
            'text-destructive',
        );
    });

    it('saves working day settings', async () => {
        const wrapper = await mountPage();

        expect(wrapper.get('[data-testid="working-day-start"]').element).toHaveProperty(
            'value',
            '09:00',
        );
        expect(wrapper.get('h2').text()).toBe(t('companyProfile.settingsTitle'));
        expect(
            wrapper.get('[data-testid="save-working-day-settings"]').classes().join(' '),
        ).toContain('bg-primary');
        expect(wrapper.get('[data-testid="working-day-monday"]').classes().join(' ')).toBe(
            nativeCheckboxClass,
        );
        expect(wrapper.get('[data-testid="break-deducted"]').classes().join(' ')).toBe(
            nativeCheckboxClass,
        );

        await wrapper.get('[data-testid="working-day-start"]').setValue('08:00');
        await wrapper.get('[data-testid="break-deducted"]').setValue(false);
        await wrapper.get('[data-testid="working-day-settings-form"]').trigger('submit');
        await flushPromises();

        expect(updateWorkingDaySettings).toHaveBeenCalledWith({
            start_time: '08:00',
            end_time: '18:00',
            working_days: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            break_duration_minutes: 60,
            break_deducted: false,
            lateness_grace_minutes: 0,
        });
        expect(wrapper.get('[data-testid="working-day-start"]').element).toHaveProperty(
            'value',
            '08:00',
        );
    });

    it('shows a validation error on each rejected settings field', async () => {
        updateWorkingDaySettings.mockRejectedValue(
            new ApiError(422, 'invalid', null, {
                start_time: ['start'],
                end_time: ['end'],
                working_days: ['days'],
                break_duration_minutes: ['break'],
                break_deducted: ['deducted'],
                lateness_grace_minutes: ['grace'],
            }),
        );

        const wrapper = await mountPage();

        await wrapper.get('[data-testid="working-day-settings-form"]').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[data-testid="working-day-start-error"]').text()).toBe('start');
        expect(wrapper.get('[data-testid="working-day-end-error"]').text()).toBe('end');
        expect(wrapper.get('[data-testid="working-days-error"]').text()).toBe('days');
        expect(wrapper.get('[data-testid="break-duration-error"]').text()).toBe('break');
        expect(wrapper.get('[data-testid="break-deducted-error"]').text()).toBe('deducted');
        expect(wrapper.get('[data-testid="lateness-grace-error"]').text()).toBe('grace');
        expect(wrapper.get('[data-testid="working-day-start-error"]').classes().join(' ')).toContain(
            'text-destructive',
        );
        expect(wrapper.get('[data-testid="working-days-error"]').classes().join(' ')).toContain(
            'text-destructive',
        );
    });

    it('shows the same values with no save controls for a viewer', async () => {
        const { useCurrentUser } = await import('../../composables/useCurrentUser');
        useCurrentUser().currentUser.value = viewer;

        const wrapper = await mountPage();

        expect(wrapper.get('[data-testid="company-profile-readonly"]').text()).toContain('Acme');
        expect(wrapper.get('[data-testid="company-name"]').text()).toBe('Acme');
        expect(wrapper.get('[data-testid="company-time-zone"]').text()).toBe('Asia/Almaty');
        expect(wrapper.get('[data-testid="company-bin"]').text()).toBe('123456789012');
        expect(wrapper.get('[data-testid="company-contact-person"]').text()).toBe('Ada Contact');
        expect(wrapper.get('[data-testid="company-phone"]').text()).toBe('+7 (700) 123-45-67');
        expect(wrapper.get('[data-testid="company-email"]').text()).toBe('office@acme.test');
        expect(wrapper.get('[data-testid="working-day-start"]').text()).toBe('09:00');
        expect(wrapper.get('[data-testid="working-day-end"]').text()).toBe('18:00');
        expect(wrapper.get('[data-testid="working-days"]').text()).toContain(
            t('companyProfile.days.monday'),
        );
        expect(wrapper.get('[data-testid="working-days"]').text()).toContain(
            t('companyProfile.days.friday'),
        );
        expect(wrapper.get('[data-testid="break-duration"]').text()).toBe('60');
        expect(wrapper.text()).toContain(t('companyProfile.breakDeducted'));
        expect(wrapper.get('[data-testid="break-deducted"]').element).toHaveProperty(
            'checked',
            true,
        );
        expect(wrapper.get('[data-testid="break-deducted"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('[data-testid="break-deducted"]').classes().join(' ')).toBe(
            nativeCheckboxClass,
        );
        expect(wrapper.get('[data-testid="lateness-grace"]').text()).toBe('0');
        expect(wrapper.find('[data-testid="save-company-profile"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="save-working-day-settings"]').exists()).toBe(false);
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('input:not([disabled])').exists()).toBe(false);
        expect(listTimeZones).not.toHaveBeenCalled();
        expect(updateCompanyProfile).not.toHaveBeenCalled();
        expect(updateWorkingDaySettings).not.toHaveBeenCalled();
    });
});

async function mountPage(): Promise<ReturnType<typeof mount>> {
    const { default: CompanyProfilePage } = await import('../CompanyProfilePage.vue');
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [{ path: '/company', component: { template: '<div />' } }],
    });
    const wrapper = mount(CompanyProfilePage, { global: { plugins: [router] } });
    await router.isReady();
    await flushPromises();

    return wrapper;
}
