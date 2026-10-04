<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disbursements', function (Blueprint $table): void {
            $table->boolean('closes_financial_period')->default(false)->index();
            $table->decimal('closing_balance', 12, 2)->nullable();
            $table->timestamp('period_closed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('disbursements', function (Blueprint $table): void {
            $table->dropIndex(['closes_financial_period']);
            $table->dropColumn(['closes_financial_period', 'closing_balance', 'period_closed_at']);
        });
    }
};
