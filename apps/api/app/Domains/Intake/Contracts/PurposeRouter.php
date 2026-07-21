<?php

declare(strict_types=1);

namespace App\Domains\Intake\Contracts;

use App\Domains\Intake\AI\PurposeRoutingRequest;
use App\Domains\Intake\Enums\IntakePurpose;

/**
 * The purpose-routing boundary: given untrusted captured content, name the most
 * likely reason the student saved it.
 *
 * The answer is ADVISORY and never authoritative. Second Brain asks the student
 * the purpose; a router only pre-selects the most likely option and the student
 * confirms it. Implementations therefore always return a purpose - never null,
 * never a refusal - and a wrong route must stay a one-click correction rather
 * than a data-integrity event.
 */
interface PurposeRouter
{
    public function name(): string;

    public function model(): string;

    public function route(PurposeRoutingRequest $request): IntakePurpose;
}
