<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Str;

class AssertNoPendingMigrations extends Command
{
    protected $signature = 'schema:assert-no-pending-migrations {--database= : Tên DB connection}';

    protected $description = 'Fail nếu còn migration pending (schema drift gate).';

    public function __construct(
        protected Migrator $migrator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $database = $this->option('database');

        if (filled($database)) {
            $connection = (string) $database;
            $this->migrator->setConnection($connection);
            $this->migrator->getRepository()->setSource($connection);
        }

        if (! $this->migrator->repositoryExists()) {
            $this->error('Migration repository chưa tồn tại. Chạy migrate trước khi gate schema drift.');

            return self::FAILURE;
        }

        $migrationPaths = array_values(array_unique([
            database_path('migrations'),
            ...$this->migrator->paths(),
        ]));
        $files = $this->migrator->getMigrationFiles($migrationPaths);
        $ran = $this->migrator->getRepository()->getRan();
        $pending = array_values(array_diff(array_keys($files), $ran));

        if ($pending === []) {
            $this->info('Schema drift gate: OK (không có migration pending).');

            return self::SUCCESS;
        }

        $this->error('Schema drift gate: FAIL (phát hiện migration pending).');

        foreach ($pending as $migration) {
            $this->line('- '.Str::afterLast($migration, '/'));
        }

        return self::FAILURE;
    }
}
