<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('lifetime_loan_count')->default(0)->after('usage_hours');
            $table->unsignedInteger('lifetime_fuel_cycles')->default(0)->after('lifetime_loan_count');
        });

        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->unsignedInteger('next_due_loans')->nullable()->after('next_due_hours');
            $table->unsignedInteger('next_due_fuel_cycles')->nullable()->after('next_due_loans');
        });

        Schema::table('return_inspections', function (Blueprint $table) {
            $table->decimal('usage_hours_reading', 10, 2)->nullable()->after('usage_hours_estimate');
        });
    }

    public function down(): void
    {
        Schema::table('return_inspections', function (Blueprint $table) {
            $table->dropColumn('usage_hours_reading');
        });

        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->dropColumn(['next_due_loans', 'next_due_fuel_cycles']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['lifetime_loan_count', 'lifetime_fuel_cycles']);
        });
    }
};
