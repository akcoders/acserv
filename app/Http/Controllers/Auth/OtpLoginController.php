<?php

namespace App\Http\Controllers\Auth;

use App\Auth\OtpService;
use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Security\SecurityEventRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OtpLoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login', [
            'demoLogin' => app()->isLocal() ? config('acserv.demo_login') : null,
        ]);
    }

    public function store(RequestOtpRequest $request, OtpService $otpService, SecurityEventRecorder $security): JsonResponse
    {
        $channel = $request->enum('channel', OtpChannel::class);
        $debugOtp = null;
        $user = $this->findUser(
            $request->string('tenant')->toString(),
            $request->string('login')->toString(),
            $channel,
        );

        if ($user === null && app()->isLocal()) {
            throw ValidationException::withMessages([
                'login' => __('auth.local_account_not_found'),
            ]);
        }

        if ($user !== null) {
            $issuedCode = $otpService->issue(
                $user,
                $channel,
                OtpPurpose::Login,
                $request->ip() ?? 'unknown',
            );
            $security->record($user, $request, 'OTP_REQUESTED', metadata: ['channel' => $channel->value]);

            if (app()->isLocal() && config('mail.default') === 'log') {
                $debugOtp = $issuedCode;
            }
        }

        $response = [
            'message' => $debugOtp === null ? __('auth.code_sent') : __('auth.local_code_ready'),
            'show_verification' => true,
        ];

        if ($debugOtp !== null) {
            $response['debug_otp'] = $debugOtp;
        }

        return response()->json($response);
    }

    public function verify(VerifyOtpRequest $request, OtpService $otpService, SecurityEventRecorder $security): JsonResponse
    {
        $channel = $request->enum('channel', OtpChannel::class);
        $user = $this->findUser(
            $request->string('tenant')->toString(),
            $request->string('login')->toString(),
            $channel,
        );

        if ($user === null) {
            throw ValidationException::withMessages([
                'code' => __('auth.otp_invalid'),
            ]);
        }

        try {
            $otpService->verify(
                $user,
                $channel,
                OtpPurpose::Login,
                $request->string('code')->toString(),
            );
        } catch (ValidationException $exception) {
            $security->record($user, $request, 'OTP_LOGIN_FAILED', 'WARNING', ['channel' => $channel->value]);

            throw $exception;
        }

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $security->record($user, $request, 'LOGIN_SUCCEEDED', metadata: ['channel' => $channel->value]);

        return response()->json([
            'message' => __('auth.signed_in'),
            'redirect' => match ($user->role) {
                Role::Technician => route('technician.dashboard'),
                Role::Customer => route('customer.dashboard'),
                default => route('admin.dashboard'),
            },
        ]);
    }

    public function destroy(Request $request, SecurityEventRecorder $security): JsonResponse
    {
        $user = $request->user();

        if ($user !== null) {
            $security->record($user, $request, 'LOGOUT');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => __('auth.signed_out'),
            'redirect' => route('home'),
        ]);
    }

    private function findUser(string $tenantSlug, string $login, OtpChannel $channel): ?User
    {
        $tenant = Tenant::query()
            ->where('slug', $tenantSlug)
            ->where('status', TenantStatus::Active)
            ->first();

        if ($tenant === null) {
            return null;
        }

        $column = $channel === OtpChannel::Email ? 'email' : 'phone';

        return User::query()
            ->forTenant($tenant)
            ->where('status', UserStatus::Active)
            ->where(function (Builder $query) use ($column, $login): void {
                $query->where($column, $login);
            })
            ->first();
    }
}
