<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api\V1;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Tests that the API auth endpoints always return JSON, never HTML.
 *
 * Bug: POST /api/v1/auth/login from Vue fetch() has no CSRF token.
 * CsrfProtectionMiddleware blocks the request and returns an HTML error
 * page. The fetch() call then fails with "Unexpected token '<'" because
 * it tries to JSON-parse the HTML response.
 *
 * Fix: CSRF checks must be skipped for all /api/ routes since API clients
 * authenticate via session/token, not browser form submissions.
 */
class AuthControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = [
        'app.Roles',
        'app.Users',
    ];

    /**
     * POST to login without a CSRF token must return JSON, not HTML.
     * This is the exact scenario the Vue fetch() call produces.
     */
    public function testLoginWithoutCsrfTokenReturnsJson(): void
    {
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);

        // Pass body as JSON string — exactly how Vue fetch() sends it.
        // Using an array here would leave the raw body empty; BodyParserMiddleware
        // would then overwrite the parsed body with [], causing auth to fail.
        $this->post('/api/v1/auth/login', json_encode([
            'email' => 'wrong@example.com',
            'password' => 'wrongpassword',
        ]));

        // Must not return HTML (which would mean CSRF blocked it)
        $this->assertHeader('Content-Type', 'application/json');

        // Body must be valid JSON
        $body = (string)$this->_response->getBody();
        $decoded = json_decode($body, true);
        $this->assertNotNull($decoded, 'Response must be valid JSON, got: ' . substr($body, 0, 100));

        // Must follow the standard API envelope
        $this->assertArrayHasKey('success', $decoded);
    }

    /**
     * Valid credentials must return a JSON success response.
     */
    public function testLoginWithValidCredentialsReturnsJsonSuccess(): void
    {
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
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
        $this->assertTrue($decoded['success']);
    }
}
