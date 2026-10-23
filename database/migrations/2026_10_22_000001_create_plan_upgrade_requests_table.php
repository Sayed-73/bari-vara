<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('pending_plan_id')->nullable()->after('plan_id');
            $table->string('plan_upgrade_status')->nullable()->after('pending_plan_id');
        });

        Schema::create('plan_upgrade_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method');
            $table->string('trx_id');
            $table->string('sender_number')->nullable();
            $table->text('note')->nullable();
            $table->string('screenshot_path')->nullable();
            $table->string('status')->default('pending');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_upgrade_requests');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pending_plan_id', 'plan_upgrade_status']);
        });
    }
};
