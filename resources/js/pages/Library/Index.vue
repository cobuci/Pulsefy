<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, Folder, FolderOpen, Home, ListMusic } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as libraryIndex, move as movePlaylist, show as libraryShow } from '@/routes/library';
import { store as storeFolder } from '@/routes/library/folders';
import { refresh as refreshLibrary } from '@/routes/library';

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
};

const props = defineProps<{
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
    }>;
}>();

const activeFolderId = ref<number | null>(null);
const dragOverFolderId = ref<number | null>(null);

const folderById = computed(() => {
    return new Map(props.folders.map((folder) => [folder.id, folder]));
});

const childFolders = computed<LibraryFolderItem[]>(() => {
    return props.folders.filter((folder) => folder.parent_id === activeFolderId.value);
});

const visiblePlaylists = computed<LibraryPlaylistItem[]>(() => {
    return props.playlists.filter(
        (playlist) => (playlist.folder_id ?? null) === activeFolderId.value,
    );
});

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

function onPlaylistDragStart(event: DragEvent, playlistId: string) {
    event.dataTransfer?.setData('text/plain', playlistId);
    event.dataTransfer?.setData('application/x-pulsefy-playlist', playlistId);
    event.dataTransfer?.setDragImage(new Image(), 0, 0);
}

function onFolderDragOver(event: DragEvent, folderId: number | null) {
    event.preventDefault();
    dragOverFolderId.value = folderId;
}

function onFolderDragLeave(folderId: number | null) {
    if (dragOverFolderId.value === folderId) {
        dragOverFolderId.value = null;
    }
}

function onFolderDrop(event: DragEvent, folderId: number | null) {
    event.preventDefault();
    dragOverFolderId.value = null;

    const playlistId =
        event.dataTransfer?.getData('application/x-pulsefy-playlist') ||
        event.dataTransfer?.getData('text/plain');

    if (!playlistId) {
        return;
    }

    router.patch(
        movePlaylist(playlistId).url,
        {
            folder_id: folderId,
        },
        {
            preserveScroll: true,
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
                <Form
                    :action="refreshLibrary().url"
                    method="post"
                    class="shrink-0"
                    v-slot="{ processing }"
                >
                    <Button type="submit" variant="outline" :disabled="processing">
                        {{ processing ? 'Refreshing…' : 'Refresh playlists' }}
                    </Button>
                </Form>

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
            <button
                type="button"
                class="inline-flex items-center gap-1 transition-colors hover:text-accent"
                @click="openFolder(null)"
            >
                <Home class="size-3" />
                Library
            </button>

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

            <div
                class="mb-6 rounded-xl border border-dashed border-border/60 p-3 transition-colors"
                :class="dragOverFolderId === null ? 'border-accent/50 bg-accent/5' : ''"
                @dragover="onFolderDragOver($event, null)"
                @dragleave="onFolderDragLeave(null)"
                @drop="onFolderDrop($event, null)"
            >
                <button
                    type="button"
                    class="text-xs text-muted-foreground transition-colors hover:text-foreground"
                    @click="openFolder(null)"
                >
                    Drop here to move playlist to root
                </button>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <div
                    v-for="folder in childFolders"
                    :key="folder.id"
                    class="group relative"
                    @dragover="onFolderDragOver($event, folder.id)"
                    @dragleave="onFolderDragLeave(folder.id)"
                    @drop="onFolderDrop($event, folder.id)"
                >
                    <button
                        type="button"
                        class="w-full rounded-2xl border border-border bg-card p-5 text-left transition-all"
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

            <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <div
                    v-for="playlist in visiblePlaylists"
                    :key="playlist.id"
                    class="group relative"
                    draggable="true"
                    @dragstart="onPlaylistDragStart($event, playlist.id)"
                >
                    <Link
                        :href="libraryShow(playlist.id).url"
                        class="block overflow-hidden rounded-2xl border border-border bg-card transition-all hover:border-accent/40 hover:shadow-accent"
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
        </section>
    </div>
</template>
