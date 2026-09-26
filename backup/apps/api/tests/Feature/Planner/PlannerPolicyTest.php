<?php

declare(strict_types=1);

namespace Tests\Feature\Planner;

use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Planner\Models\FocusSession;
use App\Domains\Planner\Models\Task;
use App\Domains\Planner\Policies\FocusSessionPolicy;
use App\Domains\Planner\Policies\TaskPolicy;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlannerPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_policy_requires_live_capability_and_exact_ownership_without_privileged_bypass(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $administrator = User::factory()->withRole(RoleKey::Admin)->create();
        $task = Task::factory()->create(['user_id' => $owner->getKey()]);
        $policy = new TaskPolicy;

        $this->assertTrue($policy->create($owner));
        $this->assertTrue($policy->view($owner, $task));
        $this->assertTrue($policy->update($owner, $task));
        $this->assertTrue($policy->delete($owner, $task));
        $this->assertFalse($policy->view($other, $task));
        $this->assertFalse($policy->create($administrator));
        $this->assertFalse($policy->view($administrator, $task));
    }

    public function test_focus_session_policy_requires_live_capability_and_exact_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $focusSession = FocusSession::factory()->create(['user_id' => $owner->getKey()]);
        $policy = new FocusSessionPolicy;

        $this->assertTrue($policy->create($owner));
        $this->assertTrue($policy->view($owner, $focusSession));
        $this->assertTrue($policy->update($owner, $focusSession));
        $this->assertTrue($policy->delete($owner, $focusSession));
        $this->assertFalse($policy->view($other, $focusSession));
    }
}
