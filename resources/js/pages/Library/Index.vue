<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronRight, Folder, FolderOpen, Heart, Home, ListMusic, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import type { ContextMenuItem } from '@/components/ui/context-menu';
import { Input } from '@/components/ui/input';
import { useContextMenu } from '@/composables/useContextMenu';
import {
    index as libraryIndex,
    move as movePlaylist,
    reorder as reorderPlaylists,
    show as libraryShow,
    visibility as updatePlaylistVisibility,
    refresh as refreshLibrary,
} from '@/routes/library';
import { store as storeFolder } from '@/routes/library/folders';
import { sync as syncLikedSongs } from '@/routes/library/liked-songs';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Library',
                href: libraryIndex(),
            },
        ],
    },
});

type LibraryFolderItem = {
    id: number;
    name: string;
    parent_id: number | null;
    position: number;
};

type LibraryPlaylistItem = {
    id: string;
    name: string;
    description: string | null;
    image: string | null;
    tracks_total: number;
    owner_name: string | null;
    synced_at: string | null;
    folder_id?: number | null;
    position: number;
    is_hidden: boolean;
};

const props = defineProps<{
    likedPlaylist: {
        id: string;
        name: string;
        tracks_total: number;
        synced_at: string | null;
        syncStatus: {
            isRunning: boolean;
            hasFailure: boolean;
            updatedAt: string | null;
        };
    } | null;
    folders: Array<{
        id: number;
        name: string;
        parent_id: number | null;
        position: number;
    }>;
    playlists: Array<{
        id: string;
        name: string;
        description: string | null;
        image: string | null;
        tracks_total: number;
        owner_name: string | null;
        synced_at: string | null;
        folder_id: number | null;
        position: number;
        is_hidden: boolean;
    }>;
    hiddenCount: number;
    showHidden: boolean;
    syncStatus?: {
        isRunning: boolean;
        hasFailure: boolean;
        completed: number;
        total: number;
        progress: number;
        updatedAt: string | null;
    };
}>();

const activeFolderId = ref<number | null>(null);
const dragOverFolderId = ref<number | null>(null);
const dragOverPlaylistId = ref<string | null>(null);
const draggedPlaylistId = ref<string | null>(null);
const dragOriginFolderId = ref<number | null>(null);
const isDraggingPlaylist = ref(false);
const movedToAnotherFolder = ref(false);
const localPlaylists = ref<LibraryPlaylistItem[]>([]);
const includeHidden = ref(props.showHidden);
const hiddenCount = computed(() => props.hiddenCount);
const contextMenu = useContextMenu();
const syncStatusRef = ref(
    props.syncStatus ?? {
        isRunning: false,
        hasFailure: false,
        completed: 0,
        total: 0,
        progress: 0,
        updatedAt: null,
    },
);
const page = usePage<{
    auth: {
        user?: {
            id: number;
        };
    };
}>();

const currentSyncStatus = computed(() => syncStatusRef.value);

onMounted(() => {
    if (typeof window === 'undefined' || !window.Echo || !page.props.auth.user?.id) {
        return;
    }

    window.Echo.private(`App.Models.User.${page.props.auth.user.id}`)
        .listen(
            '.Library.SyncStatusUpdated',
            (event: {
                status: {
                    isRunning: boolean;
                    hasFailure: boolean;
                    completed: number;
                    total: number;
                    progress: number;
                    updatedAt: string | null;
                };
            }) => {
                syncStatusRef.value = event.status;

                if (! event.status.isRunning) {
                    router.reload({
                        only: ['playlists', 'syncStatus'],
                    });
                }
            },
        );
});

onUnmounted(() => {
    if (typeof window === 'undefined' || !window.Echo || !page.props.auth.user?.id) {
        return;
    }

    window.Echo.leave(`App.Models.User.${page.props.auth.user.id}`);
});

const folderById = computed(() => {
    return new Map(props.folders.map((folder) => [folder.id, folder]));
});

const childFolders = computed<LibraryFolderItem[]>(() => {
    return props.folders.filter((folder) => folder.parent_id === activeFolderId.value);
});

