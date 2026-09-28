<script setup lang="ts">
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { signIn } from '../api/auth';
import { ApiError } from '../api/client';
import { useCurrentUser } from '../composables/useCurrentUser';
import { t } from '../utils/i18n';

const route = useRoute();
const router = useRouter();
const { load } = useCurrentUser();

const email = ref(typeof route.query.email === 'string' ? route.query.email : '');
const password = ref('');
const failureMessage = ref<string | null>(null);

async function submit(): Promise<void> {
    failureMessage.value = null;

    try {
        await signIn(email.value, password.value);
        await load();
        await router.push('/');
    } catch (error) {
        if (error instanceof ApiError && (error.status === 422 || error.status === 429)) {
            failureMessage.value = error.errors?.email?.[0] ?? error.message;

            return;
        }

        throw error;
    }
}
</script>

<template>
    <form class="mx-auto flex max-w-sm flex-col gap-4" @submit.prevent="submit">
        <label class="flex flex-col gap-1 text-sm">
            {{ t('auth.email') }}
            <input
                v-model="email"
                data-testid="email"
                type="email"
                name="email"
                autocomplete="username"
                required
                class="rounded border border-zinc-300 px-3 py-2"
            />
        </label>
        <label class="flex flex-col gap-1 text-sm">
            {{ t('auth.password') }}
            <input
                v-model="password"
                data-testid="password"
                type="password"
                name="password"
                autocomplete="current-password"
                required
                class="rounded border border-zinc-300 px-3 py-2"
            />
        </label>
        <p v-if="failureMessage" data-testid="sign-in-error" class="text-sm text-red-700">
            {{ failureMessage }}
        </p>
        <RouterLink to="/forgot-password" data-testid="forgot-password" class="text-sm underline">
            {{ t('auth.forgotPassword') }}
        </RouterLink>
        <button
            type="submit"
            data-testid="sign-in"
            class="rounded bg-zinc-900 px-3 py-2 text-white"
        >
            {{ t('auth.signIn') }}
        </button>
    </form>
</template>
