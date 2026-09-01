<script setup lang="ts">
/**
 * Application Logo component rendering the brand SVG asset.
 */
import logoUrl from '@/../images/logo.svg';
</script>

<template>
    <img :src="logoUrl" alt="Application Logo" v-bind="$attrs" />
</template>
