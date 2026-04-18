<script setup lang="ts">
import { Deferred, Head, Link } from '@inertiajs/vue3';
import { Skeleton } from '@/components/ui/skeleton';
import IconPause from '@/components/icons/IconPause.vue';
import IconPlay from '@/components/icons/IconPlay.vue';
import { usePlayer } from '@/composables/usePlayer';
import { show as artistShow } from '@/routes/artists';
import { dashboard, recentlyPlayed } from '@/routes';
import type { SpotifyTrack } from '@/types/spotify';

defineOptions({
    inheritAttrs: false,
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Recently Played', href: recentlyPlayed() },
        ],
    },
});

const props = defineProps<{
    playGroups?: PlayGroup[];
}>();

const SKELETON_COUNT = 10;

const { isPlayingTrack, playTrack } = usePlayer();

async function handlePlay(track: SpotifyTrack) {
    await playTrack(`spotify:track:${track.id}`);
}

function formatTime(isoString: string): string {
    const date = new Date(isoString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;

    return date.toLocaleTimeString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
    });
}

interface TrackEntry {
    track: SpotifyTrack;
    lastPlayedAt: string;
    count: number;
    rank: number;
}

interface PlayGroup {
    label: string;
    entries: TrackEntry[];
}

function albumImageUrl(track: SpotifyTrack): string | null {
    return track.album?.images?.[0]?.url ?? null;
}
</script>

<template>
    <Head title="Recently Played" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">Recently Played</h1>
            <p class="text-sm text-muted-foreground">
                Your full listening history
            </p>
        </div>

        <Deferred data="playGroups">
            <template #fallback>
                <div class="rounded-xl border border-border bg-card shadow-sm">
                    <div class="border-b border-border px-4 py-2.5">
                        <Skeleton class="h-3 w-12" />
                    </div>
                    <div class="p-2">
                        <div
                            v-for="n in SKELETON_COUNT"
                            :key="n"
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5"
                        >
                            <Skeleton class="size-5 shrink-0 rounded" />
                            <Skeleton class="size-11 shrink-0 rounded-lg" />
                            <div class="flex flex-1 flex-col gap-1.5">
                                <Skeleton class="h-3.5 w-36" />
                                <Skeleton class="h-3 w-20" />
                            </div>
                            <Skeleton class="h-3 w-8" />
                        </div>
                    </div>
                </div>
            </template>

            <template #default>
                <div
                    v-if="playGroups!.length === 0"
                    class="py-16 text-center text-sm text-muted-foreground"
                >
                    No listening history found.
                </div>

                <div v-else class="flex flex-col gap-4">
                    <section
                        v-for="group in playGroups"
                        :key="group.label"
                        class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
                    >
                        <!-- Day header -->
                        <div class="border-b border-border px-4 py-2.5">
                            <h2
                                class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                            >
                                {{ group.label }}
                            </h2>
                        </div>

                        <!-- Track list -->
                        <div class="p-2">
                            <div
                                v-for="entry in group.entries"
                                :key="entry.track.id"
                                class="group flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors hover:bg-accent/40"
                            >
                                <!-- Play/pause button -->
                                <button
                                    class="relative flex size-5 shrink-0 items-center justify-center"
                                    :aria-label="
                                        isPlayingTrack(entry.track.id)
                                            ? 'Pause'
                                            : 'Play'
                                    "
                                    @click="handlePlay(entry.track)"
                                >
                                    <span
                                        class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-muted-foreground transition-opacity duration-150"
                                        :class="
                                            isPlayingTrack(entry.track.id)
                                                ? 'opacity-0'
                                                : 'group-hover:opacity-0'
                                        "
                                        aria-hidden="true"
                                    >
                                        {{ entry.rank }}
                                    </span>
                                    <IconPlay
                                        v-if="!isPlayingTrack(entry.track.id)"
                                        class="absolute inset-0 m-auto size-4 text-foreground opacity-0 transition-opacity duration-150 group-hover:opacity-100"
                                        aria-hidden="true"
                                    />
                                    <IconPause
                                        v-else
                                        class="absolute inset-0 m-auto size-4 text-green-500 opacity-100"
                                        aria-hidden="true"
                                    />
                                </button>

                                <!-- Album art -->
                                <img
                                    v-if="albumImageUrl(entry.track)"
                                    :src="albumImageUrl(entry.track)!"
                                    :alt="entry.track.album.name"
                                    class="size-11 shrink-0 rounded-lg object-cover"
                                />
                                <div
                                    v-else
                                    class="size-11 shrink-0 rounded-lg bg-muted"
                                />

                                <!-- Track info + time -->
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate text-sm leading-tight font-semibold transition-colors duration-150"
                                        :class="
                                            isPlayingTrack(entry.track.id)
                                                ? 'text-green-500'
                                                : 'text-foreground'
                                        "
                                    >
                                        {{ entry.track.name }}
                                    </p>
                                    <p
                                        class="mt-0.5 truncate text-xs text-muted-foreground"
                                    >
                                        <template
                                            v-for="(
                                                artist, artistIndex
                                            ) in entry.track.artists"
                                            :key="artist.id"
                                        >
                                            <Link
                                                :href="
                                                    artistShow(artist.id).url
                                                "
                                                class="hover:text-foreground"
                                            >
                                                {{ artist.name }}
                                            </Link>
                                            <span
                                                v-if="
                                                    artistIndex <
                                                    entry.track.artists.length -
                                                        1
                                                "
                                                >,
                                            </span>
                                        </template>
                                        <span class="mx-1 opacity-40">·</span>
                                        {{ formatTime(entry.lastPlayedAt) }}
                                    </p>
                                </div>

                                <!-- Play count dot -->
                                <span
                                    v-if="entry.count > 1"
                                    class="flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-[10px] font-medium text-muted-foreground tabular-nums"
                                    :title="`Played ${entry.count} times`"
                                >
                                    {{ entry.count }}
                                </span>
                            </div>
                        </div>
                    </section>
                </div>
            </template>
        </Deferred>
    </div>
</template>
