<?php

namespace App\Models;

use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['invoice_id', 'payment_number', 'mode', 'status', 'amount', 'currency', 'reference', 'provider', 'provider_order_id', 'provider_payment_id', 'provider_signature_hash', 'provider_metadata', 'paid_at', 'failed_at'])]
#[Hidden(['provider_signature_hash'])]
class Payment extends TenantModel
{
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'mode' => PaymentMode::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'provider_metadata' => 'array',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
