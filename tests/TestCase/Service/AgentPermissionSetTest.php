<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AgentPermissionSet;
use Cake\TestSuite\TestCase;

/**
 * Unit tests for AgentPermissionSet.
 *
 * Pure in-memory behaviour: no DB, no fixtures. Verifies that membership
 * checks honour the supplied grant list and that hasAnyForIntegration()
 * applies the `<integration>.` prefix correctly.
 */
class AgentPermissionSetTest extends TestCase
{
    public function testHasReturnsFalseForEmptySet(): void
    {
        $set = new AgentPermissionSet([]);
        $this->assertFalse($set->has('github.issues.read'));
    }

    public function testHasReturnsTrueForGrantedAction(): void
    {
        $set = new AgentPermissionSet(['github.issues.read', 'github.repos.read']);
        $this->assertTrue($set->has('github.issues.read'));
        $this->assertTrue($set->has('github.repos.read'));
        $this->assertFalse($set->has('github.issues.write'));
    }

    public function testHasAnyForIntegrationDetectsPrefix(): void
    {
        $set = new AgentPermissionSet(['github.issues.read']);
        $this->assertTrue($set->hasAnyForIntegration('github'));
        $this->assertFalse($set->hasAnyForIntegration('slack'));
    }

    public function testHasAnyForIntegrationReturnsFalseForEmptySet(): void
    {
        $set = new AgentPermissionSet([]);
        $this->assertFalse($set->hasAnyForIntegration('github'));
    }

    public function testHasAnyForIntegrationDoesNotMatchOnSubstring(): void
    {
        // "github" must not match "githubx.foo.bar" — prefix MUST end in '.'
        $set = new AgentPermissionSet(['githubx.foo.bar']);
        $this->assertFalse($set->hasAnyForIntegration('github'));
    }

    public function testAllReturnsTheGrantList(): void
    {
        $set = new AgentPermissionSet(['github.issues.read', 'github.repos.read']);
        $all = $set->all();
        sort($all);
        $this->assertSame(['github.issues.read', 'github.repos.read'], $all);
    }
}
