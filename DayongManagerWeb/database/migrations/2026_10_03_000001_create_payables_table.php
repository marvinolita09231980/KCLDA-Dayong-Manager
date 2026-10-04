<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payables', function (Blueprint $table) {
            $table->id();
            $table->string('payee');
            $table->string('category');
            $table->text('particulars');
            $table->decimal('amount', 12, 2);
            $table->date('due_date')->nullable();
            $table->string('recorded_by')->default('');
            $table->foreignId('disbursement_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payables');
    }
};
