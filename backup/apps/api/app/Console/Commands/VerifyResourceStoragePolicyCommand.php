<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Resources\Data\StoragePolicyCheck;
use App\Domains\Resources\Exceptions\StoragePolicyInspectionFailure;
use App\Domains\Resources\Support\ResourceStoragePolicyVerifier;
use App\Domains\Resources\Support\S3StoragePolicyInspector;
use Illuminate\Console\Command;
use Throwable;

final class VerifyResourceStoragePolicyCommand extends Command
{
    protected $signature = 'resources:verify-storage-policy';

    protected $description = 'Verify private resource bucket lifecycle, CORS, versioning, and public-access policy.';

    public function handle(
        S3StoragePolicyInspector $inspector,
        ResourceStoragePolicyVerifier $verifier,
    ): int {
        try {
            $report = $verifier->verify($inspector->inspect());
        } catch (StoragePolicyInspectionFailure $exception) {
            $this->components->error($exception->safeMessage);

            return self::FAILURE;
        } catch (Throwable) {
            $this->components->error('Storage policy verification failed unexpectedly.');

            return self::FAILURE;
        }

        foreach ($report->checks as $check) {
            $message = "{$check->status}: {$check->message}";

            match ($check->status) {
                StoragePolicyCheck::PASS => $this->components->info($message),
                StoragePolicyCheck::MANUAL => $this->components->warn($message),
                default => $this->components->error($message),
            };
        }

        return $report->passes() ? self::SUCCESS : self::FAILURE;
    }
}
