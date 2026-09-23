<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'branch_id', 'attendance_date', 'checked_in_at', 'checked_out_at', 'check_in_latitude', 'check_in_longitude', 'check_in_accuracy_metres', 'check_out_latitude', 'check_out_longitude', 'check_out_accuracy_metres', 'check_in_selfie_path', 'check_out_selfie_path', 'check_in_device_id', 'check_out_device_id', 'status', 'worked_minutes', 'notes'])]
class AttendanceRecord extends TenantModel
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'check_in_latitude' => 'decimal:7',
            'check_in_longitude' => 'decimal:7',
            'check_in_accuracy_metres' => 'decimal:2',
            'check_out_latitude' => 'decimal:7',
            'check_out_longitude' => 'decimal:7',
            'check_out_accuracy_metres' => 'decimal:2',
        ];
    }
}
