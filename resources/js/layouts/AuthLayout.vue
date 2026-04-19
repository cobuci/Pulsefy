<script setup lang="ts">
import { AppContextMenu } from '@/components/ui/context-menu';
import { useContextMenu } from '@/composables/useContextMenu';
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();

const contextMenu = useContextMenu();

function openGlobalContextMenu(event: MouseEvent): void {
    contextMenu.open(event, []);
}
</script>

<template>
    <AuthLayout :title="title" :description="description" @contextmenu="openGlobalContextMenu">
        <slot />
    </AuthLayout>

    <AppContextMenu
        :open="contextMenu.state.open"
        :x="contextMenu.state.x"
        :y="contextMenu.state.y"
        :items="contextMenu.state.items"
        @close="contextMenu.close"
    />
</template>
