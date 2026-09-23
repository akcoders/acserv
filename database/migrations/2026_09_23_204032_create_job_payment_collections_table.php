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
        Schema::create('job_payment_collections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('job_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('technician_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode', 20);
            $table->string('status', 20)->default('PENDING');
            $table->decimal('amount', 14, 2);
            $table->string('reference', 191)->nullable();
            $table->string('proof_path')->nullable();
            $table->timestamp('submitted_at');
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status', 'submitted_at']);
            $table->index(['job_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_payment_collections');
    }
};
