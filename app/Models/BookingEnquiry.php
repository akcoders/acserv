<?php

namespace App\Models;

use App\Enums\EnquiryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cms_service_id', 'converted_booking_id', 'name', 'phone', 'email', 'source', 'status', 'service_type', 'postal_code', 'message', 'utm_source', 'utm_medium', 'utm_campaign', 'contacted_at', 'assigned_to'])]
class BookingEnquiry extends TenantModel
{
    public function service(): BelongsTo
    {
        return $this->belongsTo(CmsService::class, 'cms_service_id');
    }

    public function convertedBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'converted_booking_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => EnquiryStatus::class,
            'contacted_at' => 'datetime',
        ];
    }
}
