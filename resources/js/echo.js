import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * The settings come from the page, never from import.meta.env.
 *
 * demgem is self-hosted: the image is built once and runs on somebody else's
 * machine, so a host baked into the bundle at build time points at the wrong
 * box. The layout renders window.demgem.reverb from config('broadcasting.client').
 *
 * No key means no Echo. An instance running without Reverb still works; it just
 * waits for the poll instead of hearing the push.
 */
const reverb = window.demgem?.reverb;

if (reverb?.key) {
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverb.key,
        wsHost: reverb.host,
        wsPort: reverb.port,
        wssPort: reverb.port,
        forceTLS: reverb.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
