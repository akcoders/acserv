<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PasskeyCredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'credential_id', 'public_key', 'sign_count', 'transports', 'aaguid', 'name', 'last_used_at', 'revoked_at'])]
#[Hidden(['public_key'])]
class PasskeyCredential extends Model
{
    /** @use HasFactory<PasskeyCredentialFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'sign_count' => 'integer',
            'transports' => 'array',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
