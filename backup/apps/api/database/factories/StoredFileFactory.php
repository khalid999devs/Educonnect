<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Resources\Enums\StoredFileStatus;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StoredFile> */
final class StoredFileFactory extends Factory
{
    protected $model = StoredFile::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'resource_id' => Resource::factory()->file(),
            'user_id' => static fn (array $attributes): int => (int) Resource::query()
                ->findOrFail($attributes['resource_id'])
                ->user_id,
            'original_name' => 'lecture-notes.pdf',
            'declared_mime_type' => 'application/pdf',
            'expected_size' => 1024,
            'sha256' => fake()->sha256(),
            'upload_key' => 'resources/v1/uploads/'.bin2hex(random_bytes(16)),
            'object_key' => 'resources/v1/objects/'.bin2hex(random_bytes(16)),
            'verified_mime_type' => null,
            'verified_size' => null,
            'status' => StoredFileStatus::Pending->value,
            'upload_expires_at' => now()->addMinutes(15),
            'cleanup_after' => now()->addMinutes(30),
            'cleanup_started_at' => null,
            'cleanup_failures' => 0,
            'purge_ready_at' => null,
            'ready_at' => null,
        ];
    }

    public function forResource(Resource $resource): static
    {
        return $this->state(fn (): array => [
            'user_id' => $resource->user_id,
            'resource_id' => $resource->getKey(),
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes): array => [
            'verified_mime_type' => $attributes['declared_mime_type'],
            'verified_size' => $attributes['expected_size'],
            'status' => StoredFileStatus::Ready->value,
            'ready_at' => now()->addSecond(),
        ]);
    }

    public function deletionPending(bool $wasReady = true): static
    {
        return $this->state(function (array $attributes) use ($wasReady): array {
            if (! $wasReady) {
                return [
                    'verified_mime_type' => null,
                    'verified_size' => null,
                    'status' => StoredFileStatus::DeletionPending->value,
                    'cleanup_after' => now()->addMinutes(30),
                    'ready_at' => null,
                ];
            }

            return [
                'verified_mime_type' => $attributes['declared_mime_type'],
                'verified_size' => $attributes['expected_size'],
                'status' => StoredFileStatus::DeletionPending->value,
                'cleanup_after' => now()->addMinutes(30),
                'ready_at' => now()->addSecond(),
            ];
        });
    }
}
