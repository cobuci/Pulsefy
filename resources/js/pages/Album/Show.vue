<script setup lang="ts">
import { Deferred, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
import IconMusicNote from '@/components/icons/IconMusicNote.vue';
import IconPause from '@/components/icons/IconPause.vue';
import IconPlay from '@/components/icons/IconPlay.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { usePlayer } from '@/composables/usePlayer';
import { dashboard } from '@/routes';
import { show as albumShow } from '@/routes/albums';
import { show as artistShow } from '@/routes/artists';
import type { SpotifyAlbum, SpotifyTrack } from '@/types/spotify';
import { formatDuration } from '@/utils/format';

const props = defineProps<{
    albumId: string;
    artistId?: string | null;
    artistName?: string | null;
    album?: SpotifyAlbum | null;
    tracks?: SpotifyTrack[];
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

const { isPlayingTrack, playTrack } = usePlayer();

async function handlePlay(track: SpotifyTrack) {
    await playTrack(`spotify:track:${track.id}`);
}
</script>

<template>
    <Head :title="albumName" />

    <div class="flex flex-col gap-6 p-4">
        <Deferred data="album">
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
                            <Skeleton class="h-8 w-56" />
                            <Skeleton class="h-4 w-40" />
                        </div>
                    </div>
                </section>
            </template>

            <template #default>
                <section
                    v-if="album"
                    class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
                >
                    <div
                        class="flex flex-col gap-4 p-4 md:flex-row md:items-center"
                    >
                        <img
                            v-if="coverImage"
                            :src="coverImage"
                            :alt="album.name"
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
                                Album
                            </p>
                            <h1
                                class="truncate text-3xl font-bold text-foreground"
                            >
                                {{ album.name }}
                            </h1>
                            <div
                                class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                            >
                                <span>{{ album.release_date }}</span>
                                <span>·</span>
                                <span
                                    >{{
                                        album.total_tracks ??
                                        tracks?.length ??
                                        0
                                    }}
                                    tracks</span
                                >
                            </div>
                            <div class="mt-3 text-sm text-muted-foreground">
                                <template
                                    v-for="(artist, index) in tracks?.[0]
                                        ?.artists ?? []"
                                    :key="artist.id"
                                >
                                    <Link
                                        :href="artistShow(artist.id).url"
                                        class="hover:text-foreground"
                                    >
                                        {{ artist.name }}
                                    </Link>
                                    <span
                                        v-if="
                                            index <
                                            (tracks?.[0]?.artists?.length ??
                                                0) -
                                                1
                                        "
                                        >,
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    v-else
                    class="rounded-xl border border-border bg-card p-8 text-center text-sm text-muted-foreground shadow-sm"
                >
                    Album not found.
                </section>
            </template>
        </Deferred>

        <section>
            <h2 class="mb-3 text-base font-semibold text-foreground">Tracks</h2>
            <Deferred data="tracks">
                <template #fallback>
                    <div
                        class="rounded-xl border border-border bg-card p-2 shadow-sm"
                    >
                        <div
                            v-for="n in 8"
                            :key="n"
                            class="flex items-center gap-3 px-2 py-2"
                        >
                            <Skeleton class="h-4 w-4" />
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
                        v-if="!tracks?.length"
                        class="rounded-xl border border-border bg-card p-8 text-center text-sm text-muted-foreground shadow-sm"
                    >
                        No tracks found.
                    </div>

                    <div
                        v-else
                        class="rounded-xl border border-border bg-card p-2 shadow-sm"
                    >
                        <div
                            v-for="(track, index) in tracks"
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
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-semibold text-foreground"
                                >
                                    {{ track.name }}
                                </p>
                            </div>
                            <span
                                class="text-xs text-muted-foreground tabular-nums"
                                >{{ formatDuration(track.duration_ms) }}</span
                            >
                        </div>
                    </div>
                </template>
            </Deferred>
        </section>
    </div>
</template>
