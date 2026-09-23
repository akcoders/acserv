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
        Schema::create('tax_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->string('name', 120);
            $table->string('gstin', 20)->nullable();
            $table->string('hsn_code', 20)->nullable();
            $table->string('sac_code', 20)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(18);
            $table->boolean('is_default')->default(false);
            $this->businessTimestamps($table);

            $table->index(['tenant_id', 'is_default', 'deleted_at']);
        });

        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('tax_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 64);
            $table->string('name', 160);
            $table->string('description')->nullable();
            $table->string('hsn_code', 20)->nullable();
            $table->string('unit', 20)->default('PCS');
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('sale_price', 14, 2)->default(0);
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->boolean('track_serials')->default(false);
            $table->boolean('is_active')->default(true);
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'is_active', 'name'], 'inventory_active_name');
        });

        Schema::create('stock_locations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('custodian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('type', 30);
            $table->json('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'type', 'is_active'], 'stock_locations_type');
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('from_location_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
            $table->foreignUlid('to_location_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
            $table->foreignUlid('job_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->string('reference', 120)->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('moved_at')->useCurrent();
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'inventory_item_id', 'moved_at'], 'stock_item_history');
            $table->index(['tenant_id', 'from_location_id', 'moved_at'], 'stock_from_history');
            $table->index(['tenant_id', 'to_location_id', 'moved_at'], 'stock_to_history');
        });

        Schema::create('job_part_consumptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('job_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('stock_location_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('consumed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('returned_quantity', 12, 3)->default(0);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->timestamp('consumed_at')->useCurrent();
            $this->businessTimestamps($table);

            $table->index(['tenant_id', 'job_id', 'created_at'], 'job_parts_history');
        });

        Schema::create('quotations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('job_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('customer_id')->constrained()->restrictOnDelete();
            $table->string('quotation_number', 40);
            $table->string('status', 30)->default('DRAFT');
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'quotation_number']);
            $table->index(['tenant_id', 'status', 'issued_on'], 'quotations_status');
        });

        Schema::create('quotation_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('hsn_code', 20)->nullable();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $this->businessTimestamps($table);
        });

        Schema::create('work_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('job_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('customer_id')->constrained()->restrictOnDelete();
            $table->string('work_order_number', 40);
            $table->string('status', 30)->default('DRAFT');
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('scope')->nullable();
            $table->text('notes')->nullable();
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'work_order_number']);
            $table->index(['tenant_id', 'status', 'created_at'], 'work_orders_status');
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('job_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('tax_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number', 40);
            $table->string('status', 30)->default('DRAFT');
            $table->string('currency', 3)->default('INR');
            $table->date('issued_on')->nullable();
            $table->date('due_on')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);
            $table->string('supplier_gstin', 20)->nullable();
            $table->json('billing_address')->nullable();
            $table->text('notes')->nullable();
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'invoice_number']);
            $table->index(['tenant_id', 'status', 'due_on'], 'invoices_due');
            $table->index(['tenant_id', 'customer_id', 'issued_on'], 'invoices_customer');
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('hsn_code', 20)->nullable();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $this->businessTimestamps($table);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('invoice_id')->constrained()->restrictOnDelete();
            $table->string('payment_number', 40);
            $table->string('mode', 30);
            $table->string('status', 30)->default('PENDING');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('reference', 191)->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('provider_order_id', 191)->nullable();
            $table->string('provider_payment_id', 191)->nullable();
            $table->char('provider_signature_hash', 64)->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'payment_number']);
            $table->index(['tenant_id', 'invoice_id', 'status'], 'payments_invoice');
            $table->index(['tenant_id', 'provider_payment_id'], 'payments_provider');
        });

        Schema::create('warranties', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('asset_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('job_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->string('provider', 160)->nullable();
            $table->string('policy_number', 100)->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 30)->default('ACTIVE');
            $table->json('coverage_terms')->nullable();
            $this->businessTimestamps($table);

            $table->index(['tenant_id', 'status', 'ends_on'], 'warranties_expiry');
            $table->index(['tenant_id', 'asset_id', 'starts_on'], 'warranties_asset');
        });

        Schema::create('warranty_claims', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('warranty_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('job_id')->nullable()->constrained()->nullOnDelete();
            $table->string('claim_number', 40);
            $table->string('status', 30)->default('SUBMITTED');
            $table->text('issue');
            $table->text('decision_notes')->nullable();
            $table->decimal('approved_amount', 14, 2)->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('decided_at')->nullable();
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'claim_number']);
            $table->index(['tenant_id', 'status', 'submitted_at'], 'claims_status');
        });

        Schema::create('amc_contracts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $this->tenantKey($table);
            $table->foreignUlid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contract_number', 40);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedSmallInteger('included_visits')->default(0);
            $table->unsignedSmallInteger('used_visits')->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('status', 30)->default('ACTIVE');
            $table->json('terms')->nullable();
            $this->businessTimestamps($table);

            $table->unique(['tenant_id', 'contract_number']);
            $table->index(['tenant_id', 'status', 'ends_on'], 'amc_expiry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amc_contracts');
        Schema::dropIfExists('warranty_claims');
        Schema::dropIfExists('warranties');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('quotation_lines');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('job_part_consumptions');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_locations');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('tax_profiles');
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
