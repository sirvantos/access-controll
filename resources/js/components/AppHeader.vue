<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { Menu } from '@lucide/vue';
import { signOut } from '../api/auth';
import { useAppSidebar } from '../composables/useAppSidebar';
import { useCurrentUser } from '../composables/useCurrentUser';
import { useSelectedCompany } from '../composables/useSelectedCompany';
import { t } from '../utils/i18n';
import { Button } from './ui/button';

const router = useRouter();
const { currentUser, clear } = useCurrentUser();
const { hasUsableSelection, selectedCompany } = useSelectedCompany();
const { open, toggle } = useAppSidebar();

const menuLabel = computed(() => (open.value ? t('nav.closeMenu') : t('nav.openMenu')));

async function submitSignOut(): Promise<void> {
    await signOut();
    clear();
    await router.push('/sign-in');
}
</script>

<template>
    <header
        v-if="currentUser"
        data-testid="app-header"
        class="flex items-center justify-between gap-4 border-b border-border bg-background px-4 py-2"
    >
        <div class="flex min-w-0 items-center gap-3">
            <Button
                type="button"
                variant="outline"
                size="sm"
                data-testid="sidebar-toggle"
                class="shrink-0"
                :aria-expanded="open"
                aria-controls="app-sidebar"
                :aria-label="menuLabel"
                @click="toggle"
            >
                <Menu class="size-4" aria-hidden="true" />
                <span class="sr-only">{{ menuLabel }}</span>
            </Button>
            <span data-testid="current-email" class="min-w-0 truncate text-sm text-slate-900">{{
                currentUser.email
            }}</span>
        </div>
        <div class="flex min-w-0 items-center gap-4">
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
                class="hidden text-sm text-muted-foreground sm:inline"
            >
                {{ t('companies.selectPrompt') }}
            </span>
            <Button
                type="button"
                data-testid="sign-out"
                variant="outline"
                size="sm"
                @click="submitSignOut"
            >
                {{ t('auth.signOut') }}
            </Button>
        </div>
    </header>
</template>
