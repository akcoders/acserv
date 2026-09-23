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
        Schema::create('report_schedules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->string('name', 160);
            $table->string('report_type', 60);
            $table->string('frequency', 30);
            $table->json('recipients');
            $table->json('filters')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $this->businessTimestamps($table);

            $table->index(['tenant_id', 'is_active', 'next_run_at'], 'report_schedules_due');
        });

        Schema::create('generated_reports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('report_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_type', 60);
            $table->string('status', 30)->default('PENDING');
            $table->date('period_starts_on')->nullable();
            $table->date('period_ends_on')->nullable();
            $table->json('parameters')->nullable();
            $table->string('disk', 40)->nullable();
            $table->text('path')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $this->businessTimestamps($table);

            $table->index(['tenant_id', 'status', 'created_at'], 'generated_reports_status');
        });

        Schema::create('system_backups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('PENDING');
            $table->string('disk', 40)->default('local');
            $table->text('path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $this->businessTimestamps($table);

            $table->index(['tenant_id', 'status', 'created_at'], 'system_backups_status');
        });

        Schema::create('analytics_snapshots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->date('snapshot_date');
            $table->string('metric', 100);
            $table->json('dimensions')->nullable();
            $table->decimal('value', 18, 4);
            $table->json('metadata')->nullable();
            $this->businessTimestamps($table);

            $table->index(['tenant_id', 'metric', 'snapshot_date'], 'analytics_metric_date');
        });

        Schema::create('security_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 80);
            $table->string('severity', 20)->default('INFO');
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $this->businessTimestamps($table);

            $table->index(['tenant_id', 'severity', 'occurred_at'], 'security_events_severity');
            $table->index(['tenant_id', 'event_type', 'occurred_at'], 'security_events_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('analytics_snapshots');
        Schema::dropIfExists('system_backups');
        Schema::dropIfExists('generated_reports');
        Schema::dropIfExists('report_schedules');
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
