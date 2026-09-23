import $ from 'jquery';
import Swal from 'sweetalert2';

function decodeKey(value) {
    const padding = '='.repeat((4 - (value.length % 4)) % 4);
    const base64 = (value + padding).replaceAll('-', '+').replaceAll('_', '/');

    return Uint8Array.from(window.atob(base64), (character) => character.charCodeAt(0));
}

export function bindPushSubscription() {
    const button = document.querySelector('[data-enable-push]');
    const publicKey = document.querySelector('meta[name="push-public-key"]')?.content;
    const endpoint = document.querySelector('meta[name="push-subscription-url"]')?.content;

    if (!button || !publicKey || !endpoint || !('serviceWorker' in navigator) || !('PushManager' in window)) {
        return;
    }

    button.classList.remove('d-none');
    button.addEventListener('click', async () => {
        try {
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                throw new Error('Notification permission was not granted.');
            }

            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: decodeKey(publicKey),
            });

            await $.ajax({
                url: endpoint,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(subscription.toJSON()),
            });

            button.classList.add('d-none');
            await Swal.fire({ icon: 'success', title: 'Notifications enabled', timer: 1500, showConfirmButton: false });
        } catch (error) {
            await Swal.fire({ icon: 'error', title: 'Could not enable notifications', text: error.message });
        }
    });
}
