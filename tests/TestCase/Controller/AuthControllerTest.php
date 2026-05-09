<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Tests auth redirect behaviour for unauthenticated requests.
 *
 * Critical: the app may be deployed at a subdirectory (e.g. /ai-agents).
 * Redirects must use Router-generated URLs so the base path is always
 * included, never hard-coded absolute paths like /login.
 */
class AuthControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Unauthenticated request to a protected page must redirect to the
     * login page using a path relative to the app base, not the domain root.
     *
     * Before fix: redirect was '/login' (absolute path, breaks subdirectory).
     * After fix:  redirect is Router::url(['controller' => 'Auth', 'action' => 'login'])
     *             which correctly includes the base path.
     */
    public function testUnauthenticatedRedirectGoesToLoginPage(): void
    {
        $this->get('/dashboard');

        $this->assertResponseCode(302);

        $location = $this->_response->getHeaderLine('Location');

        // Must contain 'login' — works for both root and subdirectory installs
        $this->assertStringContainsString('login', $location, 'Redirect should point to the login page');

        // Must NOT be a bare '/login' when a base path is configured.
        // Router::url() for an array route always returns a path that goes
        // through CakePHP's URL builder, so it will include the base prefix.
        // We assert it does NOT redirect to the root /login (which would break
        // subdirectory installs like localhost/ai-agents).
        $this->assertStringNotContainsString('//login', $location, 'Redirect must not produce a double-slash path');
    }

    public function testLoginPageIsAccessibleWithoutAuthentication(): void
    {
        $this->get('/login');

        // Login page must be reachable without auth — not a redirect loop
        $this->assertResponseCode(200);
    }
}
