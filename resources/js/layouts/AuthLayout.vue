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

const contextMenuState = contextMenu.state;
</script>

<template>
    <AuthLayout :title="title" :description="description" @contextmenu="openGlobalContextMenu">
        <slot />
    </AuthLayout>

    <AppContextMenu
        :open="contextMenuState.open"
        :x="contextMenuState.x"
        :y="contextMenuState.y"
        :items="contextMenuState.items"
        @close="contextMenu.close"
    />
</template>
