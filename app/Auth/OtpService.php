<?php

namespace App\Auth;

use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use App\Models\OtpChallenge;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function __construct(
        private readonly OtpCodeGenerator $codeGenerator,
        private readonly OtpSender $sender,
    ) {}

    public function issue(User $user, OtpChannel $channel, OtpPurpose $purpose, string $ipAddress): string
    {
        $destination = $this->destination($user, $channel);
        $code = $this->codeGenerator->generate();

        DB::transaction(function () use ($user, $channel, $purpose, $ipAddress, $destination, $code): void {
            OtpChallenge::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            OtpChallenge::query()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'channel' => $channel,
                'purpose' => $purpose,
                'destination_hash' => $this->hashValue($destination),
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(config('acserv.otp.ttl_minutes')),
                'requested_ip_hash' => $this->hashValue($ipAddress),
            ]);
        });

        $this->sender->send($user, $channel, $code);

        return $code;
    }

    /**
     * @throws ValidationException
     */
    public function verify(User $user, OtpChannel $channel, OtpPurpose $purpose, string $code): void
    {
        $destinationHash = $this->hashValue($this->destination($user, $channel));

        $verified = DB::transaction(function () use ($user, $purpose, $destinationHash, $code): bool {
            $challenge = OtpChallenge::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->where('destination_hash', $destinationHash)
                ->whereNull('consumed_at')
                ->latest()
                ->lockForUpdate()
                ->first();

            if ($challenge === null || ! $challenge->isUsable()) {
                return false;
            }

            $challenge->increment('attempts');

            if (! Hash::check($code, $challenge->code_hash)) {
                return false;
            }

            $challenge->forceFill(['consumed_at' => now()])->save();

            return true;
        });

        if (! $verified) {
            throw ValidationException::withMessages([
                'code' => __('auth.otp_invalid'),
            ]);
        }
    }

    private function destination(User $user, OtpChannel $channel): string
    {
        $destination = $channel === OtpChannel::Email ? $user->email : $user->phone;

        if ($destination === null) {
            throw ValidationException::withMessages([
                'login' => __('auth.login_unavailable'),
            ]);
        }

        return mb_strtolower(trim($destination));
    }

    private function hashValue(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
