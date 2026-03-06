<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('passes when critical inventory schema columns and indexes are present', function (): void {
    $this->artisan('schema:assert-critical-inventory-columns')
        ->expectsOutputToContain('Critical inventory schema gate: OK')
        ->assertSuccessful();
});

it('fails when critical inventory schema columns or indexes are missing', function (): void {
    $databasePath = storage_path('app/testing/inventory-schema-gate/missing-'.Str::uuid().'.sqlite');
    File::ensureDirectoryExists(dirname($databasePath));
    touch($databasePath);

    $connectionName = 'inventory_schema_gate_missing';

    Config::set("database.connections.{$connectionName}", [
        'driver' => 'sqlite',
        'url' => null,
        'database' => $databasePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    DB::purge($connectionName);
    DB::connection($connectionName)->getPdo();

    Schema::connection($connectionName)->create('materials', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('branch_id');
        $table->string('sku')->nullable();
    });

    Schema::connection($connectionName)->create('material_issue_items', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('material_issue_note_id')->nullable();
    });

    Schema::connection($connectionName)->create('inventory_transactions', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('material_id')->nullable();
        $table->timestamp('created_at')->nullable();
    });

    $this->artisan('schema:assert-critical-inventory-columns', [
        '--database' => $connectionName,
    ])
        ->expectsOutputToContain('Critical inventory schema gate: FAIL.')
        ->expectsOutputToContain('Thiếu cột `material_issue_items.material_batch_id`.')
        ->expectsOutputToContain('Thiếu cột `inventory_transactions.material_batch_id`.')
        ->expectsOutputToContain('Thiếu index `materials_branch_id_sku_unique` trên `materials(branch_id, sku)`.')
        ->assertFailed();

    DB::disconnect($connectionName);
    DB::purge($connectionName);

    if (file_exists($databasePath)) {
        unlink($databasePath);
    }
});
