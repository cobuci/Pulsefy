<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import NowPlayingPlayer from '@/components/NowPlayingPlayer.vue';
import { AppContextMenu } from '@/components/ui/context-menu';
import { Toaster } from '@/components/ui/sonner';
import { useContextMenu } from '@/composables/useContextMenu';
import type { BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';
import { toast } from 'vue-sonner';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const contextMenu = useContextMenu();
const page = usePage();

function openGlobalContextMenu(event: MouseEvent): void {
    contextMenu.open(event, []);
}

const contextMenuState = contextMenu.state;

onMounted(() => {
    const userId = page.props.auth?.user?.id;

    if (!userId || typeof window === 'undefined' || !window.Echo) {
        return;
    }

    window.Echo.private(`App.Models.User.${userId}`).listen(
        '.Spotify.SyncFailed',
        (event: { message: string }) => {
            toast.error(event.message);
        },
    );
});

onUnmounted(() => {
    const userId = page.props.auth?.user?.id;

    if (!userId || typeof window === 'undefined' || !window.Echo) {
        return;
    }

    window.Echo.private(`App.Models.User.${userId}`).stopListening('.Spotify.SyncFailed');
});
</script>

<template>
    <AppShell variant="header" @contextmenu="openGlobalContextMenu">
        <AppHeader :breadcrumbs="breadcrumbs" />
        <AppContent variant="header" class="pt-16 pb-24">
            <slot />
        </AppContent>
        <NowPlayingPlayer />
        <Toaster />

        <AppContextMenu
            :open="contextMenuState.open"
            :x="contextMenuState.x"
            :y="contextMenuState.y"
            :items="contextMenuState.items"
            @close="contextMenu.close"
        />
    </AppShell>
</template>
