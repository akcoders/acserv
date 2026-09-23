import * as bootstrap from 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import $ from 'jquery';
import Swal from 'sweetalert2';
import { bindAjaxActions, bindAjaxForms, confirmAjaxAction } from './ajax';
import { bindRichTables } from './admin-tables';
import { syncOfflineActions } from './offline';
import { bindPushSubscription } from './push';
import '../css/app.css';

window.bootstrap = bootstrap;
window.$ = window.jQuery = $;
window.Swal = Swal;
window.confirmAjaxAction = confirmAjaxAction;

$.ajaxSetup({
    headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
    },
});

$(document).ajaxError((_event, response) => {
    if (response.status === 419) {
        void Swal.fire({
            icon: 'warning',
            title: 'Session expired',
            text: 'Please refresh the page and try again.',
            confirmButtonText: 'Refresh',
        }).then(() => window.location.reload());
    }
});

$(() => {
    bindAjaxForms();
    bindAjaxActions();
    bindRichTables();
    bindPushSubscription();

    if ('serviceWorker' in navigator) {
        void navigator.serviceWorker.register('/sw.js');
    }

    void syncOfflineActions();
});

window.addEventListener('online', () => void syncOfflineActions());
