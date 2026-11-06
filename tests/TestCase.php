<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function assertPostgreSqlIntegrityPrerequisites(): void
    {
        $this->assertTrue(extension_loaded('pdo_pgsql'));
        $this->assertTrue(extension_loaded('pgsql'));
        $this->assertGreaterThanOrEqual(150000, (int) DB::connection('central')->scalar('SHOW server_version_num'));
        $this->assertGreaterThanOrEqual(150000, (int) DB::connection('hospital')->scalar('SHOW server_version_num'));
        $this->assertSame('btree_gist', DB::connection('hospital')->scalar("SELECT extname FROM pg_extension WHERE extname = 'btree_gist'"));
    }

    /** Cuántas migraciones de HOSPITAL hay desde la indicada (incluida): los pasos para revertir hasta antes de ella. */
    protected function hospitalMigrationsFrom(string $prefix): int
    {
        return count(array_filter(glob(database_path('migrations/hospital/*.php')), fn (string $f) => strcmp(basename($f), $prefix) >= 0));
    }
}
