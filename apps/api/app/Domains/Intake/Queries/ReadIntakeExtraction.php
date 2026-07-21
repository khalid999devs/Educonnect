<?php

declare(strict_types=1);

namespace App\Domains\Intake\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Intake\Data\IntakeExtractionWindow;
use App\Domains\Intake\Enums\IntakeArtifactKind;
use App\Domains\Intake\Exceptions\IntakePersistenceFailure;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Reads a bounded window of intake_artifacts.text_content.
 *
 * The slice is computed in Postgres (substring/char_length) so a 200,000
 * character artifact is never hydrated into PHP memory to serve a 20,000
 * character page.
 */
final readonly class ReadIntakeExtraction
{
    public const MAX_WINDOW_CHARACTERS = 20000;

    public function execute(User $user, IntakeItem $item, int $offset, int $limit): IntakeExtractionWindow
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        if ($offset < 0) {
            throw new InvalidArgumentException('Intake extraction offset must not be negative.');
        }

        if ($limit < 1 || $limit > self::MAX_WINDOW_CHARACTERS) {
            throw new InvalidArgumentException('Intake extraction limit must be between 1 and '.self::MAX_WINDOW_CHARACTERS.'.');
        }

        if ((int) $item->user_id !== (int) $user->getKey()) {
            throw new InvalidArgumentException('Intake extraction may only be read for an owned intake item.');
        }

        try {
            $row = DB::table('intake_artifacts')
                ->where('intake_item_id', $item->getKey())
                ->where('kind', IntakeArtifactKind::ExtractedText->value)
                ->orderByDesc('id')
                /* The ::int casts are load-bearing. PDO binds these
                   placeholders as text, and substring(text, text, text) is
                   the SQL-regex overload substring(string from pattern for
                   escape), which rejects a multi-character length as an
                   invalid escape (SQLSTATE 22025). Casting pins the
                   positional overload. Neither the comma form nor the
                   FROM/FOR keyword form is safe without it. */
                ->selectRaw(
                    'content_type,'
                    .' COALESCE(CHAR_LENGTH(text_content), 0) AS total_characters,'
                    .' COALESCE(SUBSTRING(text_content, ?::int, ?::int), \'\') AS window_text',
                    [$offset + 1, $limit],
                )
                ->first();
        } catch (QueryException $exception) {
            throw IntakePersistenceFailure::fromQueryException($exception, 'intake.extraction.read');
        }

        if ($row === null) {
            return IntakeExtractionWindow::absent((string) $item->public_id, $offset, $limit);
        }

        return new IntakeExtractionWindow(
            itemPublicId: (string) $item->public_id,
            hasExtraction: true,
            contentType: (string) $row->content_type,
            text: (string) $row->window_text,
            offset: $offset,
            limit: $limit,
            totalCharacters: (int) $row->total_characters,
        );
    }
}
