<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Courses\Actions\MaterializeOnboardingWorkspaceAction;
use App\Domains\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class MaterializeOnboardingCoursesCommand extends Command
{
    protected $signature = 'courses:materialize-onboarding
        {--limit=100 : Maximum number of completed onboarding aggregates to inspect}';

    protected $description = 'Materialize completed onboarding drafts into owned academic terms and courses';

    public function handle(MaterializeOnboardingWorkspaceAction $materialize): int
    {
        $rawLimit = (string) $this->option('limit');

        if (! ctype_digit($rawLimit) || (int) $rawLimit < 1 || (int) $rawLimit > 1000) {
            $this->error('The --limit option must be an integer between 1 and 1000.');

            return self::FAILURE;
        }

        $userIds = DB::table('onboarding_progress')
            ->whereNotNull('completed_at')
            ->whereNull('academic_materialized_at')
            ->orderBy('user_id')
            ->limit((int) $rawLimit)
            ->pluck('user_id');
        $materialized = 0;

        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);

            if (! $user instanceof User) {
                continue;
            }

            try {
                if ($materialize->execute($user)) {
                    $materialized++;
                }
            } catch (Throwable $exception) {
                Log::error('Onboarding academic materialization failed.', [
                    'exception_type' => $exception::class,
                ]);
                $this->error('Academic materialization failed. Resolve the reported database error and retry.');

                return self::FAILURE;
            }
        }

        $this->info("Materialized {$materialized} of {$userIds->count()} inspected onboarding aggregates.");

        return self::SUCCESS;
    }
}
