<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Folder, ListMusic } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as libraryIndex } from '@/routes/library';
import { store as storeFolder } from '@/routes/library/folders';

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

defineProps<{
    folders: Array<{
        id: number;
        name: string;
        parent_id: number | null;
        position: number;
    }>;
    rootPlaylists: Array<{
        id: string;
        name: string;
        description: string | null;
        image: string | null;
        tracks_total: number;
        owner_name: string | null;
        synced_at: string | null;
    }>;
}>();
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

            <Form
                :action="storeFolder().url"
                method="post"
                class="flex w-full max-w-sm items-center gap-2"
                v-slot="{ processing }"
            >
                <Input name="name" placeholder="New folder" autocomplete="off" />
                <Button type="submit" :disabled="processing">Create folder</Button>
            </Form>
        </section>

        <section class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-2xl border border-border/60 bg-card/70 p-4">
                <div class="mb-3 flex items-center gap-2">
                    <Folder class="size-4 text-accent" />
                    <h2 class="text-sm font-semibold text-foreground">Folders</h2>
                </div>

                <div v-if="folders.length === 0" class="text-sm text-muted-foreground">
                    No folders yet.
                </div>

                <ul v-else class="space-y-2">
                    <li
                        v-for="folder in folders"
                        :key="folder.id"
                        class="rounded-lg border border-border/50 px-3 py-2 text-sm text-foreground"
                    >
                        {{ folder.name }}
                    </li>
                </ul>
            </div>

            <div class="rounded-2xl border border-border/60 bg-card/70 p-4 lg:col-span-2">
                <div class="mb-3 flex items-center gap-2">
                    <ListMusic class="size-4 text-accent" />
                    <h2 class="text-sm font-semibold text-foreground">Root playlists</h2>
                </div>

                <div v-if="rootPlaylists.length === 0" class="text-sm text-muted-foreground">
                    No cached playlists yet.
                </div>

                <ul v-else class="space-y-2">
                    <li
                        v-for="playlist in rootPlaylists"
                        :key="playlist.id"
                        class="flex items-center gap-3 rounded-lg border border-border/50 px-3 py-2"
                    >
                        <img
                            v-if="playlist.image"
                            :src="playlist.image"
                            :alt="playlist.name"
                            class="size-10 rounded object-cover"
                        />
                        <div v-else class="size-10 rounded bg-muted" />

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-foreground">{{ playlist.name }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ playlist.tracks_total }} tracks
                                <span v-if="playlist.owner_name">· {{ playlist.owner_name }}</span>
                            </p>
                        </div>
                    </li>
                </ul>
            </div>
        </section>
    </div>
</template>
