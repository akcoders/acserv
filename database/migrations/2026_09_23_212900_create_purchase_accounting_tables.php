<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('contact_name', 160)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('gstin', 20)->nullable();
            $table->text('address')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->boolean('is_active')->default(true);
            $this->businessTimestamps($table);
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active', 'name']);
        });

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('stock_location_id')->constrained()->restrictOnDelete();
            $table->string('order_number', 40);
            $table->string('status', 20)->default('ORDERED');
            $table->string('supplier_invoice_number', 100)->nullable();
            $table->date('ordered_on');
            $table->date('due_on')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $this->businessTimestamps($table);
            $table->unique(['tenant_id', 'order_number']);
            $table->index(['tenant_id', 'status', 'ordered_on']);
            $table->index(['tenant_id', 'vendor_id', 'due_on']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('subtotal', 14, 2);
            $table->decimal('tax_amount', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $this->businessTimestamps($table);
            $table->index(['tenant_id', 'purchase_order_id']);
        });

        Schema::create('account_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('purchase_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('entry_number', 40);
            $table->string('type', 30);
            $table->string('category', 60);
            $table->string('description', 191);
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 20);
            $table->string('reference', 120)->nullable();
            $table->date('entry_date');
            $this->businessTimestamps($table);
            $table->unique(['tenant_id', 'entry_number']);
            $table->index(['tenant_id', 'type', 'entry_date']);
            $table->index(['tenant_id', 'purchase_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_entries');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('vendors');
    }

    private function businessTimestamps(Blueprint $table): void
    {
        $table->ulid('created_by')->nullable();
        $table->ulid('updated_by')->nullable();
        $table->timestamps();
        $table->softDeletes();
    }
};
