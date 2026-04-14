<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { redirect } from '@/actions/App/Http/Controllers/Auth/SpotifyController';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

defineOptions({
    inheritAttrs: false,
    layout: false,
});
</script>

<template>
    <Head title="Sign in" />

    <div
        class="flex min-h-dvh flex-col items-center justify-center bg-background px-6"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col items-center gap-8">
                <div class="flex flex-col items-center gap-3">
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary shadow-lg"
                    >
                        <AppLogoIcon
                            class="size-8 fill-current text-primary-foreground"
                        />
                    </div>
                    <div class="space-y-1 text-center">
                        <h1
                            class="text-2xl font-semibold tracking-tight text-foreground"
                        >
                            Welcome to Pulsefy
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            Your Spotify stats, beautifully visualized
                        </p>
                    </div>
                </div>

                <div
                    class="w-full rounded-2xl border border-border bg-card p-8 shadow-sm"
                >
                    <div class="flex flex-col gap-4">
                        <p class="text-center text-sm text-muted-foreground">
                            Sign in with your Spotify account to get started
                        </p>

                        <a
                            :href="redirect.url()"
                            class="flex w-full items-center justify-center gap-3 rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground shadow transition-colors hover:bg-primary-dark focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <svg
                                class="size-5 shrink-0"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"
                                />
                            </svg>
                            Continue with Spotify
                        </a>
                    </div>
                </div>

                <p class="text-center text-xs text-muted-foreground">
                    By signing in, you agree to our
                    <span class="font-medium text-foreground"
                        >Terms of Service</span
                    >
                    and
                    <span class="font-medium text-foreground"
                        >Privacy Policy</span
                    >
                </p>
            </div>
        </div>
    </div>
</template>
