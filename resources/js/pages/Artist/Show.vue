<script setup lang="ts">
import { Deferred, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Heart, Play, Shuffle } from 'lucide-vue-next';
import { computed, ref, watchEffect } from 'vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import IconMusicNote from '@/components/icons/IconMusicNote.vue';
import IconPause from '@/components/icons/IconPause.vue';
import IconPlay from '@/components/icons/IconPlay.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { usePlayer } from '@/composables/usePlayer';
import { dashboard } from '@/routes';
import { show as albumShow } from '@/routes/albums';
import { index as artistsIndex, show as artistShow } from '@/routes/artists';
import { shuffle as shuffleRoute } from '@/routes/player';
import type {
    SpotifyAlbum,
    SpotifyArtist,
    SpotifyArtistAlbum,
    SpotifyTrack,
} from '@/types/spotify';
import { getCsrfToken } from '@/utils/csrf';
import { formatDuration } from '@/utils/format';

const props = defineProps<{
    artistId: string;
    artist?: SpotifyArtist | null;
    topTracks?: SpotifyTrack[];
    albums?: SpotifyArtistAlbum[];
    insights?: {
        rankLabel?: string;
        firstListenLabel?: string;
        hoursLabel?: string;
        genreLabel?: string | null;
    };
}>();

defineOptions({
    inheritAttrs: false,
});

const artistName = computed(() => props.artist?.name ?? 'Artist');

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Artists', href: artistsIndex() },
            { title: artistName.value, href: artistShow(props.artistId).url },
        ],
    });
});

const coverImage = computed(() => props.artist?.images?.[0]?.url ?? null);
const primaryGenre = computed(() => props.artist?.genres?.[0] ?? 'Unknown');
const albumsCount = computed(() => props.albums?.length ?? 0);
const listenedHours = computed(() => {
    if (props.insights?.hoursLabel) {
        return props.insights.hoursLabel;
    }

    const totalMs = (props.topTracks ?? []).reduce(
        (carry, track) => carry + track.duration_ms,
        0,
    );

    const hours = Math.max(1, Math.round(totalMs / (1000 * 60 * 60)));

    return `${hours}h`;
});
const genreLabel = computed(() => {
    return props.insights?.genreLabel ?? primaryGenre.value;
});
const artistRank = computed(() => {
    if (props.insights?.rankLabel) {
        return props.insights.rankLabel;
    }

    return '#—';
});

const firstListenLabel = computed(() => {
    return props.insights?.firstListenLabel ?? 'Not enough history';
});

function albumImage(album: SpotifyAlbum): string | null {
    return album.images?.[0]?.url ?? null;
}

const { isPlayingTrack, playTrack } = usePlayer();

const INITIAL_ALBUMS_COUNT = 6;

const showAllTopTracks = ref(false);
const showAllAlbums = ref(false);
const shuffleBusy = ref(false);
const albumFilter = ref<
    'all' | 'album' | 'single' | 'appears_on' | 'compilation'
>('all');

const albumFilterOptions: Array<{
    value: 'all' | 'album' | 'single' | 'appears_on' | 'compilation';
    label: string;
}> = [
    { value: 'all', label: 'All' },
    { value: 'album', label: 'Albums' },
    { value: 'single', label: 'Singles' },
    { value: 'appears_on', label: 'Appears On' },
    { value: 'compilation', label: 'Compilations' },
];

const displayedTopTracks = computed(() => {
    const tracks = props.topTracks ?? [];

    if (showAllTopTracks.value) {
        return tracks;
    }

    return tracks.slice(0, 5);
});

const canExpandTopTracks = computed(() => (props.topTracks?.length ?? 0) > 5);

const filteredAlbums = computed(() => {
    const albums = props.albums ?? [];

    if (albumFilter.value === 'all') {
        return albums;
    }

    return albums.filter(
        (album) =>
            (album.album_group ?? album.album_type) === albumFilter.value,
    );
});

