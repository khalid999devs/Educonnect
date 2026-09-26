<?php

declare(strict_types=1);

namespace App\Domains\Telemetry\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single durable operational telemetry row. Diagnostic, not compliance
 * evidence - it never carries an actor, a reason, or private academic content.
 */
final class TelemetryEvent extends Model
{
    public $timestamps = false;

    protected $table = 'telemetry_events';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
