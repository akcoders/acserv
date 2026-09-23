import Swal from 'sweetalert2';

const databaseName = 'acserv-offline';
const storeName = 'actions';

function database() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(databaseName, 1);

        request.onupgradeneeded = () => {
            if (!request.result.objectStoreNames.contains(storeName)) {
                request.result.createObjectStore(storeName, { keyPath: 'id', autoIncrement: true });
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function transaction(db, mode, callback) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, mode);
        const store = tx.objectStore(storeName);
        const result = callback(store);

        tx.oncomplete = () => resolve(result);
        tx.onerror = () => reject(tx.error);
    });
}

export async function queueOfflineForm(form) {
    const db = await database();
    const entries = Array.from(new FormData(form).entries());

    await transaction(db, 'readwrite', (store) => store.add({
        url: form.getAttribute('action'),
        method: form.getAttribute('method') || 'POST',
        entries,
        queuedAt: new Date().toISOString(),
    }));
}

export async function syncOfflineActions() {
    if (!navigator.onLine || !('indexedDB' in window)) {
        return;
    }

    const db = await database();
    const actions = await new Promise((resolve, reject) => {
        const request = db.transaction(storeName, 'readonly').objectStore(storeName).getAll();
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
    let synced = 0;

    for (const action of actions) {
        const body = new FormData();
        action.entries.forEach(([key, value]) => body.append(key, value));
        const response = await fetch(action.url, {
            method: action.method,
            body,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
        }).catch(() => null);

        if (response === null || !response.ok) {
            break;
        }

        await transaction(db, 'readwrite', (store) => store.delete(action.id));
        synced += 1;
    }

    if (synced > 0) {
        await Swal.fire({
            icon: 'success',
            title: `${synced} offline action${synced === 1 ? '' : 's'} synced`,
            timer: 1800,
            showConfirmButton: false,
        });
        window.location.reload();
    }
}
