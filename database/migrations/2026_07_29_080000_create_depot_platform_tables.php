<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('code', 32)->unique();
            $table->string('slug', 160)->unique();
            $table->string('address_line1', 190)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('contact_email', 190)->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('depots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_depot_id')->nullable()->constrained('depots')->nullOnDelete();
            $table->string('name', 160);
            $table->string('code', 32);
            $table->string('type', 16)->default('main');
            $table->boolean('is_pickup_point')->default(true);
            $table->boolean('is_return_point')->default(true);
            $table->string('address_line1', 190)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('phone', 32)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('allow_cross_property_transfer')->default(false);
            $table->unsignedSmallInteger('default_max_loan_days')->default(7);
            $table->boolean('pickup_window_enabled')->default(false);
            $table->unsignedSmallInteger('pickup_window_hours')->default(48);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['code']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('email');
            $table->string('avatar_path', 255)->nullable()->after('phone');
            $table->string('job_title', 120)->nullable()->after('avatar_path');
            $table->foreignId('default_property_id')->nullable()->after('job_title')->constrained('properties')->nullOnDelete();
            $table->string('auth_provider', 16)->default('local')->after('default_property_id');
            $table->string('saml_name_id', 190)->nullable()->unique()->after('auth_provider');
            $table->boolean('is_active')->default(true)->after('saml_name_id');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->softDeletes();
        });

        Schema::create('property_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['property_id', 'user_id']);
        });

        Schema::create('custom_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('availability_effect', 16);
            $table->string('color', 9)->nullable();
            $table->string('icon', 64)->nullable();
            $table->string('description', 255)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->string('icon', 64)->nullable();
            $table->string('image_path', 255)->nullable();
            $table->string('color', 9)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tool_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 160);
            $table->string('slug', 160)->unique();
            $table->string('sku_prefix', 16)->nullable();
            $table->text('description')->nullable();
            $table->string('image_path', 255)->nullable();
            $table->boolean('is_consumable')->default(false);
            $table->string('manufacturer', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->unsignedSmallInteger('default_loan_days')->nullable();
            $table->unsignedSmallInteger('max_loan_days')->nullable();
            $table->boolean('tracks_fuel')->default(false);
            $table->string('fuel_type', 16)->nullable();
            $table->boolean('tracks_usage_hours')->default(false);
            $table->boolean('allow_waitlist')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depot_id')->constrained()->restrictOnDelete();
            $table->foreignId('home_depot_id')->nullable()->constrained('depots')->nullOnDelete();
            $table->foreignId('tool_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('custom_status_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->foreignId('current_property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->boolean('is_kit')->default(false);
            $table->string('asset_tag', 64)->unique();
            $table->string('serial_number', 120)->nullable();
            $table->string('qr_token', 64)->unique();
            $table->string('name', 190)->nullable();
            $table->text('description')->nullable();
            $table->string('image_path', 255)->nullable();
            $table->string('manual_path', 255)->nullable();
            $table->string('condition', 16)->default('good');
            $table->boolean('is_consumable')->default(false);
            $table->decimal('stock_qty', 12, 2)->default(0);
            $table->boolean('is_loanable')->default(true);
            $table->decimal('usage_hours', 10, 2)->default(0);
            $table->unsignedTinyInteger('fuel_pct')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 14, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->date('warranty_expires_on')->nullable();
            $table->unsignedTinyInteger('lifespan_years')->nullable();
            $table->decimal('salvage_value', 14, 2)->nullable();
            $table->decimal('replacement_cost', 14, 2)->nullable();
            $table->boolean('end_of_life_soon')->default(false);
            $table->string('location_note', 190)->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['depot_id', 'custom_status_id']);
            $table->index(['tool_type_id', 'is_loanable']);
        });

        Schema::create('item_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('child_item_id')->constrained('items')->cascadeOnDelete();
            $table->boolean('is_required')->default(true);
            $table->timestamps();
            $table->unique(['parent_item_id', 'child_item_id']);
        });

        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 48);
            $table->string('key', 64);
            $table->string('label', 190);
            $table->string('help_text', 255)->nullable();
            $table->string('field_type', 24);
            $table->json('options')->nullable();
            $table->text('default_value')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['entity_type', 'key']);
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            $table->morphs('fieldable');
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 18, 4)->nullable();
            $table->boolean('value_bool')->nullable();
            $table->dateTime('value_date')->nullable();
            $table->json('value_json')->nullable();
            $table->timestamps();
            $table->unique(['custom_field_id', 'fieldable_type', 'fieldable_id'], 'uq_cfv');
        });

        Schema::create('borrow_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('on_behalf_of_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pickup_depot_id')->constrained('depots')->restrictOnDelete();
            $table->string('status', 32)->default('draft');
            $table->string('priority', 16)->default('normal');
            $table->text('purpose')->nullable();
            $table->dateTime('needed_from');
            $table->dateTime('needed_until');
            $table->dateTime('expected_dropoff_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approval_note', 255)->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamp('modification_requested_at')->nullable();
            $table->foreignId('modification_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('modification_note')->nullable();
            $table->json('modification_snapshot')->nullable();
            $table->boolean('modification_accepted')->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('pickup_deadline_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['property_id', 'status']);
            $table->index(['requester_id', 'status']);
        });

        Schema::create('borrow_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrow_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->string('request_mode', 16);
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tool_type_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->foreignId('allocated_item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('status', 24)->default('pending');
            $table->string('notes', 255)->nullable();
            $table->string('reject_reason', 255)->nullable();
            $table->timestamps();
            $table->unique(['borrow_request_id', 'line_no']);
        });

        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('borrow_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('borrow_request_line_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('tool_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->dateTime('desired_from')->nullable();
            $table->dateTime('desired_until')->nullable();
            $table->string('status', 16)->default('waiting');
            $table->timestamps();
            $table->index(['tool_type_id', 'status', 'position']);
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('borrow_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('borrower_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('depot_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('reserved');
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('original_due_at')->nullable();
            $table->unsignedTinyInteger('extension_count')->default(0);
            $table->timestamp('return_requested_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('checkout_notes')->nullable();
            $table->text('return_notes')->nullable();
            $table->boolean('damage_reported')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'due_at']);
        });

        Schema::create('loan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('borrow_request_line_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('status', 24)->default('reserved');
            $table->timestamp('checked_out_at')->nullable();
            $table->string('condition_out', 16)->nullable();
            $table->unsignedTinyInteger('fuel_pct_out')->nullable();
            $table->decimal('usage_hours_out', 10, 2)->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('condition_in', 16)->nullable();
            $table->unsignedTinyInteger('fuel_pct_in')->nullable();
            $table->decimal('usage_hours_in', 10, 2)->nullable();
            $table->decimal('usage_hours_delta', 10, 2)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->unique(['loan_id', 'item_id']);
        });

        Schema::create('loan_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('previous_due_at');
            $table->dateTime('requested_due_at');
            $table->dateTime('approved_due_at')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('reason', 255)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 160)->unique();
            $table->string('kind', 24)->default('preventive');
            $table->text('description')->nullable();
            $table->boolean('requires_downtime')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 190);
            $table->string('context', 24);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_template_id')->constrained()->cascadeOnDelete();
            $table->string('label', 255);
            $table->string('response_type', 24)->default('pass_fail');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('tool_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_type_id')->constrained()->restrictOnDelete();
            $table->string('name', 190);
            $table->string('trigger_type', 24);
            $table->unsignedSmallInteger('interval_days')->nullable();
            $table->decimal('interval_hours', 10, 2)->nullable();
            $table->unsignedSmallInteger('interval_loans')->nullable();
            $table->unsignedSmallInteger('interval_fuel_cycles')->nullable();
            $table->dateTime('next_due_at')->nullable();
            $table->decimal('next_due_hours', 10, 2)->nullable();
            $table->timestamp('last_performed_at')->nullable();
            $table->boolean('blocks_checkout_when_overdue')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ticket_type', 24);
            $table->foreignId('maintenance_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 190);
            $table->text('description')->nullable();
            $table->string('severity', 16)->default('medium');
            $table->string('priority', 16)->default('normal');
            $table->string('status', 24)->default('open');
            $table->boolean('takes_out_of_service')->default(false);
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution_code', 24)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->decimal('total_cost', 14, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'priority']);
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('maintenance_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('maintenance_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 190);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('draft');
            $table->string('priority', 16)->default('normal');
            $table->boolean('is_recurring')->default(false);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('scheduled_start_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('labour_hours', 8, 2)->nullable();
            $table->decimal('parts_cost', 14, 2)->nullable();
            $table->decimal('total_cost', 14, 2)->nullable();
            $table->text('completion_notes')->nullable();
            $table->string('parts_used', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('return_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_self_return')->default(false);
            $table->boolean('admin_reviewed')->default(false);
            $table->timestamp('inspected_at')->nullable();
            $table->string('overall_result', 24)->nullable();
            $table->string('condition', 16)->nullable();
            $table->unsignedTinyInteger('fuel_pct')->nullable();
            $table->decimal('usage_hours_estimate', 10, 2)->nullable();
            $table->boolean('damage_found')->default(false);
            $table->text('damage_description')->nullable();
            $table->boolean('end_of_life_soon')->default(false);
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('checklist_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_template_id')->constrained()->restrictOnDelete();
            $table->string('context', 24);
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('completed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->string('result', 16)->nullable();
            $table->json('answers')->nullable();
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('collection', 48)->default('default');
            $table->string('disk', 32)->default('local');
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('notification_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 96)->unique();
            $table->string('name', 160);
            $table->string('description', 255)->nullable();
            $table->string('group', 32);
            $table->json('default_channels');
            $table->json('available_channels');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type', 16)->default('global');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->foreignId('notification_type_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->boolean('is_enabled')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['scope_type', 'scope_id', 'notification_type_id', 'channel'], 'uq_notif_settings');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 32);
            $table->string('key', 96);
            $table->longText('value')->nullable();
            $table->string('value_type', 16)->default('string');
            $table->boolean('is_encrypted')->default(false);
            $table->boolean('is_public')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['group', 'key']);
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_label', 190)->nullable();
            $table->string('event', 48);
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('description', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('occurred_at');
            $table->timestamp('created_at')->nullable();
            $table->index(['event', 'occurred_at']);
        });

        Schema::create('installation_state', function (Blueprint $table) {
            $table->id();
            $table->uuid('instance_uuid')->unique();
            $table->boolean('is_installed')->default(false);
            $table->string('current_step', 32)->default('welcome');
            $table->json('completed_steps')->nullable();
            $table->string('installed_version', 24)->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('install_token', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 24)->unique();
            $table->string('previous_version', 24)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('applied');
            $table->text('release_notes')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('magic_link_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('offline_scan_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('client_uuid', 64);
            $table->string('action', 24);
            $table->string('qr_token', 64);
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('scanned_at');
            $table->timestamp('synced_at')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('error_message', 255)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        $tables = [
            'offline_scan_events', 'magic_link_tokens', 'app_versions', 'installation_state',
            'audit_events', 'settings', 'notification_settings', 'notification_types',
            'attachments', 'checklist_responses', 'return_inspections', 'work_orders',
            'tickets', 'maintenance_plans', 'checklist_items', 'checklist_templates',
            'maintenance_types', 'loan_extensions', 'loan_items', 'loans',
            'waitlist_entries', 'borrow_request_lines', 'borrow_requests',
            'custom_field_values', 'custom_fields', 'item_links', 'items',
            'tool_types', 'categories', 'custom_statuses', 'property_user',
            'depots', 'properties',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_property_id');
            $table->dropColumn([
                'phone', 'avatar_path', 'job_title', 'auth_provider',
                'saml_name_id', 'is_active', 'last_login_at', 'deleted_at',
            ]);
        });
    }
};
