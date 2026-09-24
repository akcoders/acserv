let deferredInstallPrompt = null;

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
});

window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    document.querySelectorAll('[data-install-app]').forEach((button) => button.classList.add('d-none'));
});

function isInstalled() {
    return window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
}

function currentPlatform() {
    if (/iPad|iPhone|iPod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)) {
        return 'ios';
    }

    return /Android/i.test(navigator.userAgent) ? 'android' : 'desktop';
}

export function bindPwaInstall() {
    const sheet = document.getElementById('appInstallSheet');

    if (!sheet) {
        return;
    }

    if (isInstalled()) {
        document.querySelectorAll('[data-install-app]').forEach((button) => button.classList.add('d-none'));
    }

    document.querySelectorAll('[data-install-platform]').forEach((instruction) => {
        instruction.classList.toggle('d-none', instruction.dataset.installPlatform !== currentPlatform());
    });

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-install-app]');

        if (!button) {
            return;
        }

        if (isInstalled()) {
            document.querySelectorAll('[data-install-app]').forEach((installButton) => installButton.classList.add('d-none'));
            return;
        }

        if (deferredInstallPrompt) {
            const installPrompt = deferredInstallPrompt;
            deferredInstallPrompt = null;

            try {
                await installPrompt.prompt();
                await installPrompt.userChoice;
                return;
            } catch {
                // Some browsers revoke the prompt when the page loses focus.
            }
        }

        window.bootstrap.Offcanvas.getOrCreateInstance(sheet).show();
    });
}
