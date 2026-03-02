<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('passes when critical foreign keys are present', function (): void {
    $this->artisan('schema:assert-critical-foreign-keys')
        ->expectsOutputToContain('Critical foreign key gate: OK')
        ->assertSuccessful();
});

it('fails when required foreign keys are missing', function (): void {
    $databasePath = storage_path('app/testing/fk-gate/missing-fk-'.Str::uuid().'.sqlite');
    File::ensureDirectoryExists(dirname($databasePath));
    touch($databasePath);

    $connectionName = 'fk_gate_missing';

    Config::set("database.connections.{$connectionName}", [
        'driver' => 'sqlite',
        'url' => null,
        'database' => $databasePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    DB::purge($connectionName);
    DB::connection($connectionName)->getPdo();

    Schema::connection($connectionName)->create('patients', function (Blueprint $table): void {
        $table->id();
    });
    Schema::connection($connectionName)->create('treatment_sessions', function (Blueprint $table): void {
        $table->id();
    });
    Schema::connection($connectionName)->create('tooth_conditions', function (Blueprint $table): void {
        $table->id();
    });
    Schema::connection($connectionName)->create('treatment_plans', function (Blueprint $table): void {
        $table->id();
    });
    Schema::connection($connectionName)->create('prescriptions', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('patient_id')->nullable();
        $table->unsignedBigInteger('treatment_session_id')->nullable();
    });
    Schema::connection($connectionName)->create('patient_tooth_conditions', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('patient_id')->nullable();
        $table->unsignedBigInteger('tooth_condition_id')->nullable();
        $table->unsignedBigInteger('treatment_plan_id')->nullable();
    });

    $this->artisan('schema:assert-critical-foreign-keys', [
        '--database' => $connectionName,
    ])
        ->expectsOutputToContain('Critical foreign key gate: FAIL.')
        ->expectsOutputToContain('Thiếu FK `prescriptions.patient_id -> patients.id`.')
        ->assertFailed();

    DB::disconnect($connectionName);
    DB::purge($connectionName);

    if (file_exists($databasePath)) {
        unlink($databasePath);
    }
});
