<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use App\Enums\Role;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['email', 'phone', 'role', 'status', 'token_hash', 'expires_at', 'accepted_at'])]
#[Hidden(['token_hash'])]
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use BelongsToTenant, HasFactory, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'status' => InvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
