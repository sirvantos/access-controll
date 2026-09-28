<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
    changeCompanyUserRole,
    deactivateCompanyUser,
    inviteCompanyUser,
    listCompanyInvitations,
    listCompanyUsers,
    reactivateCompanyUser,
    resendCompanyInvitation,
    revokeCompanyInvitation,
    type CompanyUser,
    type PendingInvitation,
} from '../api/companyUsers';
import { ApiError } from '../api/client';
import {
    ASSIGNABLE_ROLES,
    type AssignableRole,
    type PaginationMeta,
    type Role,
} from '../api/types';
import { t } from '../utils/i18n';

const route = useRoute();
const router = useRouter();
const users = ref<CompanyUser[]>([]);
const invitations = ref<PendingInvitation[]>([]);
const meta = ref<PaginationMeta | null>(null);
const inviteEmail = ref('');
const inviteRole = ref<AssignableRole>(ASSIGNABLE_ROLES[0]);
const inviteError = ref<string | null>(null);
const invitationError = ref<string | null>(null);
const userError = ref<string | null>(null);
const listVersion = ref(0);

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
    const [usersBody, invitationsBody] = await Promise.all([
        listCompanyUsers(page.value),
        listCompanyInvitations(1),
    ]);
    users.value = usersBody.data;
    meta.value = usersBody.meta;
    invitations.value = invitationsBody.data;
    listVersion.value += 1;
}

function showPage(next: number): void {
    void router.push({
        path: '/company/users',
        query: next > 1 ? { page: String(next) } : {},
    });
}

function roleLabel(role: Role): string {
    return t(`roles.${role}`);
}

async function invite(): Promise<void> {
    inviteError.value = null;

    try {
        await inviteCompanyUser(inviteEmail.value, inviteRole.value);
        inviteEmail.value = '';
        await refresh();
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            inviteError.value = error.errors?.email?.[0] ?? error.message;

            return;
        }

        throw error;
    }
}

async function resend(id: number): Promise<void> {
    await changeInvitation(() => resendCompanyInvitation(id));
}

async function revoke(id: number): Promise<void> {
    await changeInvitation(() => revokeCompanyInvitation(id));
}

async function changeInvitation(action: () => Promise<unknown>): Promise<void> {
    invitationError.value = null;

    try {
        await action();
        await refresh();
    } catch (error) {
        if (error instanceof ApiError && error.status === 409) {
            invitationError.value = error.message;

            return;
        }

        throw error;
    }
}

function changeRole(id: number, event: Event): void {
    const role = assignableRole(event);

    if (role === null) {
        return;
    }

    void changeUser(() => changeCompanyUserRole(id, role));
}

function assignableRole(event: Event): AssignableRole | null {
    const target = event.target;

    if (!(target instanceof HTMLSelectElement)) {
        return null;
    }

    return ASSIGNABLE_ROLES.find((role) => role === target.value) ?? null;
}

async function deactivate(id: number): Promise<void> {
    await changeUser(() => deactivateCompanyUser(id));
}

async function reactivate(id: number): Promise<void> {
    await changeUser(() => reactivateCompanyUser(id));
}

async function changeUser(action: () => Promise<unknown>): Promise<void> {
    userError.value = null;

    try {
        await action();
        await refresh();
    } catch (error) {
        if (
            error instanceof ApiError &&
            error.status === 409 &&
            error.errorCode === 'last_active_admin'
        ) {
            userError.value = t('companyUsers.lastActiveAdmin');
            await refresh();

            return;
        }

        throw error;
    }
}
</script>

