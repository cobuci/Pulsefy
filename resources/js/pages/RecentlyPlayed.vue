<script setup lang="ts">
import { Deferred, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import IconPause from '@/components/icons/IconPause.vue';
import IconPlay from '@/components/icons/IconPlay.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { usePlayer } from '@/composables/usePlayer';
import { useTrackContextMenu } from '@/composables/useTrackContextMenu';
import { dashboard, recentlyPlayed } from '@/routes';
import { show as artistShow } from '@/routes/artists';
import type { SpotifyTrack } from '@/types/spotify';
import { formatDuration } from '@/utils/format';

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

const { isPlayingTrack, playTrack, pausePlayback } = usePlayer();
const { openTrackContextMenu } = useTrackContextMenu();

async function handlePlay(track: SpotifyTrack) {
    if (isPlayingTrack.value(track.id)) {
        await pausePlayback();

        return;
    }

    await playTrack(`spotify:track:${track.id}`);
}

function formatTime(isoString: string): string {
    const date = new Date(isoString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);

    if (diffMins < 1) {
        return 'Just now';
    }

    if (diffMins < 60) {
        return `${diffMins}m ago`;
    }

    if (diffHours < 24) {
        return `${diffHours}h ago`;
    }

    return date.toLocaleTimeString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
    });
}

interface TrackEntry {
    track: SpotifyTrack;
    lastPlayedAt: string;
    count: number;
}

interface PlayGroup {
    label: string;
    entries: TrackEntry[];
}

function albumImageUrl(track: SpotifyTrack): string | null {
    return track.album?.images?.[0]?.url ?? null;
}

const totalEntries = computed(() => {
    return (props.playGroups ?? []).reduce(
        (carry, group) => carry + group.entries.length,
        0,
    );
});
</script>

<template>
    <Head title="Recently Played" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-6 py-8">
        <div class="mb-2">
            <p
                class="text-xs font-semibold tracking-[0.2em] text-accent uppercase"
            >
                Library
            </p>
            <h1 class="mt-2 font-display text-4xl font-bold">
                Recently Played
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Your full listening history · {{ totalEntries }} plays tracked
            </p>
        </div>

        <Deferred data="playGroups">
            <template #fallback>
                <div
                    class="rounded-2xl border border-border bg-card p-4 shadow-card"
                >
                    <Skeleton class="mb-3 h-3 w-20" />
                    <div class="space-y-1">
                        <div
                            v-for="n in SKELETON_COUNT"
                            :key="n"
                            class="flex items-center gap-3 rounded-lg p-2"
                        >
                            <Skeleton class="h-4 w-6 shrink-0" />
                            <Skeleton class="size-10 shrink-0 rounded-md" />
                            <div class="flex flex-1 flex-col gap-1.5">
                                <Skeleton class="h-3.5 w-36" />
                                <Skeleton class="h-3 w-20" />
                            </div>
                            <Skeleton class="h-3 w-12" />
                        </div>
                    </div>
                </div>
            </template>

            <template #default>
                <div
                    v-if="playGroups!.length === 0"
                    class="rounded-2xl border border-border bg-card py-16 text-center text-sm text-muted-foreground"
                >
                    No listening history found.
                </div>

                <div v-else class="flex flex-col gap-4">
                    <section
                        v-for="group in playGroups"
                        :key="group.label"
                        class="overflow-hidden rounded-2xl border border-border bg-card shadow-card"
                    >
                        <div class="border-b border-border px-4 py-2.5">
                            <h2
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >
                                {{ group.label }}
                            </h2>
                        </div>

                        <div class="p-2">
                            <div
                                v-for="entry in group.entries"
                                :key="`${entry.track.id}-${entry.lastPlayedAt}`"
                                class="group flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 transition-colors hover:bg-secondary/60"
                                @contextmenu="openTrackContextMenu($event, entry.track)"
                            >
                                <div class="relative flex size-5 w-6 shrink-0 items-center justify-center">
                                    <span
                                        class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-muted-foreground transition-opacity duration-150"
                                        :class="isPlayingTrack(entry.track.id) ? 'opacity-0' : 'opacity-100 group-hover:opacity-0'"
                                    >
                                        •
                                    </span>

                                    <button
                                        v-if="!isPlayingTrack(entry.track.id)"
                                        type="button"
                                        class="absolute inset-0 m-auto size-3.5 text-foreground opacity-0 transition-opacity duration-150 group-hover:opacity-100"
                                        @click="handlePlay(entry.track)"
                                    >
                                        <IconPlay class="size-3.5" />
                                    </button>

                                    <div
                                        v-else
                                        class="absolute inset-0 m-auto flex items-end justify-center gap-0.5"
                                    >
                                        <span class="eq-bar h-3 w-0.5 rounded-full bg-accent transition-opacity duration-150 group-hover:opacity-0" />
                                        <span class="eq-bar h-3 w-0.5 rounded-full bg-accent transition-opacity duration-150 group-hover:opacity-0" style="animation-delay: 0.15s" />
                                        <span class="eq-bar h-3 w-0.5 rounded-full bg-accent transition-opacity duration-150 group-hover:opacity-0" style="animation-delay: 0.3s" />
                                        <button
                                            type="button"
                                            class="absolute inset-0 m-auto opacity-0 transition-opacity duration-150 group-hover:opacity-100"
                                            @click="handlePlay(entry.track)"
                                        >
                                            <IconPause class="size-3.5 text-accent" />
                                        </button>
                                    </div>
                                </div>

                                <img
                                    v-if="albumImageUrl(entry.track)"
                                    :src="albumImageUrl(entry.track)!"
                                    :alt="entry.track.album.name"
                                    class="size-10 shrink-0 rounded-md object-cover"
                                />
                                <div
                                    v-else
                                    class="size-10 shrink-0 rounded-md bg-muted"
                                />

                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate text-sm font-medium"
                                        :class="isPlayingTrack(entry.track.id) ? 'text-accent' : 'text-foreground'"
                                    >
                                        {{ entry.track.name }}
                                    </p>
                                    <div
                                        v-if="isPlayingTrack(entry.track.id)"
                                        class="mt-1 h-0.5 w-20 overflow-hidden rounded-full bg-accent/25"
                                    >
                                        <div class="bg-gradient-primary h-full w-full animate-pulse" />
                                    </div>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
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
                                                class="cursor-pointer hover:text-foreground"
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

                                <span
                                    v-if="entry.count > 1"
                                    class="hidden text-xs text-muted-foreground tabular-nums sm:block"
                                    :title="`Played ${entry.count} times`"
                                >
                                    {{ entry.count }} plays
                                </span>

                                <span
                                    class="w-12 shrink-0 text-right text-xs text-muted-foreground tabular-nums"
                                >
                                    {{
                                        formatDuration(entry.track.duration_ms)
                                    }}
                                </span>
                            </div>
                        </div>
                    </section>
                </div>
            </template>
        </Deferred>
    </div>
</template>
