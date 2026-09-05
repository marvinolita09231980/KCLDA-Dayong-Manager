<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('type', ['Dayong', 'Annual Dues', 'Registration Fee']);
            $table->decimal('expected_amount', 12, 2);
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->default('');
            $table->text('address')->default('');
            $table->date('birth_date')->nullable();
            $table->string('council');
            $table->string('membership_type')->default('Brother Knight');
            $table->string('sponsor_name')->default('');
            $table->string('contact_number')->default('');
            $table->string('beneficiary_name')->default('');
            $table->string('beneficiary_contact')->default('');
            $table->boolean('is_fourth_degree')->default(false);
            $table->enum('member_status', ['Active', 'Inactive', 'Expelled', 'Deceased'])->default('Active');
            $table->text('remarks')->default('');
            $table->date('registration_date')->nullable();
            $table->foreignId('start_cycle_id')->nullable()->constrained('collection_cycles')->nullOnDelete();
            $table->date('date_of_death')->nullable();
            $table->text('claimed_benefits')->default('');
            $table->date('service_date')->nullable();
            $table->date('claim_received_date')->nullable();
            $table->string('claim_received_by')->default('');
            $table->timestamps();
            $table->unique(['last_name', 'first_name', 'middle_name', 'council']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_cycle_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('date_paid')->nullable();
            $table->string('receipt_number')->default('');
            $table->text('notes')->default('');
            $table->timestamps();
            $table->unique(['member_id', 'collection_cycle_id']);
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date');
            $table->enum('transaction_type', ['Deposit', 'Withdrawal']);
            $table->decimal('amount', 12, 2);
            $table->string('reference_number')->default('');
            $table->text('description')->default('');
            $table->string('recorded_by')->default('');
            $table->timestamps();
        });

        Schema::create('disbursements', function (Blueprint $table) {
            $table->id();
            $table->date('disbursement_date');
            $table->string('voucher_number')->default('');
            $table->string('payee')->default('');
            $table->string('category')->default('Other Expense');
            $table->text('particulars')->default('');
            $table->decimal('amount', 12, 2);
            $table->string('recorded_by')->default('');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursements');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('members');
        Schema::dropIfExists('collection_cycles');
    }
};
