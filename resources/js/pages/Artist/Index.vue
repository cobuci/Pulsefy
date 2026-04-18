<script setup lang="ts">
import { Deferred, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
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

const props = defineProps<{
    topArtists?: SpotifyArtist[];
}>();

const artistsCount = computed(() => props.topArtists?.length ?? 0);
</script>

<template>
    <Head title="Artists" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-8">
            <p
                class="text-xs font-semibold tracking-[0.2em] text-accent uppercase"
            >
                Library
            </p>
            <h1 class="mt-2 font-display text-4xl font-bold">Your Artists</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ artistsCount }} artists shaped your sound this season.
            </p>
        </div>

        <Deferred data="topArtists">
            <template #fallback>
                <div
                    class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4"
                >
                    <ArtistCard
                        v-for="n in 8"
                        :key="n"
                        :rank="n"
                        :loading="true"
                    />
                </div>
            </template>

            <template #default>
                <div
                    v-if="!topArtists?.length"
                    class="rounded-2xl border border-border bg-card py-16 text-center text-sm text-muted-foreground"
                >
                    No artists found.
                </div>

                <div
                    v-else
                    class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4"
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