const visiblePlaylists = computed<LibraryPlaylistItem[]>(() => {
    return localPlaylists.value.filter(
        (playlist) => (playlist.folder_id ?? null) === activeFolderId.value && !playlist.is_hidden,
    );
});

const hiddenPlaylists = computed<LibraryPlaylistItem[]>(() => {
    return localPlaylists.value.filter(
        (playlist) => (playlist.folder_id ?? null) === activeFolderId.value && playlist.is_hidden,
    );
});

watch(
    () => props.playlists,
    (playlists) => {
        localPlaylists.value = playlists
            .map((playlist) => ({ ...playlist }))
            .sort((a, b) => {
                const folderA = a.folder_id ?? -1;
                const folderB = b.folder_id ?? -1;

                if (folderA !== folderB) {
                    return folderA - folderB;
                }

                if (a.is_hidden !== b.is_hidden) {
                    return Number(a.is_hidden) - Number(b.is_hidden);
                }

                return a.position - b.position;
            });
    },
    { immediate: true },
);

watch(
    () => props.showHidden,
    (showHidden) => {
        includeHidden.value = showHidden;
    },
);

const activeFolderPath = computed<LibraryFolderItem[]>(() => {
    const path: LibraryFolderItem[] = [];
    let cursor = activeFolderId.value;

    while (cursor !== null) {
        const folder = folderById.value.get(cursor);

        if (!folder) {
            break;
        }

        path.unshift(folder);
        cursor = folder.parent_id;
    }

    return path;
});

function openFolder(folderId: number | null) {
    activeFolderId.value = folderId;
}

function resetDragState(): void {
    dragOverFolderId.value = null;
    dragOverPlaylistId.value = null;
    draggedPlaylistId.value = null;
    dragOriginFolderId.value = null;
    isDraggingPlaylist.value = false;
}

function onPlaylistDragStart(event: DragEvent, playlistId: string) {
    isDraggingPlaylist.value = true;
    draggedPlaylistId.value = playlistId;
    dragOriginFolderId.value = activeFolderId.value;
    movedToAnotherFolder.value = false;
    event.dataTransfer?.setData('text/plain', playlistId);
    event.dataTransfer?.setData('application/x-pulsefy-playlist', playlistId);
    event.dataTransfer?.setData('application/x-pulsefy-origin-folder', String(activeFolderId.value ?? 'root'));

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function onFolderDragOver(event: DragEvent, folderId: number | null) {
    event.preventDefault();

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }

    dragOverFolderId.value = folderId;
}

function onFolderDragLeave(folderId: number | null) {
    if (dragOverFolderId.value === folderId) {
        dragOverFolderId.value = null;
    }
}

function onFolderDrop(event: DragEvent, folderId: number | null) {
    event.preventDefault();
    const playlistId =
        event.dataTransfer?.getData('application/x-pulsefy-playlist') ||
        event.dataTransfer?.getData('text/plain') ||
        draggedPlaylistId.value;

    resetDragState();

    if (!playlistId) {
        return;
    }

    movedToAnotherFolder.value = true;

    router.patch(
        movePlaylist(playlistId).url,
        {
            folder_id: folderId,
        },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['playlists', 'hiddenCount', 'showHidden'],
            onError: () => {
                router.reload({
                    only: ['playlists', 'hiddenCount', 'showHidden'],
                });
            },
        },
    );
}

function onPlaylistDragOver(event: DragEvent, playlistId: string) {
    event.preventDefault();

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }

    if (!draggedPlaylistId.value || playlistId === draggedPlaylistId.value) {
        return;
    }

    dragOverPlaylistId.value = playlistId;
}

