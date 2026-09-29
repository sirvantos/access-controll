<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
    createCompany,
    deactivateCompany,
    DEFAULT_COMPANY_TIME_ZONE,
    inviteFirstAdmin,
    listCompanies,
    listFirstAdminInvitations,
    listTimeZones,
    reactivateCompany,
    resendFirstAdminInvitation,
    revokeFirstAdminInvitation,
    type Company,
    type PendingInvitation,
} from '../api/adminCompanies';
import {
    clearSelectedCompany as clearSelectedCompanyRequest,
    selectCompany,
} from '../api/selectedCompany';
import { ApiError } from '../api/client';
import type { PaginationMeta, Role } from '../api/types';
import { useSelectedCompany } from '../composables/useSelectedCompany';
import { t } from '../utils/i18n';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { NativeSelect } from '../components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '../components/ui/table';

const route = useRoute();
const router = useRouter();
const { setSelectedCompany, clearSelectedCompany, selectedCompany } = useSelectedCompany();
const companies = ref<Company[]>([]);
const invitations = ref<Record<number, PendingInvitation[]>>({});
const meta = ref<PaginationMeta | null>(null);
const companyName = ref('');
const timeZone = ref(DEFAULT_COMPANY_TIME_ZONE);
const timeZones = ref<string[]>([DEFAULT_COMPANY_TIME_ZONE]);
const bin = ref('');
const contactPerson = ref('');
const phone = ref('');
const companyEmail = ref('');
const firstAdminEmail = ref('');
const nameError = ref<string | null>(null);
const timeZoneError = ref<string | null>(null);
const binError = ref<string | null>(null);
const contactPersonError = ref<string | null>(null);
const phoneError = ref<string | null>(null);
const companyEmailError = ref<string | null>(null);
const firstAdminEmailError = ref<string | null>(null);
const replacementEmails = reactive<Record<number, string>>({});
const invitationErrors = ref<Record<number, string | null>>({});

const awaitingCompanies = computed(() =>
    companies.value.filter((company) => company.awaiting_first_admin),
);

const page = computed(() => {
    const raw = route.query.page;
    const parsed = typeof raw === 'string' ? Number.parseInt(raw, 10) : 1;

    return Number.isInteger(parsed) && parsed > 0 ? parsed : 1;
});

const search = computed(() => (typeof route.query.search === 'string' ? route.query.search : ''));

watch(
    [page, search],
    () => {
        void refresh();
    },
    { immediate: true },
);

void loadTimeZones();

async function loadTimeZones(): Promise<void> {
    const body = await listTimeZones();
    timeZones.value = body.data.identifiers;
}

async function refresh(): Promise<void> {
    const body =
        search.value === ''
            ? await listCompanies(page.value)
            : await listCompanies(page.value, search.value);
    companies.value = body.data;
    meta.value = body.meta;

    const awaiting = body.data.filter((company) => company.awaiting_first_admin);
    const loaded = await Promise.all(
        awaiting.map(async (company) => ({
            id: company.id,
            invitations: (await listFirstAdminInvitations(company.id, 1)).data,
        })),
    );
    const next: Record<number, PendingInvitation[]> = {};

    for (const item of loaded) {
        next[item.id] = item.invitations;
    }

    invitations.value = next;
}

function showPage(next: number): void {
    void router.push({
        path: '/companies',
        query: listQuery(next, search.value),
    });
}

function applySearch(value: string): void {
    void router.push({
        path: '/companies',
        query: listQuery(page.value, value),
    });
}

function listQuery(nextPage: number, nextSearch: string): Record<string, string> {
    const query: Record<string, string> = {};

    if (nextPage > 1) {
        query.page = String(nextPage);
    }

    if (nextSearch !== '') {
        query.search = nextSearch;
    }

    return query;
}

function stateLabel(company: Company): string {
    return t(company.is_active ? 'companies.active' : 'companies.deactivated');
}

function roleLabel(role: Role): string {
    return t(`roles.${role}`);
}

