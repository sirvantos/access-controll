<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppHeader from './components/AppHeader.vue';

const route = useRoute();
const showHeader = computed(() => route.meta.guest !== true);
</script>

<template>
    <div class="min-h-screen bg-white text-zinc-900">
        <AppHeader v-if="showHeader" />
        <main class="mx-auto max-w-3xl px-4 py-6">
            <RouterView />
        </main>
    </div>
</template>
