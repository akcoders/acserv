import $ from 'jquery';
import Swal from 'sweetalert2';

function decodeKey(value) {
    const padding = '='.repeat((4 - (value.length % 4)) % 4);
    const base64 = (value + padding).replaceAll('-', '+').replaceAll('_', '/');

    return Uint8Array.from(window.atob(base64), (character) => character.charCodeAt(0));
}

function bindLegacyPushSubscription(button) {
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

function bindOneSignalPush(button, appId, externalId) {
    if (!window.isSecureContext || !('serviceWorker' in navigator) || !('Notification' in window)) {
        return;
    }

    window.OneSignalDeferred = window.OneSignalDeferred || [];
    window.OneSignalDeferred.push(async (oneSignal) => {
        try {
            await oneSignal.init({
                appId,
                serviceWorkerPath: 'onesignal/OneSignalSDKWorker.js',
                serviceWorkerParam: { scope: '/onesignal/' },
                promptOptions: { slidedown: { prompts: [{ type: 'push', autoPrompt: false }] } },
            });
            await oneSignal.login(externalId);

            if (!oneSignal.Notifications.isPushSupported()) {
                return;
            }

            if (!oneSignal.User.PushSubscription.optedIn) {
                button.classList.remove('d-none');
            }

            button.addEventListener('click', async () => {
                try {
                    await oneSignal.User.PushSubscription.optIn();

                    if (!oneSignal.Notifications.permission) {
                        throw new Error('Notification permission was not granted. Allow notifications in your browser settings.');
                    }

                    button.classList.add('d-none');
                    await Swal.fire({ icon: 'success', title: 'Notifications enabled', timer: 1500, showConfirmButton: false });
                } catch (error) {
                    await Swal.fire({ icon: 'error', title: 'Could not enable notifications', text: error.message });
                }
            });

            const logoutForm = document.querySelector('form[action$="/logout"]');
            logoutForm?.addEventListener('submit', async (event) => {
                if (logoutForm.dataset.onesignalLogout === 'complete') {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();

                await Promise.race([
                    (async () => {
                        await oneSignal.User.PushSubscription.optOut();
                        await oneSignal.logout();
                    })().catch(() => {}),
                    new Promise((resolve) => window.setTimeout(resolve, 1800)),
                ]);

                logoutForm.dataset.onesignalLogout = 'complete';
                logoutForm.requestSubmit(event.submitter || undefined);
            }, true);
        } catch (error) {
            console.warn('OneSignal could not start:', error);
        }
    });

    const script = document.createElement('script');
    script.src = 'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js';
    script.defer = true;
    script.addEventListener('error', () => console.warn('OneSignal SDK could not load.'));
    document.head.append(script);
}

export function bindPushSubscription() {
    const button = document.querySelector('[data-enable-push]');

    if (!button) {
        return;
    }

    const appId = document.querySelector('meta[name="onesignal-app-id"]')?.content;
    const externalId = document.querySelector('meta[name="onesignal-external-id"]')?.content;

    if (appId && externalId) {
        bindOneSignalPush(button, appId, externalId);
        return;
    }

    bindLegacyPushSubscription(button);
}