function onPlaylistDragDropZone(event: DragEvent): void {
    event.preventDefault();

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function onPlaylistDragEnter(event: DragEvent, playlistId: string) {
    event.preventDefault();

    if (!draggedPlaylistId.value || playlistId === draggedPlaylistId.value || dragOverPlaylistId.value === playlistId) {
        return;
    }

    dragOverPlaylistId.value = playlistId;

    previewReorder(draggedPlaylistId.value, playlistId);
}

function onPlaylistDragLeave(playlistId: string) {
    if (dragOverPlaylistId.value === playlistId) {
        dragOverPlaylistId.value = null;
    }
}

function persistReorderIfNeeded() {
    const orderedIds = visiblePlaylists.value.map((playlist) => playlist.id);
    const originalIds = props.playlists
        .filter((playlist) => (playlist.folder_id ?? null) === activeFolderId.value && !playlist.is_hidden)
        .sort((a, b) => a.position - b.position)
        .map((playlist) => playlist.id);

    if (orderedIds.every((playlistId, index) => playlistId === originalIds[index])) {
        return;
    }

    router.patch(
        reorderPlaylists().url,
        {
            folder_id: activeFolderId.value,
            ordered_playlist_ids: orderedIds,
        },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['playlists'],
            onError: () => {
                router.reload({
                    only: ['playlists'],
                });
            },
        },
    );
}

function onPlaylistDrop(event: DragEvent, targetPlaylistId: string) {
    event.preventDefault();

    const sourcePlaylistId =
        event.dataTransfer?.getData('application/x-pulsefy-playlist') ||
        event.dataTransfer?.getData('text/plain') ||
        draggedPlaylistId.value;
    const originFolderRaw = event.dataTransfer?.getData('application/x-pulsefy-origin-folder');
    const originFolderId = originFolderRaw === 'root' || originFolderRaw === '' ? null : Number(originFolderRaw);

    if (!sourcePlaylistId || sourcePlaylistId === targetPlaylistId) {
        onPlaylistDragEnd();

        return;
    }

    if (originFolderId !== activeFolderId.value || dragOriginFolderId.value !== activeFolderId.value) {
        onPlaylistDragEnd();

        return;
    }

    persistReorderIfNeeded();

    onPlaylistDragEnd();
}

function previewReorder(sourcePlaylistId: string, targetPlaylistId: string) {
    const sourceIndex = localPlaylists.value.findIndex(
        (playlist) => playlist.id === sourcePlaylistId && (playlist.folder_id ?? null) === activeFolderId.value,
    );
    const targetIndex = localPlaylists.value.findIndex(
        (playlist) => playlist.id === targetPlaylistId && (playlist.folder_id ?? null) === activeFolderId.value,
    );

    if (sourceIndex < 0 || targetIndex < 0 || sourceIndex === targetIndex) {
        return;
    }

    const next = [...localPlaylists.value];
    const [moved] = next.splice(sourceIndex, 1);
    next.splice(targetIndex, 0, moved);
    localPlaylists.value = next;
}

function onPlaylistDragEnd() {
    if (movedToAnotherFolder.value) {
        router.reload({
            only: ['playlists'],
        });
    }

    resetDragState();
    movedToAnotherFolder.value = false;
}

function onPlaylistCardClick(event: MouseEvent) {
    if (!isDraggingPlaylist.value) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
}

function closeContextMenu() {
    contextMenu.close();
}

function openContextMenu(event: MouseEvent, items: ContextMenuItem[]) {
    contextMenu.open(event, items);
}

function setPlaylistVisibility(playlist: LibraryPlaylistItem, hidden: boolean) {
    closeContextMenu();

    router.patch(
        updatePlaylistVisibility(playlist.id).url,
        { hidden },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['playlists', 'hiddenCount', 'showHidden'],
            onError: () => {
                router.reload({
                    only: ['playlists', 'hiddenCount', 'showHidden'],
                });
            },
        },
    );
}

function movePlaylistToFolder(playlist: LibraryPlaylistItem, folderId: number | null) {
    closeContextMenu();

    router.patch(
        movePlaylist(playlist.id).url,
        {
            folder_id: folderId,
        },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['playlists'],
            onError: () => {
                router.reload({
                    only: ['playlists'],
                });
            },
        },
    );
}

