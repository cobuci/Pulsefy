import { computed, ref } from 'vue';
import { useHttp } from '@inertiajs/vue3';
import { devices, transfer } from '@/routes/player';
import { getCsrfToken } from '@/utils/csrf';
import type { SpotifyDevice } from '@/types/spotify';

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
    const devicesHttp = useHttp<{ devices: SpotifyDevice[] }>();
    const selectedDeviceId = ref<string>('');
    const transferBusy = ref(false);
    const devicesOpen = ref(false);

    const availableDevices = computed(
        () => devicesHttp.response?.devices ?? [],
    );

    const selectableDevices = computed(() => {
        const spotifyDevices = availableDevices.value.filter(
            (device) => device.id && !device.is_restricted,
        );

        if (!localPlayerReady.value || !localDeviceId.value) {
            return spotifyDevices;
        }

        const hasLocal = spotifyDevices.some(
            (device) => device.id === localDeviceId.value,
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

    async function refreshDevices() {
        try {
            await devicesHttp.get(devices.url());
            onStatus('');

            const active = selectableDevices.value.find(
                (device) => device.is_active,
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
            await refreshDevices();
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
