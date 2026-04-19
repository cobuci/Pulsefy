<script setup lang="ts">
import { Form, Head, Link, router, setLayoutProps, usePage } from '@inertiajs/vue3';
import { Clock3, Play } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import IconPause from '@/components/icons/IconPause.vue';
import { Button } from '@/components/ui/button';
import { usePlayer } from '@/composables/usePlayer';
import { show as artistShow } from '@/routes/artists';
import { index as libraryIndex } from '@/routes/library';
import { syncPlaylist } from '@/routes/library';

const props = defineProps<{
    playlist: {
        id: string;
        name: string;
        description: string | null;
        image: string | null;
        tracks_total: number;
        owner_name: string | null;
        synced_at: string | null;
        sync_status: {
            isRunning: boolean;
            hasFailure: boolean;
            updatedAt: string | null;
        };
        items: Array<{
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
        }>;
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
let echoBootstrapTimer: ReturnType<typeof setInterval> | null = null;

watch(
    () => props.playlist.sync_status,
    (nextStatus) => {
        playlistSyncStatus.value = nextStatus;
    },
);

const playableItems = computed(() => {
    return props.playlist.items.filter((item) => item.uri && item.track);
});

const playableUris = computed(() => {
    return props.playlist.items
        .map((item) => item.uri)
        .filter((uri): uri is string => typeof uri === 'string' && uri !== '');
});

onMounted(() => {
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
                if (! event.status.isRunning && playlistSyncStatus.value.isRunning) {
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

async function onTrackRowClick(item: (typeof props.playlist.items)[number]): Promise<void> {
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

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-8 pt-6 pb-4">
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
                        <Form :action="syncPlaylist(playlist.id).url" method="post" v-slot="{ processing }">
                            <Button type="submit" variant="outline" :disabled="processing || playlistSyncStatus.isRunning">
                                {{ playlistSyncStatus.isRunning ? 'Syncing tracks…' : processing ? 'Starting sync…' : 'Sync tracks' }}
                            </Button>
                        </Form>
                    </div>
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
                    class="group flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-secondary/60"
                    :class="item.uri && item.track ? 'cursor-pointer' : 'cursor-default'"
                    @click="onTrackRowClick(item)"
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
        </section>
    </div>
</template>
