<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\StoredFile;
use App\Domains\Resources\Policies\ResourcePolicy;
use App\Domains\Resources\Policies\StoredFilePolicy;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ResourcePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_policy_requires_live_capability_and_exact_ownership_without_privileged_bypass(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $administrator = User::factory()->withRole(RoleKey::Admin)->create();
        $resource = Resource::factory()->create(['user_id' => $owner->getKey()]);
        $policy = new ResourcePolicy;

        $this->assertTrue($policy->create($owner));
        $this->assertTrue($policy->view($owner, $resource));
        $this->assertTrue($policy->update($owner, $resource));
        $this->assertTrue($policy->delete($owner, $resource));
        $this->assertFalse($policy->view($other, $resource));
        $this->assertFalse($policy->create($administrator));
        $this->assertFalse($policy->view($administrator, $resource));

        $owner->roles()->detach();
        $this->assertFalse($policy->view($owner, $resource));
    }

    public function test_stored_file_policy_requires_live_capability_and_exact_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $resource = Resource::factory()->file()->create(['user_id' => $owner->getKey()]);
        $storedFile = StoredFile::factory()->forResource($resource)->create();
        $policy = new StoredFilePolicy;

        $this->assertTrue($policy->create($owner));
        $this->assertTrue($policy->view($owner, $storedFile));
        $this->assertTrue($policy->update($owner, $storedFile));
        $this->assertTrue($policy->delete($owner, $storedFile));
        $this->assertFalse($policy->view($other, $storedFile));

        $serialized = $storedFile->toArray();
        $this->assertArrayNotHasKey('user_id', $serialized);
        $this->assertArrayNotHasKey('resource_id', $serialized);
        $this->assertArrayNotHasKey('upload_key', $serialized);
        $this->assertArrayNotHasKey('object_key', $serialized);
        $this->assertArrayNotHasKey('sha256', $serialized);
    }
}
