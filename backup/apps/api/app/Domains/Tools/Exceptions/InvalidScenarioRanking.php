<?php

declare(strict_types=1);

namespace App\Domains\Tools\Exceptions;

use App\Support\Ai\Exceptions\InvalidAiOutput;

/**
 * A provider answered the scenario-search call but the answer failed
 * ScenarioRankingSchemaV1: unknown keys, an over-long or unsafe match reason,
 * a duplicated tool, or - the case that matters most - an id that was never in
 * the candidate set the query supplied.
 *
 * Being an InvalidAiOutput (and not a bare Throwable) is what makes a bounded
 * output retry worthwhile here: the provider is healthy, its answer was not.
 */
final class InvalidScenarioRanking extends InvalidAiOutput {}
