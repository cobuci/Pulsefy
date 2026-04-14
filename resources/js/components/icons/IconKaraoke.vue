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
            d="M12 1c-1.66 0-3 1.34-3 3v6c0 1.66 1.34 3 3 3s3-1.34 3-3V4c0-1.66-1.34-3-3-3z"
        />
        <path
            d="M17 10c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V20H9v2h6v-2h-2v-3.08C16.39 16.43 19 13.53 19 10h-2z"
        />
        <path
            d="M20 6c.55 0 1 .45 1 1v2c0 .55-.45 1-1 1s-1-.45-1-1V7c0-.55.45-1 1-1z"
        />
        <path
            d="M22 8c.55 0 1 .45 1 1v0c0 .55-.45 1-1 1s-1-.45-1-1v0c0-.55.45-1 1-1z"
        />
    </svg>
</template>
