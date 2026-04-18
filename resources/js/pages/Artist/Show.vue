<script setup lang="ts">
import { Deferred, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import IconMusicNote from '@/components/icons/IconMusicNote.vue';
import IconPause from '@/components/icons/IconPause.vue';
import IconPlay from '@/components/icons/IconPlay.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { usePlayer } from '@/composables/usePlayer';
import { dashboard } from '@/routes';
import { index as artistsIndex, show as artistShow } from '@/routes/artists';
import { show as albumShow } from '@/routes/albums';
import type {
    SpotifyAlbum,
    SpotifyArtist,
    SpotifyArtistAlbum,
    SpotifyTrack,
} from '@/types/spotify';
import { formatDuration } from '@/utils/format';

const props = defineProps<{
    artistId: string;
    artist?: SpotifyArtist | null;
    topTracks?: SpotifyTrack[];
    albums?: SpotifyArtistAlbum[];
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

function albumImage(album: SpotifyAlbum): string | null {
    return album.images?.[0]?.url ?? null;
}

const { isPlayingTrack, playTrack } = usePlayer();

const showAllTopTracks = ref(false);
const showAllAlbums = ref(false);
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

    return albums.slice(0, 10);
});

const canExpandAlbums = computed(() => filteredAlbums.value.length > 10);

function setAlbumFilter(
    value: 'all' | 'album' | 'single' | 'appears_on' | 'compilation',
) {
    albumFilter.value = value;
    showAllAlbums.value = false;
}

async function handlePlay(track: SpotifyTrack) {
    await playTrack(`spotify:track:${track.id}`);
}
</script>

<template>
    <Head :title="artistName" />

    <div class="flex flex-col gap-6 p-4">
        <Deferred data="artist">
            <template #fallback>
                <section
                    class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
                >
                    <div
                        class="flex flex-col gap-4 p-4 md:flex-row md:items-center"
                    >
                        <Skeleton class="size-28 rounded-xl" />
                        <div class="flex-1 space-y-2">
                            <Skeleton class="h-4 w-16" />
                            <Skeleton class="h-9 w-56" />
                            <Skeleton class="h-4 w-40" />
                        </div>
                    </div>
                </section>
            </template>

            <template #default>
                <section
                    v-if="artist"
                    class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
                >
                    <div
                        class="flex flex-col gap-4 p-4 md:flex-row md:items-center"
                    >
                        <img
                            v-if="coverImage"
                            :src="coverImage"
                            :alt="artist.name"
                            class="size-28 rounded-xl object-cover"
                        />
                        <div
                            v-else
                            class="flex size-28 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                        >
                            <IconMusicNote class="size-8" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Artist
                            </p>
                            <h1
                                class="truncate text-3xl font-bold text-foreground"
                            >
                                {{ artist.name }}
                            </h1>
                            <div
                                class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                            >
                                <span
                                    >Popularity
                                    {{ artist.popularity }}/100</span
                                >
                                <span v-if="artist.genres?.length">·</span>
                                <span
                                    v-if="artist.genres?.length"
                                    class="truncate"
                                    >{{
                                        artist.genres.slice(0, 3).join(', ')
                                    }}</span
                                >
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    v-else
                    class="rounded-xl border border-border bg-card p-8 text-center text-sm text-muted-foreground shadow-sm"
                >
                    Artist not found.
                </section>
            </template>
        </Deferred>

        <section>
            <h2 class="mb-3 text-base font-semibold text-foreground">
                Top Tracks
            </h2>
            <Deferred data="topTracks">
                <template #fallback>
                    <div
                        class="rounded-xl border border-border bg-card p-2 shadow-sm"
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
                        class="rounded-xl border border-border bg-card p-8 text-center text-sm text-muted-foreground shadow-sm"
                    >
                        No top tracks found for this artist.
                    </div>

                    <div
                        v-else
                        class="rounded-xl border border-border bg-card p-2 shadow-sm"
                    >
                        <div
                            v-for="(track, index) in displayedTopTracks"
                            :key="track.id"
                            class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-accent/30"
                        >
                            <button
                                class="flex size-5 shrink-0 items-center justify-center text-muted-foreground hover:text-foreground"
                                :aria-label="
                                    isPlayingTrack(track.id) ? 'Pause' : 'Play'
                                "
                                @click="handlePlay(track)"
                            >
                                <IconPause
                                    v-if="isPlayingTrack(track.id)"
                                    class="size-4 text-green-500"
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
                            <div v-else class="size-10 rounded-md bg-muted" />

                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-semibold text-foreground"
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
                                >{{ formatDuration(track.duration_ms) }}</span
                            >
                        </div>

                        <div v-if="canExpandTopTracks" class="px-2 pt-2">
                            <button
                                type="button"
                                class="text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                                @click="showAllTopTracks = !showAllTopTracks"
                            >
                                {{
                                    showAllTopTracks ? 'Show less' : 'Show more'
                                }}
                            </button>
                        </div>
                    </div>
                </template>
            </Deferred>
        </section>

        <section>
            <h2 class="mb-3 text-base font-semibold text-foreground">Albums</h2>

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
                    <div
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5"
                    >
                        <div
                            v-for="n in 10"
                            :key="n"
                            class="rounded-xl border border-border bg-card p-3 shadow-sm"
                        >
                            <Skeleton class="aspect-square w-full rounded-lg" />
                            <Skeleton class="mt-3 h-4 w-3/4" />
                            <Skeleton class="mt-2 h-3 w-1/2" />
                        </div>
                    </div>
                </template>

                <template #default>
                    <div
                        v-if="!displayedAlbums.length"
                        class="rounded-xl border border-border bg-card p-8 text-center text-sm text-muted-foreground shadow-sm"
                    >
                        No albums found for this filter.
                    </div>

                    <div
                        v-else
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5"
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
                            class="rounded-xl border border-border bg-card p-3 shadow-sm transition-colors hover:bg-accent/20"
                        >
                            <img
                                v-if="albumImage(album)"
                                :src="albumImage(album)!"
                                :alt="album.name"
                                class="aspect-square w-full rounded-lg object-cover"
                            />
                            <div
                                v-else
                                class="aspect-square w-full rounded-lg bg-muted"
                            />
                            <p
                                class="mt-3 line-clamp-2 text-sm font-semibold text-foreground"
                            >
                                {{ album.name }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ album.release_date }}
                            </p>
                        </Link>

                        <div v-if="canExpandAlbums" class="col-span-full pt-2">
                            <button
                                type="button"
                                class="text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                                @click="showAllAlbums = !showAllAlbums"
                            >
                                {{ showAllAlbums ? 'Show less' : 'Show more' }}
                            </button>
                        </div>
                    </div>
                </template>
            </Deferred>
        </section>
    </div>
</template>
