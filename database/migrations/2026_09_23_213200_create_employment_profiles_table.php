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
        Schema::create('employment_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_code', 40);
            $table->string('designation', 120);
            $table->string('pay_grade', 40)->nullable();
            $table->string('employment_type', 30)->default('FULL_TIME');
            $table->decimal('monthly_salary', 14, 2)->default(0);
            $table->decimal('incentive_per_job', 14, 2)->default(0);
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->foreignUlid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'user_id']);
            $table->unique(['tenant_id', 'employee_code']);
            $table->index(['tenant_id', 'designation']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employment_profiles');
    }
};
