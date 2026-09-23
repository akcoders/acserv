import $ from 'jquery';
import Swal from 'sweetalert2';
import { queueOfflineForm } from './offline';

function clearValidation($form) {
    $form.find('.is-invalid').removeClass('is-invalid');
    $form.find('[data-validation-error]').remove();
}

function showValidationErrors($form, errors) {
    Object.entries(errors).forEach(([field, messages]) => {
        const selector = `[name="${CSS.escape(field)}"]`;
        const $input = $form.find(selector).first();

        if ($input.length === 0) {
            return;
        }

        $input.addClass('is-invalid');
        $('<div>', {
            class: 'invalid-feedback',
            'data-validation-error': true,
            text: messages[0],
        }).insertAfter($input);
    });
}

export function bindAjaxForms(root = document) {
    $(root)
        .off('submit.acserv', 'form[data-ajax]')
        .on('submit.acserv', 'form[data-ajax]', function (event) {
            event.preventDefault();

            const $form = $(this);
            const $submit = $form.find('[type="submit"]');
            const originalLabel = $submit.html();

            clearValidation($form);
            $submit.prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Saving…',
            );

            $.ajax({
                url: $form.attr('action'),
                method: $form.attr('method') ?? 'POST',
                data: new FormData(this),
                contentType: false,
                processData: false,
            })
                .done((response) => {
                    $form.trigger('acserv:ajax-success', [response]);

                    if (($form.attr('action') ?? '').endsWith('/logout') && navigator.serviceWorker?.controller) {
                        navigator.serviceWorker.controller.postMessage({ type: 'CLEAR_PRIVATE' });
                    }

                    void Swal.fire({
                        icon: 'success',
                        title: response.message ?? 'Saved successfully',
                        timer: 1500,
                        showConfirmButton: false,
                    }).then(() => {
                        if (response.redirect) {
                            window.location.assign(response.redirect);
                        } else if (response.reload) {
                            window.location.reload();
                        }
                    });
                })
                .fail((response) => {
                    if ($form.is('[data-offline-queue]') && (response.status === 0 || ! navigator.onLine)) {
                        void queueOfflineForm(this).then(() => Swal.fire({
                            icon: 'info',
                            title: 'Saved offline',
                            text: 'This action will sync automatically when your connection returns.',
                        }));

                        return;
                    }

                    if (response.status === 422 && response.responseJSON?.errors) {
                        showValidationErrors($form, response.responseJSON.errors);
                        return;
                    }

                    void Swal.fire({
                        icon: 'error',
                        title: 'Request failed',
                        text: response.responseJSON?.message ?? 'Please try again.',
                    });
                })
                .always(() => $submit.prop('disabled', false).html(originalLabel));
        });
}

export function bindAjaxActions(root = document) {
    $(root)
        .off('click.acserv', '[data-confirm-ajax]')
        .on('click.acserv', '[data-confirm-ajax]', function () {
            void confirmAjaxAction({
                url: this.dataset.url,
                method: this.dataset.method ?? 'DELETE',
                title: this.dataset.title ?? 'Are you sure?',
                text: this.dataset.text ?? 'This action cannot be undone.',
            });
        });
}

export async function confirmAjaxAction({ url, method = 'DELETE', title, text }) {
    const result = await Swal.fire({
        icon: 'warning',
        title,
        text,
        showCancelButton: true,
        confirmButtonText: 'Yes, continue',
        confirmButtonColor: '#dc3545',
    });

    if (!result.isConfirmed) {
        return;
    }

    try {
        const response = await $.ajax({ url, method });

        await Swal.fire({
            icon: 'success',
            title: response.message ?? 'Completed successfully',
            timer: 1500,
            showConfirmButton: false,
        });

        window.location.reload();
    } catch (error) {
        await Swal.fire({
            icon: 'error',
            title: 'Request failed',
            text: error.responseJSON?.message ?? 'Please try again.',
        });
    }
}