function getPlaylistContextItems(playlist: LibraryPlaylistItem): ContextMenuItem[] {
    const folderItems: ContextMenuItem[] = props.folders
        .filter((folder) => folder.id !== playlist.folder_id)
        .map((folder) => ({
            key: `move-folder-${playlist.id}-${folder.id}`,
            label: folder.name,
            onSelect: () => movePlaylistToFolder(playlist, folder.id),
        }));

    if (folderItems.length === 0) {
        folderItems.push({
            key: `move-folder-empty-${playlist.id}`,
            label: 'No other folder available',
            disabled: true,
        });
    }

    return [
        {
            key: `toggle-hidden-${playlist.id}`,
            label: playlist.is_hidden ? 'Show playlist' : 'Hide playlist',
            onSelect: () => setPlaylistVisibility(playlist, !playlist.is_hidden),
        },
        {
            key: `playlist-sep-${playlist.id}`,
            separator: true,
        },
        {
            key: `move-root-${playlist.id}`,
            label: 'Move to root',
            disabled: playlist.folder_id === null,
            onSelect: () => movePlaylistToFolder(playlist, null),
        },
        {
            key: `move-folder-${playlist.id}`,
            label: 'Move to folder',
            children: folderItems,
        },
    ];
}

function onPlaylistContextMenu(event: MouseEvent, playlist: LibraryPlaylistItem) {
    openContextMenu(event, getPlaylistContextItems(playlist));
}

function onFolderContextMenu(event: MouseEvent, folder: LibraryFolderItem) {
    openContextMenu(event, [
        {
            key: `folder-rename-${folder.id}`,
            label: 'Rename (coming soon)',
            disabled: true,
        },
        {
            key: `folder-delete-${folder.id}`,
            label: 'Delete (coming soon)',
            destructive: true,
            disabled: true,
        },
    ]);
}

function toggleShowHidden() {
    includeHidden.value = !includeHidden.value;
    closeContextMenu();

    router.visit(
        libraryIndex({
            query: {
                show_hidden: includeHidden.value ? 1 : 0,
            },
        }).url,
        {
            only: ['playlists', 'hiddenCount', 'showHidden'],
            preserveScroll: true,
            preserveState: true,
        },
    );
}
</script>

