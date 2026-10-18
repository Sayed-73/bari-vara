<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('monthly_fee', 12, 2)->default(0);
            $table->unsignedInteger('max_units')->nullable(); // null = unlimited
            $table->json('features')->nullable();             // list of enabled feature keys
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('plan');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('email');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->unsignedBigInteger('plan_id')->nullable()->index()->after('is_active');
            $table->decimal('custom_fee', 12, 2)->nullable()->after('plan_id');
            $table->unsignedInteger('custom_unit_limit')->nullable()->after('custom_fee');
            $table->json('feature_overrides')->nullable()->after('subscription_ends_at');
        });

        Schema::create('subscription_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('changes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['plan_id']);
            $table->dropColumn(['is_admin', 'is_active', 'plan_id', 'custom_fee', 'custom_unit_limit', 'feature_overrides']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('plan', 20)->default('free');
        });

        Schema::dropIfExists('plans');
    }
};
