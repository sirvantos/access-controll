<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import AppHeader from './components/AppHeader.vue';
import AppSidebar from './components/AppSidebar.vue';
import { useAppSidebar } from './composables/useAppSidebar';

const route = useRoute();
const showChrome = computed(() => route.meta.guest !== true);
const { open, close } = useAppSidebar();

function onKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Escape' || !open.value) {
        return;
    }

    if (
        typeof window !== 'undefined' &&
        typeof window.matchMedia === 'function' &&
        !window.matchMedia('(min-width: 768px)').matches
    ) {
        close();
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <div v-if="showChrome" class="flex min-h-screen">
            <AppSidebar />
            <div class="flex min-w-0 flex-1 flex-col">
                <AppHeader />
                <main
                    class="mx-auto mt-6 w-full max-w-3xl flex-1 rounded-[var(--radius)] border border-border bg-card px-4 py-6 md:mx-6 md:mb-6"
                >
                    <RouterView />
                </main>
            </div>
        </div>
        <main
            v-else
            class="mx-auto mt-6 max-w-3xl rounded-[var(--radius)] border border-border bg-card px-4 py-6"
        >
            <RouterView />
        </main>
    </div>
</template>
