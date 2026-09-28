<script setup lang="ts">
import { useRouter } from 'vue-router';
import { signOut } from '../api/auth';
import { useCurrentUser } from '../composables/useCurrentUser';
import { t } from '../utils/i18n';

const router = useRouter();
const { currentUser, clear } = useCurrentUser();

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
        <button
            type="button"
            data-testid="sign-out"
            class="text-sm underline"
            @click="submitSignOut"
        >
            {{ t('auth.signOut') }}
        </button>
    </header>
</template>
