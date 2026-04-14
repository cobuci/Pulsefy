<script setup lang="ts">
import { computed, ref } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import IconPause from '@/components/icons/IconPause.vue';
import IconPlay from '@/components/icons/IconPlay.vue';
import type { SpotifyTrack } from '@/types/spotify';
import { formatDuration } from '@/utils/format';

const props = defineProps<{
    track?: SpotifyTrack;
    rank: number;
    loading?: boolean;
    isPlaying?: boolean;
}>();

const emit = defineEmits<{
    play: [track: SpotifyTrack];
}>();

const isHovered = ref(false);

const albumImageUrl = computed(() => {
    if (!props.track || !Array.isArray(props.track.album?.images)) {
        return null;
    }

    return props.track.album.images[0]?.url ?? null;
});

const showPlayButton = computed(() => isHovered.value || props.isPlaying);

function handlePlay() {
    if (props.track) {
        emit('play', props.track);
    }
}
</script>

<template>
    <div
        class="flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-accent/30"
        @mouseenter="isHovered = true"
        @mouseleave="isHovered = false"
    >
        <button
            v-if="!loading && track"
            class="relative flex size-5 shrink-0 items-center justify-center"
            :aria-label="isPlaying ? 'Pause' : 'Play'"
            @click="handlePlay"
        >
            <!-- Rank number — shown when not hovered and not playing -->
            <span
                class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-muted-foreground transition-opacity duration-150"
                :class="showPlayButton ? 'opacity-0' : 'opacity-100'"
                aria-hidden="true"
            >
                {{ rank }}
            </span>

            <!-- Play icon — shown on hover when not playing -->
            <IconPlay
                v-if="!isPlaying"
                class="absolute inset-0 m-auto size-4 text-foreground transition-opacity duration-150"
                :class="showPlayButton ? 'opacity-100' : 'opacity-0'"
                aria-hidden="true"
            />

            <!-- Pause / equalizer icon — shown when track is actively playing -->
            <IconPause
                v-else
                class="absolute inset-0 m-auto size-4 text-green-500 opacity-100 transition-opacity duration-150"
                aria-hidden="true"
            />
        </button>

        <span
            v-else
            class="w-5 shrink-0 text-center text-sm font-semibold text-muted-foreground"
        >
            {{ rank }}
        </span>

        <template v-if="loading || !track">
            <Skeleton class="size-10 shrink-0 rounded-md" />
            <div class="flex flex-1 flex-col gap-1">
                <Skeleton class="h-4 w-40" />
                <Skeleton class="h-3 w-24" />
            </div>
            <Skeleton class="h-3 w-8" />
        </template>

        <template v-else>
            <img
                v-if="albumImageUrl"
                :src="albumImageUrl"
                :alt="track.album.name"
                class="size-10 shrink-0 rounded-md object-cover"
            />
            <div class="min-w-0 flex-1">
                <p
                    class="truncate text-sm font-semibold transition-colors duration-150"
                    :class="isPlaying ? 'text-green-500' : 'text-foreground'"
                >
                    {{ track.name }}
                </p>
                <p class="truncate text-xs text-muted-foreground">
                    {{ track.artists.map((a) => a.name).join(', ') }}
                </p>
            </div>
            <span class="shrink-0 text-xs text-muted-foreground tabular-nums">
                {{ formatDuration(track.duration_ms) }}
            </span>
        </template>
    </div>
</template>
