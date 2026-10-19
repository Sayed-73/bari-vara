<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->string('pay_token', 24)->nullable()->unique()->after('status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_token', 24)->nullable()->unique()->after('note');
            $table->string('source', 20)->default('manual')->after('receipt_token');
            $table->unsignedBigInteger('payment_attempt_id')->nullable()->after('source');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('sms_credits')->default(0)->after('custom_unit_limit');
        });

        Schema::table('sms_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('charge_id')->nullable()->after('tenant_id');
            $table->string('kind', 20)->nullable()->after('charge_id');
            $table->index(['charge_id', 'kind']);
        });

        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider', 20)->default('sslcommerz');
            $table->text('store_id')->nullable();        // encrypted
            $table->text('store_password')->nullable();  // encrypted
            $table->boolean('is_sandbox')->default(true);
            $table->boolean('is_enabled')->default(false);
            $table->text('manual_instructions')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_id')->constrained()->cascadeOnDelete();
            $table->string('tran_id', 40)->unique();
            $table->string('gateway', 20)->default('sslcommerz');
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending'); // pending, paid, failed, cancelled, needs_review
            $table->string('session_key')->nullable();
            $table->string('val_id', 60)->nullable();
            $table->string('card_type', 60)->nullable();
            $table->string('bank_tran_id', 80)->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['charge_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payment_accounts');

        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropIndex(['charge_id', 'kind']);
            $table->dropColumn(['charge_id', 'kind']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sms_credits');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['receipt_token']);
            $table->dropColumn(['receipt_token', 'source', 'payment_attempt_id']);
        });

        Schema::table('charges', function (Blueprint $table) {
            $table->dropUnique(['pay_token']);
            $table->dropColumn('pay_token');
        });
    }
};
