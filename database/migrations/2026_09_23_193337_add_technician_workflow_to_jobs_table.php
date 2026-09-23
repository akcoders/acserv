<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->timestamp('reached_at')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->text('inspection_remark')->nullable();
            $table->text('completion_remark')->nullable();
            $table->text('prework_signature_path')->nullable();
            $table->decimal('service_cost', 14, 2)->default(0);
            $table->decimal('service_tax_rate', 5, 2)->default(18);
        });

        Schema::table('job_evidence', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->change();
            $table->decimal('longitude', 10, 7)->nullable()->change();
            $table->decimal('accuracy_metres', 8, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('job_evidence')->whereNull('latitude')->update(['latitude' => 0]);
        DB::table('job_evidence')->whereNull('longitude')->update(['longitude' => 0]);
        DB::table('job_evidence')->whereNull('accuracy_metres')->update(['accuracy_metres' => 0]);

        Schema::table('job_evidence', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable(false)->change();
            $table->decimal('longitude', 10, 7)->nullable(false)->change();
            $table->decimal('accuracy_metres', 8, 2)->nullable(false)->change();
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->dropColumn(['reached_at', 'inspected_at', 'authorized_at', 'inspection_remark', 'completion_remark', 'prework_signature_path', 'service_cost', 'service_tax_rate']);
        });
    }
};
