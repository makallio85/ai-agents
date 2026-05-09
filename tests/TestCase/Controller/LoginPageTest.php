<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Tests that the login page HTML is structured so Vue is loaded
 * before the app scripts that depend on it.
 *
 * Bug: $this->Html->script() inside a template outputs tags inside the
 * `content` block, which renders before the Vue CDN script at the bottom
 * of the layout. Vue is undefined when the app script executes, so the
 * login form never mounts.
 *
 * Fix: scripts in templates must use $this->append('script', ...) so they
 * land in the `script` block, which is fetched after Vue is loaded.
 */
class LoginPageTest extends TestCase
{
    use IntegrationTestTrait;

    public function testLoginPageContainsLoginAppMountPoint(): void
    {
        $this->get('/login');

        $this->assertResponseCode(200);
        $this->assertResponseContains('id="login-app"');
    }

    public function testVueIsLoadedBeforeLoginAppScript(): void
    {
        $this->get('/login');

        $body = (string)$this->_response->getBody();

        $vuePos = strpos($body, 'vue.global');
        $appPos = strpos($body, 'vue/pages/Login/index');

        $this->assertNotFalse($vuePos, 'Vue CDN script must be present in the page');
        $this->assertNotFalse($appPos, 'Login app script must be present in the page');

        $this->assertLessThan(
            $appPos,
            $vuePos,
            'Vue CDN must be loaded before the login app script — otherwise Vue is undefined when the app mounts'
        );
    }

    public function testApiScriptIsLoadedBeforeLoginAppScript(): void
    {
        $this->get('/login');

        $body = (string)$this->_response->getBody();

        $apiPos = strpos($body, 'vue/api');
        $appPos = strpos($body, 'vue/pages/Login/index');

        $this->assertNotFalse($apiPos, 'api.js must be present in the page');
        $this->assertNotFalse($appPos, 'Login app script must be present in the page');

        $this->assertLessThan(
            $appPos,
            $apiPos,
            'api.js must be loaded before the login app script — the app depends on the Api global'
        );
    }
}
