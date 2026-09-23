import * as bootstrap from 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import $ from 'jquery';
import Swal from 'sweetalert2';
import '../css/installer.css';

window.bootstrap = bootstrap;
window.$ = window.jQuery = $;
window.Swal = Swal;

function clearValidation($form) {
    $form.find('.is-invalid').removeClass('is-invalid').removeAttr('aria-invalid');
    $form.find('[data-installer-error]').remove();
}

function showValidationErrors($form, errors) {
    if (!errors || typeof errors !== 'object') {
        return;
    }

    Object.entries(errors).forEach(([field, messages]) => {
        const $input = $form.find('[name]').filter((_index, element) => element.name === field).first();

        if (!$input.length) {
            return;
        }

        const message = Array.isArray(messages) ? messages[0] : messages;

        $input.addClass('is-invalid').attr('aria-invalid', 'true');
        $('<div>', {
            class: 'invalid-feedback d-block',
            'data-installer-error': '',
        }).text(String(message ?? 'Please check this field.')).insertAfter($input);
    });

    $form.find('.is-invalid').first().trigger('focus');
}

function setStatus($form, message, state = 'working') {
    const $status = $form.find('[data-install-status], #installer-status').first().add(
        $('#installer-status').first(),
    ).first();

    if ($status.length) {
        $status.text(message).attr('data-state', state);
    }
}

function toggleDemoFields() {
    const enabled = $('#installer-form [name="demo"]').is(':checked');
    const $form = $('#installer-form');
    const $fields = $form.find('[data-demo-fields], #demo-fields');
    const $liveFields = $form.find('[data-live-fields]');

    $form.find('[name="company"], [name="owner_email"]').prop('required', !enabled);
    $liveFields.prop('hidden', enabled).toggleClass('d-none', enabled);
    $liveFields.find('input, select, textarea').prop('disabled', enabled);

    $fields.prop('hidden', !enabled).toggleClass('d-none', !enabled);
    $fields.find('input, select, textarea').each((_index, element) => {
        if (element.dataset.installerAlwaysEnabled === 'true') {
            return;
        }

        element.disabled = !enabled;

        if (element.name.startsWith('demo_')) {
            element.required = enabled;
        }
    });
}

$(() => {
    const $form = $('#installer-form');

    if (!$form.length) {
        return;
    }

    $('#installer-form [name="demo"]').on('change', toggleDemoFields);
    toggleDemoFields();

    $form.on('submit.installer', function (event) {
        event.preventDefault();

        if (!this.reportValidity()) {
            return;
        }

        clearValidation($form);

        const $submit = $form.find('[type="submit"]').first();
        const originalHtml = $submit.html();
        $submit.prop('disabled', true).attr('aria-busy', 'true');
        $submit.empty().append(
            $('<span>', { class: 'spinner-border spinner-border-sm me-2', 'aria-hidden': 'true' }),
            document.createTextNode('Installing…'),
        );
        setStatus($form, 'Creating the database, workspace, and demo records. Please keep this tab open.');

        void Swal.fire({
            title: 'Installing ACServ ERP',
            text: 'We are creating your workspace and database tables. Please keep this tab open.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading(),
        });

        $.ajax({
            url: $form.attr('action') || window.location.href,
            method: ($form.attr('method') || 'POST').toUpperCase(),
            data: new FormData(this),
            contentType: false,
            processData: false,
            dataType: 'json',
            headers: { Accept: 'application/json' },
        })
            .done((response) => {
                Swal.close();

                if (response?.success === false) {
                    showValidationErrors($form, response.errors);
                    setStatus($form, response.message || 'Installation could not finish.', 'error');
                    void Swal.fire({
                        icon: 'error',
                        title: 'Installation not completed',
                        text: response.message || 'Review the details and try again.',
                    });
                    return;
                }

                setStatus($form, 'Installation completed successfully.', 'success');
                void Swal.fire({
                    icon: 'success',
                    title: 'Your ERP is ready',
                    text: response?.message || 'Installation completed. You can now sign in.',
                    confirmButtonText: response?.redirect ? 'Go to login' : 'Done',
                }).then(() => {
                    if (response?.redirect) {
                        window.location.assign(response.redirect);
                    }
                });
            })
            .fail((request) => {
                Swal.close();
                const payload = request.responseJSON;
                const message = payload?.message || payload?.error || 'Check your server settings and try again.';

                showValidationErrors($form, payload?.errors);
                setStatus($form, message, 'error');
                void Swal.fire({
                    icon: 'error',
                    title: 'Installation not completed',
                    text: message,
                });
            })
            .always(() => {
                $submit.prop('disabled', false).removeAttr('aria-busy').html(originalHtml);
            });
    });
});
