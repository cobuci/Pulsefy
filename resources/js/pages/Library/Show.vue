<script setup lang="ts">
import { Form, Head, Link, InfiniteScroll, router, setLayoutProps, usePage } from '@inertiajs/vue3';
import { Clock3, Heart, Play } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import IconPause from '@/components/icons/IconPause.vue';
import { Button } from '@/components/ui/button';
import { usePlayer } from '@/composables/usePlayer';
import { useTrackContextMenu } from '@/composables/useTrackContextMenu';
import { show as artistShow } from '@/routes/artists';
import { index as libraryIndex, syncPlaylist } from '@/routes/library';
import { sync as syncLikedSongs } from '@/routes/library/liked-songs';
import type { SpotifyTrack } from '@/types/spotify';

type TrackItem = {
    spotify_track_id: string;
    uri: string | null;
    position: number;
    added_at: string | null;
    track: {
        id: string;
        name: string;
        duration_ms: number;
        image: string | null;
        artists: Array<{
            id: string;
            name: string;
        }>;
    } | null;
};

const props = defineProps<{
    playlist: {
        id: string;
        name: string;
        description: string | null;
        image: string | null;
        is_liked_playlist: boolean;
        tracks_total: number;
        owner_name: string | null;
        synced_at: string | null;
        sync_status: {
            isRunning: boolean;
            hasFailure: boolean;
            updatedAt: string | null;
        };
    };
    items: {
        data: TrackItem[];
    };
}>();

const page = usePage<{
    auth: {
        user?: {
            id: number;
        };
    };
}>();

const playlistSyncStatus = ref(props.playlist.sync_status);
const { playTrack, isPlayingTrack, pausePlayback } = usePlayer();
const { openTrackContextMenu } = useTrackContextMenu();
let echoBootstrapTimer: ReturnType<typeof setInterval> | null = null;
const showStickyBar = ref(false);
const stickyBarTop = ref(0);
const headerSectionRef = ref<HTMLElement | null>(null);
let scrollContainer: HTMLElement | null = null;
let stickyBarTopTimer: ReturnType<typeof setTimeout> | null = null;

function onScroll(): void {
    if (!scrollContainer || !headerSectionRef.value) {
        return;
    }

    const sectionBottom = headerSectionRef.value.getBoundingClientRect().bottom;
    const containerTop = scrollContainer.getBoundingClientRect().top;

    showStickyBar.value = sectionBottom < containerTop;
}

watch(
    () => props.playlist.sync_status,
    (nextStatus) => {
        playlistSyncStatus.value = nextStatus;
    },
);

const playableItems = computed(() => {
    return props.items.data.filter((item) => item.uri && item.track);
});

const playableUris = computed(() => {
    return props.items.data
        .map((item) => item.uri)
        .filter((uri): uri is string => typeof uri === 'string' && uri !== '');
});

onMounted(() => {
    scrollContainer = document.getElementById('app-content');

    const appHeader = document.querySelector<HTMLElement>('header');

    if (appHeader) {
        stickyBarTopTimer = setTimeout(() => {
            stickyBarTop.value = appHeader.getBoundingClientRect().bottom;
            stickyBarTopTimer = null;
        }, 0);
    }

    if (scrollContainer) {
        scrollContainer.addEventListener('scroll', onScroll, { passive: true });
    }

    if (typeof window === 'undefined' || !page.props.auth.user?.id) {
        return;
    }

    const channelName = `App.Models.User.${page.props.auth.user.id}`;

    const subscribe = (): boolean => {
        if (!window.Echo) {
            return false;
        }

        window.Echo.private(channelName).listen(
            '.Library.PlaylistTracksSynced',
            (event: { playlistId: string; hasFailure: boolean }) => {
                if (event.playlistId !== props.playlist.id) {
                    return;
                }

                playlistSyncStatus.value = {
                    isRunning: false,
                    hasFailure: event.hasFailure,
                    updatedAt: new Date().toISOString(),
                };

                router.reload({
                    only: ['playlist'],
                });
            },
        );

        window.Echo.private(channelName).listen(
            '.Library.SyncStatusUpdated',
            (event: {
                status: {
                    isRunning: boolean;
                };
            }) => {
                if (!event.status.isRunning && playlistSyncStatus.value.isRunning) {
                    router.reload({
                        only: ['playlist'],
                    });
                }
            },
        );

        return true;
    };

    if (subscribe()) {
        return;
    }

    let attempts = 0;
    echoBootstrapTimer = setInterval(() => {
        attempts++;

        if (subscribe() || attempts >= 20) {
            if (echoBootstrapTimer) {
                clearInterval(echoBootstrapTimer);
                echoBootstrapTimer = null;
            }
        }
    }, 250);
});

