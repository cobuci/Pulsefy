<script setup lang="ts">
import { Deferred, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Clock, Heart, Play, Shuffle } from 'lucide-vue-next';
import { computed, ref, watchEffect } from 'vue';
import { toast } from 'vue-sonner';
import StatCard from '@/components/dashboard/StatCard.vue';
import IconMusicNote from '@/components/icons/IconMusicNote.vue';
import IconPause from '@/components/icons/IconPause.vue';
import IconPlay from '@/components/icons/IconPlay.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { usePlayer } from '@/composables/usePlayer';
import { dashboard } from '@/routes';
import { favorite as favoriteAlbum } from '@/routes/albums';
import { show as albumShow } from '@/routes/albums';
import { show as artistShow } from '@/routes/artists';
import { pause as pauseRoute } from '@/routes/player';
import { shuffle as shuffleRoute } from '@/routes/player';
import type { SpotifyAlbum, SpotifyTrack } from '@/types/spotify';
import { getCsrfToken } from '@/utils/csrf';
import { formatDuration } from '@/utils/format';

const props = defineProps<{
    albumId: string;
    artistId?: string | null;
    artistName?: string | null;
    album?: SpotifyAlbum | null;
    tracks?: SpotifyTrack[];
    isFavorite?: boolean;
    insights?: {
        playsLabel?: string;
        timeLabel?: string;
        affinityLabel?: string;
    };
}>();

const albumName = computed(() => props.album?.name ?? 'Album');

watchEffect(() => {
    const breadcrumbs = [{ title: 'Dashboard', href: dashboard() }];

    if (props.artistId && props.artistName) {
        breadcrumbs.push({
            title: props.artistName,
            href: artistShow(props.artistId).url,
        });
    }

    breadcrumbs.push({
        title: albumName.value,
        href: albumShow(props.albumId).url,
    });

    setLayoutProps({ breadcrumbs });
});

defineOptions({
    inheritAttrs: false,
});

const coverImage = computed(() => props.album?.images?.[0]?.url ?? null);
const totalDurationMs = computed(() =>
    (props.tracks ?? []).reduce((carry, track) => carry + track.duration_ms, 0),
);
const totalDurationLabel = computed(() => {
    const minutes = Math.round(totalDurationMs.value / 60000);

    return `${minutes} min`;
});
const primaryArtistName = computed(() => {
    return (
        props.tracks?.[0]?.artists?.[0]?.name ?? props.artistName ?? 'Unknown'
    );
});
const primaryArtistId = computed(() => {
    return props.tracks?.[0]?.artists?.[0]?.id ?? props.artistId ?? null;
});

const { isPlayingTrack, nowPlayingData, playTrack, fetchNowPlaying } = usePlayer();
const favorite = ref<boolean>(props.isFavorite ?? false);
const favoriteBusy = ref(false);
const shuffleBusy = ref(false);
const playbackBusy = ref(false);

const isAlbumCurrentlyPlaying = computed(() => {
    const nowPlayingAlbumId = nowPlayingData.value?.track?.album?.id;

    return !!(nowPlayingData.value?.is_playing && nowPlayingAlbumId === props.albumId);
});

watchEffect(() => {
    if (typeof props.isFavorite === 'boolean') {
        favorite.value = props.isFavorite;
    }
});

async function handlePlay(track: SpotifyTrack) {
    const queueUris = (props.tracks ?? [])
        .map((item) => `spotify:track:${item.id}`)
        .filter((value) => value !== 'spotify:track:');
    const currentUri = `spotify:track:${track.id}`;
    const trackIndex = queueUris.findIndex((value) => value === currentUri);

    await playTrack(currentUri, {
        uris: queueUris.length > 0 ? queueUris : undefined,
        offsetPosition: trackIndex >= 0 ? trackIndex : undefined,
    });
}

async function pausePlayback(): Promise<void> {
    if (playbackBusy.value) {
        return;
    }

    playbackBusy.value = true;

    try {
        await fetch(pauseRoute.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        await new Promise((resolve) => setTimeout(resolve, 300));
        await fetchNowPlaying();
    } finally {
        playbackBusy.value = false;
    }
}

async function onPrimaryPlayButtonClick(): Promise<void> {
    if (isAlbumCurrentlyPlaying.value) {
        await pausePlayback();

        return;
    }

    if (props.tracks?.[0]) {
        await handlePlay(props.tracks[0]);
    }
}

async function onTrackRowClick(track: SpotifyTrack): Promise<void> {
    if (isPlayingTrack.value(track.id)) {
        await pausePlayback();

        return;
    }

    await handlePlay(track);
}

async function toggleFavorite() {
    if (favoriteBusy.value) {
        return;
    }

    favoriteBusy.value = true;

    try {
        const response = await fetch(favoriteAlbum(props.albumId).url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ favorite: !favorite.value }),
        });

        if (!response.ok) {
            return;
        }

        const payload = (await response.json()) as {
            ok?: boolean;
            favorite?: boolean;
            requires_reauth?: boolean;
            message?: string;
        };

        if (payload.ok && typeof payload.favorite === 'boolean') {
            favorite.value = payload.favorite;

            return;
        }

        if (payload.requires_reauth && payload.message) {
            toast.error(payload.message);
        }
    } finally {
        favoriteBusy.value = false;
    }
}

