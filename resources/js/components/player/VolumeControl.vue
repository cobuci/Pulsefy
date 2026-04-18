<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import IconVolume from '@/components/icons/IconVolume.vue';
import { volume as volumeRoute } from '@/routes/player';
import type { SpotifyPlayer } from '@/types/spotify-web-playback';
import { getCsrfToken } from '@/utils/csrf';

const props = withDefaults(
    defineProps<{
        initialVolume: number | null;
        localPlayer: SpotifyPlayer | null;
        localPlaybackActive: boolean;
        orientation?: 'vertical' | 'horizontal';
        showIcon?: boolean;
    }>(),
    {
        orientation: 'vertical',
        showIcon: true,
    },
);

const volumePct = ref<number>(props.initialVolume ?? 50);
const isDragging = ref(false);
const isHovered = ref(false);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let closeTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.initialVolume,
    (val) => {
        if (val !== null && !isDragging.value) {
            volumePct.value = val;
        }
    },
);

onUnmounted(() => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    if (closeTimer) {
        clearTimeout(closeTimer);
    }
});

const trackStyle = computed(() => ({
    background: `linear-gradient(to right, var(--color-primary) ${volumePct.value}%, color-mix(in srgb, var(--color-foreground) 12%, transparent) ${volumePct.value}%)`,
}));

function onMouseEnter() {
    if (closeTimer) {
        clearTimeout(closeTimer);
        closeTimer = null;
    }

    isHovered.value = true;
}

function onMouseLeave() {
    if (isDragging.value) {
        return;
    }

    closeTimer = setTimeout(() => {
        isHovered.value = false;
        closeTimer = null;
    }, 500);
}

function onInput(event: Event) {
    isDragging.value = true;
    volumePct.value = Number((event.target as HTMLInputElement).value);

    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => {
        applyVolume(volumePct.value);
        isDragging.value = false;
    }, 300);
}

function applyVolume(pct: number) {
    if (props.localPlaybackActive && props.localPlayer) {
        void props.localPlayer.setVolume(pct / 100);

        return;
    }

    void fetch(volumeRoute.url(), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ volume_percent: pct }),
    });
}
</script>

<template>
    <div
        :class="
            props.orientation === 'vertical'
                ? 'relative hidden items-center justify-center md:flex'
                : 'relative flex items-center gap-2'
        "
        @mouseenter="onMouseEnter"
        @mouseleave="onMouseLeave"
    >
        <template v-if="props.orientation === 'vertical'">
            <Transition
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0 translate-y-3 scale-90"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition-all duration-[400ms] ease-in-out"
                leave-from-class="opacity-100 translate-y-0 scale-100"
                leave-to-class="opacity-0 translate-y-3 scale-90"
            >
                <div
                    v-show="isHovered"
                    class="absolute right-0 bottom-full mb-2"
                    style="transform-origin: bottom right"
                    @mouseenter="onMouseEnter"
                    @mouseleave="onMouseLeave"
                >
                    <div
                        class="volume-popup flex flex-col items-center gap-2 rounded-2xl px-3 pt-4 pb-3"
                    >
                        <span
                            class="text-[10px] leading-none font-semibold text-foreground/60 tabular-nums"
                        >
                            {{ volumePct }}
                        </span>

                        <div class="flex h-24 w-6 items-center justify-center">
                            <input
                                type="range"
                                min="0"
                                max="100"
                                step="1"
                                :value="volumePct"
                                :style="trackStyle"
                                class="volume-slider"
                                title="Volume"
                                @input="onInput"
                                @mouseup="isDragging = false"
                            />
                        </div>
                    </div>
                </div>
            </Transition>

            <button
                v-if="props.showIcon"
                type="button"
                class="flex size-8 items-center justify-center rounded-md transition-colors"
                :class="
                    isHovered
                        ? 'text-foreground'
                        : 'text-muted-foreground hover:text-foreground'
                "
                tabindex="-1"
            >
                <IconVolume class="size-4" />
            </button>
        </template>

        <template v-else>
            <IconVolume
                v-if="props.showIcon"
                class="size-4 shrink-0 text-muted-foreground"
            />
            <input
                type="range"
                min="0"
                max="100"
                step="1"
                :value="volumePct"
                :style="trackStyle"
                class="volume-slider-horizontal"
                title="Volume"
                @input="onInput"
                @mouseup="isDragging = false"
            />
        </template>
    </div>
</template>

<style scoped>
.volume-popup {
    background: color-mix(in srgb, var(--color-card) 95%, transparent);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid
        color-mix(in srgb, var(--color-foreground) 8%, transparent);
    box-shadow:
        0 8px 32px rgb(0 0 0 / 0.18),
        0 1px 0 color-mix(in srgb, var(--color-foreground) 6%, transparent)
            inset;
}

.volume-slider {
    appearance: none;
    -webkit-appearance: none;
    width: 96px;
    height: 4px;
    border-radius: 9999px;
    outline: none;
    border: none;
    cursor: pointer;
    transform: rotate(270deg) translateX(-5px);
    overflow: visible;
}

.volume-slider::-webkit-slider-runnable-track {
    height: 4px;
    border-radius: 9999px;
}

.volume-slider::-moz-range-track {
    height: 4px;
    border-radius: 9999px;
    background: transparent;
}

.volume-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 14px;
    height: 14px;
    margin-top: -5px;
    border-radius: 50%;
    background: var(--color-foreground);
    cursor: pointer;
    box-shadow:
        0 0 0 3px color-mix(in srgb, var(--color-primary) 25%, transparent),
        0 2px 6px rgb(0 0 0 / 0.3);
    transition: box-shadow 0.15s ease;
}

.volume-slider:hover::-webkit-slider-thumb,
.volume-slider:active::-webkit-slider-thumb {
    box-shadow:
        0 0 0 5px color-mix(in srgb, var(--color-primary) 30%, transparent),
        0 2px 6px rgb(0 0 0 / 0.3);
}

.volume-slider::-moz-range-thumb {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: none;
    background: var(--color-foreground);
    cursor: pointer;
    box-shadow:
        0 0 0 3px color-mix(in srgb, var(--color-primary) 25%, transparent),
        0 2px 6px rgb(0 0 0 / 0.3);
}

.volume-slider-horizontal {
    appearance: none;
    -webkit-appearance: none;
    width: 96px;
    height: 4px;
    border-radius: 9999px;
    border: none;
    outline: none;
    cursor: pointer;
}

.volume-slider-horizontal::-webkit-slider-runnable-track {
    height: 4px;
    border-radius: 9999px;
}

.volume-slider-horizontal::-moz-range-track {
    height: 4px;
    border-radius: 9999px;
    background: transparent;
}

.volume-slider-horizontal::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 12px;
    height: 12px;
    margin-top: -4px;
    border-radius: 9999px;
    background: var(--color-foreground);
    box-shadow:
        0 0 0 3px color-mix(in srgb, var(--color-primary) 20%, transparent),
        0 2px 4px rgb(0 0 0 / 0.25);
}

.volume-slider-horizontal::-moz-range-thumb {
    width: 12px;
    height: 12px;
    border: none;
    border-radius: 9999px;
    background: var(--color-foreground);
    box-shadow:
        0 0 0 3px color-mix(in srgb, var(--color-primary) 20%, transparent),
        0 2px 4px rgb(0 0 0 / 0.25);
}
</style>
