<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'manager_user_id', 'employee_code', 'designation', 'pay_grade', 'employment_type', 'monthly_salary', 'incentive_per_job', 'joined_on', 'left_on', 'notes'])]
class EmploymentProfile extends TenantModel
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:2',
            'incentive_per_job' => 'decimal:2',
            'joined_on' => 'date',
            'left_on' => 'date',
        ];
    }
}
