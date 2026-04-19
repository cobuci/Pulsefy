import { useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { devices, transfer } from '@/routes/player';
import type { SpotifyDevice } from '@/types/spotify';
import { getCsrfToken } from '@/utils/csrf';

export function useSpotifyDevices(
    localPlayerReady: ReturnType<typeof ref<boolean>>,
    localDeviceId: ReturnType<typeof ref<string | null>>,
    localPlayer: ReturnType<
        typeof ref<{ activateElement(): Promise<void> } | null>
    >,
    onStatus: (message: string) => void,
    isPlaying: () => boolean,
    onRefreshed: () => void,
) {
    const DEVICES_TTL_MS = 10_000;

    const devicesHttp = useHttp<{ devices: SpotifyDevice[] }>();
    const selectedDeviceId = ref<string>('');
    const transferBusy = ref(false);
    const devicesOpen = ref(false);
    const lastFetchedAt = ref<number>(0);

    const availableDevices = computed(
        () =>
            ((devicesHttp.response as { devices?: SpotifyDevice[] } | null)
                ?.devices ?? []),
    );

    const selectableDevices = computed(() => {
        const spotifyDevices = availableDevices.value.filter(
            (device: SpotifyDevice) => device.id && !device.is_restricted,
        );

        if (!localPlayerReady.value || !localDeviceId.value) {
            return spotifyDevices;
        }

        const hasLocal = spotifyDevices.some(
            (device: SpotifyDevice) => device.id === localDeviceId.value,
        );

        if (hasLocal) {
            return spotifyDevices;
        }

        return [
            {
                id: localDeviceId.value,
                is_active: false,
                is_private_session: false,
                is_restricted: false,
                name: 'Pulsefy Web Player',
                type: 'computer',
                volume_percent: null,
                supports_volume: true,
            },
            ...spotifyDevices,
        ];
    });

    async function refreshDevices(force = false) {
        const now = Date.now();

        if (!force && now - lastFetchedAt.value < DEVICES_TTL_MS) {
            return;
        }

        try {
            await devicesHttp.get(devices.url());
            lastFetchedAt.value = now;
            onStatus('');

            const active = selectableDevices.value.find(
                (device: SpotifyDevice) => device.is_active,
            );

            if (active?.id) {
                selectedDeviceId.value = active.id;

                return;
            }

            if (!selectedDeviceId.value && selectableDevices.value[0]?.id) {
                selectedDeviceId.value = selectableDevices.value[0].id;
            }
        } catch {
            onStatus('Unable to load Spotify devices.');
        }
    }

    async function onTransferToSelectedDevice() {
        if (!selectedDeviceId.value || transferBusy.value) {
            return;
        }

        transferBusy.value = true;
        onStatus('');

        try {
            if (
                localPlayer.value &&
                localDeviceId.value &&
                selectedDeviceId.value === localDeviceId.value
            ) {
                await localPlayer.value.activateElement();
            }

            const response = await fetch(transfer.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    device_id: selectedDeviceId.value,
                    play: isPlaying(),
                }),
            });

            if (!response.ok) {
                onStatus('Could not switch device.');

                return;
            }

            onStatus('Playback device updated.');
            await refreshDevices(true);
            onRefreshed();
            devicesOpen.value = false;
        } catch {
            onStatus('Could not switch device.');
        } finally {
            transferBusy.value = false;
        }
    }

    return {
        devicesHttp,
        selectedDeviceId,
        transferBusy,
        devicesOpen,
        availableDevices,
        selectableDevices,
        refreshDevices,
        onTransferToSelectedDevice,
    };
}
