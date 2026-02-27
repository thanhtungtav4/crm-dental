<?php

use App\Models\Branch;
use Illuminate\Support\Facades\DB;

it('generates branch code when creating a branch without code', function (): void {
    $branch = Branch::factory()->create([
        'code' => null,
    ]);

    expect($branch->code)
        ->not->toBeEmpty()
        ->toMatch('/^BR-\d{8}-[A-Z0-9]{6}$/');
});

it('backfills branch code when updating legacy branch record without code', function (): void {
    DB::table('branches')->insert([
        'code' => null,
        'name' => 'Legacy Branch Without Code',
        'address' => 'Legacy Address',
        'phone' => '0900000000',
        'active' => 1,
        'manager_id' => null,
        'created_at' => now()->subDays(30),
        'updated_at' => now()->subDays(30),
        'deleted_at' => null,
    ]);

    $branch = Branch::query()
        ->where('name', 'Legacy Branch Without Code')
        ->firstOrFail();

    expect($branch->code)->toBeNull();

    $branch->name = 'Legacy Branch Updated';
    $branch->save();
    $branch->refresh();

    expect($branch->code)
        ->not->toBeEmpty()
        ->toMatch('/^BR-\d{8}-[A-Z0-9]{6}$/');
});
