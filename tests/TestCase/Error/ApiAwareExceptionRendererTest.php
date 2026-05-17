<?php
declare(strict_types=1);

namespace App\Test\TestCase\Error;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * End-to-end tests for ApiAwareExceptionRenderer.
 *
 * The Vue frontend always uses fetch() with `Accept: application/json` and
 * blows up with "Unexpected token '<', '<!DOCTYPE ...'" if any /api/* path
 * answers with the framework's HTML error template. These tests assert the
 * renderer keeps the JSON contract intact for every uncaught exception
 * type that bubbles up to it, and that non-API paths still render HTML
 * the way they did before.
 */
class ApiAwareExceptionRendererTest extends TestCase
{
    use IntegrationTestTrait;

    /** @var array<string> */
    protected array $fixtures = [
        'app.Roles',
        'app.Users',
        'app.Permissions',
    ];

    public function testApiForbiddenReturnsJson(): void
    {
        // Hitting any authenticated API endpoint without a session yields
        // a ForbiddenException-equivalent — the renderer must keep it JSON.
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $this->get('/api/v1/users');

        $this->assertNotNull($this->_response);
        $body = (string)$this->_response->getBody();
        $this->assertStringNotContainsString('<!DOCTYPE', $body, 'API responses must never be HTML');
        $this->assertHeaderContains('Content-Type', 'application/json');

        $decoded = json_decode($body, true);
        $this->assertIsArray($decoded, 'API error must be valid JSON, got: ' . substr($body, 0, 200));
        $this->assertSame(false, $decoded['success'] ?? null, 'Body: ' . substr($body, 0, 400));
        $this->assertArrayHasKey('errors', $decoded);
        $this->assertNotEmpty($decoded['errors']);
    }

    public function testApiNotFoundReturnsJson(): void
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/v1/this-route-does-not-exist');

        $this->assertNotNull($this->_response);
        $body = (string)$this->_response->getBody();
        $this->assertStringNotContainsString('<!DOCTYPE', $body);
        $this->assertHeaderContains('Content-Type', 'application/json');

        $decoded = json_decode($body, true);
        $this->assertIsArray($decoded);
        $this->assertSame(false, $decoded['success']);
    }

    public function testNonApiRouteStillRendersHtml(): void
    {
        // Browser routes must continue to use the HTML error template, so
        // operators aren't shown a raw JSON blob when they fat-finger a URL.
        $this->get('/this-route-does-not-exist');

        $this->assertNotNull($this->_response);
        $body = (string)$this->_response->getBody();
        $this->assertStringContainsString('<', $body, 'HTML responses should not be JSON');
    }
}
