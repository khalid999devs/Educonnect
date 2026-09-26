<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array{name: string, display_priority: int, is_protected: bool}>
     */
    private const ROLES = [
        'student' => ['name' => 'Student', 'display_priority' => 10, 'is_protected' => false],
        'mentor' => ['name' => 'Mentor', 'display_priority' => 20, 'is_protected' => false],
        'moderator' => ['name' => 'Moderator', 'display_priority' => 30, 'is_protected' => false],
        'admin' => ['name' => 'Admin', 'display_priority' => 40, 'is_protected' => true],
        'super_admin' => ['name' => 'Super admin', 'display_priority' => 50, 'is_protected' => true],
    ];

    /**
     * @var array<string, string>
     */
    private const CAPABILITIES = [
        'academic.manage-own' => 'Manage owned academic data',
        'admin.access' => 'Access the administrative application surface',
        'moderation.scoped' => 'Moderate explicitly assigned scopes',
        'moderation.global' => 'Moderate across all scopes',
        'users.private-support-access' => 'Access private user data through an audited support workflow',
        'users.suspend' => 'Suspend and reactivate user accounts',
        'content.curate' => 'Curate platform-owned content',
        'mentors.curate' => 'Curate mentor profiles',
        'authorization.roles-view' => 'View role and capability assignments',
        'authorization.roles-assign' => 'Assign non-protected roles',
        'authorization.protected-roles-manage' => 'Assign or remove protected roles',
        'authorization.capabilities-manage' => 'Change role capability mappings',
        'audit.view-scoped' => 'View audit events within an assigned scope',
        'audit.view-all' => 'View all authorization audit events',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const ROLE_CAPABILITIES = [
        'student' => [
            'academic.manage-own',
        ],
        'mentor' => [
            'academic.manage-own',
        ],
        'moderator' => [
            'admin.access',
            'moderation.scoped',
            'audit.view-scoped',
        ],
        'admin' => [
            'admin.access',
            'moderation.global',
            'users.suspend',
            'content.curate',
            'mentors.curate',
            'authorization.roles-view',
            'authorization.roles-assign',
            'audit.view-all',
        ],
        'super_admin' => [
            'admin.access',
            'moderation.scoped',
            'moderation.global',
            'users.suspend',
            'content.curate',
            'mentors.curate',
            'authorization.roles-view',
            'authorization.roles-assign',
            'authorization.protected-roles-manage',
            'authorization.capabilities-manage',
            'audit.view-scoped',
            'audit.view-all',
        ],
    ];

    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('display_priority')->unique();
            $table->boolean('is_system')->default(true);
            $table->boolean('is_protected')->default(false);
            $table->timestampsTz(0);
        });

        Schema::create('capabilities', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 128)->unique();
            $table->string('name', 160);
            $table->timestampsTz(0);
        });

        Schema::create('role_capability', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('capability_id')->constrained('capabilities')->cascadeOnDelete();
            $table->primary(['role_id', 'capability_id']);
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('assigned_at', 0)->useCurrent();
            $table->primary(['role_id', 'user_id']);
            $table->index(['user_id', 'assigned_at']);
        });

        DB::statement("ALTER TABLE roles ADD CONSTRAINT roles_key_format CHECK (key ~ '^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$')");
        DB::statement("ALTER TABLE capabilities ADD CONSTRAINT capabilities_key_format CHECK (key ~ '^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*$')");
        $this->createKeyImmutabilityTriggers();
        $this->insertAuthorizationCatalog();
        $this->backfillStudents();
    }

    public function down(): void
    {
        if (Schema::hasTable('audit_events') && DB::table('audit_events')->exists()) {
            throw new RuntimeException('Cannot roll back authorization tables while immutable audit evidence exists.');
        }

        $studentRoleId = DB::table('roles')->where('key', 'student')->value('id');
        $hasNonStudentAssignments = DB::table('role_user')
            ->when($studentRoleId !== null, fn ($query) => $query->where('role_id', '<>', $studentRoleId))
            ->exists();

        if ($hasNonStudentAssignments) {
            throw new RuntimeException('Cannot roll back authorization tables while privileged role assignments exist.');
        }

        DB::statement('DROP TRIGGER IF EXISTS capabilities_key_immutable ON capabilities');
        DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_capability_key_update()');
        DB::statement('DROP TRIGGER IF EXISTS roles_key_immutable ON roles');
        DB::statement('DROP FUNCTION IF EXISTS educonnect_reject_role_key_update()');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('role_capability');
        Schema::dropIfExists('capabilities');
        Schema::dropIfExists('roles');
    }

    private function insertAuthorizationCatalog(): void
    {
        $now = now();

        foreach (self::ROLES as $key => $definition) {
            DB::table('roles')->insert([
                'key' => $key,
                'name' => $definition['name'],
                'display_priority' => $definition['display_priority'],
                'is_system' => true,
                'is_protected' => $definition['is_protected'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (self::CAPABILITIES as $key => $name) {
            DB::table('capabilities')->insert([
                'key' => $key,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleIds = DB::table('roles')->pluck('id', 'key');
        $capabilityIds = DB::table('capabilities')->pluck('id', 'key');

        foreach (self::ROLE_CAPABILITIES as $roleKey => $capabilityKeys) {
            foreach ($capabilityKeys as $capabilityKey) {
                DB::table('role_capability')->insert([
                    'role_id' => $roleIds[$roleKey],
                    'capability_id' => $capabilityIds[$capabilityKey],
                ]);
            }
        }
    }

    private function backfillStudents(): void
    {
        $studentRoleId = DB::table('roles')->where('key', 'student')->value('id');

        if (! is_int($studentRoleId)) {
            throw new RuntimeException('The canonical student role was not created.');
        }

        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($users) use ($studentRoleId): void {
                $assignedAt = now();
                $assignments = $users->map(fn ($user): array => [
                    'role_id' => $studentRoleId,
                    'user_id' => $user->id,
                    'assigned_at' => $assignedAt,
                ])->all();

                if ($assignments !== []) {
                    DB::table('role_user')->insert($assignments);
                }
            });
    }

    private function createKeyImmutabilityTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION educonnect_reject_role_key_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.key IS DISTINCT FROM OLD.key THEN
                    RAISE EXCEPTION 'roles.key is immutable' USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER roles_key_immutable
            BEFORE UPDATE OF key ON roles
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_role_key_update();

            CREATE OR REPLACE FUNCTION educonnect_reject_capability_key_update()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.key IS DISTINCT FROM OLD.key THEN
                    RAISE EXCEPTION 'capabilities.key is immutable' USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER capabilities_key_immutable
            BEFORE UPDATE OF key ON capabilities
            FOR EACH ROW
            EXECUTE FUNCTION educonnect_reject_capability_key_update();
            SQL);
    }
};
