<script setup lang="ts">
/**
 * Form Input Error message component displaying validation failures.
 */
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-red-600">
            {{ message }}
        </p>
    </div>
</template>