async function playShuffledTracks() {
    if (shuffleBusy.value) {
        return;
    }

    const queueUris = (props.tracks ?? [])
        .map((item) => `spotify:track:${item.id}`)
        .filter((value) => value !== 'spotify:track:');

    if (queueUris.length === 0) {
        return;
    }

    shuffleBusy.value = true;

    try {
        await fetch(shuffleRoute.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ state: true }),
        });

        const randomOffset = Math.floor(Math.random() * queueUris.length);

        await playTrack(queueUris[randomOffset], {
            uris: queueUris,
            offsetPosition: randomOffset,
        });
    } finally {
        shuffleBusy.value = false;
    }
}
</script>

<template>
    <Head :title="albumName" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="grid items-start gap-8 lg:grid-cols-3">
            <div class="lg:col-span-1">
                <Deferred data="album">
                    <template #fallback>
                        <div class="sticky top-24">
                            <Skeleton
                                class="aspect-square w-full rounded-2xl"
                            />
                            <div class="mt-5 space-y-2">
                                <Skeleton class="h-4 w-24" />
                                <Skeleton class="h-10 w-48" />
                                <Skeleton class="h-4 w-32" />
                            </div>
                        </div>
                    </template>

                    <template #default>
                        <div v-if="album" class="sticky top-24">
                            <div
                                class="relative aspect-square overflow-hidden rounded-2xl shadow-accent"
                            >
                                <img
                                    v-if="coverImage"
                                    :src="coverImage"
                                    :alt="album.name"
                                    class="size-full object-cover"
                                />
                                <div
                                    v-else
                                    class="flex size-full items-center justify-center bg-muted text-muted-foreground"
                                >
                                    <IconMusicNote class="size-10" />
                                </div>

                                <div
                                    class="absolute -inset-4 -z-10 rounded-full bg-accent/20 blur-3xl"
                                />
                            </div>

                            <div class="mt-5">
                                <p
                                    class="text-xs font-semibold tracking-[0.2em] text-accent uppercase"
                                >
                                    Album · {{ album.release_date }}
                                </p>
                                <h1
                                    class="mt-2 font-display text-4xl font-bold"
                                >
                                    {{ album.name }}
                                </h1>
                                <p class="mt-1 text-base text-foreground">
                                    <Link
                                        v-if="primaryArtistId"
                                        :href="artistShow(primaryArtistId).url"
                                        class="transition-colors hover:text-accent"
                                    >
                                        {{ primaryArtistName }}
                                    </Link>
                                    <span v-else>{{ primaryArtistName }}</span>
                                </p>
                                <p
                                    class="mt-3 flex items-center gap-1 text-xs text-muted-foreground"
                                >
                                    <Clock class="size-3" />
                                    {{
                                        album.total_tracks ??
                                        tracks?.length ??
                                        0
                                    }}
                                    tracks ·
                                    {{ totalDurationLabel }}
                                </p>

                                <div class="mt-5 flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="shadow-glow bg-gradient-primary flex h-11 items-center gap-2 rounded-full px-5 font-semibold text-primary-foreground"
                                        :disabled="!tracks?.length || playbackBusy"
                                        :class="
                                            (tracks?.length ?? 0) > 0 && !playbackBusy
                                                ? 'cursor-pointer'
                                                : 'cursor-not-allowed'
                                        "
                                        @click="onPrimaryPlayButtonClick"
                                    >
                                        <IconPause
                                            v-if="isAlbumCurrentlyPlaying"
                                            class="size-4"
                                        />
                                        <Play v-else class="size-4" />
                                        {{ isAlbumCurrentlyPlaying ? 'Pause' : 'Play' }}
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="
                                            shuffleBusy || !tracks?.length
                                        "
                                        class="grid size-11 place-items-center rounded-full border border-border transition-colors hover:bg-secondary disabled:cursor-not-allowed disabled:opacity-50"
                                        @click="playShuffledTracks"
                                    >
                                        <Shuffle class="size-4" />
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="favoriteBusy"
                                        class="grid size-11 place-items-center rounded-full border border-border transition-colors hover:bg-secondary disabled:cursor-not-allowed disabled:opacity-50"
                                        @click="toggleFavorite"
                                    >
                                        <Heart
                                            :class="[
                                                'size-4',
                                                favorite
                                                    ? 'fill-current text-accent'
                                                    : '',
                                            ]"
                                        />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <section
                            v-else
                            class="rounded-2xl border border-border bg-card p-8 text-center text-sm text-muted-foreground shadow-card"
                        >
                            Album not found.
                        </section>
                    </template>
                </Deferred>
            </div>

            <div
                class="space-y-6 lg:col-span-2 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto lg:pr-2 lg:[scrollbar-gutter:stable]"
            >
                <section class="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        label="Your plays"
                        :value="insights?.playsLabel ?? '0'"
                        hint="Recent window"
                    />
                    <StatCard
                        label="Time"
                        :value="insights?.timeLabel ?? '0m'"
                        hint="Recent window"
                    />
                    <StatCard
                        accent
                        label="Affinity"
                        :value="insights?.affinityLabel ?? '0%'"
                        hint="Share of recent plays"
                    />
                </section>

                <Deferred data="tracks">
                    <template #fallback>
                        <div
                            class="min-h-[28rem] max-h-[28rem] lg:min-h-[calc(100vh-24rem)] lg:max-h-[calc(100vh-24rem)]"
                        >
                            <div
                                class="h-full rounded-2xl border border-border bg-card p-5 shadow-card"
                            >
                                <Skeleton class="mb-4 h-8 w-32" />
                                <div class="space-y-1">
                                    <div
                                        v-for="n in 12"
                                        :key="n"
                                        class="flex items-center gap-3 rounded-lg px-2 py-2"
                                    >
                                        <Skeleton class="h-4 w-4" />
                                        <div class="flex-1 space-y-1">
                                            <Skeleton class="h-4 w-40" />
                                        </div>
                                        <Skeleton class="h-3 w-8" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template #default>
                        <div
                            v-if="!tracks?.length"
                            class="min-h-[28rem] max-h-[28rem] rounded-2xl border border-border bg-card p-8 text-center text-sm text-muted-foreground shadow-card lg:min-h-[calc(100vh-24rem)] lg:max-h-[calc(100vh-24rem)]"
                        >
                            No tracks found.
                        </div>

                        <div
                            v-else
                            class="rounded-2xl border border-border bg-card p-5 shadow-card"
                        >
                            <h2 class="mb-4 font-display text-2xl font-bold">
                                Tracklist
                            </h2>
                            <div class="space-y-1">
                                <div
                                    v-for="(track, index) in tracks"
                                    :key="track.id"
                                    class="group flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-secondary/60"
                                    :class="
                                        isPlayingTrack(track.id)
                                            ? 'bg-accent/10 ring-1 ring-accent/30'
                                            : track.id
                                              ? 'cursor-pointer'
                                              : 'cursor-default'
                                    "
                                    @click="onTrackRowClick(track)"
                                >
                                    <button
                                        class="grid size-5 shrink-0 place-items-center text-muted-foreground transition-colors hover:text-foreground"
                                        :aria-label="
                                            isPlayingTrack(track.id)
                                                ? 'Pause'
                                                : 'Play'
                                        "
                                        @click.stop="onTrackRowClick(track)"
                                    >
                                        <div
                                            v-if="isPlayingTrack(track.id)"
                                            class="flex items-end gap-0.5"
                                        >
                                            <span class="eq-bar h-3 w-0.5 rounded-full bg-accent" />
                                            <span
                                                class="eq-bar h-3 w-0.5 rounded-full bg-accent"
                                                style="animation-delay: 0.15s"
                                            />
                                            <span
                                                class="eq-bar h-3 w-0.5 rounded-full bg-accent"
                                                style="animation-delay: 0.3s"
                                            />
                                        </div>
                                        <IconPlay v-else class="size-4" />
                                    </button>
                                    <span
                                        class="w-5 text-center text-sm text-muted-foreground tabular-nums"
                                        >{{ index + 1 }}</span
                                    >
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="truncate text-sm font-medium"
                                            :class="
                                                isPlayingTrack(track.id)
                                                    ? 'text-accent'
                                                    : 'text-foreground'
                                            "
                                        >
                                            {{ track.name }}
                                        </p>
                                        <div
                                            v-if="isPlayingTrack(track.id)"
                                            class="mt-1 h-0.5 w-20 overflow-hidden rounded-full bg-accent/25"
                                        >
                                            <div class="bg-gradient-primary h-full w-full animate-pulse" />
                                        </div>
                                    </div>
                                    <span
                                        class="text-xs text-muted-foreground tabular-nums"
                                        >{{
                                            formatDuration(track.duration_ms)
                                        }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </template>
                </Deferred>
            </div>
        </div>
    </div>
</template>
