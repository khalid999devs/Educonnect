<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Domains\Authorization\Contracts\ModerationTarget;
use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Authorization\Policies\ModerationPolicy;
use App\Domains\Users\Models\User;
use App\Domains\Users\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Tests\TestCase;

final class AuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_policy_permits_only_exact_self_view_and_defaults_other_abilities_to_denied(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Gate::policy(User::class, UserPolicy::class);

        $this->assertTrue(Gate::forUser($user)->allows('view', $user));
        $this->assertFalse(Gate::forUser($user)->allows('view', $otherUser));
        $this->assertFalse(Gate::forUser($user)->allows('update', $user));
        $this->assertFalse(Gate::forUser($otherUser)->allows('view', $user));
    }

    public function test_scoped_moderator_requires_both_the_capability_and_explicit_target_scope(): void
    {
        $moderator = $this->userWithCapabilities(
            101,
            CapabilityKey::ModerationScoped,
        );
        $policy = new ModerationPolicy;

        $this->assertTrue($policy->moderate(
            $moderator,
            new TestModerationTarget([101]),
        ));
        $this->assertFalse($policy->moderate(
            $moderator,
            new TestModerationTarget([202]),
        ));
    }

    public function test_scope_membership_without_the_scoped_capability_is_denied(): void
    {
        $user = $this->userWithCapabilities(101);

        $this->assertFalse((new ModerationPolicy)->moderate(
            $user,
            new TestModerationTarget([101]),
        ));
    }

    public function test_global_moderation_capability_does_not_require_target_scope_membership(): void
    {
        $admin = $this->userWithCapabilities(
            101,
            CapabilityKey::ModerationGlobal,
        );

        $this->assertTrue((new ModerationPolicy)->moderate(
            $admin,
            new TestModerationTarget([]),
        ));
    }

    private function userWithCapabilities(int $id, CapabilityKey ...$capabilities): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->setAttribute('id', $id);
        $user->shouldReceive('hasCapability')
            ->andReturnUsing(
                static fn (CapabilityKey $capability): bool => in_array($capability, $capabilities, true),
            );

        return $user;
    }
}

/**
 * A test-only target proves the composite policy contract without creating a
 * speculative moderation-scope table before a scoped product domain exists.
 */
final readonly class TestModerationTarget implements ModerationTarget
{
    /**
     * @param  list<int>  $moderatorIds
     */
    public function __construct(private array $moderatorIds) {}

    public function isInModerationScopeFor(User $moderator): bool
    {
        return in_array((int) $moderator->getKey(), $this->moderatorIds, true);
    }
}
