<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AgentLogService;
use App\Service\AgentService;
use Cake\TestSuite\TestCase;

/**
 * Tests for AgentService.
 *
 * Covers the regression reported on PR #31 (issue #9 review): creating an
 * agent from the UI with only `name`, `description`, and `plugin` failed
 * because `slug` has no default value in the `agents` table. The service
 * (or table layer) must derive a slug from `name` when the caller does not
 * supply one.
 */
class AgentServiceTest extends TestCase
{
    /** @var array<string> */
    protected array $fixtures = [
        'app.Agents',
    ];

    private AgentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AgentService(new AgentLogService());
    }

    public function testCreateAgentWithoutSlugDerivesSlugFromName(): void
    {
        $agent = $this->service->create([
            'name' => 'My New Agent',
            'description' => 'Created from the UI without supplying a slug.',
            'plugin' => 'DevOpsOrchestrator',
        ]);

        $this->assertNotEmpty($agent->slug, 'Service must derive a slug when none is supplied');
        $this->assertSame('my-new-agent', $agent->slug);
        $this->assertNotNull($agent->id);
    }

    public function testCreateAgentRespectsCallerSuppliedSlug(): void
    {
        $agent = $this->service->create([
            'name' => 'Explicit Slug Agent',
            'slug' => 'custom-handle',
            'plugin' => 'DevOpsOrchestrator',
        ]);

        $this->assertSame('custom-handle', $agent->slug);
    }

    public function testCreateAgentDerivedSlugIsUniqueOnCollision(): void
    {
        // The fixture already contains slug = 'devops-orchestrator'. Creating
        // another agent with the same name must still succeed by suffixing.
        $agent = $this->service->create([
            'name' => 'DevOps Orchestrator',
            'plugin' => 'DevOpsOrchestrator',
        ]);

        $this->assertNotSame('devops-orchestrator', $agent->slug);
        $this->assertStringStartsWith('devops-orchestrator', $agent->slug);
    }
}
