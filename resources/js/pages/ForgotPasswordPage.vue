<script setup lang="ts">
import { ref } from 'vue';
import { requestPasswordReset } from '../api/auth';
import { ApiError } from '../api/client';
import { t } from '../utils/i18n';

const email = ref('');
const emailError = ref<string | null>(null);
const confirmed = ref(false);

async function submit(): Promise<void> {
    emailError.value = null;

    try {
        await requestPasswordReset(email.value);
        confirmed.value = true;
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            emailError.value = error.errors?.email?.[0] ?? error.message;

            return;
        }

        throw error;
    }
}
</script>

<template>
    <form class="mx-auto flex max-w-sm flex-col gap-4" @submit.prevent="submit">
        <p v-if="confirmed" data-testid="reset-confirmation" class="text-sm">
            {{ t('auth.resetConfirmation') }}
        </p>
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
        <p v-if="emailError" data-testid="email-error" class="text-sm text-red-700">
            {{ emailError }}
        </p>
        <button
            type="submit"
            data-testid="send-reset-link"
            class="rounded bg-zinc-900 px-3 py-2 text-white"
        >
            {{ t('auth.sendResetLink') }}
        </button>
    </form>
</template>
