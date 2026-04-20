<?php

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Models\User;
use Database\Seeders\LocalDemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('dumps audit log page html', function () {
    $this->seed(LocalDemoDataSeeder::class);
    $admin = User::query()->where('email', 'admin@demo.ident.test')->firstOrFail();
    $response = $this->actingAs($admin)->get(AuditLogResource::getUrl('index'));
    $content = $response->getContent();
    // Find text around "audit" or "log"
    preg_match_all('/.{0,30}(?:audit|log|Audit|Log).{0,30}/i', $content, $m);
    foreach (array_slice($m[0], 0, 20) as $line) {
        echo $line."\n";
    }
    expect(true)->toBeTrue();
});
