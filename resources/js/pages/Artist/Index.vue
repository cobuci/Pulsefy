<script setup lang="ts">
import { Deferred, Head, Link } from '@inertiajs/vue3';
import ArtistCard from '@/components/dashboard/ArtistCard.vue';
import { dashboard } from '@/routes';
import { index as artistsIndex, show as artistShow } from '@/routes/artists';
import type { SpotifyArtist } from '@/types/spotify';

defineOptions({
    inheritAttrs: false,
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Artists', href: artistsIndex() },
        ],
    },
});

defineProps<{
    topArtists?: SpotifyArtist[];
}>();
</script>

<template>
    <Head title="Artists" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">Artists</h1>
            <p class="text-sm text-muted-foreground">Your top artists</p>
        </div>

        <Deferred data="topArtists">
            <template #fallback>
                <div
                    class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5"
                >
                    <ArtistCard
                        v-for="n in 10"
                        :key="n"
                        :rank="n"
                        :loading="true"
                    />
                </div>
            </template>

            <template #default>
                <div
                    v-if="!topArtists?.length"
                    class="py-16 text-center text-sm text-muted-foreground"
                >
                    No artists found.
                </div>

                <div
                    v-else
                    class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5"
                >
                    <Link
                        v-for="(artist, index) in topArtists"
                        :key="artist.id"
                        :href="artistShow(artist.id).url"
                    >
                        <ArtistCard :rank="index + 1" :artist="artist" />
                    </Link>
                </div>
            </template>
        </Deferred>
    </div>
</template>
