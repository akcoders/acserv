@extends('layouts.app')

@section('title', __('auth.login_title').' — '.__('app.name'))

@section('content')
    <section class="auth-shell d-flex align-items-center py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-9 col-lg-6 col-xl-5">
                    <div class="card content-card">
                        <div class="card-body p-4 p-lg-5">
                            <div class="text-center mb-4">
                                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary fs-2 mb-3" style="width: 4rem; height: 4rem">
                                    <i class="bi bi-shield-lock" aria-hidden="true"></i>
                                </span>
                                <h1 class="h3 fw-bold">{{ __('auth.login_title') }}</h1>
                                <p class="text-secondary mb-0">{{ __('auth.login_help') }}</p>
                            </div>

                            @if ($demoLogin !== null)
                                <div class="alert alert-light border small" role="note">
                                    <div class="fw-semibold mb-1">{{ __('auth.local_demo_login') }}</div>
                                    <div>{{ __('auth.local_workspace') }} <code>{{ $demoLogin['workspace'] }}</code></div>
                                    <div>{{ __('auth.local_owner_email') }} <code>{{ $demoLogin['owner_email'] }}</code></div>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('login.otp.store') }}" data-ajax id="request-otp-form">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label" for="tenant">{{ __('auth.tenant') }}</label>
                                    <input class="form-control form-control-lg" id="tenant" name="tenant" value="{{ $demoLogin['workspace'] ?? '' }}" placeholder="acserv-demo" required autocomplete="organization" spellcheck="false">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="login">{{ __('auth.login') }}</label>
                                    <input class="form-control form-control-lg" id="login" name="login" value="{{ $demoLogin['owner_email'] ?? '' }}" placeholder="owner@acserv.test" required autocomplete="username" spellcheck="false">
                                </div>
                                <fieldset class="mb-4">
                                    <legend class="form-label fs-6">{{ __('auth.channel') }}</legend>
                                    <div class="d-flex gap-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="channel" id="channel-email" value="EMAIL" checked>
                                            <label class="form-check-label" for="channel-email">{{ __('auth.email') }}</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="channel" id="channel-sms" value="SMS">
                                            <label class="form-check-label" for="channel-sms">{{ __('auth.sms') }}</label>
                                        </div>
                                    </div>
                                </fieldset>
                                <button class="btn btn-primary btn-lg w-100" type="submit">
                                    {{ __('auth.send_code') }}
                                </button>
                            </form>

                            <form method="POST" action="{{ route('login.otp.verify') }}" data-ajax id="verify-otp-form" class="d-none">
                                @csrf
                                <input type="hidden" name="tenant">
                                <input type="hidden" name="login">
                                <input type="hidden" name="channel">
                                <div class="alert alert-info d-none" id="local-test-otp" role="status">
                                    {{ __('auth.local_test_code') }}
                                    <strong class="font-monospace" data-local-test-otp></strong>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label" for="code">{{ __('auth.code') }}</label>
                                    <input class="form-control form-control-lg text-center fs-3 font-monospace" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code">
                                </div>
                                <button class="btn btn-primary btn-lg w-100" type="submit">
                                    {{ __('auth.verify_code') }}
                                </button>
                                <button class="btn btn-link w-100 mt-2" type="button" id="change-login">
                                    {{ __('auth.login') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const $ = window.jQuery;
            const $requestForm = $('#request-otp-form');
            const $verifyForm = $('#verify-otp-form');
            const $localTestOtp = $('#local-test-otp');
            const $codeInput = $verifyForm.find('[name="code"]');

            $requestForm.on('acserv:ajax-success', (_event, response) => {
                if (! response.show_verification) {
                    return;
                }

                $verifyForm.find('[name="tenant"]').val($requestForm.find('[name="tenant"]').val());
                $verifyForm.find('[name="login"]').val($requestForm.find('[name="login"]').val());
                $verifyForm.find('[name="channel"]').val($requestForm.find('[name="channel"]:checked').val());
                $requestForm.addClass('d-none');
                $verifyForm.removeClass('d-none');

                if (response.debug_otp) {
                    $localTestOtp.find('[data-local-test-otp]').text(response.debug_otp);
                    $localTestOtp.removeClass('d-none');
                    $codeInput.val(response.debug_otp);
                } else {
                    $localTestOtp.addClass('d-none');
                    $codeInput.val('');
                }

                $codeInput.trigger('focus');
            });

            $('#change-login').on('click', () => {
                $verifyForm.addClass('d-none');
                $requestForm.removeClass('d-none');
                $localTestOtp.addClass('d-none').find('[data-local-test-otp]').text('');
                $codeInput.val('');
            });
        });
    </script>
@endpush
