<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_type_spec_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_type_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('label', 120);
            $table->string('unit', 32)->nullable();
            $table->string('field_type', 16)->default('text'); // number|text|select
            $table->json('options')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_filterable')->default(false);
            $table->timestamps();

            $table->unique(['tool_type_id', 'key']);
        });

        Schema::create('item_spec_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tool_type_spec_field_id')->constrained()->cascadeOnDelete();
            $table->string('value', 255);
            $table->timestamps();

            $table->unique(['item_id', 'tool_type_spec_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_spec_values');
        Schema::dropIfExists('tool_type_spec_fields');
    }
};
