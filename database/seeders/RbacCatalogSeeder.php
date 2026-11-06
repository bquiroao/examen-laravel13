<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Copia config/nido.php a roles, permissions y role_permissions. Idempotente: se puede correr en cada despliegue. */
class RbacCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $db = DB::connection('central');
        $permissions = config('nido.permissions');
        $roles = config('nido.roles');

        $db->transaction(function () use ($db, $permissions, $roles): void {
            $db->table('permissions')->upsert(
                collect($permissions)->map(fn (array $p, string $code) => ['permission_code' => $code, 'module' => $p[0], 'description' => $p[1]])->values()->all(),
                ['permission_code'], ['module', 'description'],
            );
            $db->table('roles')->upsert(
                collect($roles)->map(fn (array $r, string $code) => ['role_code' => $code, 'name' => $r[0], 'scope' => $r[1]])->values()->all(),
                ['role_code'], ['name', 'scope'],
            );
            foreach ($roles as $code => [, , $granted]) {
                $granted = $granted === '*' ? array_keys($permissions) : $granted;
                $db->table('role_permissions')->where('role_code', $code)->whereNotIn('permission_code', $granted)->delete();
                $db->table('role_permissions')->insertOrIgnore(array_map(fn (string $p) => ['role_code' => $code, 'permission_code' => $p], $granted));
            }
        });
    }
}
