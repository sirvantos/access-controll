<script setup lang="ts">
import { ref } from 'vue';
import { requestPasswordReset } from '../api/auth';
import { ApiError } from '../api/client';
import { t } from '../utils/i18n';
import { Alert, AlertDescription } from '../components/ui/alert';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';

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
        <Alert v-if="confirmed" data-testid="reset-confirmation">
            <AlertDescription>{{ t('auth.resetConfirmation') }}</AlertDescription>
        </Alert>
        <Label class="flex flex-col items-stretch gap-1">
            {{ t('auth.email') }}
            <Input
                v-model="email"
                data-testid="email"
                type="email"
                name="email"
                autocomplete="username"
                required
            />
        </Label>
        <p v-if="emailError" data-testid="email-error" class="text-sm text-destructive">
            {{ emailError }}
        </p>
        <Button type="submit" data-testid="send-reset-link">
            {{ t('auth.sendResetLink') }}
        </Button>
    </form>
</template>
