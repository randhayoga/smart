<script setup lang="ts">
/**
 * Basic Checkbox input component with two-way binding proxy.
 */
import { computed } from 'vue';

const emit = defineEmits(['update:checked']);

const props = defineProps<{
    checked: boolean;
    value?: any;
}>();

const proxyChecked = computed({
    get() {
        return props.checked;
    },

    set(val) {
        emit('update:checked', val);
    },
});
</script>

<template>
    <input
        type="checkbox"
        :value="value"
        v-model="proxyChecked"
        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
    />
</template>
