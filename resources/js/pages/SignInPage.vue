<script setup lang="ts">
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { signIn } from '../api/auth';
import { ApiError } from '../api/client';
import { useCurrentUser } from '../composables/useCurrentUser';
import { t } from '../utils/i18n';
import { Alert, AlertDescription } from '../components/ui/alert';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';

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
        <Label class="flex flex-col items-stretch gap-1">
            {{ t('auth.password') }}
            <Input
                v-model="password"
                data-testid="password"
                type="password"
                name="password"
                autocomplete="current-password"
                required
            />
        </Label>
        <Alert v-if="failureMessage" variant="destructive" data-testid="sign-in-error">
            <AlertDescription>{{ failureMessage }}</AlertDescription>
        </Alert>
        <RouterLink
            to="/forgot-password"
            data-testid="forgot-password"
            class="text-sm text-primary underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
            {{ t('auth.forgotPassword') }}
        </RouterLink>
        <Button type="submit" data-testid="sign-in">
            {{ t('auth.signIn') }}
        </Button>
    </form>
</template>