onUnmounted(() => {
    if (stickyBarTopTimer) {
        clearTimeout(stickyBarTopTimer);
        stickyBarTopTimer = null;
    }

    if (scrollContainer) {
        scrollContainer.removeEventListener('scroll', onScroll);
        scrollContainer = null;
    }

    if (echoBootstrapTimer) {
        clearInterval(echoBootstrapTimer);
        echoBootstrapTimer = null;
    }

    if (typeof window === 'undefined' || !window.Echo || !page.props.auth.user?.id) {
        return;
    }

    window.Echo.leave(`App.Models.User.${page.props.auth.user.id}`);
});

async function playPlaylist(): Promise<void> {
    if (playableUris.value.length === 0) {
        return;
    }

    await playTrack(playableUris.value[0], {
        uris: playableUris.value,
        offsetPosition: 0,
    });
}

async function playItem(index: number): Promise<void> {
    if (playableUris.value.length === 0 || index < 0 || index >= playableUris.value.length) {
        return;
    }

    await playTrack(playableUris.value[index], {
        uris: playableUris.value,
        offsetPosition: index,
    });
}

async function onTrackRowClick(item: TrackItem): Promise<void> {
    if (!item.track || !item.uri) {
        return;
    }

    if (isPlayingTrack.value(item.track.id)) {
        await pausePlayback();

        return;
    }

    const playableIndex = playableItems.value.findIndex(
        (playableItem) => playableItem.spotify_track_id === item.spotify_track_id,
    );

    await playItem(playableIndex);
}

function formatTrackDuration(durationMs: number): string {
    const minutes = Math.floor(durationMs / 60000);
    const seconds = String(Math.floor((durationMs % 60000) / 1000)).padStart(2, '0');

    return `${minutes}:${seconds}`;
}

function formatSyncedAt(iso: string): string {
    const value = new Date(iso);

    if (Number.isNaN(value.getTime())) {
        return iso;
    }

    const day = String(value.getUTCDate()).padStart(2, '0');
    const month = String(value.getUTCMonth() + 1).padStart(2, '0');
    const year = value.getUTCFullYear();
    const hours = String(value.getUTCHours()).padStart(2, '0');
    const minutes = String(value.getUTCMinutes()).padStart(2, '0');
    const seconds = String(value.getUTCSeconds()).padStart(2, '0');

    return `${day}/${month}/${year}, ${hours}:${minutes}:${seconds}`;
}

