<script setup lang="ts">
import { computed, ref } from 'vue';
import { listTimeZones } from '../api/adminCompanies';
import { ApiError } from '../api/client';
import {
    showCompanyProfile,
    showWorkingDaySettings,
    updateCompanyProfile,
    updateWorkingDaySettings,
    type WorkingDaySettings,
} from '../api/companyProfile';
import { useCurrentUser } from '../composables/useCurrentUser';
import { t } from '../utils/i18n';

const { currentUser } = useCurrentUser();
const isViewer = computed(() => currentUser.value?.role === 'viewer');

const WEEK_DAYS = [
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
    'sunday',
] as const;

const name = ref('');
const timeZone = ref('');
const timeZones = ref<string[]>([]);
const bin = ref('');
const contactPerson = ref('');
const phone = ref('');
const email = ref('');
const nameError = ref<string | null>(null);
const timeZoneError = ref<string | null>(null);
const binError = ref<string | null>(null);
const contactPersonError = ref<string | null>(null);
const phoneError = ref<string | null>(null);
const emailError = ref<string | null>(null);

const startTime = ref('09:00');
const endTime = ref('18:00');
const workingDays = ref<string[]>(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
const breakDurationMinutes = ref(60);
const breakDeducted = ref(true);
const latenessGraceMinutes = ref(0);
const startTimeError = ref<string | null>(null);
const endTimeError = ref<string | null>(null);
const workingDaysError = ref<string | null>(null);
const breakDurationError = ref<string | null>(null);
const breakDeductedError = ref<string | null>(null);
const graceError = ref<string | null>(null);

void load();

async function load(): Promise<void> {
    const profileRequest = showCompanyProfile();
    const settingsRequest = showWorkingDaySettings();
    const zonesRequest = isViewer.value ? Promise.resolve(null) : listTimeZones();
    const [profile, zones, settings] = await Promise.all([
        profileRequest,
        zonesRequest,
        settingsRequest,
    ]);

    name.value = profile.data.name;
    timeZone.value = profile.data.time_zone;

    if (zones !== null) {
        timeZones.value = zones.data.identifiers;
    }
    bin.value = profile.data.bin ?? '';
    contactPerson.value = profile.data.contact_person ?? '';
    phone.value = profile.data.phone ?? '';
    email.value = profile.data.email ?? '';
    applySettings(settings.data);
}

function applySettings(settings: WorkingDaySettings): void {
    startTime.value = settings.start_time;
    endTime.value = settings.end_time;
    workingDays.value = [...settings.working_days];
    breakDurationMinutes.value = settings.break_duration_minutes;
    breakDeducted.value = settings.break_deducted;
    latenessGraceMinutes.value = settings.lateness_grace_minutes;
}

async function saveProfile(): Promise<void> {
    nameError.value = null;
    timeZoneError.value = null;
    binError.value = null;
    contactPersonError.value = null;
    phoneError.value = null;
    emailError.value = null;

    try {
        const saved = await updateCompanyProfile({
            name: name.value,
            time_zone: timeZone.value,
            bin: bin.value,
            contact_person: contactPerson.value,
            phone: phone.value,
            email: email.value,
        });
        name.value = saved.data.name;
        timeZone.value = saved.data.time_zone;
        bin.value = saved.data.bin ?? '';
        contactPerson.value = saved.data.contact_person ?? '';
        phone.value = saved.data.phone ?? '';
        email.value = saved.data.email ?? '';
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            nameError.value = error.errors?.name?.[0] ?? null;
            timeZoneError.value = error.errors?.time_zone?.[0] ?? null;
            binError.value = error.errors?.bin?.[0] ?? null;
            contactPersonError.value = error.errors?.contact_person?.[0] ?? null;
            phoneError.value = error.errors?.phone?.[0] ?? null;
            emailError.value = error.errors?.email?.[0] ?? null;

            return;
        }

        throw error;
    }
}

