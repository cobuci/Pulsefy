import { createInertiaApp } from '@inertiajs/vue3';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

if (typeof window !== 'undefined') {
    const reverbHost = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
    const reverbPort = Number(import.meta.env.VITE_REVERB_PORT || 8080);
    const reverbScheme = (import.meta.env.VITE_REVERB_SCHEME || 'http').toLowerCase();
    const useTls = import.meta.env.DEV ? false : reverbScheme === 'https';

    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: reverbHost,
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: useTls,
        enabledTransports: useTls ? ['wss', 'ws'] : ['ws', 'wss'],
    });
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
