<script setup lang="ts">
import { ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ApiError } from '../api/client';
import { acceptInvitation, showInvitation, type InvitationPreview } from '../api/invitations';
import { t } from '../utils/i18n';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';

const route = useRoute();
const router = useRouter();
const preview = ref<InvitationPreview | null>(null);
const invalid = ref(false);
const password = ref('');
const passwordError = ref<string | null>(null);

watch(
    () => route.params.token,
    () => {
        void load();
    },
    { immediate: true },
);

async function load(): Promise<void> {
    const token = routeToken();

    if (token === null) {
        invalid.value = true;
        preview.value = null;

        return;
    }

    invalid.value = false;
    preview.value = null;
    passwordError.value = null;

    try {
        const body = await showInvitation(token);
        preview.value = body.data;
    } catch (error) {
        if (error instanceof ApiError && error.status === 410) {
            invalid.value = true;

            return;
        }

        throw error;
    }
}

async function submit(): Promise<void> {
    const token = routeToken();
    const current = preview.value;

    if (token === null || current === null) {
        return;
    }

    passwordError.value = null;

    try {
        await acceptInvitation(token, password.value);
        await router.push({ path: '/sign-in', query: { email: current.email } });
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            passwordError.value = error.errors?.password?.[0] ?? error.message;

            return;
        }

        if (error instanceof ApiError && error.status === 410) {
            invalid.value = true;
            preview.value = null;

            return;
        }

        throw error;
    }
}

function routeToken(): string | null {
    const token = route.params.token;

    return typeof token === 'string' && token !== '' ? token : null;
}

function roleLabel(role: InvitationPreview['role']): string {
    return t(`roles.${role}`);
}
</script>

<template>
    <section class="mx-auto flex max-w-sm flex-col gap-4">
        <p v-if="invalid" data-testid="invitation-invalid" class="text-sm text-destructive">
            {{ t('invitations.noLongerValid') }}
        </p>
        <form v-else-if="preview" class="flex flex-col gap-4" @submit.prevent="submit">
            <p data-testid="invitation-email" class="text-sm text-foreground">
                {{ preview.email }}
            </p>
            <p data-testid="invitation-role" class="text-sm text-foreground">
                {{ roleLabel(preview.role) }}
            </p>
            <Label class="flex flex-col items-stretch gap-1">
                {{ t('auth.password') }}
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
            <Button type="submit" data-testid="accept-invitation">
                {{ t('invitations.accept') }}
            </Button>
        </form>
    </section>
</template>