const displayedAlbums = computed(() => {
    const albums = filteredAlbums.value;

    if (showAllAlbums.value) {
        return albums;
    }

    return albums.slice(0, INITIAL_ALBUMS_COUNT);
});

const canExpandAlbums = computed(
    () => filteredAlbums.value.length > INITIAL_ALBUMS_COUNT,
);

function setAlbumFilter(
    value: 'all' | 'album' | 'single' | 'appears_on' | 'compilation',
) {
    albumFilter.value = value;
}

function trackUri(track: SpotifyTrack): string | null {
    if (track.uri) {
        return track.uri;
    }

    if (track.id) {
        return `spotify:track:${track.id}`;
    }

    return null;
}

function canPlayTrack(track: SpotifyTrack): boolean {
    return trackUri(track) !== null;
}

async function handlePlay(track: SpotifyTrack) {
    const uri = trackUri(track);

    if (!uri) {
        return;
    }

    const queueUris = (props.topTracks ?? [])
        .map((item) => trackUri(item))
        .filter((value): value is string => value !== null);

    const trackIndex = queueUris.findIndex((value) => value === uri);

    await playTrack(uri, {
        uris: queueUris.length > 0 ? queueUris : undefined,
        offsetPosition: trackIndex >= 0 ? trackIndex : undefined,
    });
}

async function playTopTrack() {
    const firstTrack = props.topTracks?.[0];

    if (firstTrack) {
        await handlePlay(firstTrack);
    }
}

