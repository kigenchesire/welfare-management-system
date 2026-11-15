<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_no', 30)->nullable()->unique(); // assigned on approval
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('full_name');
            $table->string('national_id', 30)->unique();
            $table->string('phone', 30)->index();
            $table->string('email')->nullable();
            $table->string('residential_address')->nullable();
            $table->string('alternative_contact', 60)->nullable();
            $table->date('registration_date');
            // pending, active, suspended, rejected, inactive
            $table->string('status', 20)->default('pending')->index();
            $table->string('source', 20)->default('online'); // online, office
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->string('status_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->string('name');
            // parent, parent_in_law, spouse, child
            $table->string('category', 20)->index();
            $table->boolean('is_deceased')->default(false);
            $table->date('date_of_death')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
        Schema::dropIfExists('members');
    }
};
