<?php
declare(strict_types=1);

namespace App\Test\TestCase\Authentication;

use Authentication\UrlChecker\DefaultUrlChecker;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;

/**
 * Tests that the Form authenticator's loginUrl matches when the app is
 * served from a subdirectory (e.g. localhost/ai-agents).
 *
 * DefaultUrlChecker prepends the request `base` attribute before comparing,
 * so a plain string loginUrl like '/api/v1/auth/login' will never match
 * '/ai-agents/api/v1/auth/login' in production.
 *
 * Fix: use a regex loginUrl that matches the path suffix regardless of
 * any base prefix.
 */
class LoginUrlSubdirectoryTest extends TestCase
{
    private DefaultUrlChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new DefaultUrlChecker();
    }

    /**
     * Plain string loginUrl fails when app has a subdirectory base path.
     * This is the bug that causes "Invalid email or password" in production.
     */
    public function testPlainStringLoginUrlFailsWithSubdirectoryBase(): void
    {
        $request = new ServerRequest([
            'url' => '/api/v1/auth/login',
            'base' => '/ai-agents',
            'environment' => ['REQUEST_METHOD' => 'POST'],
        ]);

        $result = $this->checker->check($request, '/api/v1/auth/login', []);

        $this->assertFalse(
            $result,
            'A plain string loginUrl must NOT match when a base path is present — ' .
            'DefaultUrlChecker prepends the base, so the compared path becomes ' .
            '/ai-agents/api/v1/auth/login which differs from /api/v1/auth/login'
        );
    }

    /**
     * Regex loginUrl must match even with a subdirectory base path.
     * This is the correct configuration after the fix.
     */
    public function testRegexLoginUrlMatchesWithSubdirectoryBase(): void
    {
        $request = new ServerRequest([
            'url' => '/api/v1/auth/login',
            'base' => '/ai-agents',
            'environment' => ['REQUEST_METHOD' => 'POST'],
        ]);

        $result = $this->checker->check(
            $request,
            '#/api/v1/auth/login$#',
            ['useRegex' => true]
        );

        $this->assertTrue(
            $result,
            'A regex loginUrl must match when a base path is present — ' .
            'the regex anchors to the end of the path, so it matches regardless of prefix'
        );
    }

    /**
     * Regex loginUrl must also match with no base path (root install).
     */
    public function testRegexLoginUrlMatchesWithoutBase(): void
    {
        $request = new ServerRequest([
            'url' => '/api/v1/auth/login',
            'base' => '',
            'environment' => ['REQUEST_METHOD' => 'POST'],
        ]);

        $result = $this->checker->check(
            $request,
            '#/api/v1/auth/login$#',
            ['useRegex' => true]
        );

        $this->assertTrue($result, 'Regex loginUrl must match when there is no base path');
    }
}
