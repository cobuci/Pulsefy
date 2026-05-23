<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import { getCsrfToken } from '@/utils/csrf';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Reverb test',
                href: '/settings/reverb-test',
            },
        ],
    },
});

const props = defineProps<{
    userId: number;
    dispatchToastUrl: string;
}>();

const userChannel = `reverb-test.${props.userId}`;

function dispatchTestJob() {
    void fetch(props.dispatchToastUrl, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error('dispatch_failed');
            }

            toast.info('Test job dispatched. Waiting for Reverb event...');
        })
        .catch(() => {
            toast.error('Could not dispatch test job.');
        });
}

function startListening() {
    if (typeof window === 'undefined' || !window.Echo) {
        toast.error('Echo client not available in browser context.');

        return;
    }

    const channel = window.Echo.channel(userChannel);

    channel.subscribed(() => {
        toast.info('Realtime listener connected.');
    });

    channel.listen(
        '.Reverb.TestToastBroadcasted',
        (event: { message: string }) => {
            toast.success(event.message);
        },
    );
}

function stopListening() {
    if (typeof window === 'undefined' || !window.Echo) {
        return;
    }

    window.Echo.leaveChannel(userChannel);
}

onMounted(() => {
    startListening();
});

onUnmounted(() => {
    stopListening();
});
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="Reverb toast test"
            description="Dispatch a queued job and verify realtime toast delivery through Reverb."
        />

        <div
            class="rounded-xl border border-border/60 bg-card/50 p-4 shadow-card"
        >
            <p class="mb-3 text-sm text-muted-foreground">
                Click to dispatch a test job. When the job runs, a Reverb event
                will trigger a toast on this page.
            </p>
            <button
                type="button"
                class="rounded-md border border-border px-3 py-2 text-xs font-medium transition-colors hover:bg-secondary"
                @click="dispatchTestJob"
            >
                Dispatch reverb toast job
            </button>
        </div>
    </div>
</template>
