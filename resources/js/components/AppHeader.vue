<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router';
import { signOut } from '../api/auth';
import { useCurrentUser } from '../composables/useCurrentUser';
import { useSelectedCompany } from '../composables/useSelectedCompany';
import { t } from '../utils/i18n';
import { Button } from './ui/button';

const router = useRouter();
const { currentUser, clear } = useCurrentUser();
const { hasUsableSelection, selectedCompany } = useSelectedCompany();

const navLinkClass =
    'rounded-[var(--radius)] text-sm text-primary underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

async function submitSignOut(): Promise<void> {
    await signOut();
    clear();
    await router.push('/sign-in');
}
</script>

<template>
    <header
        v-if="currentUser"
        class="flex items-center justify-between gap-4 border-b border-border bg-background px-4 py-2"
    >
        <span data-testid="current-email" class="shrink-0 text-sm text-slate-900">{{
            currentUser.email
        }}</span>
        <nav class="flex min-w-0 items-center gap-4">
            <span
                v-if="currentUser.role === 'super_admin' && hasUsableSelection && selectedCompany"
                data-testid="selected-company-name"
                class="min-w-0 max-w-48 truncate text-sm text-slate-900"
                :title="selectedCompany.name"
            >
                {{ t('companies.selected', { name: selectedCompany.name }) }}
            </span>
            <span
                v-else-if="currentUser.role === 'super_admin'"
                data-testid="select-company-prompt"
                class="text-sm text-muted-foreground"
            >
                {{ t('companies.selectPrompt') }}
            </span>
            <RouterLink
                v-if="currentUser.role === 'super_admin'"
                to="/companies"
                data-testid="companies-link"
                :class="navLinkClass"
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
                :class="navLinkClass"
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
                :class="navLinkClass"
            >
                {{ t('companyProfile.nav') }}
            </RouterLink>
            <Button
                type="button"
                data-testid="sign-out"
                variant="outline"
                size="sm"
                @click="submitSignOut"
            >
                {{ t('auth.signOut') }}
            </Button>
        </nav>
    </header>
</template>
