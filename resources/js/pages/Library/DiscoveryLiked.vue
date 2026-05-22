<script setup lang="ts">
import { Head, InfiniteScroll, setLayoutProps } from '@inertiajs/vue3';
import { Compass, Play } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import IconPause from '@/components/icons/IconPause.vue';
import { Button } from '@/components/ui/button';
import { usePlayer } from '@/composables/usePlayer';
import { discoveryLiked as discoveryLikedRoute, index as libraryIndex } from '@/routes/library';

interface LikedTrack {
    id: number;
    spotify_id: string;
    uri: string;
    name: string;
    artist_name: string;
    duration_ms: number;
    image_url: string | null;
    liked_at: string;
}

const props = defineProps<{
    total: number;
    likedTracks: { data: LikedTrack[] };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Library', href: libraryIndex() },
        { title: 'Liked from Discovery', href: discoveryLikedRoute() },
    ],
});

const { playTrack, isPlayingTrack, pausePlayback } = usePlayer();

const showStickyBar = ref(false);
const stickyBarTop = ref(0);
const headerSectionRef = ref<HTMLElement | null>(null);
let scrollContainer: HTMLElement | null = null;

function onScroll(): void {
    if (!scrollContainer || !headerSectionRef.value) {
        return;
    }

    const sectionBottom = headerSectionRef.value.getBoundingClientRect().bottom;
    const containerTop = scrollContainer.getBoundingClientRect().top;

    showStickyBar.value = sectionBottom < containerTop;
}

onMounted(() => {
    scrollContainer = document.getElementById('app-content');

    const appHeader = document.querySelector<HTMLElement>('header');

    if (appHeader) {
        setTimeout(() => {
            stickyBarTop.value = appHeader.getBoundingClientRect().bottom;
        }, 0);
    }

    if (scrollContainer) {
        scrollContainer.addEventListener('scroll', onScroll, { passive: true });
    }
});

onUnmounted(() => {
    if (scrollContainer) {
        scrollContainer.removeEventListener('scroll', onScroll);
        scrollContainer = null;
    }
});

const playableUris = computed(() =>
    props.likedTracks.data.map((t) => t.uri).filter(Boolean),
);

async function playAll(): Promise<void> {
    if (playableUris.value.length === 0) {
        return;
    }

    await playTrack(playableUris.value[0], {
        uris: playableUris.value,
        offsetPosition: 0,
    });
}

async function onRowClick(track: LikedTrack, index: number): Promise<void> {
    if (isPlayingTrack.value(track.spotify_id)) {
        await pausePlayback();

        return;
    }

    await playTrack(track.uri, {
        uris: playableUris.value,
        offsetPosition: index,
    });
}

function formatDuration(ms: number): string {
    const minutes = Math.floor(ms / 60000);
    const seconds = String(Math.floor((ms % 60000) / 1000)).padStart(2, '0');

    return `${minutes}:${seconds}`;
}
</script>

<template>
    <Head title="Liked from Discovery" />

    <div class="flex flex-col gap-8 py-6 pb-4">
        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="-translate-y-full opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="-translate-y-full opacity-0"
        >
            <div
                v-if="showStickyBar"
                class="glass fixed right-0 left-0 z-40 border-b border-border/60 bg-card/90 px-6 py-3"
                :style="{ top: `${stickyBarTop}px` }"
            >
                <div class="mx-auto flex max-w-7xl items-center gap-4">
                    <Button
                        type="button"
                        size="sm"
                        :disabled="playableUris.length === 0"
                        @click="playAll"
                    >
                        <Play class="mr-1 size-3.5" />
                        Play
                    </Button>
                    <span class="truncate font-display text-sm font-bold text-foreground">
                        Liked from Discovery
                    </span>
                </div>
            </div>
        </Transition>

        <section ref="headerSectionRef" class="flex flex-wrap gap-4 rounded-2xl border border-border/60 bg-card/70 p-5">
            <div class="grid size-28 shrink-0 place-items-center rounded-xl bg-accent/10">
                <Compass class="size-12 text-accent" />
            </div>

            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    Library
                </p>
                <h1 class="mt-1 truncate font-display text-3xl font-bold text-foreground">
                    Liked from Discovery
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Tracks you saved while browsing Discovery.
                </p>

                <div class="mt-3 text-xs text-muted-foreground">
                    {{ total }} tracks
                </div>

                <div class="mt-4">
                    <Button
                        type="button"
                        :disabled="playableUris.length === 0"
                        @click="playAll"
                    >
                        <Play class="mr-1 size-3.5" />
                        Play all
                    </Button>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-border/60 bg-card/70 p-4">
            <div v-if="likedTracks.data.length === 0" class="py-12 text-center text-sm text-muted-foreground">
                No liked tracks yet. Head to Discovery and like some tracks.
            </div>

            <InfiniteScroll v-else data="likedTracks" only-next>
                <ol class="space-y-1">
                    <li
                        v-for="(track, index) in likedTracks.data"
                        :key="track.id"
                        class="group flex cursor-pointer items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-secondary/60"
                        @click="onRowClick(track, index)"
                    >
                        <button
                            type="button"
                            class="relative flex size-5 shrink-0 items-center justify-center"
                            @click.stop="onRowClick(track, index)"
                        >
                            <span
                                class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-muted-foreground transition-opacity duration-150"
                                :class="isPlayingTrack(track.spotify_id) ? 'opacity-0' : 'opacity-100 group-hover:opacity-0'"
                            >
                                {{ index + 1 }}
                            </span>

                            <Play
                                v-if="!isPlayingTrack(track.spotify_id)"
                                class="absolute inset-0 m-auto size-4 text-foreground opacity-0 transition-opacity duration-150 group-hover:opacity-100"
                            />
                            <div
                                v-else
                                class="absolute inset-0 m-auto flex items-end justify-center gap-0.5"
                            >
                                <span class="eq-bar h-3 w-0.5 rounded-full bg-accent transition-opacity duration-150 group-hover:opacity-0" />
                                <span class="eq-bar h-3 w-0.5 rounded-full bg-accent transition-opacity duration-150 group-hover:opacity-0" style="animation-delay: 0.15s" />
                                <span class="eq-bar h-3 w-0.5 rounded-full bg-accent transition-opacity duration-150 group-hover:opacity-0" style="animation-delay: 0.3s" />
                                <IconPause class="absolute inset-0 m-auto size-4 text-accent opacity-0 transition-opacity duration-150 group-hover:opacity-100" />
                            </div>
                        </button>

                        <img
                            v-if="track.image_url"
                            :src="track.image_url"
                            :alt="track.name"
                            class="size-10 rounded object-cover"
                        />
                        <div v-else class="size-10 rounded bg-muted" />

                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-medium"
                                :class="isPlayingTrack(track.spotify_id) ? 'text-accent' : 'text-foreground'"
                            >
                                {{ track.name }}
                            </p>
                            <div
                                v-if="isPlayingTrack(track.spotify_id)"
                                class="mt-1 h-0.5 w-20 overflow-hidden rounded-full bg-accent/25"
                            >
                                <div class="bg-gradient-primary h-full w-full animate-pulse" />
                            </div>
                            <p
                                v-if="track.artist_name"
                                class="truncate text-[11px] text-muted-foreground"
                            >
                                {{ track.artist_name }}
                            </p>
                        </div>

                        <span class="ml-auto shrink-0 text-xs text-muted-foreground tabular-nums">
                            {{ formatDuration(track.duration_ms) }}
                        </span>
                    </li>
                </ol>
            </InfiniteScroll>
        </section>
    </div>
</template>
