<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import NowPlayingPlayer from '@/components/NowPlayingPlayer.vue';
import { AppContextMenu } from '@/components/ui/context-menu';
import { Toaster } from '@/components/ui/sonner';
import { useContextMenu } from '@/composables/useContextMenu';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const contextMenu = useContextMenu();

function openGlobalContextMenu(event: MouseEvent): void {
    contextMenu.open(event, []);
}
</script>

<template>
    <AppShell variant="header" @contextmenu="openGlobalContextMenu">
        <AppHeader :breadcrumbs="breadcrumbs" />
        <AppContent variant="header" class="pb-24">
            <slot />
        </AppContent>
        <NowPlayingPlayer />
        <Toaster />

        <AppContextMenu
            :open="contextMenu.state.open"
            :x="contextMenu.state.x"
            :y="contextMenu.state.y"
            :items="contextMenu.state.items"
            @close="contextMenu.close"
        />
    </AppShell>
</template>