<template>
    <Head title="Library" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-8 py-4">
        <section class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    Your Library
                </p>
                <h1 class="mt-1 font-display text-3xl font-bold tracking-tight text-foreground">
                    Playlists
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Cached from Spotify and organized locally with folders.
                </p>
            </div>

            <div class="flex w-full max-w-2xl items-center justify-end gap-2">
                <span
                    v-if="currentSyncStatus.isRunning"
                    class="rounded-md border border-accent/40 bg-accent/10 px-2 py-1 text-[11px] font-medium text-accent"
                >
                    Syncing {{ currentSyncStatus.completed }}/{{ currentSyncStatus.total }} · {{ currentSyncStatus.progress }}%
                </span>
                <span
                    v-else-if="currentSyncStatus.hasFailure"
                    class="rounded-md border border-destructive/40 bg-destructive/10 px-2 py-1 text-[11px] font-medium text-destructive"
                >
                    Library sync failed
                </span>

                <Form
                    :action="refreshLibrary().url"
                    method="post"
                    class="shrink-0"
                    v-slot="{ processing }"
                >
                    <Button type="submit" variant="outline" :disabled="processing || currentSyncStatus.isRunning">
                        {{ processing ? 'Refreshing…' : 'Refresh playlists' }}
                    </Button>
                </Form>

                <Button
                    type="button"
                    variant="outline"
                    :disabled="hiddenCount === 0"
                    @click="toggleShowHidden"
                >
                    <Check v-if="includeHidden" class="mr-1.5 size-4" />
                    {{ includeHidden ? 'Hide hidden playlists' : `Show hidden playlists (${hiddenCount})` }}
                </Button>

                <Form
                    :action="storeFolder().url"
                    method="post"
                    class="flex w-full max-w-sm items-center gap-2"
                    v-slot="{ processing }"
                >
                    <Input name="name" placeholder="New folder" autocomplete="off" />
                    <Button type="submit" :disabled="processing">Create folder</Button>
                </Form>
            </div>
        </section>

        <div class="flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
            <div
                class="rounded-md border border-transparent px-1.5 py-0.5 transition-colors"
                :class="dragOverFolderId === null && activeFolderId !== null ? 'border-accent/50 bg-accent/10' : ''"
                @dragover="activeFolderId !== null ? onFolderDragOver($event, null) : undefined"
                @dragleave="activeFolderId !== null ? onFolderDragLeave(null) : undefined"
                @drop="activeFolderId !== null ? onFolderDrop($event, null) : undefined"
            >
                <button
                    type="button"
                    class="inline-flex items-center gap-1 transition-colors hover:text-accent"
                    @click="openFolder(null)"
                >
                    <Home class="size-3" />
                    Library
                </button>
            </div>

            <template v-for="folder in activeFolderPath" :key="folder.id">
                <ChevronRight class="size-3" />
                <button
                    type="button"
                    class="transition-colors hover:text-accent"
                    @click="openFolder(folder.id)"
                >
                    {{ folder.name }}
                </button>
            </template>
        </div>

        <section>
            <div class="mb-3 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                Folders
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <div
                    v-if="likedPlaylist && activeFolderId === null"
                    class="group relative"
                >
                    <Link
                        :href="libraryShow(likedPlaylist.id).url"
                        class="block w-full rounded-2xl border border-border bg-card p-5 text-left transition-all duration-200 hover:border-accent/40 hover:shadow-accent"
                    >
                        <div class="mb-6 flex items-start justify-between">
                            <div class="grid h-11 w-11 place-items-center rounded-xl bg-accent/10 transition-colors group-hover:bg-accent/20">
                                <Heart class="size-5 text-accent" fill="currentColor" />
                            </div>
                            <Form
                                :action="syncLikedSongs().url"
                                method="post"
                                v-slot="{ processing }"
                                @click.stop
                            >
                                <button
                                    type="submit"
                                    class="rounded-md p-1 text-muted-foreground/40 opacity-0 transition-opacity hover:text-accent group-hover:opacity-100"
                                    :disabled="processing || likedPlaylist.syncStatus.isRunning"
                                    :title="likedPlaylist.syncStatus.isRunning ? 'Syncing…' : 'Sync liked songs'"
                                    @click.prevent.stop="($event.currentTarget as HTMLButtonElement).closest('form')?.requestSubmit()"
                                >
                                    <RefreshCw class="size-4" :class="(processing || likedPlaylist.syncStatus.isRunning) ? 'animate-spin' : ''" />
                                </button>
                            </Form>
                        </div>

                        <div class="truncate font-display text-base font-bold text-foreground">
                            {{ likedPlaylist.name }}
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            {{ likedPlaylist.tracks_total }} tracks
                        </div>
                    </Link>
                </div>

                <div
                    v-for="folder in childFolders"
                    :key="folder.id"
                    class="group relative"
                    @dragover="onFolderDragOver($event, folder.id)"
                    @dragleave="onFolderDragLeave(folder.id)"
                    @drop="onFolderDrop($event, folder.id)"
                    @contextmenu="onFolderContextMenu($event, folder)"
                >
                    <div
                        v-if="dragOverFolderId === folder.id"
                        class="pointer-events-none absolute inset-0 z-20 rounded-2xl border-2 border-accent/60"
                    />
                    <button
                        type="button"
                        class="w-full rounded-2xl border border-border bg-card p-5 text-left transition-all duration-200"
                        :class="
                            dragOverFolderId === folder.id
                                ? 'border-accent/60 bg-accent/10 shadow-accent'
                                : 'hover:border-accent/40 hover:shadow-accent'
                        "
                        @click="openFolder(folder.id)"
                    >
                        <div class="mb-6 flex items-start justify-between">
                            <div class="grid h-11 w-11 place-items-center rounded-xl bg-accent/10 transition-colors group-hover:bg-accent/20">
                                <Folder class="size-5 text-accent" />
                            </div>
                            <FolderOpen class="size-4 text-muted-foreground/40 opacity-0 transition-opacity group-hover:opacity-100" />
                        </div>

                        <div class="truncate font-display text-base font-bold text-foreground">
                            {{ folder.name }}
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            {{ folders.filter((item) => item.parent_id === folder.id).length }} folders
                        </div>
                    </button>
                </div>
            </div>
        </section>

        <section>
            <div class="mb-3 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                Playlists
            </div>

            <div v-if="visiblePlaylists.length === 0" class="rounded-2xl border border-dashed border-border p-12 text-center">
                <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-secondary">
                    <ListMusic class="size-6 text-muted-foreground" />
                </div>
                <h3 class="font-display text-lg font-bold">Nothing here yet</h3>
                <p class="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                    Sync your Spotify playlists and organize them into folders.
                </p>
            </div>

            <TransitionGroup
                v-else
                name="playlist-grid"
                tag="div"
                class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4"
                @dragover="onPlaylistDragDropZone"
            >
                <div
                    v-for="playlist in visiblePlaylists"
                    :key="playlist.id"
                    class="group relative"
                    draggable="true"
                    @dragstart="onPlaylistDragStart($event, playlist.id)"
                    @dragend="onPlaylistDragEnd"
                    @dragover="onPlaylistDragOver($event, playlist.id)"
                    @dragenter="onPlaylistDragEnter($event, playlist.id)"
                    @dragleave="onPlaylistDragLeave(playlist.id)"
                    @drop="onPlaylistDrop($event, playlist.id)"
                >
                    <Link
                        :href="libraryShow(playlist.id).url"
                        class="block overflow-hidden rounded-2xl border border-border bg-card transition-all duration-200 hover:border-accent/40 hover:shadow-accent"
                        :class="[
                            draggedPlaylistId === playlist.id ? 'scale-[0.98] opacity-70' : 'opacity-100',
                            dragOverPlaylistId === playlist.id && draggedPlaylistId !== playlist.id
                                ? 'ring-2 ring-accent/50'
                                : '',
                        ]"
                        @click="onPlaylistCardClick"
                        @contextmenu="onPlaylistContextMenu($event, playlist)"
                    >
                        <img
                            v-if="playlist.image"
                            :src="playlist.image"
                            :alt="playlist.name"
                            class="aspect-square w-full object-cover"
                        />
                        <div v-else class="aspect-square w-full bg-muted" />

                        <div class="p-3">
                            <div class="truncate text-sm font-medium text-foreground">
                                {{ playlist.name }}
                            </div>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ playlist.tracks_total }} tracks
                                <span v-if="playlist.owner_name">· {{ playlist.owner_name }}</span>
                            </p>
                        </div>
                    </Link>
                </div>
            </TransitionGroup>

            <TransitionGroup
                v-if="includeHidden && hiddenPlaylists.length > 0"
                name="playlist-grid"
                tag="div"
                class="mt-8"
            >
                <div key="hidden-title" class="mb-3 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                    Hidden playlists
                </div>

                <div key="hidden-grid" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    <div
                        v-for="playlist in hiddenPlaylists"
                        :key="`hidden-${playlist.id}`"
                        class="group relative"
                    >
                        <Link
                            :href="libraryShow(playlist.id).url"
                            class="block overflow-hidden rounded-2xl border border-border/70 bg-card/60 opacity-85 transition-all duration-200 hover:border-accent/40 hover:opacity-100 hover:shadow-accent"
                            @contextmenu="onPlaylistContextMenu($event, playlist)"
                        >
                            <img
                                v-if="playlist.image"
                                :src="playlist.image"
                                :alt="playlist.name"
                                class="aspect-square w-full object-cover"
                            />
                            <div v-else class="aspect-square w-full bg-muted" />

                            <div class="p-3">
                                <div class="truncate text-sm font-medium text-foreground">
                                    {{ playlist.name }}
                                </div>
                                <p class="truncate text-xs text-muted-foreground">
                                    {{ playlist.tracks_total }} tracks
                                    <span v-if="playlist.owner_name">· {{ playlist.owner_name }}</span>
                                </p>
                            </div>
                        </Link>
                    </div>
                </div>
            </TransitionGroup>
        </section>
    </div>

</template>

<style scoped>
.playlist-grid-move {
    transition: transform 220ms cubic-bezier(0.22, 1, 0.36, 1);
}

.playlist-grid-enter-active,
.playlist-grid-leave-active {
    transition: all 180ms ease;
}

.playlist-grid-enter-from,
.playlist-grid-leave-to {
    opacity: 0;
    transform: scale(0.98);
}
</style>
