<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('key', 'moderator')->value('id');
        $capabilityId = DB::table('capabilities')->where('key', 'academic.manage-own')->value('id');

        if (! is_int($roleId) || ! is_int($capabilityId)) {
            throw new RuntimeException('The canonical moderator role and academic capability are required.');
        }

        DB::table('role_capability')->insertOrIgnore([
            'role_id' => $roleId,
            'capability_id' => $capabilityId,
        ]);
    }

    public function down(): void
    {
        // This durable authorization correction cannot distinguish its insert from a pre-existing operator grant.
    }
};
