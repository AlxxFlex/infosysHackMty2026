import './bootstrap';
import './map';
import './echo';

window.addEventListener('simulation-broadcast', () => window.Livewire?.dispatch('simulation-refresh'));
window.addEventListener('realtime-status', (event) => {
    const element = document.getElementById('realtime-status');
    if (element && event.detail?.message) element.textContent = event.detail.message;
});
