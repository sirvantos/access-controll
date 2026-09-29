<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { resetPassword } from '../api/auth';
import { ApiError } from '../api/client';
import { t } from '../utils/i18n';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';

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
        <p v-if="tokenInvalid" data-testid="reset-token-error" class="text-sm text-destructive">
            <RouterLink
                to="/forgot-password"
                data-testid="request-new-link"
                class="text-sm text-primary underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                {{ t('auth.requestNewLink') }}
            </RouterLink>
        </p>
        <Label class="flex flex-col items-stretch gap-1">
            {{ t('auth.newPassword') }}
            <Input
                v-model="password"
                data-testid="password"
                type="password"
                name="password"
                autocomplete="new-password"
                required
            />
        </Label>
        <p v-if="passwordError" data-testid="password-error" class="text-sm text-destructive">
            {{ passwordError }}
        </p>
        <Button type="submit" data-testid="reset-password">
            {{ t('auth.resetPassword') }}
        </Button>
    </form>
</template>
