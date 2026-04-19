<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowLeft, Clock3 } from 'lucide-vue-next';
import { index as libraryIndex } from '@/routes/library';

const props = defineProps<{
    playlist: {
        id: string;
        name: string;
        description: string | null;
        image: string | null;
        tracks_total: number;
        owner_name: string | null;
        synced_at: string | null;
        items: Array<{
            spotify_track_id: string;
            position: number;
            added_at: string | null;
            track: {
                id: string;
                name: string;
                duration_ms: number;
                image: string | null;
            } | null;
        }>;
    };
}>();

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Library',
            href: libraryIndex(),
        },
        {
            title: props.playlist.name,
            href: libraryIndex(),
        },
    ],
});
</script>

<template>
    <Head :title="playlist.name" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-8 py-4">
        <div class="flex items-center">
            <Link
                :href="libraryIndex()"
                class="inline-flex items-center gap-2 text-xs text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft class="size-3.5" />
                Back to Library
            </Link>
        </div>

        <section class="flex flex-wrap gap-4 rounded-2xl border border-border/60 bg-card/70 p-5">
            <img
                v-if="playlist.image"
                :src="playlist.image"
                :alt="playlist.name"
                class="size-28 rounded-xl object-cover"
            />
            <div v-else class="size-28 rounded-xl bg-muted" />

            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    Playlist
                </p>
                <h1 class="mt-1 truncate font-display text-3xl font-bold text-foreground">
                    {{ playlist.name }}
                </h1>
                <p v-if="playlist.description" class="mt-2 text-sm text-muted-foreground">
                    {{ playlist.description }}
                </p>

                <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                    <span>{{ playlist.tracks_total }} tracks</span>
                    <span v-if="playlist.owner_name">· {{ playlist.owner_name }}</span>
                    <span v-if="playlist.synced_at" class="inline-flex items-center gap-1">
                        <Clock3 class="size-3" />
                        Synced {{ new Date(playlist.synced_at).toLocaleString() }}
                    </span>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-border/60 bg-card/70 p-4">
            <h2 class="mb-3 text-sm font-semibold text-foreground">Cached tracks</h2>

            <div v-if="playlist.items.length === 0" class="text-sm text-muted-foreground">
                No cached tracks yet.
            </div>

            <ol v-else class="space-y-1">
                <li
                    v-for="item in playlist.items"
                    :key="`${item.spotify_track_id}-${item.position}`"
                    class="grid grid-cols-[40px_48px_1fr] items-center gap-2 rounded-md px-2 py-1 text-sm text-foreground/90"
                >
                    <span class="text-xs text-muted-foreground">{{ item.position + 1 }}</span>
                    <img
                        v-if="item.track?.image"
                        :src="item.track.image"
                        :alt="item.track.name"
                        class="size-10 rounded object-cover"
                    />
                    <div v-else class="size-10 rounded bg-muted" />

                    <div class="min-w-0">
                        <p class="truncate">{{ item.track?.name ?? item.spotify_track_id }}</p>
                        <p
                            v-if="item.track"
                            class="truncate text-[11px] text-muted-foreground tabular-nums"
                        >
                            {{ Math.floor(item.track.duration_ms / 60000) }}:{
                                String(Math.floor((item.track.duration_ms % 60000) / 1000)).padStart(2, '0')
                            }
                        </p>
                    </div>
                </li>
            </ol>
        </section>
    </div>
</template>
