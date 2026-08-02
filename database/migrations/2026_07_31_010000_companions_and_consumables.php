<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_links', function (Blueprint $table) {
            $table->string('role', 24)->default('companion')->after('is_required');
        });

        Schema::create('tool_type_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_tool_type_id')->constrained('tool_types')->cascadeOnDelete();
            $table->foreignId('child_tool_type_id')->constrained('tool_types')->cascadeOnDelete();
            $table->string('role', 24)->default('companion');
            $table->boolean('is_required')->default(false);
            $table->timestamps();
            $table->unique(['parent_tool_type_id', 'child_tool_type_id', 'role'], 'tool_type_links_unique');
        });

        Schema::table('loan_items', function (Blueprint $table) {
            $table->foreignId('companion_of_loan_item_id')
                ->nullable()
                ->after('borrow_request_line_id')
                ->constrained('loan_items')
                ->nullOnDelete();
        });

        Schema::create('loan_consumable_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('companion_of_loan_item_id')
                ->nullable()
                ->constrained('loan_items')
                ->nullOnDelete();
            $table->decimal('qty_estimated', 12, 2);
            $table->decimal('qty_used', 12, 2)->nullable();
            $table->string('status', 24)->default('estimated');
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->index(['loan_id', 'status']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('delta', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('reason', 32);
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_consumable_issue_id')->nullable()->constrained('loan_consumable_issues')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->index(['item_id', 'created_at']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->decimal('reorder_point', 12, 2)->default(0)->after('stock_qty');
            $table->decimal('reorder_qty', 12, 2)->nullable()->after('reorder_point');
            $table->string('stock_unit', 24)->default('ea')->after('reorder_qty');
            $table->string('supplier_name', 160)->nullable()->after('stock_unit');
            $table->string('supplier_part_number', 120)->nullable()->after('supplier_name');
            $table->decimal('typical_cost', 12, 2)->nullable()->after('supplier_part_number');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn([
                'reorder_point',
                'reorder_qty',
                'stock_unit',
                'supplier_name',
                'supplier_part_number',
                'typical_cost',
            ]);
        });

        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('loan_consumable_issues');

        Schema::table('loan_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('companion_of_loan_item_id');
        });

        Schema::dropIfExists('tool_type_links');

        Schema::table('item_links', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
