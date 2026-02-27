<?php

use Illuminate\Support\Facades\File;

it('passes when there are no pending migrations', function () {
    $this->artisan('schema:assert-no-pending-migrations')
        ->expectsOutputToContain('Schema drift gate: OK')
        ->assertSuccessful();
});

it('fails when a migration file is pending', function () {
    $migrationFile = database_path('migrations/2099_12_31_235959_pending_schema_drift_probe.php');

    File::put($migrationFile, <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
    }

    public function down(): void
    {
    }
};
PHP);

    try {
        $this->artisan('schema:assert-no-pending-migrations')
            ->expectsOutputToContain('Schema drift gate: FAIL')
            ->assertFailed();
    } finally {
        if (File::exists($migrationFile)) {
            File::delete($migrationFile);
        }
    }
});
