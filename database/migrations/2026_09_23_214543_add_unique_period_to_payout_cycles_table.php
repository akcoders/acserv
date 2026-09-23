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
        Schema::table('payout_cycles', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'type', 'starts_on', 'ends_on'], 'payout_cycles_period_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payout_cycles', function (Blueprint $table): void {
            $table->dropUnique('payout_cycles_period_unique');
        });
    }
};
