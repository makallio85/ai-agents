<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AgentIntegrationPermissionService;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * Integration tests for AgentIntegrationPermissionService.
 *
 * Covers the catalog accessors, the tool→action lookup, and the
 * persistence side of loading/replacing grants against a real
 * agent_integration_permissions table (in-memory test DB).
 *
 * No external services are touched — only TableRegistry-backed CRUD.
 */
class AgentIntegrationPermissionServiceTest extends TestCase
{
    /** @var array<string> */
    protected array $fixtures = [
        'app.Agents',
        'app.AgentIntegrationPermissions',
    ];

    private AgentIntegrationPermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AgentIntegrationPermissionService();
    }

    public function testCatalogContainsGithubGroup(): void
    {
        $catalog = $this->service->getCatalog();
        $this->assertArrayHasKey('github', $catalog);
        $this->assertNotEmpty($catalog['github']);
    }

    public function testCatalogFilterByIntegration(): void
    {
        $catalog = $this->service->getCatalog('github');
        $this->assertSame(['github'], array_keys($catalog));

        $catalog = $this->service->getCatalog('does-not-exist');
        $this->assertSame([], $catalog);
    }

    public function testAllActionKeysContainsGithubActions(): void
    {
        $keys = $this->service->getAllActionKeys();
        $this->assertContains('github.issues.read', $keys);
        $this->assertContains('github.issues.write', $keys);
        $this->assertContains('github.repos.read', $keys);
    }

    public function testGetActionForKnownToolReturnsAction(): void
    {
        $this->assertSame('github.issues.read', $this->service->getActionForTool('github_list_issues'));
        $this->assertSame('github.issues.write', $this->service->getActionForTool('github_create_issue'));
        $this->assertSame('github.pulls.read', $this->service->getActionForTool('github_get_pull_request'));
    }

    public function testGetActionForUnknownToolReturnsNull(): void
    {
        $this->assertNull($this->service->getActionForTool('does_not_exist'));
    }

    public function testLoadForAgentReturnsEmptySetWhenNoRows(): void
    {
        $set = $this->service->loadForAgent(1);
        $this->assertSame([], $set->all());
        $this->assertFalse($set->hasAnyForIntegration('github'));
    }

    public function testReplaceForAgentInsertsGrantsAndLoadReadsThem(): void
    {
        $this->service->replaceForAgent(1, ['github.issues.read', 'github.repos.read']);

        $set = $this->service->loadForAgent(1);
        $all = $set->all();
        sort($all);

        $this->assertSame(['github.issues.read', 'github.repos.read'], $all);
        $this->assertTrue($set->hasAnyForIntegration('github'));
    }

    public function testReplaceForAgentDropsUnknownActions(): void
    {
        $this->service->replaceForAgent(1, ['github.issues.read', 'not.a.real.action']);

        $set = $this->service->loadForAgent(1);
        $this->assertSame(['github.issues.read'], $set->all());
    }

    public function testReplaceForAgentRemovesPreviousGrants(): void
    {
        $this->service->replaceForAgent(1, ['github.issues.read', 'github.repos.read']);
        $this->service->replaceForAgent(1, ['github.commits.read']);

        $set = $this->service->loadForAgent(1);
        $this->assertSame(['github.commits.read'], $set->all());

        // Confirm count on the table directly
        $table = TableRegistry::getTableLocator()->get('AgentIntegrationPermissions');
        $count = $table->find()->where(['agent_id' => 1])->count();
        $this->assertSame(1, $count);
    }

    public function testReplaceForAgentScopedPerAgent(): void
    {
        $this->service->replaceForAgent(1, ['github.issues.read']);
        $this->service->replaceForAgent(2, ['github.repos.read']);

        $this->assertSame(['github.issues.read'], $this->service->loadForAgent(1)->all());
        $this->assertSame(['github.repos.read'], $this->service->loadForAgent(2)->all());
    }

    public function testReplaceForAgentDeduplicatesInput(): void
    {
        $this->service->replaceForAgent(1, [
            'github.issues.read',
            'github.issues.read',
            'github.issues.read',
        ]);

        $table = TableRegistry::getTableLocator()->get('AgentIntegrationPermissions');
        $this->assertSame(1, $table->find()->where(['agent_id' => 1])->count());
    }
}
