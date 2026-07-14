<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Authorization\Actions\BootstrapSuperAdminAction;
use App\Domains\Users\Models\User;
use DomainException;
use Illuminate\Console\Command;

final class BootstrapSuperAdminCommand extends Command
{
    protected $signature = 'authorization:bootstrap-super-admin
        {user : Public ULID of an existing verified user}
        {--reason= : Required operational reason recorded in the immutable audit log}
        {--confirm : Explicitly confirm this one-use privilege bootstrap}';

    protected $description = 'Grant the first super-admin role to an existing verified user';

    public function handle(BootstrapSuperAdminAction $bootstrapSuperAdmin): int
    {
        $reason = trim((string) $this->option('reason'));

        if (! $this->option('confirm') || $reason === '') {
            $this->error('Both --confirm and a non-empty --reason are required.');

            return self::FAILURE;
        }

        $user = User::query()->where('public_id', (string) $this->argument('user'))->first();

        if (! $user instanceof User) {
            $this->error('No user matches the supplied public ULID.');

            return self::FAILURE;
        }

        try {
            $bootstrapSuperAdmin->execute(
                target: $user,
                reason: $reason,
                requestId: 'req_'.bin2hex(random_bytes(16)),
            );
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('The initial super administrator was granted and all prior sessions were revoked.');

        return self::SUCCESS;
    }
}