<template>
    <section class="flex flex-col gap-4">
        <h1 class="text-xl font-semibold">{{ t('companyUsers.title') }}</h1>
        <form class="flex max-w-sm flex-col gap-3" @submit.prevent="invite">
            <label class="flex flex-col gap-1 text-sm">
                {{ t('companyUsers.email') }}
                <input
                    v-model="inviteEmail"
                    data-testid="invite-email"
                    type="email"
                    name="email"
                    required
                    class="rounded border border-zinc-300 px-3 py-2"
                />
            </label>
            <label class="flex flex-col gap-1 text-sm">
                {{ t('companyUsers.role') }}
                <select
                    v-model="inviteRole"
                    data-testid="invite-role"
                    name="role"
                    class="rounded border border-zinc-300 px-3 py-2"
                >
                    <option v-for="role in ASSIGNABLE_ROLES" :key="role" :value="role">
                        {{ roleLabel(role) }}
                    </option>
                </select>
            </label>
            <p v-if="inviteError" data-testid="invite-error" class="text-sm text-red-700">
                {{ inviteError }}
            </p>
            <button
                type="submit"
                data-testid="invite-user"
                class="rounded bg-zinc-900 px-3 py-2 text-white"
            >
                {{ t('companyUsers.invite') }}
            </button>
        </form>
        <section class="flex flex-col gap-2">
            <h2 class="text-lg font-semibold">{{ t('companyUsers.invitations') }}</h2>
            <p v-if="invitationError" data-testid="invitation-error" class="text-sm text-red-700">
                {{ invitationError }}
            </p>
            <table class="w-full text-left text-sm">
                <thead>
                    <tr>
                        <th class="py-2">{{ t('companyUsers.email') }}</th>
                        <th class="py-2">{{ t('companyUsers.role') }}</th>
                        <th class="py-2">{{ t('companyUsers.expires') }}</th>
                        <th class="py-2">{{ t('companyUsers.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="invitation in invitations"
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
                                @click="resend(invitation.id)"
                            >
                                {{ t('companyUsers.resend') }}
                            </button>
                            <button
                                type="button"
                                :data-testid="`revoke-invitation-${invitation.id}`"
                                class="ml-2 rounded border border-zinc-300 px-3 py-1"
                                @click="revoke(invitation.id)"
                            >
                                {{ t('companyUsers.revoke') }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
        <table class="w-full text-left text-sm">
            <thead>
                <tr>
                    <th class="py-2">{{ t('companyUsers.email') }}</th>
                    <th class="py-2">{{ t('companyUsers.role') }}</th>
                    <th class="py-2">{{ t('companyUsers.state') }}</th>
                    <th class="py-2">{{ t('companyUsers.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="user in users" :key="user.id" data-testid="user-row">
                    <td class="py-2">{{ user.email }}</td>
                    <td class="py-2">
                        <select
                            :key="`${user.id}-${listVersion}`"
                            :data-testid="`user-role-${user.id}`"
                            :value="user.role"
                            class="rounded border border-zinc-300 px-3 py-1"
                            @change="changeRole(user.id, $event)"
                        >
                            <option v-for="role in ASSIGNABLE_ROLES" :key="role" :value="role">
                                {{ roleLabel(role) }}
                            </option>
                        </select>
                    </td>
                    <td class="py-2">
                        {{ t(user.is_active ? 'companyUsers.active' : 'companyUsers.deactivated') }}
                    </td>
                    <td class="py-2">
                        <button
                            v-if="user.is_active"
                            type="button"
                            :data-testid="`deactivate-user-${user.id}`"
                            class="rounded border border-zinc-300 px-3 py-1"
                            @click="deactivate(user.id)"
                        >
                            {{ t('companyUsers.deactivate') }}
                        </button>
                        <button
                            v-else
                            type="button"
                            :data-testid="`reactivate-user-${user.id}`"
                            class="rounded border border-zinc-300 px-3 py-1"
                            @click="reactivate(user.id)"
                        >
                            {{ t('companyUsers.reactivate') }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
        <p v-if="userError" data-testid="user-error" class="text-sm text-red-700">
            {{ userError }}
        </p>
        <div v-if="meta !== null && meta.last_page > 1" class="flex gap-2">
            <button
                type="button"
                data-testid="previous-page"
                class="rounded border border-zinc-300 px-3 py-1"
                :disabled="meta.current_page <= 1"
                @click="showPage(meta.current_page - 1)"
            >
                {{ t('companyUsers.previous') }}
            </button>
            <button
                type="button"
                data-testid="next-page"
                class="rounded border border-zinc-300 px-3 py-1"
                :disabled="meta.current_page >= meta.last_page"
                @click="showPage(meta.current_page + 1)"
            >
                {{ t('companyUsers.next') }}
            </button>
        </div>
    </section>
</template>