function companyInvitations(companyId: number): PendingInvitation[] {
    return invitations.value[companyId] ?? [];
}

async function create(): Promise<void> {
    nameError.value = null;
    timeZoneError.value = null;
    binError.value = null;
    contactPersonError.value = null;
    phoneError.value = null;
    companyEmailError.value = null;
    firstAdminEmailError.value = null;

    try {
        await createCompany({
            name: companyName.value,
            first_admin_email: firstAdminEmail.value,
            time_zone: timeZone.value,
            bin: bin.value,
            contact_person: contactPerson.value,
            phone: phone.value,
            email: companyEmail.value,
        });
        companyName.value = '';
        timeZone.value = DEFAULT_COMPANY_TIME_ZONE;
        bin.value = '';
        contactPerson.value = '';
        phone.value = '';
        companyEmail.value = '';
        firstAdminEmail.value = '';
        await refresh();
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            nameError.value = error.errors?.name?.[0] ?? null;
            timeZoneError.value = error.errors?.time_zone?.[0] ?? null;
            binError.value = error.errors?.bin?.[0] ?? null;
            contactPersonError.value = error.errors?.contact_person?.[0] ?? null;
            phoneError.value = error.errors?.phone?.[0] ?? null;
            companyEmailError.value = error.errors?.email?.[0] ?? null;
            firstAdminEmailError.value = error.errors?.first_admin_email?.[0] ?? null;

            if (
                nameError.value !== null ||
                timeZoneError.value !== null ||
                binError.value !== null ||
                contactPersonError.value !== null ||
                phoneError.value !== null ||
                companyEmailError.value !== null ||
                firstAdminEmailError.value !== null
            ) {
                return;
            }
        }

        throw error;
    }
}

async function inviteReplacement(companyId: number): Promise<void> {
    invitationErrors.value = { ...invitationErrors.value, [companyId]: null };

    try {
        await inviteFirstAdmin(companyId, replacementEmails[companyId] ?? '');
        replacementEmails[companyId] = '';
        await refresh();
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            invitationErrors.value = {
                ...invitationErrors.value,
                [companyId]: error.errors?.email?.[0] ?? error.message,
            };

            return;
        }

        throw error;
    }
}

async function resend(companyId: number, invitationId: number): Promise<void> {
    await changeInvitation(companyId, () => resendFirstAdminInvitation(companyId, invitationId));
}

async function revoke(companyId: number, invitationId: number): Promise<void> {
    await changeInvitation(companyId, () => revokeFirstAdminInvitation(companyId, invitationId));
}

async function deactivate(id: number): Promise<void> {
    await deactivateCompany(id);
    await refresh();
}

async function reactivate(id: number): Promise<void> {
    await reactivateCompany(id);
    await refresh();
}

async function select(company: Company): Promise<void> {
    const body = await selectCompany(company.id);
    setSelectedCompany(body.data);
}

async function clearSelection(): Promise<void> {
    await clearSelectedCompanyRequest();
    clearSelectedCompany();
}

async function changeInvitation(companyId: number, action: () => Promise<unknown>): Promise<void> {
    invitationErrors.value = { ...invitationErrors.value, [companyId]: null };

    try {
        await action();
        await refresh();
    } catch (error) {
        if (error instanceof ApiError && error.status === 409) {
            invitationErrors.value = {
                ...invitationErrors.value,
                [companyId]: error.message,
            };

            return;
        }

        throw error;
    }
}
</script>

