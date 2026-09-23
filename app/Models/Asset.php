<?php

namespace App\Models;

use App\Enums\AssetStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['customer_id', 'branch_id', 'name', 'brand', 'model', 'serial_number', 'capacity', 'asset_type', 'install_date', 'status', 'metadata'])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use BelongsToTenant, HasFactory, HasUlids, SoftDeletes;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function warranties(): HasMany
    {
        return $this->hasMany(Warranty::class);
    }

    public function serviceReminders(): HasMany
    {
        return $this->hasMany(ServiceReminder::class);
    }

    protected function casts(): array
    {
        return [
            'install_date' => 'date',
            'status' => AssetStatus::class,
            'metadata' => 'array',
        ];
    }
}
