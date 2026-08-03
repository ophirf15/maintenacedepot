<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pickup_depot_id')->nullable()->constrained('depots')->nullOnDelete();
            $table->dateTime('needed_from')->nullable();
            $table->dateTime('needed_until')->nullable();
            $table->text('purpose')->nullable();
            $table->string('priority', 16)->default('normal');
            $table->json('lines')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_carts');
    }
};