<template>
    <section class="flex flex-col gap-4">
        <h1 class="text-xl font-semibold text-slate-900">{{ t('companies.title') }}</h1>
        <form class="flex max-w-sm flex-col gap-3" @submit.prevent="create">
            <Label class="flex flex-col items-stretch gap-1">
                {{ t('companies.name') }}
                <Input
                    v-model="companyName"
                    data-testid="company-name"
                    type="text"
                    name="name"
                    required
                />
            </Label>
            <p v-if="nameError" data-testid="company-name-error" class="text-sm text-destructive">
                {{ nameError }}
            </p>
            <Label class="flex flex-col items-stretch gap-1">
                {{ t('companies.timeZone') }}
                <NativeSelect
                    v-model="timeZone"
                    data-testid="company-time-zone"
                    name="time_zone"
                    class="w-full"
                >
                    <option v-for="zone in timeZones" :key="zone" :value="zone">{{ zone }}</option>
                </NativeSelect>
            </Label>
            <p
                v-if="timeZoneError"
                data-testid="company-time-zone-error"
                class="text-sm text-destructive"
            >
                {{ timeZoneError }}
            </p>
            <Label class="flex flex-col items-stretch gap-1">
                {{ t('companies.bin') }}
                <Input v-model="bin" data-testid="company-bin" type="text" name="bin" />
            </Label>
            <p v-if="binError" data-testid="company-bin-error" class="text-sm text-destructive">
                {{ binError }}
            </p>
            <Label class="flex flex-col items-stretch gap-1">
                {{ t('companies.contactPerson') }}
                <Input
                    v-model="contactPerson"
                    data-testid="company-contact-person"
                    type="text"
                    name="contact_person"
                />
            </Label>
            <p
                v-if="contactPersonError"
                data-testid="company-contact-person-error"
                class="text-sm text-destructive"
            >
                {{ contactPersonError }}
            </p>
            <Label class="flex flex-col items-stretch gap-1">
                {{ t('companies.phone') }}
                <Input v-model="phone" data-testid="company-phone" type="text" name="phone" />
            </Label>
            <p v-if="phoneError" data-testid="company-phone-error" class="text-sm text-destructive">
                {{ phoneError }}
            </p>
            <Label class="flex flex-col items-stretch gap-1">
                {{ t('companies.email') }}
                <Input
                    v-model="companyEmail"
                    data-testid="company-email"
                    type="email"
                    name="email"
                />
            </Label>
            <p
                v-if="companyEmailError"
                data-testid="company-email-error"
                class="text-sm text-destructive"
            >
                {{ companyEmailError }}
            </p>
            <Label class="flex flex-col items-stretch gap-1">
                {{ t('companies.firstAdminEmail') }}
                <Input
                    v-model="firstAdminEmail"
                    data-testid="first-admin-email"
                    type="email"
                    name="first_admin_email"
                    required
                />
            </Label>
            <p
                v-if="firstAdminEmailError"
                data-testid="first-admin-email-error"
                class="text-sm text-destructive"
            >
                {{ firstAdminEmailError }}
            </p>
            <Button type="submit" data-testid="create-company">
                {{ t('companies.create') }}
            </Button>
        </form>
        <Label class="flex max-w-sm flex-col items-stretch gap-1">
            {{ t('companies.search') }}
            <Input
                :model-value="search"
                data-testid="company-search"
                type="search"
                name="search"
                @update:model-value="applySearch(String($event))"
            />
        </Label>
        <p
            v-if="search !== '' && companies.length === 0"
            data-testid="companies-empty"
            class="text-sm text-slate-900"
        >
            {{ t('companies.empty') }}
        </p>
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>{{ t('companies.name') }}</TableHead>
                    <TableHead>{{ t('companies.bin') }}</TableHead>
                    <TableHead>{{ t('companies.state') }}</TableHead>
                    <TableHead>{{ t('companies.created') }}</TableHead>
                    <TableHead>{{ t('companies.actions') }}</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow
                    v-for="company in companies"
                    :key="company.id"
                    data-testid="company-row"
                    :class="selectedCompany?.id === company.id ? 'bg-slate-100' : undefined"
                >
                    <TableCell>{{ company.name }}</TableCell>
                    <TableCell :data-testid="`company-bin-${company.id}`">
                        {{ company.bin ?? '' }}
                    </TableCell>
                    <TableCell>
                        <Badge v-if="company.is_active" variant="success">
                            {{ stateLabel(company) }}
                        </Badge>
                        <Badge v-else variant="secondary">
                            {{ stateLabel(company) }}
                        </Badge>
                    </TableCell>
                    <TableCell>{{ company.created_at }}</TableCell>
                    <TableCell>
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :data-testid="`select-company-${company.id}`"
                                @click="select(company)"
                            >
                                {{ t('companies.select') }}
                            </Button>
                            <Button
                                v-if="company.is_active"
                                type="button"
                                variant="destructive"
                                size="sm"
                                :data-testid="`deactivate-company-${company.id}`"
                                @click="deactivate(company.id)"
                            >
                                {{ t('companies.deactivate') }}
                            </Button>
                            <Button
                                v-else
                                type="button"
                                variant="outline"
                                size="sm"
                                :data-testid="`reactivate-company-${company.id}`"
                                @click="reactivate(company.id)"
                            >
                                {{ t('companies.reactivate') }}
                            </Button>
                        </div>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
        <Button
            type="button"
            variant="outline"
            size="sm"
            data-testid="clear-selected-company"
            class="w-fit"
            @click="clearSelection"
        >
            {{ t('companies.clear') }}
        </Button>
        <section
            v-for="company in awaitingCompanies"
            :key="`invitations-${company.id}`"
            :data-testid="`first-admin-invitations-${company.id}`"
            class="flex flex-col gap-2"
        >
            <h2 class="text-lg font-semibold text-slate-900">
                {{ t('companies.firstAdminInvitations', { name: company.name }) }}
            </h2>
            <p
                v-if="invitationErrors[company.id]"
                :data-testid="`invitation-error-${company.id}`"
                class="text-sm text-destructive"
            >
                {{ invitationErrors[company.id] }}
            </p>
            <form
                class="flex max-w-sm flex-col gap-3"
                :data-testid="`replacement-form-${company.id}`"
                @submit.prevent="inviteReplacement(company.id)"
            >
                <Label class="flex flex-col items-stretch gap-1">
                    {{ t('companies.replacementEmail') }}
                    <Input
                        v-model="replacementEmails[company.id]"
                        :data-testid="`replacement-email-${company.id}`"
                        type="email"
                        name="email"
                        required
                    />
                </Label>
                <Button type="submit" :data-testid="`invite-replacement-${company.id}`">
                    {{ t('companies.inviteReplacement') }}
                </Button>
            </form>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ t('companies.email') }}</TableHead>
                        <TableHead>{{ t('companies.role') }}</TableHead>
                        <TableHead>{{ t('companies.expires') }}</TableHead>
                        <TableHead>{{ t('companies.actions') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="invitation in companyInvitations(company.id)"
                        :key="invitation.id"
                        data-testid="invitation-row"
                    >
                        <TableCell>{{ invitation.email }}</TableCell>
                        <TableCell>{{ roleLabel(invitation.role) }}</TableCell>
                        <TableCell>{{ invitation.expires_at }}</TableCell>
                        <TableCell>
                            <div class="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :data-testid="`resend-invitation-${invitation.id}`"
                                    @click="resend(company.id, invitation.id)"
                                >
                                    {{ t('companies.resend') }}
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    :data-testid="`revoke-invitation-${invitation.id}`"
                                    @click="revoke(company.id, invitation.id)"
                                >
                                    {{ t('companies.revoke') }}
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>
        <div v-if="meta !== null && meta.last_page > 1" class="flex gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                data-testid="previous-page"
                :disabled="meta.current_page <= 1"
                @click="showPage(meta.current_page - 1)"
            >
                {{ t('companies.previous') }}
            </Button>
            <Button
                type="button"
                variant="outline"
                size="sm"
                data-testid="next-page"
                :disabled="meta.current_page >= meta.last_page"
                @click="showPage(meta.current_page + 1)"
            >
                {{ t('companies.next') }}
            </Button>
        </div>
    </section>
</template>
