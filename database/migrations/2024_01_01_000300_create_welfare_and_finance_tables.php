<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('welfare_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_no', 30)->unique();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('beneficiary_id')->nullable()->constrained('beneficiaries')->restrictOnDelete();
            // self, parent, parent_in_law, spouse, child
            $table->string('category', 20)->index();
            $table->string('deceased_name');
            $table->date('bereavement_date');
            $table->text('remarks')->nullable();
            $table->decimal('contribution_amount', 12, 2);
            // open, in_progress, fully_collected, closed, cancelled
            $table->string('status', 20)->default('open')->index();
            $table->unsignedInteger('members_invoiced')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->unique()->constrained('members')->restrictOnDelete();
            $table->decimal('balance', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 30)->unique();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('welfare_case_id')->nullable()->constrained('welfare_cases')->restrictOnDelete();
            $table->string('type', 20)->default('welfare')->index(); // welfare, custom
            $table->string('category', 40)->nullable(); // case category or custom category (airtime, lunch...)
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            // unpaid, partially_paid, paid, cancelled
            $table->string('status', 20)->default('unpaid')->index();
            $table->string('remarks')->nullable();
            $table->string('batch_ref', 40)->nullable()->index(); // groups custom invoices raised together
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            // Business rule: one invoice per member per welfare case.
            $table->unique(['welfare_case_id', 'member_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 30)->unique();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->string('reference', 60)->unique(); // Paybill transaction code
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->string('method', 60);
            $table->string('remarks')->nullable();
            $table->string('status', 20)->default('confirmed')->index(); // confirmed, reversed
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->string('direction', 10); // credit, debit
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->boolean('negative_balance_authorized')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Immutable ledger. Every wallet balance change has exactly one row here.
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->string('reference', 40)->unique();
            // deposit, contribution, invoice_payment, refund, adjustment, reversal
            $table->string('type', 20)->index();
            $table->string('direction', 10); // credit, debit
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('description');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->foreignId('adjustment_id')->nullable()->constrained('financial_adjustments')->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transaction_date')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('financial_adjustments');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('welfare_cases');
    }
};
