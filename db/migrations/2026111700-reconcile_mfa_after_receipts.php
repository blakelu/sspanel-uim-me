<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;

return new class() implements MigrationInterface {
    public function up(): int
    {
        // Installations at the receipt migration could have skipped the older upstream MFA migration.
        $migration = require __DIR__ . '/2025073100-refactor_mfa.php';
        $migration->up();

        return 2026111700;
    }

    public function down(): int
    {
        // The original MFA migration owns the schema; keep the devices when rolling back this version.
        return 2026081100;
    }
};
