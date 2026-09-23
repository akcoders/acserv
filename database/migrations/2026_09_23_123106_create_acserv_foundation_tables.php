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
        Schema::create('branches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name', 160);
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->json('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active', 'deleted_at']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_number', 32);
            $table->string('type', 30)->default('RESIDENTIAL');
            $table->string('name', 160);
            $table->string('email')->nullable();
            $table->string('phone', 20);
            $table->string('alternate_phone', 20)->nullable();
            $table->json('billing_address')->nullable();
            $table->json('service_address')->nullable();
            $table->text('notes')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'customer_number']);
            $table->index(['tenant_id', 'phone', 'deleted_at']);
            $table->index(['tenant_id', 'branch_id', 'deleted_at']);
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('brand', 100);
            $table->string('model', 100)->nullable();
            $table->string('serial_number', 120)->nullable();
            $table->string('capacity', 50)->nullable();
            $table->string('asset_type', 80)->nullable();
            $table->date('install_date')->nullable();
            $table->string('status', 30)->default('ACTIVE');
            $table->json('metadata')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'serial_number']);
            $table->index(['tenant_id', 'customer_id', 'status', 'deleted_at']);
            $table->index(['tenant_id', 'branch_id', 'deleted_at']);
        });

        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('purpose', 40);
            $table->char('destination_hash', 64);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->char('requested_ip_hash', 64);
            $table->timestamps();

            $table->index(['tenant_id', 'destination_hash', 'purpose', 'created_at'], 'otp_destination_lookup');
            $table->index(['expires_at', 'consumed_at']);
        });

        Schema::create('passkey_credentials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('credential_id', 512)->unique();
            $table->binary('public_key');
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->json('transports')->nullable();
            $table->string('aaguid', 64)->nullable();
            $table->string('name', 100)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'revoked_at']);
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('role', 30);
            $table->string('status', 30)->default('PENDING');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status', 'expires_at', 'deleted_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('actor_id')->nullable();
            $table->string('entity', 100);
            $table->string('entity_id', 191);
            $table->string('action', 30);
            $table->json('diff')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'entity', 'entity_id', 'created_at'], 'audit_entity_lookup');
            $table->index(['tenant_id', 'actor_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('passkey_credentials');
        Schema::dropIfExists('otp_challenges');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('branches');
    }
};
