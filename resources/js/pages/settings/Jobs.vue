<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { edit as jobsEdit, dispatch as dispatchJob } from '@/routes/jobs';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Jobs',
                href: jobsEdit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Jobs settings" />

    <h1 class="sr-only">Jobs settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Manual jobs"
            description="Dispatch maintenance jobs on demand for diagnostics and data refresh."
        />

        <div class="space-y-4">
            <div class="rounded-xl border border-border/60 bg-card/50 p-4 shadow-card">
                <p class="text-sm font-medium text-foreground">Backfill artist genres</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Queue the Last.fm genre backfill for artists that still have empty genres.
                </p>

                <Form
                    :action="dispatchJob.url()"
                    method="post"
                    class="mt-4"
                    v-slot="{ processing }"
                >
                    <input type="hidden" name="job" value="backfill_artist_genres" />

                    <Button :disabled="processing" class="text-xs">
                        Dispatch genre backfill
                    </Button>
                </Form>
            </div>

            <div class="rounded-xl border border-border/60 bg-card/50 p-4 shadow-card">
                <p class="text-sm font-medium text-foreground">Sync current user Spotify data</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Queue a full sync for top artists, top tracks, and recent plays.
                </p>

                <Form
                    :action="dispatchJob.url()"
                    method="post"
                    class="mt-4"
                    v-slot="{ processing }"
                >
                    <input type="hidden" name="job" value="sync_user_spotify" />

                    <Button :disabled="processing" class="text-xs">
                        Dispatch Spotify sync
                    </Button>
                </Form>
            </div>
        </div>
    </div>
</template>
