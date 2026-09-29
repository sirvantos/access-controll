<script setup lang="ts">
import { watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useCurrentUser } from '../composables/useCurrentUser';
import { useAppSidebar } from '../composables/useAppSidebar';
import { useSelectedCompany } from '../composables/useSelectedCompany';
import { t } from '../utils/i18n';

const route = useRoute();
const { currentUser } = useCurrentUser();
const { hasUsableSelection } = useSelectedCompany();
const { open, close } = useAppSidebar();

const linkClass =
    'block rounded-[var(--radius)] px-3 py-2 text-sm text-primary underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

watch(
    () => route.fullPath,
    () => {
        if (
            typeof window !== 'undefined' &&
            typeof window.matchMedia === 'function' &&
            !window.matchMedia('(min-width: 768px)').matches
        ) {
            close();
        }
    },
);

function onBackdropClick(): void {
    close();
}
</script>

<template>
    <Teleport to="body">
        <button
            v-if="open"
            type="button"
            data-testid="sidebar-backdrop"
            class="fixed inset-0 z-40 bg-slate-900/40 md:hidden"
            :aria-label="t('nav.closeMenu')"
            @click="onBackdropClick"
        />
    </Teleport>
    <aside
        v-if="currentUser"
        id="app-sidebar"
        data-testid="app-sidebar"
        :class="[
            'fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-border bg-card transition-transform duration-200 md:static md:z-auto md:translate-x-0',
            open ? 'translate-x-0' : '-translate-x-full md:hidden',
        ]"
        :aria-hidden="!open"
    >
        <div class="border-b border-border px-4 py-3 text-sm font-medium text-slate-900">
            {{ t('nav.menu') }}
        </div>
        <nav data-testid="app-sidebar-nav" class="flex flex-col gap-1 p-3">
            <RouterLink
                v-if="currentUser.role === 'super_admin'"
                to="/companies"
                data-testid="companies-link"
                :class="linkClass"
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
                :class="linkClass"
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
                :class="linkClass"
            >
                {{ t('companyProfile.nav') }}
            </RouterLink>
        </nav>
    </aside>
</template>
