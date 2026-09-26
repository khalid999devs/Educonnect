<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Authorization\Support\AuthorizationCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $newRoleKeys = [];

        foreach (AuthorizationCatalog::roles() as $key => $definition) {
            $exists = DB::table('roles')->where('key', $key)->exists();

            if (! $exists) {
                DB::table('roles')->insert([
                    'key' => $key,
                    'name' => $definition['name'],
                    'display_priority' => $definition['display_priority'],
                    'is_system' => true,
                    'is_protected' => $definition['is_protected'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $newRoleKeys[] = $key;
            }
        }

        foreach (AuthorizationCatalog::capabilities() as $key => $name) {
            if (! DB::table('capabilities')->where('key', $key)->exists()) {
                DB::table('capabilities')->insert([
                    'key' => $key,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if ($newRoleKeys === []) {
            return;
        }

        $roleIds = DB::table('roles')->pluck('id', 'key');
        $capabilityIds = DB::table('capabilities')->pluck('id', 'key');

        foreach ($newRoleKeys as $roleKey) {
            foreach (AuthorizationCatalog::roleCapabilities()[$roleKey] as $capability) {
                DB::table('role_capability')->insertOrIgnore([
                    'role_id' => $roleIds[$roleKey],
                    'capability_id' => $capabilityIds[$capability->value],
                ]);
            }
        }
    }
}
