<?php

use Illuminate\Support\Facades\Schema;

function corePublicTables(): array
{
    return [
        'projects',
        'project_stages',
        'time_entries',
        'transaction_categories',
        'payment_methods',
        'accounts',
        'transactions',
    ];
}

test('core public tables use normalized id uuid and timestamps conventions', function () {
    foreach (corePublicTables() as $table) {
        expect(Schema::hasTable($table))->toBeTrue();

        expect(Schema::hasColumns($table, [
            'id',
            'uuid',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
    }
});

test('foreign key columns follow snake case id naming convention', function () {
    expect(Schema::hasColumns('time_entries', [
        'project_id',
        'project_stage_id',
        'user_id',
    ]))->toBeTrue();

    expect(Schema::hasColumns('transactions', [
        'account_id',
        'transaction_category_id',
        'payment_method_id',
        'project_id',
    ]))->toBeTrue();
});

test('audit strategy table structure is available for model change tracking', function () {
    expect(Schema::hasTable('audit_logs'))->toBeTrue();

    expect(Schema::hasColumns('audit_logs', [
        'id',
        'user_id',
        'auditable_type',
        'auditable_id',
        'action',
        'old_values',
        'new_values',
        'occurred_at',
    ]))->toBeTrue();
});
