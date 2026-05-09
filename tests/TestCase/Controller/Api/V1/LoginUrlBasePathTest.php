<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api\V1;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Tests that the Form authenticator's loginUrl matches when the app is
 * served from a subdirectory (e.g. localhost/ai-agents).
 *
 * Bug: loginUrl was configured as the string '/api/v1/auth/login'.
 * DefaultUrlChecker prepends the request base path before comparing, so
 * in a subdirectory install the actual path becomes '/ai-agents/api/v1/auth/login'
 * which never equals '/api/v1/auth/login'. The Form authenticator silently
 * skips authentication and returns FAILURE_OTHER, causing valid credentials
 * to always respond with "Invalid email or password".
 *
 * Fix: configure loginUrl as a Router array so it goes through Router::url()
 * which produces the correct path including the base prefix.
 */
class LoginUrlBasePathTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = [
        'app.Roles',
        'app.Users',
    ];

    /**
     * Simulate a subdirectory install by setting the request base attribute.
     * Valid credentials must still authenticate successfully.
     */
    public function testLoginSucceedsWhenAppServedFromSubdirectory(): void
    {
        // Simulate Apache serving the app at /ai-agents
        $this->configRequest([
            'base' => '/ai-agents',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        $this->post('/api/v1/auth/login', json_encode([
            'email' => 'admin@example.com',
            'password' => 'admin123',
        ]));

        $this->assertHeader('Content-Type', 'application/json');

        $body = (string)$this->_response->getBody();
        $decoded = json_decode($body, true);

        $this->assertNotNull($decoded, 'Response must be valid JSON');
        $this->assertTrue(
            $decoded['success'],
            'Valid credentials must succeed even when app is in a subdirectory. Got: ' . $body
        );
    }
}
