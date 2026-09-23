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
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignUlid('user_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('feedback', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('job_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('status', 30)->default('OPEN');
            $table->timestamp('escalated_at')->nullable();
            $table->foreignUlid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'job_id']);
            $table->index(['tenant_id', 'rating', 'status', 'created_at'], 'feedback_escalation');
        });

        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->string('event', 100);
            $table->string('channel', 30);
            $table->string('locale', 16)->default('en_IN');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'event', 'channel', 'locale'], 'templates_event_channel_locale');
            $table->index(['tenant_id', 'event', 'is_active'], 'templates_active_event');
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 100)->default('*');
            $table->string('channel', 30);
            $table->boolean('is_enabled')->default(true);
            $table->time('quiet_starts_at')->nullable();
            $table->time('quiet_ends_at')->nullable();
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'user_id', 'event', 'channel'], 'preferences_user_event_channel');
        });

        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('notification_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 100);
            $table->string('channel', 30);
            $table->string('recipient_masked', 191);
            $table->string('status', 30)->default('PENDING');
            $table->string('provider', 60)->nullable();
            $table->string('provider_id', 191)->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $this->businessTimestamps($table);

            $table->index(['tenant_id', 'status', 'created_at'], 'notification_delivery_queue');
            $table->index(['tenant_id', 'event', 'channel', 'created_at'], 'notification_event_history');
        });

        Schema::create('service_reminders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('asset_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUlid('warranty_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUlid('amc_contract_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->date('due_on');
            $table->date('notify_on');
            $table->string('status', 30)->default('PENDING');
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('metadata')->nullable();
            $this->businessTimestamps($table);

            $table->index(['tenant_id', 'status', 'notify_on'], 'service_reminders_due');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_reminders');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('feedback');

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
        });
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
