import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

let echo = null;
let subscribedRunId = null;
let connectionStatusBound = false;

export function configureEcho() {
    const key = import.meta.env.VITE_REVERB_APP_KEY;
    if (!key) {
        reportStatus('Polling activo · Reverb no configurado');
        return null;
    }
    echo ??= new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
    return echo;
}

export function subscribeToSimulation(runId) {
    const instance = configureEcho();
    if (! instance || ! runId || subscribedRunId === runId) return false;
    if (subscribedRunId) instance.leave(`simulation.${subscribedRunId}`);
    subscribedRunId = runId;
    if (! connectionStatusBound) {
        instance.connector?.pusher?.connection?.bind('connected', () => reportStatus('Reverb conectado · polling de respaldo activo'));
        instance.connector?.pusher?.connection?.bind('error', () => reportStatus('Reverb no disponible · polling de respaldo activo'));
        connectionStatusBound = true;
    }
    instance.channel(`simulation.${runId}`).listen('.order.available', forward).listen('.order.expired', forward).listen('.ranking.updated', forward).listen('.event.applied', forward).listen('.surge.changed', forward).listen('.traffic.changed', forward).listen('.weather.changed', forward).listen('.closure.changed', forward).listen('.restaurant.delay.changed', forward).listen('.delivery.completed', forward).listen('.run.finished', forward);
    return true;
}

export function unsubscribeFromSimulation(runId = subscribedRunId) {
    if (!echo || !runId) return;
    echo.leave(`simulation.${runId}`);
    if (subscribedRunId === runId) subscribedRunId = null;
}

function forward(payload) {
    window.dispatchEvent(new CustomEvent('simulation-broadcast', { detail: payload }));
}

function reportStatus(message) {
    window.dispatchEvent(new CustomEvent('realtime-status', { detail: { message } }));
}

window.addEventListener('echo-subscribe', (event) => subscribeToSimulation(event.detail?.runId));

window.CourierEcho = { configureEcho, subscribeToSimulation, unsubscribeFromSimulation };
