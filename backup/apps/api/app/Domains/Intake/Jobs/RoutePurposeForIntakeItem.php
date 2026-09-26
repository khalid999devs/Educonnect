<?php

declare(strict_types=1);

namespace App\Domains\Intake\Jobs;

use App\Domains\Intake\AI\PurposeRouterPolicy;
use App\Domains\Intake\AI\PurposeRoutingRequest;
use App\Domains\Intake\AI\RulePurposeRouter;
use App\Domains\Intake\Contracts\PurposeRouter;
use App\Domains\Intake\Enums\IntakeArtifactKind;
use App\Domains\Intake\Enums\IntakePurpose;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Telemetry\Enums\TelemetryOutcome;
use App\Domains\Telemetry\Support\TelemetryRecorder;
use App\Support\Ai\AiFeature;
use App\Support\Ai\Exceptions\InvalidAiOutput;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pre-selects why the student captured something and writes it to
 * `knowledge_items.purpose`.
 *
 * The routed purpose is ADVISORY, never authoritative. Second Brain asks the
 * student the purpose; this job only pre-selects the most likely option so the
 * confirmation step starts on the right answer more often than not. The student
 * confirms, and a wrong route must stay a one-click correction rather than a
 * data-integrity event. Nothing downstream may treat this column as a fact the
 * user asserted, and nothing here may fail an intake: a routing failure leaves
 * `purpose` NULL and the student simply picks unaided.
 *
 * Structure is copied from ClassifyIntakeItem: `tries = 1` because retry is
 * domain-owned, a scalar-id-only constructor so no stale model is serialised,
 * collaborators method-injected into handle(), a locking claim() that makes
 * duplicate dispatch a no-op, a bounded output-retry loop where InvalidAiOutput
 * retries and any other Throwable abandons that router, and a persist() that
 * re-locks and re-checks before writing.
 *
 * Dispatch this with `->afterCommit()` so the knowledge item and its
 * `intake_item_id` link are visible to the worker. The dispatch site itself
 * lands with the intake -> knowledge suggestion flow (W2-C3); until then this
 * job is a no-op for items that have no linked knowledge item.
 */
final class RoutePurposeForIntakeItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $intakeItemId) {}

    public function handle(
        PurposeRouterPolicy $policy,
        TelemetryRecorder $telemetry,
    ): void {
        $claimed = $this->claim();

        if ($claimed === null) {
            return;
        }

        $request = $this->buildRequest($claimed['item'], $policy);
        $usedFallback = false;

        foreach ($policy->routers() as $router) {
            $remote = ! $router instanceof RulePurposeRouter;
            $startedAt = hrtime(true);
            $purpose = $this->attemptRouter($router, $policy, $request);
            $latencyMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

            if ($purpose instanceof IntakePurpose) {
                if (! $remote && $usedFallback) {
                    // A remote router failed first; the deterministic path
                    // answered. Success, Failure, and Degraded outcomes are all
                    // recorded by AiAgentRunner inside the agent, so recording
                    // them again here would double-count.
                    $telemetry->recordAiCall(
                        AiFeature::PurposeRouting->telemetryName(),
                        TelemetryOutcome::Fallback,
                        $latencyMs,
                        ['provider' => $router->name(), 'model' => $router->model()],
                    );
                }

                $this->persist($claimed['knowledge'], $purpose);
                $telemetry->recordIntakeJob('intake.route_purpose', TelemetryOutcome::Success);

                return;
            }

            if ($remote) {
                $usedFallback = true;
            }
        }

        // Advisory routing that produced nothing is not an intake failure: the
        // item stays untouched with a NULL purpose and the student chooses.
        $telemetry->recordIntakeJob('intake.route_purpose', TelemetryOutcome::Failure, null, [
            'code' => 'purpose_routing_failed',
        ]);
    }

    /**
     * There is no transient state to free: an unrouted item is simply one whose
     * purpose is still NULL, which is exactly what a dead worker leaves behind.
     * The terminal outcome is still recorded so a silently failing router is
     * visible in telemetry rather than merely absent.
     */
    public function failed(?Throwable $exception): void
    {
        app(TelemetryRecorder::class)->recordIntakeJob(
            'intake.route_purpose',
            TelemetryOutcome::Failure,
            null,
            ['code' => 'interrupted'],
        );
    }

    /**
     * Locks the intake item and its knowledge item, and refuses to run when the
     * purpose has already been routed. That state guard is what makes duplicate
     * dispatch a no-op rather than a second provider call and a second write.
     *
     * @return array{item: IntakeItem, knowledge: KnowledgeItem}|null
     */
    private function claim(): ?array
    {
        return DB::transaction(function (): ?array {
            $item = IntakeItem::query()->whereKey($this->intakeItemId)->lockForUpdate()->first();

            if (! $item instanceof IntakeItem) {
                return null;
            }

            $knowledge = KnowledgeItem::query()
                ->where('user_id', $item->user_id)
                ->where('intake_item_id', $item->getKey())
                ->lockForUpdate()
                ->first();

            // getAttribute rather than property access: KnowledgeItem declares
            // no @property docblocks, and W2-C2 owns that model.
            if (! $knowledge instanceof KnowledgeItem || $knowledge->getAttribute('purpose') !== null) {
                return null;
            }

            return ['item' => $item, 'knowledge' => $knowledge];
        }, 3);
    }

    private function buildRequest(IntakeItem $item, PurposeRouterPolicy $policy): PurposeRoutingRequest
    {
        $artifact = $item->artifacts()
            ->where('kind', IntakeArtifactKind::ExtractedText->value)
            ->first();
        $text = $artifact?->text_content;
        // Read the stored file by its foreign key rather than through
        // IntakeItem::resource(), whose PHPDoc type is the PHP `resource`
        // pseudo-type and therefore carries no relation properties.
        $storedFile = $item->resource_id === null
            ? null
            : StoredFile::query()->where('resource_id', $item->resource_id)->first();

        return new PurposeRoutingRequest(
            extractedText: mb_substr(is_string($text) ? $text : '', 0, $policy->maxInputCharacters()),
            sourceType: $item->source_type,
            context: $item->context,
            sourceUrl: $item->url,
            fileName: $storedFile?->original_name,
            mimeType: $storedFile?->declared_mime_type,
        );
    }

    /**
     * Bounded output retries: a schema rejection means the router answered but
     * answered badly, which is worth one more attempt. Any other Throwable
     * means the router itself is unhealthy, so it is abandoned immediately and
     * the next router in the chain takes over.
     */
    private function attemptRouter(
        PurposeRouter $router,
        PurposeRouterPolicy $policy,
        PurposeRoutingRequest $request,
    ): ?IntakePurpose {
        $attempts = $policy->maxOutputRetries() + 1;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            try {
                return $router->route($request);
            } catch (InvalidAiOutput) {
                Log::info('Purpose router output rejected.', [
                    'router' => $router->name(),
                    'attempt' => $attempt + 1,
                    'intake_item_id' => $this->intakeItemId,
                ]);
            } catch (Throwable $exception) {
                Log::warning('Purpose router failed.', [
                    'router' => $router->name(),
                    'exception_type' => $exception::class,
                    'intake_item_id' => $this->intakeItemId,
                ]);

                return null;
            }
        }

        return null;
    }

    private function persist(KnowledgeItem $knowledge, IntakePurpose $purpose): void
    {
        DB::transaction(function () use ($knowledge, $purpose): void {
            $fresh = KnowledgeItem::query()->whereKey($knowledge->getKey())->lockForUpdate()->first();

            // Re-checked under the lock: if anything routed or, more
            // importantly, if the STUDENT chose a purpose while this job was
            // talking to a provider, the advisory answer must not overwrite it.
            if (! $fresh instanceof KnowledgeItem || $fresh->getAttribute('purpose') !== null) {
                return;
            }

            $fresh->forceFill(['purpose' => $purpose->value])->save();
        }, 3);
    }
}