async function saveSettings(): Promise<void> {
    startTimeError.value = null;
    endTimeError.value = null;
    workingDaysError.value = null;
    breakDurationError.value = null;
    breakDeductedError.value = null;
    graceError.value = null;

    try {
        const saved = await updateWorkingDaySettings({
            start_time: startTime.value,
            end_time: endTime.value,
            working_days: [...workingDays.value],
            break_duration_minutes: breakDurationMinutes.value,
            break_deducted: breakDeducted.value,
            lateness_grace_minutes: latenessGraceMinutes.value,
        });
        applySettings(saved.data);
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            startTimeError.value = error.errors?.start_time?.[0] ?? null;
            endTimeError.value = error.errors?.end_time?.[0] ?? null;
            workingDaysError.value = error.errors?.working_days?.[0] ?? null;
            breakDurationError.value = error.errors?.break_duration_minutes?.[0] ?? null;
            breakDeductedError.value = error.errors?.break_deducted?.[0] ?? null;
            graceError.value = error.errors?.lateness_grace_minutes?.[0] ?? null;

            return;
        }

        throw error;
    }
}
</script>

<template>
    <section
        v-if="isViewer"
        class="flex max-w-sm flex-col gap-3"
        data-testid="company-profile-readonly"
    >
        <h1 class="text-xl font-semibold">{{ t('companyProfile.title') }}</h1>
        <p data-testid="company-name">{{ name }}</p>
        <p data-testid="company-time-zone">{{ timeZone }}</p>
        <p data-testid="company-bin">{{ bin }}</p>
        <p data-testid="company-contact-person">{{ contactPerson }}</p>
        <p data-testid="company-phone">{{ phone }}</p>
        <p data-testid="company-email">{{ email }}</p>
        <h2 class="text-xl font-semibold">{{ t('companyProfile.settingsTitle') }}</h2>
        <p data-testid="working-day-start">{{ startTime }}</p>
        <p data-testid="working-day-end">{{ endTime }}</p>
        <p data-testid="working-days">
            <span v-for="day in workingDays" :key="day">{{ t(`companyProfile.days.${day}`) }}</span>
        </p>
        <p data-testid="break-duration">{{ breakDurationMinutes }}</p>
        <label class="flex items-center gap-2 text-sm">
            {{ t('companyProfile.breakDeducted') }}
            <input data-testid="break-deducted" type="checkbox" :checked="breakDeducted" disabled />
        </label>
        <p data-testid="lateness-grace">{{ latenessGraceMinutes }}</p>
    </section>
    <section v-else class="flex flex-col gap-8">
        <form
            class="flex max-w-sm flex-col gap-3"
            data-testid="company-profile-form"
            @submit.prevent="saveProfile"
        >
            <h1 class="text-xl font-semibold">{{ t('companyProfile.title') }}</h1>
            <label class="flex flex-col gap-1 text-sm">
                {{ t('companies.name') }}
                <input
                    v-model="name"
                    data-testid="company-name"
                    type="text"
                    name="name"
                    required
                    class="rounded border border-zinc-300 px-3 py-2"
                />
            </label>
            <p v-if="nameError" data-testid="company-name-error" class="text-sm text-red-700">
                {{ nameError }}
            </p>
            <label class="flex flex-col gap-1 text-sm">
                {{ t('companies.timeZone') }}
                <select
                    v-model="timeZone"
                    data-testid="company-time-zone"
                    name="time_zone"
                    class="rounded border border-zinc-300 px-3 py-2"
                >
                    <option v-for="zone in timeZones" :key="zone" :value="zone">{{ zone }}</option>
                </select>
            </label>
            <p
                v-if="timeZoneError"
                data-testid="company-time-zone-error"
                class="text-sm text-red-700"
            >
                {{ timeZoneError }}
            </p>
            <label class="flex flex-col gap-1 text-sm">
                {{ t('companies.bin') }}
                <input
                    v-model="bin"
                    data-testid="company-bin"
                    type="text"
                    name="bin"
                    class="rounded border border-zinc-300 px-3 py-2"
                />
            </label>
            <p v-if="binError" data-testid="company-bin-error" class="text-sm text-red-700">
                {{ binError }}
            </p>
            <label class="flex flex-col gap-1 text-sm">
                {{ t('companies.contactPerson') }}
                <input
                    v-model="contactPerson"
                    data-testid="company-contact-person"
                    type="text"
                    name="contact_person"
                    class="rounded border border-zinc-300 px-3 py-2"
                />
            </label>
            <p
                v-if="contactPersonError"
                data-testid="company-contact-person-error"
                class="text-sm text-red-700"
            >
                {{ contactPersonError }}
            </p>
            <label class="flex flex-col gap-1 text-sm">
                {{ t('companies.phone') }}
                <input
                    v-model="phone"
                    data-testid="company-phone"
                    type="text"
                    name="phone"
                    class="rounded border border-zinc-300 px-3 py-2"
                />
            </label>
            <p v-if="phoneError" data-testid="company-phone-error" class="text-sm text-red-700">
                {{ phoneError }}
            </p>
            <label class="flex flex-col gap-1 text-sm">
                {{ t('companies.email') }}
                <input
                    v-model="email"
                    data-testid="company-email"
                    type="email"
                    name="email"
                    class="rounded border border-zinc-300 px-3 py-2"
                />
            </label>
            <p v-if="emailError" data-testid="company-email-error" class="text-sm text-red-700">
                {{ emailError }}
            </p>
            <button
                type="submit"
                data-testid="save-company-profile"
                class="rounded bg-zinc-900 px-3 py-2 text-white"
            >
                {{ t('companyProfile.save') }}
            </button>
        </form>

        <form
            class="flex max-w-sm flex-col gap-3"
            data-testid="working-day-settings-form"
            @submit.prevent="saveSettings"
        >
            <h2 class="text-xl font-semibold">{{ t('companyProfile.settingsTitle') }}</h2>
            <label class="flex flex-col gap-1 text-sm">
                {{ t('companyProfile.start') }}
                <input
                    v-model="startTime"
                    data-testid="working-day-start"
                    type="time"
                    name="start_time"
                    required
                    class="rounded border border-zinc-300 px-3 py-2"
                />
            </label>
            <p
                v-if="startTimeError"
                data-testid="working-day-start-error"
                class="text-sm text-red-700"
            >
                {{ startTimeError }}
            </p>
            <label class="flex flex-col gap-1 text-sm">
                {{ t('companyProfile.end') }}
                <input
                    v-model="endTime"
                    data-testid="working-day-end"
                    type="time"
                    name="end_time"
                    required
                    class="rounded border border-zinc-300 px-3 py-2"
                />
            </label>
            <p v-if="endTimeError" data-testid="working-day-end-error" class="text-sm text-red-700">
                {{ endTimeError }}
            </p>
            <fieldset class="flex flex-col gap-1 text-sm">
                <legend>{{ t('companyProfile.workingDays') }}</legend>
                <label v-for="day in WEEK_DAYS" :key="day" class="flex items-center gap-2">
                    <input
                        v-model="workingDays"
                        type="checkbox"
                        name="working_days"
                        :value="day"
                        :data-testid="`working-day-${day}`"
                    />
                    {{ t(`companyProfile.days.${day}`) }}
                </label>
            </fieldset>
            <p
                v-if="workingDaysError"
                data-testid="working-days-error"
                class="text-sm text-red-700"
            >
                {{ workingDaysError }}
            </p>
            <label class="flex flex-col gap-1 text-sm">
                {{ t('companyProfile.breakDuration') }}
                <input
                    v-model.number="breakDurationMinutes"
                    data-testid="break-duration"
                    type="number"
                    name="break_duration_minutes"
                    required
                    class="rounded border border-zinc-300 px-3 py-2"
                />
            </label>
            <p
                v-if="breakDurationError"
                data-testid="break-duration-error"
                class="text-sm text-red-700"
            >
                {{ breakDurationError }}
            </p>
            <label class="flex items-center gap-2 text-sm">
                <input
                    v-model="breakDeducted"
                    data-testid="break-deducted"
                    type="checkbox"
                    name="break_deducted"
                />
                {{ t('companyProfile.breakDeducted') }}
            </label>
            <p
                v-if="breakDeductedError"
                data-testid="break-deducted-error"
                class="text-sm text-red-700"
            >
                {{ breakDeductedError }}
            </p>
            <label class="flex flex-col gap-1 text-sm">
                {{ t('companyProfile.grace') }}
                <input
                    v-model.number="latenessGraceMinutes"
                    data-testid="lateness-grace"
                    type="number"
                    name="lateness_grace_minutes"
                    required
                    class="rounded border border-zinc-300 px-3 py-2"
                />
            </label>
            <p v-if="graceError" data-testid="lateness-grace-error" class="text-sm text-red-700">
                {{ graceError }}
            </p>
            <button
                type="submit"
                data-testid="save-working-day-settings"
                class="rounded bg-zinc-900 px-3 py-2 text-white"
            >
                {{ t('companyProfile.saveSettings') }}
            </button>
        </form>
    </section>
</template>
