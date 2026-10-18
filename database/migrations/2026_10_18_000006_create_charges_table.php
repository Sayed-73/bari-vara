<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->date('period');
            $table->string('type', 20)->default('rent');
            $table->string('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('unpaid');
            $table->date('due_on');
            $table->timestamps();

            // One charge per type per month per lease. Also guards against duplicate auto-generation.
            $table->unique(['lease_id', 'period', 'type']);
            $table->index(['user_id', 'status', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
