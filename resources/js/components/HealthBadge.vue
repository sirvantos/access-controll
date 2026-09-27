<script setup lang="ts">
defineProps<{
    status: string;
}>();
</script>

<template>
    <p data-testid="health-status">{{ status }}</p>
</template>
