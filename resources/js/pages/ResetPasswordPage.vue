<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { resetPassword } from '../api/auth';
import { ApiError } from '../api/client';
import { t } from '../utils/i18n';

const route = useRoute();
const router = useRouter();
const password = ref('');
const passwordError = ref<string | null>(null);
const tokenInvalid = ref(false);

const token = computed(() => (typeof route.params.token === 'string' ? route.params.token : ''));
const email = computed(() => (typeof route.query.email === 'string' ? route.query.email : ''));

async function submit(): Promise<void> {
    passwordError.value = null;
    tokenInvalid.value = false;

    try {
        await resetPassword(token.value, email.value, password.value);
        await router.push({ path: '/sign-in', query: { email: email.value } });
    } catch (error) {
        if (error instanceof ApiError && error.status === 422 && error.errors?.token?.[0]) {
            tokenInvalid.value = true;

            return;
        }

        if (error instanceof ApiError && error.status === 422) {
            passwordError.value = error.errors?.password?.[0] ?? error.message;

            return;
        }

        throw error;
    }
}
</script>

<template>
    <form class="mx-auto flex max-w-sm flex-col gap-4" @submit.prevent="submit">
        <p v-if="tokenInvalid" data-testid="reset-token-error" class="text-sm text-red-700">
            <RouterLink to="/forgot-password" data-testid="request-new-link" class="underline">
                {{ t('auth.requestNewLink') }}
            </RouterLink>
        </p>
        <label class="flex flex-col gap-1 text-sm">
            {{ t('auth.newPassword') }}
            <input
                v-model="password"
                data-testid="password"
                type="password"
                name="password"
                autocomplete="new-password"
                required
                class="rounded border border-zinc-300 px-3 py-2"
            />
        </label>
        <p v-if="passwordError" data-testid="password-error" class="text-sm text-red-700">
            {{ passwordError }}
        </p>
        <button
            type="submit"
            data-testid="reset-password"
            class="rounded bg-zinc-900 px-3 py-2 text-white"
        >
            {{ t('auth.resetPassword') }}
        </button>
    </form>
</template>
