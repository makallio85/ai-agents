<?php
declare(strict_types=1);

namespace App\Test\TestCase\Authentication;

use Authentication\UrlChecker\DefaultUrlChecker;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;

/**
 * Tests DefaultUrlChecker behaviour in cakephp/authentication v4.
 *
 * v4 breaking change: RegexUrlChecker was removed. DefaultUrlChecker now uses
 * Router::url() to resolve the configured loginUrl before comparing it against
 * the request path. In a fully-booted application, Router::url('/api/v1/auth/login')
 * returns the base-prefixed path (e.g. '/ai-agents/api/v1/auth/login'), so a plain
 * string loginUrl works correctly in production.
 *
 * The integration test LoginUrlBasePathTest covers the end-to-end case with a
 * properly booted Router. The unit tests here document the lower-level checker
 * behaviour in isolation.
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
     * Plain string loginUrl fails in a unit-test context with a subdirectory base
     * because Router::url() is not aware of the base in isolation.
     * In a fully-booted application, Router::url('/api/v1/auth/login') resolves
     * to '/ai-agents/api/v1/auth/login' and the check passes — see LoginUrlBasePathTest.
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
            'In unit-test context, Router::url() does not prepend the base, so the ' .
            'compared path /api/v1/auth/login does not equal /ai-agents/api/v1/auth/login. ' .
            'The integration test LoginUrlBasePathTest validates the production behaviour.'
        );
    }

    /**
     * Plain string loginUrl matches when there is no subdirectory base.
     * Router::url('/api/v1/auth/login') returns '/api/v1/auth/login' and the
     * request path is also '/api/v1/auth/login'.
     */
    public function testPlainStringLoginUrlMatchesWithoutBase(): void
    {
        $request = new ServerRequest([
            'url' => '/api/v1/auth/login',
            'base' => '',
            'environment' => ['REQUEST_METHOD' => 'POST'],
        ]);

        $result = $this->checker->check($request, '/api/v1/auth/login', []);

        $this->assertTrue($result, 'Plain string loginUrl must match when there is no base path');
    }

    /**
     * In v4, DefaultUrlChecker does not support the useRegex option — it was
     * removed along with RegexUrlChecker. Passing a regex string as loginUrl
     * causes Router::url() to return the regex as-is, which does not match
     * the request path.
     */
    public function testRegexLoginUrlNoLongerWorksInV4(): void
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

        $this->assertFalse(
            $result,
            'In cakephp/authentication v4, DefaultUrlChecker does not support regex. ' .
            'The useRegex option is silently ignored and the regex string is compared ' .
            'literally against the request path, causing a mismatch.'
        );
    }
}
