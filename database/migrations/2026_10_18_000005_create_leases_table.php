<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->decimal('monthly_rent', 12, 2);
            $table->unsignedTinyInteger('due_day')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['unit_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
