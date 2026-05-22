<script setup lang="ts">
import { BookOpen, Compass, Hash, Lightbulb, Music, RefreshCw, Smile, Sparkles, X } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { useTrackInsights } from '@/composables/useTrackInsights';
import type { NowPlaying } from '@/types/spotify';

const props = defineProps<{
    open: boolean;
    track: NowPlaying['track'] | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const trackInsights = useTrackInsights();

const isMounted = ref(false);

let echoCleanup: (() => void) | null = null;

function handleKeyDown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && props.open) {
        emit('close');
    }
}

watch(
    () => props.track?.id,
    (trackId) => {
        echoCleanup?.();
        echoCleanup = null;

        if (trackId) {
            echoCleanup = trackInsights.listenForUpdates(trackId);
        }
    },
    { immediate: true },
);

watch(
    () => [props.open, props.track?.id] as const,
    ([isOpen]) => {
        if (!isOpen) {
            trackInsights.reset();

            return;
        }

        if (props.track) {
            void trackInsights.open(props.track);
        }
    },
);

onMounted(() => {
    isMounted.value = true;
    document.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    echoCleanup?.();
    document.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
    <Teleport v-if="isMounted" to="body">
        <!-- Full-page backdrop (blurs everything including the player) -->
        <Transition
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-[60] bg-black/60 backdrop-blur-md"
                @click="emit('close')"
            />
        </Transition>

        <!-- Panel -->
        <Transition
            enter-active-class="transition-transform duration-300 ease-out"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition-transform duration-250 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <aside
                v-if="open"
                class="fixed top-0 right-0 z-[70] flex h-full w-96 flex-col overflow-hidden border-l border-border/60 bg-background shadow-2xl"
            >
                <!-- Header -->
                <header class="flex items-center justify-between border-b border-border/60 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <Sparkles class="h-4 w-4 text-purple-400" />
                        <span class="text-sm font-semibold">AI Track Insights</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <!-- Language toggle -->
                        <button
                            v-if="trackInsights.isReady.value"
                            class="rounded-md px-2 py-1 text-[11px] font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            :title="trackInsights.language.value === 'en' ? 'Switch to Portuguese' : 'Switch to English'"
                            @click="trackInsights.toggleLanguage()"
                        >
                            {{ trackInsights.language.value === 'en' ? 'EN' : 'PT' }}
                        </button>

                        <button
                            :disabled="trackInsights.isLoading.value || trackInsights.isBusy.value"
                            class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                            title="Regenerate insights"
                            @click="track && trackInsights.regenerate(track)"
                        >
                            <RefreshCw
                                :class="{ 'animate-spin': trackInsights.isBusy.value }"
                                class="h-3.5 w-3.5"
                            />
                        </button>
                        <button
                            class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            aria-label="Close insights"
                            @click="emit('close')"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </header>

                <!-- Track info -->
                <div class="border-b border-border/60 px-4 py-3">
                    <p class="truncate text-sm font-medium">{{ track?.name }}</p>
                    <p class="truncate text-xs text-muted-foreground">{{ track?.artists[0]?.name }}</p>
                </div>

                <!-- Body -->
                <div class="flex-1 space-y-6 overflow-y-auto p-4">
                    <!-- Loading skeleton -->
                    <template v-if="trackInsights.isLoading.value">
                        <div v-for="i in 6" :key="i" class="space-y-2">
                            <div class="h-3 w-20 animate-pulse rounded bg-muted" />
                            <div class="h-4 w-full animate-pulse rounded bg-muted" />
                            <div class="h-4 w-3/4 animate-pulse rounded bg-muted" />
                        </div>
                    </template>

                    <!-- Error -->
                    <template v-else-if="trackInsights.hasFailed.value">
                        <div class="space-y-2 rounded-lg border border-red-800 bg-red-950/50 p-4">
                            <p class="text-sm text-red-400">Could not generate insights.</p>
                            <p v-if="trackInsights.errorMessage.value" class="text-xs text-red-500/80">
                                {{ trackInsights.errorMessage.value }}
                            </p>
                            <button
                                class="text-xs text-red-300 underline underline-offset-2"
                                @click="track && trackInsights.regenerate(track)"
                            >
                                Try again
                            </button>
                        </div>
                    </template>

                    <!-- Ready -->
                    <template v-else-if="trackInsights.isReady.value && trackInsights.localizedInsights.value">
                        <!-- About the track -->
                        <section class="space-y-2">
                            <div class="flex items-center gap-1.5">
                                <Music class="h-3.5 w-3.5 text-muted-foreground" />
                                <span class="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                                    About
                                </span>
                            </div>
                            <p class="text-sm leading-relaxed">{{ trackInsights.localizedInsights.value.summary }}</p>
                        </section>

                        <!-- Mood -->
                        <section class="space-y-2">
                            <div class="flex items-center gap-1.5">
                                <Smile class="h-3.5 w-3.5 text-muted-foreground" />
                                <span class="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                                    Mood
                                </span>
                            </div>
                            <span
                                class="inline-block rounded-full bg-gradient-to-r from-purple-600 to-pink-600 px-3 py-1 text-sm text-white"
                            >
                                {{ trackInsights.localizedInsights.value.mood }}
                            </span>
                        </section>

                        <!-- Themes -->
                        <section class="space-y-2">
                            <div class="flex items-center gap-1.5">
                                <Hash class="h-3.5 w-3.5 text-muted-foreground" />
                                <span class="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                                    Themes
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="theme in trackInsights.localizedInsights.value.themes"
                                    :key="theme"
                                    class="rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground"
                                >
                                    #{{ theme }}
                                </span>
                            </div>
                        </section>

                        <!-- Meaning -->
                        <section class="space-y-2">
                            <div class="flex items-center gap-1.5">
                                <BookOpen class="h-3.5 w-3.5 text-muted-foreground" />
                                <span class="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                                    Meaning
                                </span>
                            </div>
                            <p class="text-sm leading-relaxed italic">{{ trackInsights.localizedInsights.value.meaning }}</p>
                        </section>

                        <!-- Trivia -->
                        <section class="space-y-2">
                            <div class="flex items-center gap-1.5">
                                <Lightbulb class="h-3.5 w-3.5 text-muted-foreground" />
                                <span class="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                                    Trivia
                                </span>
                            </div>
                            <ul class="space-y-2">
                                <li
                                    v-for="fact in trackInsights.localizedInsights.value.trivia"
                                    :key="fact"
                                    class="border-l-2 border-purple-500 pl-3 text-sm leading-relaxed"
                                >
                                    {{ fact }}
                                </li>
                            </ul>
                        </section>

                        <!-- You might also like -->
                        <section class="space-y-2">
                            <div class="flex items-center gap-1.5">
                                <Compass class="h-3.5 w-3.5 text-muted-foreground" />
                                <span class="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                                    You might also like
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="item in trackInsights.localizedInsights.value.similar"
                                    :key="item"
                                    class="rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground"
                                >
                                    {{ item }}
                                </span>
                            </div>
                        </section>
                    </template>
                </div>

                <!-- Footer -->
                <footer class="border-t border-border/60 px-4 py-3">
                    <p class="text-[11px] text-muted-foreground">
                        AI-generated — may contain creative interpretations
                    </p>
                </footer>
            </aside>
        </Transition>
    </Teleport>
</template>
