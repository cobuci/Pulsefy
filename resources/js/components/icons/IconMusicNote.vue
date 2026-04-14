<script setup lang="ts">
defineProps<{
    class?: string;
}>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="currentColor"
        :class="$props.class"
    >
        <path
            d="M12 3v10.55A4 4 0 1 0 14 17V8.69l6-1.5V15a4 4 0 1 0 2 3.45V4.63L12 7.13V3z"
        />
    </svg>
</template>
