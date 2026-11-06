<?php

namespace Tests\Feature\Platform;

use App\Models\Central\Hospital;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoNetworkSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Base central limpia con el catálogo RBAC y la red de demostración en cada prueba. */
abstract class PlatformTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => config('jwt.secret') ?: str_repeat('s', 32)]);
        $this->assertPostgreSqlIntegrityPrerequisites();
        Artisan::call('migrate:fresh', ['--database' => 'central', '--path' => database_path('migrations/central'), '--realpath' => true, '--force' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    protected function hospital(string $code = 'HN-CEN'): Hospital
    {
        return Hospital::query()->where('code', $code)->firstOrFail();
    }

    protected function login(string $email, string $code = 'HN-CEN', string $password = DemoNetworkSeeder::PASSWORD): TestResponse
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password], ['X-Tenant-ID' => $this->hospital($code)->hospital_id]);
    }

    /** @return array<string, string> */
    protected function as(string $email, string $code = 'HN-CEN'): array
    {
        $token = $this->login($email, $code)->assertOk()->json('access_token');

        return ['X-Tenant-ID' => $this->hospital($code)->hospital_id, 'Authorization' => 'Bearer '.$token];
    }
}
