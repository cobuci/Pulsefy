<script setup lang="ts">
import { Deferred, Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import ArtistCard from '@/components/dashboard/ArtistCard.vue';
import SectionHeader from '@/components/dashboard/SectionHeader.vue';
import { index as artistsIndex, show as artistShow } from '@/routes/artists';
import type { SpotifyArtist } from '@/types/spotify';

defineProps<{
    periodDescription: string;
    topArtistsPreview: SpotifyArtist[];
}>();
</script>

<template>
    <section>
        <SectionHeader title="Top Artists" :description="periodDescription">
            <Link
                :href="artistsIndex()"
                class="flex items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-accent"
            >
                See all
                <ChevronRight class="size-3" />
            </Link>
        </SectionHeader>
        <Deferred data="topArtists">
            <template #fallback>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    <ArtistCard v-for="n in 4" :key="n" :rank="n" :loading="true" />
                </div>
            </template>

            <template #default="{ reloading }">
                <div
                    class="grid grid-cols-2 gap-4 transition-opacity duration-300 sm:grid-cols-3 lg:grid-cols-4"
                    :class="{ 'opacity-40': reloading }"
                >
                    <Link
                        v-for="(artist, i) in topArtistsPreview"
                        :key="artist.id"
                        :href="artistShow(artist.id).url"
                    >
                        <ArtistCard :rank="i + 1" :artist="artist" />
                    </Link>
                </div>
            </template>
        </Deferred>
    </section>
</template>
