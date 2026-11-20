<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Office and transport shares are copied from settings when a case is raised,
        // so later setting changes never alter an existing case. Existing cases keep 0.
        Schema::table('welfare_cases', function (Blueprint $table) {
            $table->decimal('office_share', 12, 2)->default(0)->after('contribution_amount');
            $table->decimal('transport_share', 12, 2)->default(0)->after('office_share');
        });

        Schema::create('office_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('account_no', 40)->unique();
            $table->timestamps();
        });
        DB::table('office_accounts')->insert([
            'name' => 'Office Expenditure Account', 'account_no' => 'OFF-0001', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Append-only ledger of top-ups and expenses. Office income from welfare
        // cases is calculated from paid invoices, so it is always in step with them.
        Schema::create('office_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_account_id')->constrained('office_accounts')->restrictOnDelete();
            $table->string('reference', 30)->unique();
            $table->string('type', 20)->index(); // topup, expense, reversal
            $table->string('direction', 10);     // credit, debit
            $table->decimal('amount', 12, 2);
            $table->date('transaction_date')->index();
            $table->string('party', 150)->nullable();    // source of a top-up / payee of an expense
            $table->string('category', 60)->nullable();  // expense category
            $table->string('description', 250);
            $table->string('external_ref', 60)->nullable(); // receipt / M-Pesa code
            $table->foreignId('reverses_id')->nullable()->unique()->constrained('office_transactions')->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('case_disbursements', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('welfare_case_id')->constrained('welfare_cases')->restrictOnDelete();
            $table->string('fund', 20)->default('welfare'); // welfare, transport
            $table->string('recipient', 150);
            $table->string('recipient_phone', 30)->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('disbursement_date')->index();
            $table->string('payment_ref', 60)->nullable();
            $table->string('remarks', 250)->nullable();
            $table->string('status', 20)->default('active')->index(); // active, voided
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 250)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_disbursements');
        Schema::dropIfExists('office_transactions');
        Schema::dropIfExists('office_accounts');
        Schema::table('welfare_cases', function (Blueprint $table) {
            $table->dropColumn(['office_share', 'transport_share']);
        });
    }
};
