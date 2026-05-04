<script setup lang="ts">
import logoUrl from '@/../images/logo.svg';
</script>

<template>
    <img :src="logoUrl" alt="Application Logo" v-bind="$attrs" />
</template>
