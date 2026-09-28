<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
    createCompany,
    deactivateCompany,
    inviteFirstAdmin,
    listCompanies,
    listFirstAdminInvitations,
    reactivateCompany,
    resendFirstAdminInvitation,
    revokeFirstAdminInvitation,
    type Company,
    type PendingInvitation,
} from '../api/adminCompanies';
import { ApiError } from '../api/client';
import type { PaginationMeta, Role } from '../api/types';
import { t } from '../utils/i18n';

const route = useRoute();
const router = useRouter();
const companies = ref<Company[]>([]);
const invitations = ref<Record<number, PendingInvitation[]>>({});
const meta = ref<PaginationMeta | null>(null);
const companyName = ref('');
const firstAdminEmail = ref('');
const nameError = ref<string | null>(null);
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

watch(
    page,
    () => {
        void refresh();
    },
    { immediate: true },
);

async function refresh(): Promise<void> {
    const body = await listCompanies(page.value);
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
        query: next > 1 ? { page: String(next) } : {},
    });
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
    firstAdminEmailError.value = null;

    try {
        await createCompany(companyName.value, firstAdminEmail.value);
        companyName.value = '';
        firstAdminEmail.value = '';
        await refresh();
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            nameError.value = error.errors?.name?.[0] ?? null;
            firstAdminEmailError.value = error.errors?.first_admin_email?.[0] ?? null;

            if (nameError.value !== null || firstAdminEmailError.value !== null) {
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
        <h1 class="text-xl font-semibold">{{ t('companies.title') }}</h1>
        <form class="flex max-w-sm flex-col gap-3" @submit.prevent="create">
            <label class="flex flex-col gap-1 text-sm">
                {{ t('companies.name') }}
                <input
                    v-model="companyName"
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
                {{ t('companies.firstAdminEmail') }}
                <input
                    v-model="firstAdminEmail"
                    data-testid="first-admin-email"
                    type="email"
                    name="first_admin_email"
                    required
                    class="rounded border border-zinc-300 px-3 py-2"
                />
            </label>
            <p
                v-if="firstAdminEmailError"
                data-testid="first-admin-email-error"
                class="text-sm text-red-700"
            >
                {{ firstAdminEmailError }}
            </p>
            <button
                type="submit"
                data-testid="create-company"
                class="rounded bg-zinc-900 px-3 py-2 text-white"
            >
                {{ t('companies.create') }}
            </button>
        </form>
        <table class="w-full text-left text-sm">
            <thead>
                <tr>
                    <th class="py-2">{{ t('companies.name') }}</th>
                    <th class="py-2">{{ t('companies.state') }}</th>
                    <th class="py-2">{{ t('companies.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="company in companies" :key="company.id" data-testid="company-row">
                    <td class="py-2">{{ company.name }}</td>
                    <td class="py-2">{{ stateLabel(company) }}</td>
                    <td class="py-2">
                        <button
                            v-if="company.is_active"
                            type="button"
                            :data-testid="`deactivate-company-${company.id}`"
                            class="rounded border border-zinc-300 px-3 py-1"
                            @click="deactivate(company.id)"
                        >
                            {{ t('companies.deactivate') }}
                        </button>
                        <button
                            v-else
                            type="button"
                            :data-testid="`reactivate-company-${company.id}`"
                            class="rounded border border-zinc-300 px-3 py-1"
                            @click="reactivate(company.id)"
                        >
                            {{ t('companies.reactivate') }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
        <section
            v-for="company in awaitingCompanies"
            :key="`invitations-${company.id}`"
            :data-testid="`first-admin-invitations-${company.id}`"
            class="flex flex-col gap-2"
        >
            <h2 class="text-lg font-semibold">
                {{ t('companies.firstAdminInvitations', { name: company.name }) }}
            </h2>
            <p
                v-if="invitationErrors[company.id]"
                :data-testid="`invitation-error-${company.id}`"
                class="text-sm text-red-700"
            >
                {{ invitationErrors[company.id] }}
            </p>
            <form
                class="flex max-w-sm flex-col gap-3"
                :data-testid="`replacement-form-${company.id}`"
                @submit.prevent="inviteReplacement(company.id)"
            >
                <label class="flex flex-col gap-1 text-sm">
                    {{ t('companies.replacementEmail') }}
                    <input
                        v-model="replacementEmails[company.id]"
                        :data-testid="`replacement-email-${company.id}`"
                        type="email"
                        name="email"
                        required
                        class="rounded border border-zinc-300 px-3 py-2"
                    />
                </label>
                <button
                    type="submit"
                    :data-testid="`invite-replacement-${company.id}`"
                    class="rounded bg-zinc-900 px-3 py-2 text-white"
                >
                    {{ t('companies.inviteReplacement') }}
                </button>
            </form>
            <table class="w-full text-left text-sm">
                <thead>
                    <tr>
                        <th class="py-2">{{ t('companies.email') }}</th>
                        <th class="py-2">{{ t('companies.role') }}</th>
                        <th class="py-2">{{ t('companies.expires') }}</th>
                        <th class="py-2">{{ t('companies.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="invitation in companyInvitations(company.id)"
                        :key="invitation.id"
                        data-testid="invitation-row"
                    >
                        <td class="py-2">{{ invitation.email }}</td>
                        <td class="py-2">{{ roleLabel(invitation.role) }}</td>
                        <td class="py-2">{{ invitation.expires_at }}</td>
                        <td class="py-2">
                            <button
                                type="button"
                                :data-testid="`resend-invitation-${invitation.id}`"
                                class="rounded border border-zinc-300 px-3 py-1"
                                @click="resend(company.id, invitation.id)"
                            >
                                {{ t('companies.resend') }}
                            </button>
                            <button
                                type="button"
                                :data-testid="`revoke-invitation-${invitation.id}`"
                                class="ml-2 rounded border border-zinc-300 px-3 py-1"
                                @click="revoke(company.id, invitation.id)"
                            >
                                {{ t('companies.revoke') }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
        <div v-if="meta !== null && meta.last_page > 1" class="flex gap-2">
            <button
                type="button"
                data-testid="previous-page"
                class="rounded border border-zinc-300 px-3 py-1"
                :disabled="meta.current_page <= 1"
                @click="showPage(meta.current_page - 1)"
            >
                {{ t('companies.previous') }}
            </button>
            <button
                type="button"
                data-testid="next-page"
                class="rounded border border-zinc-300 px-3 py-1"
                :disabled="meta.current_page >= meta.last_page"
                @click="showPage(meta.current_page + 1)"
            >
                {{ t('companies.next') }}
            </button>
        </div>
    </section>
</template>
