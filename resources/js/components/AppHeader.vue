<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router';
import { signOut } from '../api/auth';
import { useCurrentUser } from '../composables/useCurrentUser';
import { useSelectedCompany } from '../composables/useSelectedCompany';
import { t } from '../utils/i18n';

const router = useRouter();
const { currentUser, clear } = useCurrentUser();
const { hasUsableSelection, selectedCompany } = useSelectedCompany();

async function submitSignOut(): Promise<void> {
    await signOut();
    clear();
    await router.push('/sign-in');
}
</script>

<template>
    <header
        v-if="currentUser"
        class="flex items-center justify-between border-b border-zinc-200 px-4 py-3"
    >
        <span data-testid="current-email">{{ currentUser.email }}</span>
        <nav class="flex items-center gap-4">
            <span
                v-if="currentUser.role === 'super_admin' && hasUsableSelection && selectedCompany"
                data-testid="selected-company-name"
                class="text-sm"
            >
                {{ t('companies.selected', { name: selectedCompany.name }) }}
            </span>
            <span
                v-else-if="currentUser.role === 'super_admin'"
                data-testid="select-company-prompt"
                class="text-sm"
            >
                {{ t('companies.selectPrompt') }}
            </span>
            <RouterLink
                v-if="currentUser.role === 'super_admin'"
                to="/companies"
                data-testid="companies-link"
                class="text-sm underline"
            >
                {{ t('companies.title') }}
            </RouterLink>
            <RouterLink
                v-if="
                    currentUser.role === 'company_admin' ||
                    (currentUser.role === 'super_admin' && hasUsableSelection)
                "
                to="/company/users"
                data-testid="company-users-link"
                class="text-sm underline"
            >
                {{ t('companyUsers.title') }}
            </RouterLink>
            <RouterLink
                v-if="
                    currentUser.role === 'company_admin' ||
                    currentUser.role === 'viewer' ||
                    (currentUser.role === 'super_admin' && hasUsableSelection)
                "
                to="/company"
                data-testid="company-profile-link"
                class="text-sm underline"
            >
                {{ t('companyProfile.nav') }}
            </RouterLink>
            <button
                type="button"
                data-testid="sign-out"
                class="text-sm underline"
                @click="submitSignOut"
            >
                {{ t('auth.signOut') }}
            </button>
        </nav>
    </header>
</template>