function onTrackContextMenu(event: MouseEvent, item: TrackItem): void {
    if (!item.track) {
        return;
    }

    void openTrackContextMenu(event, item.track as unknown as SpotifyTrack);
}

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
                        @click="playPlaylist"
                    >
                        <Play class="mr-1 size-3.5" />
                        Play
                    </Button>
                    <span class="truncate font-display text-sm font-bold text-foreground">
                        {{ playlist.name }}
                    </span>
                </div>
            </div>
        </Transition>

        <section ref="headerSectionRef" class="flex flex-wrap gap-4 rounded-2xl border border-border/60 bg-card/70 p-5">
            <div
                v-if="playlist.is_liked_playlist"
                class="grid size-28 shrink-0 place-items-center rounded-xl bg-accent/10"
            >
                <Heart class="size-12 text-accent" fill="currentColor" />
            </div>
            <img
                v-else-if="playlist.image"
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
                    <span
                        v-if="playlistSyncStatus.isRunning"
                        class="rounded-md border border-accent/40 bg-accent/10 px-2 py-1 text-[11px] font-medium text-accent"
                    >
                        Syncing tracks...
                    </span>
                    <span
                        v-else-if="playlistSyncStatus.hasFailure"
                        class="rounded-md border border-destructive/40 bg-destructive/10 px-2 py-1 text-[11px] font-medium text-destructive"
                    >
                        Track sync failed
                    </span>
                    <span v-if="playlist.synced_at" class="inline-flex items-center gap-1">
                        <Clock3 class="size-3" />
                        Synced {{ formatSyncedAt(playlist.synced_at) }}
                    </span>
                </div>

                <div class="mt-4">
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            :disabled="playableUris.length === 0"
                            @click="playPlaylist"
                        >
                            <Play class="mr-1 size-3.5" />
                            Play playlist
                        </Button>
                        <Form
                            v-if="playlist.is_liked_playlist"
                            :action="syncLikedSongs().url"
                            method="post"
                            v-slot="{ processing }"
                        >
                            <Button type="submit" variant="outline" :disabled="processing || playlistSyncStatus.isRunning">
                                {{ playlistSyncStatus.isRunning ? 'Syncing…' : processing ? 'Starting sync…' : 'Sync liked songs' }}
                            </Button>
                        </Form>
                        <Form
                            v-else
                            :action="syncPlaylist(playlist.id).url"
                            method="post"
                            v-slot="{ processing }"
                        >
                            <Button type="submit" variant="outline" :disabled="processing || playlistSyncStatus.isRunning">
                                {{ playlistSyncStatus.isRunning ? 'Syncing tracks…' : processing ? 'Starting sync…' : 'Sync tracks' }}
                            </Button>
                        </Form>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-border/60 bg-card/70 p-4">
            <div v-if="items.data.length === 0" class="text-sm text-muted-foreground">
                No cached tracks yet.
            </div>

            <InfiniteScroll v-else data="items" only-next>
                <ol class="space-y-1">
                    <li
                        v-for="item in items.data"
                        :key="`${item.spotify_track_id}-${item.position}`"
                        class="group flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-secondary/60"
                        :class="item.uri && item.track ? 'cursor-pointer' : 'cursor-default'"
                        @click="onTrackRowClick(item)"
                        @contextmenu="item.track ? onTrackContextMenu($event, item) : undefined"
                    >
                        <button
                            type="button"
                            class="relative flex size-5 shrink-0 items-center justify-center"
                            :disabled="!item.uri || !item.track"
                            @click="onTrackRowClick(item)"
                            @click.stop
                        >
                            <span
                                class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-muted-foreground transition-opacity duration-150"
                                :class="
                                    item.track?.id && isPlayingTrack(item.track.id)
                                        ? 'opacity-0'
                                        : 'opacity-100 group-hover:opacity-0'
                                "
                            >
                                {{ item.position + 1 }}
                            </span>

                            <Play
                                v-if="!(item.track?.id && isPlayingTrack(item.track.id))"
                                class="absolute inset-0 m-auto size-4 text-foreground opacity-0 transition-opacity duration-150 group-hover:opacity-100"
                            />
                            <div
                                v-else
                                class="absolute inset-0 m-auto flex items-end justify-center gap-0.5"
                            >
                                <span class="eq-bar h-3 w-0.5 rounded-full bg-accent transition-opacity duration-150 group-hover:opacity-0" />
                                <span
                                    class="eq-bar h-3 w-0.5 rounded-full bg-accent transition-opacity duration-150 group-hover:opacity-0"
                                    style="animation-delay: 0.15s"
                                />
                                <span
                                    class="eq-bar h-3 w-0.5 rounded-full bg-accent transition-opacity duration-150 group-hover:opacity-0"
                                    style="animation-delay: 0.3s"
                                />
                                <IconPause
                                    class="absolute inset-0 m-auto size-4 text-accent opacity-0 transition-opacity duration-150 group-hover:opacity-100"
                                />
                            </div>
                        </button>

                        <img
                            v-if="item.track?.image"
                            :src="item.track.image"
                            :alt="item.track.name"
                            class="size-10 rounded object-cover"
                        />
                        <div v-else class="size-10 rounded bg-muted" />

                        <div class="min-w-0">
                            <p
                                class="truncate text-sm font-medium"
                                :class="item.track?.id && isPlayingTrack(item.track.id) ? 'text-accent' : 'text-foreground'"
                            >
                                {{ item.track?.name ?? item.spotify_track_id }}
                            </p>
                            <div
                                v-if="item.track?.id && isPlayingTrack(item.track.id)"
                                class="mt-1 h-0.5 w-20 overflow-hidden rounded-full bg-accent/25"
                            >
                                <div class="bg-gradient-primary h-full w-full animate-pulse" />
                            </div>
                            <p
                                v-if="item.track"
                                class="truncate text-[11px] text-muted-foreground"
                            >
                                <template
                                    v-for="(artist, artistIndex) in item.track.artists"
                                    :key="artist.id"
                                >
                                    <Link
                                        :href="artistShow(artist.id).url"
                                        class="hover:text-foreground"
                                        @click.stop
                                    >
                                        {{ artist.name }}
                                    </Link>
                                    <span v-if="artistIndex < item.track.artists.length - 1">, </span>
                                </template>
                            </p>
                        </div>

                        <span v-if="item.track" class="ml-auto shrink-0 text-xs text-muted-foreground tabular-nums">
                            {{ formatTrackDuration(item.track.duration_ms) }}
                        </span>
                    </li>
                </ol>
            </InfiniteScroll>
        </section>
    </div>
</template>
