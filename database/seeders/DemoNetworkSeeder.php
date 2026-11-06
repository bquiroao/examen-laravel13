<?php

namespace Database\Seeders;

use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Central\HospitalMembership;
use App\Models\Central\Network;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** Red de demostración: cuatro hospitales y un usuario por rol. Contraseña de todos: NidoDemo2026. */
class DemoNetworkSeeder extends Seeder
{
    public const PASSWORD = 'NidoDemo2026';

    public const HOSPITALS = [
        'HN-CEN' => 'Hospital Nido Central',
        'HN-NOR' => 'Hospital Nido Norte',
        'HN-ORI' => 'Hospital Nido Oriente',
        'HN-OCC' => 'Hospital Nido Occidente',
    ];

    public function run(): void
    {
        $network = Network::query()->firstOrCreate(['name' => 'Red Hospital Nido'], ['status' => 'active', 'timezone' => 'America/Guatemala', 'metadata' => []]);
        $hospitals = collect(self::HOSPITALS)->map(fn (string $name, string $code) => Hospital::query()->firstOrCreate(
            ['network_id' => $network->network_id, 'code' => $code],
            ['name' => $name, 'status' => 'active', 'timezone' => 'America/Guatemala', 'metadata' => []],
        ));

        $admin = $this->user('admin@nido.test', 'Administración de la red');
        foreach ($hospitals as $hospital) {
            $this->member($admin, $hospital, 'admin_red');
        }

        $central = $hospitals['HN-CEN'];
        foreach (array_keys(config('nido.roles')) as $role) {
            if ($role !== 'admin_red') {
                $name = $role === 'medico' ? 'Dr. Carlos Ramírez Soto' : config("nido.roles.{$role}.0").' (demo)';
                $this->member($this->user(str_replace('_', '.', $role).'@nido.test', $name), $central, $role);
            }
        }
        // Un médico con acceso a dos hospitales, para probar el cambio de hospital, y dos especialistas más.
        $this->member(CentralUser::query()->where('email', 'medico@nido.test')->firstOrFail(), $hospitals['HN-NOR'], 'medico');
        $this->member($this->user('pediatria@nido.test', 'Dra. Ana Lucía Méndez'), $central, 'medico');
        $this->member($this->user('cardiologia@nido.test', 'Dr. Roberto Paz Orellana'), $central, 'medico');

        // En desarrollo cada hospital tiene su propia base clínica; Central usa la de .env. En pruebas, todos usan la de pruebas.
        if (app()->environment('local')) {
            foreach ($hospitals as $code => $hospital) {
                if ($code !== 'HN-CEN' && ! isset($hospital->metadata['database'])) {
                    $hospital->forceFill(['metadata' => [...$hospital->metadata, 'database' => 'his_hospital_'.strtolower(substr($code, 3))]])->save();
                }
            }
        }
    }

    private function user(string $email, string $name): CentralUser
    {
        $user = CentralUser::query()->firstOrCreate(['email' => $email], ['subject' => 'local|'.$email, 'display_name' => $name, 'status' => 'active', 'metadata' => []]);
        $user->credential()->firstOrCreate([], ['password_hash' => Hash::make(self::PASSWORD)]);

        return $user;
    }

    private function member(CentralUser $user, Hospital $hospital, string $role): void
    {
        HospitalMembership::query()->firstOrCreate(
            ['user_id' => $user->user_id, 'hospital_id' => $hospital->hospital_id, 'role_code' => $role, 'status' => 'active'],
            ['valid_from' => now()->subDay(), 'metadata' => []],
        );
    }
}
