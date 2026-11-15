<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Optional M-Pesa/bank charge paid on the transfer. Recorded for information only:
            // it is NOT credited to the wallet.
            $table->decimal('transaction_cost', 12, 2)->nullable()->after('amount');
            // Set when the deposit was recorded against a specific welfare case.
            $table->foreignId('welfare_case_id')->nullable()->after('member_id')->constrained('welfare_cases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('welfare_case_id');
            $table->dropColumn('transaction_cost');
        });
    }
};
