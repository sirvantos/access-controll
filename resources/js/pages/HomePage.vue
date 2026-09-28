<script setup lang="ts">
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useCurrentUser } from '../composables/useCurrentUser';
import { t } from '../utils/i18n';

const router = useRouter();
const { currentUser } = useCurrentUser();

onMounted(() => {
    const role = currentUser.value?.role;

    if (role === 'super_admin') {
        void router.replace('/companies');

        return;
    }

    if (role === 'company_admin') {
        void router.replace('/company/users');
    }
});
</script>

<template>
    <p data-testid="home-placeholder">{{ t('home.placeholder') }}</p>
</template>
