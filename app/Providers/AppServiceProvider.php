<?php

namespace App\Providers;

use App\Auth\NotificationOtpSender;
use App\Auth\OtpCodeGenerator;
use App\Auth\OtpSender;
use App\Auth\SecureOtpCodeGenerator;
use App\Services\Billing\PaymentGateway;
use App\Services\Billing\RazorpayGateway;
use App\Support\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->bind(OtpCodeGenerator::class, SecureOtpCodeGenerator::class);
        $this->app->bind(OtpSender::class, NotificationOtpSender::class);
        $this->app->bind(PaymentGateway::class, RazorpayGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Model::preventLazyLoading(! $this->app->isProduction());

        RateLimiter::for('otp-request', fn (Request $request): Limit => Limit::perMinute(3)
            ->by($this->otpRateLimitKey($request)));

        RateLimiter::for('otp-verify', fn (Request $request): Limit => Limit::perMinute(5)
            ->by($this->otpRateLimitKey($request)));
    }

    private function otpRateLimitKey(Request $request): string
    {
        return hash('sha256', implode('|', [
            mb_strtolower($request->string('tenant')->toString()),
            mb_strtolower($request->string('login')->toString()),
            $request->ip() ?? 'unknown',
        ]));
    }
}
