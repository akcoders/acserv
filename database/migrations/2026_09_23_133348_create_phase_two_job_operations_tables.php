<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jobs') && Schema::hasColumn('jobs', 'queue') && ! Schema::hasTable('queue_jobs')) {
            Schema::rename('jobs', 'queue_jobs');
        }

        Schema::create('technician_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->json('skills')->nullable();
            $table->json('service_zones')->nullable();
            $table->boolean('is_available')->default(true);
            $table->decimal('home_latitude', 10, 7)->nullable();
            $table->decimal('home_longitude', 10, 7)->nullable();
            $table->time('work_starts_at')->nullable();
            $table->time('work_ends_at')->nullable();
            $table->unsignedTinyInteger('max_daily_jobs')->default(6);
            $this->businessTimestamps($table);

            $table->unique('user_id');
            $table->index(['tenant_id', 'is_available', 'branch_id'], 'technicians_availability');
        });

        Schema::create('bookings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('booking_number', 40);
            $table->string('channel', 30);
            $table->string('status', 30)->default('PENDING');
            $table->string('service_type', 100);
            $table->text('complaint')->nullable();
            $table->timestamp('preferred_start_at')->nullable();
            $table->timestamp('preferred_end_at')->nullable();
            $table->json('service_address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'booking_number']);
            $table->index(['tenant_id', 'status', 'preferred_start_at'], 'bookings_schedule');
            $table->index(['tenant_id', 'customer_id', 'created_at'], 'bookings_customer');
        });

        Schema::create('jobs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('job_number', 40);
            $table->string('status', 30)->default('CREATED');
            $table->string('priority', 20)->default('NORMAL');
            $table->string('service_type', 100);
            $table->text('description')->nullable();
            $table->text('resolution')->nullable();
            $table->json('service_address')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->decimal('start_latitude', 10, 7)->nullable();
            $table->decimal('start_longitude', 10, 7)->nullable();
            $table->decimal('end_latitude', 10, 7)->nullable();
            $table->decimal('end_longitude', 10, 7)->nullable();
            $table->text('customer_signature_path')->nullable();
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'job_number']);
            $table->index(['tenant_id', 'status', 'scheduled_at'], 'jobs_schedule');
            $table->index(['tenant_id', 'customer_id', 'created_at'], 'jobs_customer');
        });

        Schema::create('job_assignments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('job_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('technician_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('ASSIGNED');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $this->businessTimestamps($table);

            $table->unique(['job_id', 'technician_id']);
            $table->index(['tenant_id', 'technician_id', 'status', 'assigned_at'], 'assignments_technician');
        });

        Schema::create('job_checklist_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('job_id')->constrained()->cascadeOnDelete();
            $table->string('label', 255);
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->foreignUlid('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $this->businessTimestamps($table);

            $table->index(['tenant_id', 'job_id', 'sort_order'], 'checklist_job_order');
        });

        Schema::create('job_evidence', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('job_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('job_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->string('disk', 40)->default('local');
            $table->text('path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy_metres', 8, 2);
            $table->timestamp('captured_at');
            $table->string('device_id', 191);
            $table->boolean('is_mock_location')->default(false);
            $table->json('metadata')->nullable();
            $this->businessTimestamps($table);

            $table->index(['tenant_id', 'job_id', 'type', 'created_at'], 'evidence_job_type');
        });

        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->char('endpoint_hash', 64);
            $table->text('endpoint');
            $table->text('public_key')->nullable();
            $table->text('auth_token')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'endpoint_hash']);
            $table->index(['tenant_id', 'user_id', 'revoked_at'], 'push_user_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('job_evidence');
        Schema::dropIfExists('job_checklist_items');
        Schema::dropIfExists('job_assignments');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('technician_profiles');
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
