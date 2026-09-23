<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('attendance_date');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();
            $table->decimal('check_in_accuracy_metres', 8, 2)->nullable();
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->decimal('check_out_accuracy_metres', 8, 2)->nullable();
            $table->string('check_in_selfie_path')->nullable();
            $table->string('check_out_selfie_path')->nullable();
            $table->string('check_in_device_id', 191)->nullable();
            $table->string('check_out_device_id', 191)->nullable();
            $table->string('status', 30)->default('PRESENT');
            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->text('notes')->nullable();
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'user_id', 'attendance_date'], 'attendance_user_day');
            $table->index(['tenant_id', 'attendance_date', 'status'], 'attendance_day_status');
        });

        Schema::create('leave_balances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('leave_type', 40);
            $table->decimal('opening_days', 6, 2)->default(0);
            $table->decimal('accrued_days', 6, 2)->default(0);
            $table->decimal('used_days', 6, 2)->default(0);
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'user_id', 'year', 'leave_type'], 'leave_balance_user_year_type');
        });

        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('leave_type', 40);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('days', 6, 2);
            $table->text('reason');
            $table->string('status', 30)->default('PENDING');
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $this->businessTimestamps($table);

            $table->index(['tenant_id', 'status', 'starts_on'], 'leave_requests_status');
            $table->index(['tenant_id', 'user_id', 'starts_on'], 'leave_requests_user');
        });

        Schema::create('payout_cycles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->string('cycle_number', 40);
            $table->string('type', 30);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 30)->default('DRAFT');
            $table->decimal('gross_total', 14, 2)->default(0);
            $table->decimal('deduction_total', 14, 2)->default(0);
            $table->decimal('net_total', 14, 2)->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'cycle_number']);
            $table->index(['tenant_id', 'status', 'starts_on'], 'payout_cycles_status');
        });

        Schema::create('payout_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('payout_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('job_count')->default(0);
            $table->decimal('worked_hours', 8, 2)->default(0);
            $table->decimal('base_amount', 14, 2)->default(0);
            $table->decimal('incentive_amount', 14, 2)->default(0);
            $table->decimal('penalty_amount', 14, 2)->default(0);
            $table->decimal('deduction_amount', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->json('details')->nullable();
            $table->string('status', 30)->default('PENDING');
            $this->businessTimestamps($table);

            $table->unique(['payout_cycle_id', 'user_id']);
            $table->index(['tenant_id', 'user_id', 'status'], 'payout_lines_user');
        });

        Schema::create('payout_disputes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('payout_line_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('OPEN');
            $table->text('reason');
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $this->businessTimestamps($table);

            $table->index(['tenant_id', 'status', 'created_at'], 'payout_disputes_status');
        });

        Schema::create('technician_scorecards', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->date('period_starts_on');
            $table->date('period_ends_on');
            $table->unsignedInteger('completed_jobs')->default(0);
            $table->decimal('average_rating', 4, 2)->default(0);
            $table->decimal('rework_percentage', 5, 2)->default(0);
            $table->decimal('sla_percentage', 5, 2)->default(0);
            $table->decimal('attendance_percentage', 5, 2)->default(0);
            $table->decimal('score', 6, 2)->default(0);
            $table->json('metrics')->nullable();
            $table->timestamp('computed_at');
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'user_id', 'period_starts_on', 'period_ends_on'], 'scorecards_user_period');
            $table->index(['tenant_id', 'period_ends_on', 'score'], 'scorecards_ranking');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technician_scorecards');
        Schema::dropIfExists('payout_disputes');
        Schema::dropIfExists('payout_lines');
        Schema::dropIfExists('payout_cycles');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('attendance_records');
    }

    private function tenantKey(Blueprint $table): void
    {
        $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
    }

    private function businessTimestamps(Blueprint $table): void
    {
        $table->ulid('created_by')->nullable();
        $table->ulid('updated_by')->nullable();
        $table->timestamps();
        $table->softDeletes();
    }
};