async function playShuffledTopTracks() {
    if (shuffleBusy.value) {
        return;
    }

    const queueUris = (props.topTracks ?? [])
        .map((item) => trackUri(item))
        .filter((value): value is string => value !== null);

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
    <Head :title="artistName" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-10 px-6 py-8">
        <Deferred data="artist">
            <template #fallback>
                <section
                    class="overflow-hidden rounded-2xl border border-border bg-card shadow-card"
                >
                    <div
                        class="flex flex-col gap-5 p-5 md:flex-row md:items-end"
                    >
                        <Skeleton class="size-40 rounded-2xl" />
                        <div class="flex-1 space-y-2">
                            <Skeleton class="h-4 w-36" />
                            <Skeleton class="h-16 w-72" />
                            <Skeleton class="h-4 w-56" />
                        </div>
                    </div>
                </section>
            </template>

            <template #default>
                <section
                    v-if="artist"
                    class="relative min-h-[320px] overflow-hidden rounded-2xl"
                >
                    <img
                        v-if="coverImage"
                        :src="coverImage"
                        :alt="artist.name"
                        class="absolute inset-0 size-full scale-110 object-cover opacity-60 blur-2xl"
                    />
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-background via-background/60 to-background/20"
                    />

                    <div
                        class="relative flex h-full min-h-[320px] items-end px-6 pb-8"
                    >
                        <div class="flex items-end gap-6">
                            <img
                                v-if="coverImage"
                                :src="coverImage"
                                :alt="artist.name"
                                class="shadow-glow size-40 rounded-2xl border border-accent/20 object-cover"
                            />
                            <p
                                v-else
                                class="flex size-40 items-center justify-center rounded-2xl border border-accent/20 bg-muted text-muted-foreground"
                            >
                                <IconMusicNote class="size-10" />
                            </p>

                            <div>
                                <p
                                    class="text-xs font-semibold tracking-[0.2em] text-accent uppercase"
                                >
                                    {{ artistRank }} this month ·
                                    {{ genreLabel }}
                                </p>

                                <h1
                                    class="mt-2 truncate font-display text-5xl font-bold sm:text-7xl"
                                >
                                    {{ artist.name }}
                                </h1>
                                <p class="mt-3 text-sm text-muted-foreground">
                                    {{ listenedHours }} listened ·
                                    {{ albumsCount }} albums ·
                                    {{ artist.popularity }} popularity
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    v-else
                    class="rounded-2xl border border-border bg-card p-8 text-center text-sm text-muted-foreground shadow-card"
                >
                    Artist not found.
                </section>
            </template>
        </Deferred>

        <div class="flex items-center gap-3">
            <button
                type="button"
                class="shadow-glow bg-gradient-primary flex h-12 items-center gap-2 rounded-full px-6 font-semibold text-primary-foreground transition-transform hover:scale-[1.02]"
                :disabled="!(topTracks?.length ?? 0)"
                :class="
                    (topTracks?.length ?? 0) > 0
                        ? 'cursor-pointer'
                        : 'cursor-not-allowed'
                "
                @click="playTopTrack"
            >
                <Play class="size-4" />
                Play
            </button>
            <button
                type="button"
                :disabled="shuffleBusy || !(topTracks?.length ?? 0)"
                class="grid size-12 place-items-center rounded-full border border-border transition-colors hover:bg-secondary disabled:cursor-not-allowed disabled:opacity-50"
                @click="playShuffledTopTracks"
            >
                <Shuffle class="size-4" />
            </button>
            <button
                type="button"
                class="grid size-12 cursor-pointer place-items-center rounded-full border border-border transition-colors hover:bg-secondary"
            >
                <Heart class="size-4" />
            </button>
        </div>

        <section class="grid gap-4 sm:grid-cols-3">
            <StatCard
                accent
                label="Your hours"
                :value="listenedHours"
                hint="This month"
            />
            <StatCard
                label="Your rank"
                :value="artistRank"
                hint="In your library"
            />
            <StatCard
                label="First listen"
                :value="firstListenLabel"
                hint="Earliest available in recent history"
            />
        </section>

        <section class="grid gap-6 lg:grid-cols-3">
            <div :class="showAllAlbums ? 'lg:col-span-3' : 'lg:col-span-2'">
                <h2 class="mb-4 font-display text-2xl font-bold">Top tracks</h2>
                <Deferred data="topTracks">
                    <template #fallback>
                        <div
                            class="rounded-2xl border border-border bg-card p-3 shadow-card"
                        >
                            <div
                                v-for="n in 5"
                                :key="n"
                                class="flex items-center gap-3 px-2 py-2"
                            >
                                <Skeleton class="h-4 w-4" />
                                <Skeleton class="size-10 rounded-md" />
                                <div class="flex-1 space-y-1">
                                    <Skeleton class="h-4 w-40" />
                                    <Skeleton class="h-3 w-24" />
                                </div>
                                <Skeleton class="h-3 w-8" />
                            </div>
                        </div>
                    </template>

                    <template #default>
                        <div
                            v-if="!topTracks?.length"
                            class="rounded-2xl border border-border bg-card p-8 text-center text-sm text-muted-foreground shadow-card"
                        >
                            No top tracks found for this artist.
                        </div>

                        <div
                            v-else
                            class="rounded-2xl border border-border bg-card p-3 shadow-card"
                        >
                            <div
                                v-for="(track, index) in displayedTopTracks"
                                :key="track.id ?? `${track.name}-${index}`"
                                class="group flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-secondary/60"
                                :class="
                                    canPlayTrack(track)
                                        ? 'cursor-pointer'
                                        : 'cursor-default'
                                "
                                @click="
                                    canPlayTrack(track)
                                        ? handlePlay(track)
                                        : null
                                "
                            >
                                <button
                                    class="flex size-5 shrink-0 items-center justify-center text-muted-foreground hover:text-foreground"
                                    :disabled="!canPlayTrack(track)"
                                    :aria-label="
                                        isPlayingTrack(track.id)
                                            ? 'Pause'
                                            : 'Play'
                                    "
                                    @click.stop="handlePlay(track)"
                                >
                                    <IconPause
                                        v-if="isPlayingTrack(track.id)"
                                        class="size-4 text-accent"
                                    />
                                    <IconPlay v-else class="size-4" />
                                </button>
                                <span
                                    class="w-5 text-center text-sm font-semibold text-muted-foreground"
                                    >{{ index + 1 }}</span
                                >
                                <img
                                    v-if="track.album?.images?.[0]?.url"
                                    :src="track.album.images[0].url"
                                    :alt="track.album.name"
                                    class="size-10 rounded-md object-cover"
                                />
                                <div
                                    v-else
                                    class="size-10 rounded-md bg-muted"
                                />

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
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ track.album?.name }}
                                    </p>
                                </div>

                                <span
                                    class="text-xs text-muted-foreground tabular-nums"
                                    >{{
                                        formatDuration(track.duration_ms)
                                    }}</span
                                >
                            </div>

                            <div v-if="canExpandTopTracks" class="px-2 pt-2">
                                <button
                                    type="button"
                                    class="text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                                    @click="
                                        showAllTopTracks = !showAllTopTracks
                                    "
                                >
                                    {{
                                        showAllTopTracks
                                            ? 'Show less'
                                            : 'Show more'
                                    }}
                                </button>
                            </div>
                        </div>
                    </template>
                </Deferred>
            </div>

            <div :class="showAllAlbums ? 'lg:col-span-3' : ''">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="font-display text-2xl font-bold">Albums</h2>

                    <button
                        v-if="canExpandAlbums"
                        type="button"
                        class="rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                        @click="showAllAlbums = !showAllAlbums"
                    >
                        {{ showAllAlbums ? 'Show less' : 'Show all albums' }}
                    </button>
                </div>

                <div class="mb-3 flex flex-wrap gap-1.5">
                    <button
                        v-for="option in albumFilterOptions"
                        :key="option.value"
                        type="button"
                        :class="[
                            'rounded-full px-3 py-1 text-xs font-medium transition-colors',
                            albumFilter === option.value
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground hover:text-foreground',
                        ]"
                        @click="setAlbumFilter(option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>

                <Deferred data="albums">
                    <template #fallback>
                        <div class="grid grid-cols-2 gap-4">
                            <div
                                v-for="n in 4"
                                :key="n"
                                class="rounded-xl border border-border bg-card p-3 shadow-card"
                            >
                                <Skeleton
                                    class="aspect-square w-full rounded-lg"
                                />
                                <Skeleton class="mt-3 h-4 w-3/4" />
                                <Skeleton class="mt-2 h-3 w-1/2" />
                            </div>
                        </div>
                    </template>

                    <template #default>
                        <div
                            v-if="!displayedAlbums.length"
                            class="rounded-2xl border border-border bg-card p-8 text-center text-sm text-muted-foreground shadow-card"
                        >
                            No albums found for this filter.
                        </div>

                        <div
                            v-else
                            class="grid grid-cols-2 gap-4"
                            :class="
                                showAllAlbums
                                    ? 'sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5'
                                    : ''
                            "
                        >
                            <Link
                                v-for="album in displayedAlbums"
                                :key="album.id"
                                :href="
                                    albumShow(album.id, {
                                        query: {
                                            artistId,
                                            artistName: artistName,
                                        },
                                    }).url
                                "
                                class="group rounded-xl border border-border bg-card p-3 shadow-card transition-colors hover:bg-secondary/50"
                            >
                                <img
                                    v-if="albumImage(album)"
                                    :src="albumImage(album)!"
                                    :alt="album.name"
                                    class="aspect-square w-full rounded-lg object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                                />
                                <div
                                    v-else
                                    class="aspect-square w-full rounded-lg bg-muted"
                                />
                                <p
                                    class="mt-3 line-clamp-2 text-sm font-medium text-foreground"
                                >
                                    {{ album.name }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ album.release_date }}
                                </p>
                            </Link>
                        </div>
                    </template>
                </Deferred>
            </div>
        </section>
    </div>
</template>
