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
import { ApiError, isCompanyNotSelectedError } from '../api/client';
import {
    ASSIGNABLE_ROLES,
    type AssignableRole,
    type PaginationMeta,
    type Role,
} from '../api/types';
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
    try {
        const [usersBody, invitationsBody] = await Promise.all([
            listCompanyUsers(page.value),
            listCompanyInvitations(1),
        ]);
        users.value = usersBody.data;
        meta.value = usersBody.meta;
        invitations.value = invitationsBody.data;
        listVersion.value += 1;
    } catch (error) {
        if (isCompanyNotSelectedError(error)) {
            return;
        }

        throw error;
    }
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
        <h1 class="text-xl font-semibold text-slate-900">{{ t('companyUsers.title') }}</h1>
        <form class="flex max-w-sm flex-col gap-3" @submit.prevent="invite">
            <Label class="flex flex-col items-stretch gap-1">
                {{ t('companyUsers.email') }}
                <Input
                    v-model="inviteEmail"
                    data-testid="invite-email"
                    type="email"
                    name="email"
                    required
                />
            </Label>
            <Label class="flex flex-col items-stretch gap-1">
                {{ t('companyUsers.role') }}
                <NativeSelect v-model="inviteRole" data-testid="invite-role" name="role" class="w-full">
                    <option v-for="role in ASSIGNABLE_ROLES" :key="role" :value="role">
                        {{ roleLabel(role) }}
                    </option>
                </NativeSelect>
            </Label>
            <p v-if="inviteError" data-testid="invite-error" class="text-sm text-destructive">
                {{ inviteError }}
            </p>
            <Button type="submit" data-testid="invite-user">
                {{ t('companyUsers.invite') }}
            </Button>
        </form>
        <section class="flex flex-col gap-2">
            <h2 class="text-lg font-semibold text-slate-900">{{ t('companyUsers.invitations') }}</h2>
            <p v-if="invitationError" data-testid="invitation-error" class="text-sm text-destructive">
                {{ invitationError }}
            </p>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ t('companyUsers.email') }}</TableHead>
                        <TableHead>{{ t('companyUsers.role') }}</TableHead>
                        <TableHead>{{ t('companyUsers.expires') }}</TableHead>
                        <TableHead>{{ t('companyUsers.actions') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="invitation in invitations"
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
                                    @click="resend(invitation.id)"
                                >
                                    {{ t('companyUsers.resend') }}
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    :data-testid="`revoke-invitation-${invitation.id}`"
                                    @click="revoke(invitation.id)"
                                >
                                    {{ t('companyUsers.revoke') }}
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>{{ t('companyUsers.email') }}</TableHead>
                    <TableHead>{{ t('companyUsers.role') }}</TableHead>
                    <TableHead>{{ t('companyUsers.state') }}</TableHead>
                    <TableHead>{{ t('companyUsers.actions') }}</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="user in users" :key="user.id" data-testid="user-row">
                    <TableCell>{{ user.email }}</TableCell>
                    <TableCell>
                        <NativeSelect
                            :key="`${user.id}-${listVersion}`"
                            :data-testid="`user-role-${user.id}`"
                            :model-value="user.role"
                            @change="changeRole(user.id, $event)"
                        >
                            <option v-for="role in ASSIGNABLE_ROLES" :key="role" :value="role">
                                {{ roleLabel(role) }}
                            </option>
                        </NativeSelect>
                    </TableCell>
                    <TableCell>
                        <Badge v-if="user.is_active" variant="success">
                            {{ t('companyUsers.active') }}
                        </Badge>
                        <Badge v-else variant="secondary">
                            {{ t('companyUsers.deactivated') }}
                        </Badge>
                    </TableCell>
                    <TableCell>
                        <Button
                            v-if="user.is_active"
                            type="button"
                            variant="destructive"
                            size="sm"
                            :data-testid="`deactivate-user-${user.id}`"
                            @click="deactivate(user.id)"
                        >
                            {{ t('companyUsers.deactivate') }}
                        </Button>
                        <Button
                            v-else
                            type="button"
                            variant="outline"
                            size="sm"
                            :data-testid="`reactivate-user-${user.id}`"
                            @click="reactivate(user.id)"
                        >
                            {{ t('companyUsers.reactivate') }}
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
        <p v-if="userError" data-testid="user-error" class="text-sm text-destructive">
            {{ userError }}
        </p>
        <div v-if="meta !== null && meta.last_page > 1" class="flex gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                data-testid="previous-page"
                :disabled="meta.current_page <= 1"
                @click="showPage(meta.current_page - 1)"
            >
                {{ t('companyUsers.previous') }}
            </Button>
            <Button
                type="button"
                variant="outline"
                size="sm"
                data-testid="next-page"
                :disabled="meta.current_page >= meta.last_page"
                @click="showPage(meta.current_page + 1)"
            >
                {{ t('companyUsers.next') }}
            </Button>
        </div>
    </section>
</template>
