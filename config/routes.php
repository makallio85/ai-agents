<?php
/**
 * Routes configuration.
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are very important mechanism that allows you to freely connect
 * different URLs to chosen controllers and their actions (functions).
 *
 * It's loaded within the context of `Application::routes()` method which
 * receives a `RouteBuilder` instance `$routes` as method argument.
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

/*
 * This file is loaded in the context of the `Application` class.
 * So you can use `$this` to reference the application class instance
 * if required.
 */
return function (RouteBuilder $routes): void {
    /*
     * The default class to use for all routes
     *
     * The following route classes are supplied with CakePHP and are appropriate
     * to set as the default:
     *
     * - Route
     * - InflectedRoute
     * - DashedRoute
     *
     * If no call is made to `Router::defaultRouteClass()`, the class used is
     * `Route` (`Cake\Routing\Route\Route`)
     *
     * Note that `Route` does not do any inflections on URLs which will result in
     * inconsistently cased URLs when used with `{plugin}`, `{controller}` and
     * `{action}` markers.
     */
    $routes->setRouteClass(DashedRoute::class);

    $routes->scope('/', function (RouteBuilder $builder): void {
        $builder->connect('/', ['controller' => 'Pages', 'action' => 'display', 'home']);
        $builder->connect('/pages/*', 'Pages::display');

        // Auth routes
        $builder->connect('/login', ['controller' => 'Auth', 'action' => 'login']);
        $builder->connect('/auth/logout', ['controller' => 'Auth', 'action' => 'logout']);

        // App pages
        $builder->connect('/dashboard', ['controller' => 'Dashboard', 'action' => 'index']);
        $builder->connect('/agents', ['controller' => 'Agents', 'action' => 'index']);
        $builder->connect('/agents/view/{id}', ['controller' => 'Agents', 'action' => 'view'], ['id' => '\d+', 'pass' => ['id']]);
        $builder->connect('/labels', ['controller' => 'Labels', 'action' => 'index']);
        $builder->connect('/github-integrations', ['controller' => 'GithubIntegrations', 'action' => 'index']);
        $builder->connect('/logs', ['controller' => 'Logs', 'action' => 'index']);
        $builder->connect('/chat', ['controller' => 'Chat', 'action' => 'index']);
        $builder->connect('/integrations', ['controller' => 'Integrations', 'action' => 'index']);
        $builder->connect('/users', ['controller' => 'Users', 'action' => 'index']);
        $builder->connect('/messaging-requests', ['controller' => 'MessagingRequests', 'action' => 'index']);
        // Backwards-compat redirect: /messaging-guests was renamed to
        // /messaging-requests in issue #14 to match the new nav label.
        $builder->redirect('/messaging-guests', '/messaging-requests');
        $builder->connect('/settings/permissions', ['controller' => 'Settings', 'action' => 'permissions']);
        $builder->connect('/profile', ['controller' => 'Profile', 'action' => 'index']);

        $builder->fallbacks();
    });

    /*
     * API v1 routes
     */
    $routes->prefix('Api/V1', ['path' => '/api/v1'], function (RouteBuilder $builder): void {
        $builder->setExtensions(['json']);

        // Auth endpoints (no auth required for login/verify-mfa)
        $builder->connect('/auth/login', ['controller' => 'Auth', 'action' => 'login'], ['_name' => 'api.v1.auth.login']);
        $builder->connect('/auth/verify-mfa', ['controller' => 'Auth', 'action' => 'verifyMfa'], ['_name' => 'api.v1.auth.verify_mfa']);
        $builder->connect('/auth/logout', ['controller' => 'Auth', 'action' => 'logout'], ['_name' => 'api.v1.auth.logout']);
        $builder->connect('/auth/me', ['controller' => 'Auth', 'action' => 'me'], ['_name' => 'api.v1.auth.me']);

        // Agent CRUD
        $builder->connect('/agents', ['controller' => 'Agents', 'action' => 'index'], ['_name' => 'api.v1.agents.index']);
        $builder->connect('/agents/create', ['controller' => 'Agents', 'action' => 'create'], ['_name' => 'api.v1.agents.create']);
        $builder->connect('/agents/view/{id}', ['controller' => 'Agents', 'action' => 'view'], ['_name' => 'api.v1.agents.view', 'id' => '\d+', 'pass' => ['id']]);
        $builder->connect('/agents/update/{id}', ['controller' => 'Agents', 'action' => 'update'], ['_name' => 'api.v1.agents.update', 'id' => '\d+', 'pass' => ['id']]);
        $builder->connect('/agents/delete/{id}', ['controller' => 'Agents', 'action' => 'delete'], ['_name' => 'api.v1.agents.delete', 'id' => '\d+', 'pass' => ['id']]);
        $builder->connect('/agents/logs/{id}', ['controller' => 'Agents', 'action' => 'logs'], ['_name' => 'api.v1.agents.logs', 'id' => '\d+', 'pass' => ['id']]);
        // Per-agent message channels (Slack, WhatsApp, ...) — unified surface
        // for the "MessageChannels" concept (issue #15). MessageChannelsController
        // delegates to MessageChannelRegistry so new channel types appear without
        // touching the routes file.
        $builder->connect('/message-channels/{id}', ['controller' => 'MessageChannels', 'action' => 'index'], ['_method' => 'GET', '_name' => 'api.v1.message_channels.index', 'id' => '\d+', 'pass' => ['id']]);
        $builder->connect('/message-channels/update/{id}/{type}', ['controller' => 'MessageChannels', 'action' => 'update'], ['_method' => 'POST', '_name' => 'api.v1.message_channels.update', 'id' => '\d+', 'type' => '[a-z0-9_-]+', 'pass' => ['id', 'type']]);

        // Users — approval workflow (and minimal admin listing)
        $builder->connect('/users', ['controller' => 'Users', 'action' => 'index'], ['_name' => 'api.v1.users.index']);
        $builder->connect('/users/view/{id}', ['controller' => 'Users', 'action' => 'view'], ['_name' => 'api.v1.users.view', 'id' => '\d+', 'pass' => ['id']]);
        $builder->connect('/users/approve/{id}', ['controller' => 'Users', 'action' => 'approve'], ['_method' => 'POST', '_name' => 'api.v1.users.approve', 'id' => '\d+', 'pass' => ['id']]);
        $builder->connect('/users/reject/{id}', ['controller' => 'Users', 'action' => 'reject'], ['_method' => 'POST', '_name' => 'api.v1.users.reject', 'id' => '\d+', 'pass' => ['id']]);
        $builder->connect('/users/reply-mode/{id}', ['controller' => 'Users', 'action' => 'setReplyMode'], ['_method' => 'POST', '_name' => 'api.v1.users.set_reply_mode', 'id' => '\d+', 'pass' => ['id']]);

        // Labels
        $builder->connect('/labels', ['controller' => 'Labels', 'action' => 'index'], ['_name' => 'api.v1.labels.index']);
        $builder->connect('/labels/create', ['controller' => 'Labels', 'action' => 'create'], ['_name' => 'api.v1.labels.create']);
        $builder->connect('/labels/view/{id}', ['controller' => 'Labels', 'action' => 'view'], ['_name' => 'api.v1.labels.view', 'id' => '\d+', 'pass' => ['id']]);
        $builder->connect('/labels/update/{id}', ['controller' => 'Labels', 'action' => 'update'], ['_name' => 'api.v1.labels.update', 'id' => '\d+', 'pass' => ['id']]);
        $builder->connect('/labels/delete/{id}', ['controller' => 'Labels', 'action' => 'delete'], ['_name' => 'api.v1.labels.delete', 'id' => '\d+', 'pass' => ['id']]);

        // Logs (cross-agent view)
        $builder->connect('/logs', ['controller' => 'Logs', 'action' => 'index'], ['_name' => 'api.v1.logs.index']);

        // Chat sessions
        $builder->connect('/chat', ['controller' => 'Chat', 'action' => 'index'], ['_name' => 'api.v1.chat.index']);
        $builder->connect('/chat/create', ['controller' => 'Chat', 'action' => 'create'], ['_name' => 'api.v1.chat.create']);
        $builder->connect('/chat/view/{id}', ['controller' => 'Chat', 'action' => 'view'], ['_name' => 'api.v1.chat.view', 'id' => '\d+', 'pass' => ['id']]);
        $builder->connect('/chat/delete/{id}', ['controller' => 'Chat', 'action' => 'delete'], ['_name' => 'api.v1.chat.delete', 'id' => '\d+', 'pass' => ['id']]);
        $builder->connect('/chat/message/{id}', ['controller' => 'Chat', 'action' => 'message'], ['_name' => 'api.v1.chat.message', 'id' => '\d+', 'pass' => ['id']]);
        // Human handoff endpoints — same controller, channel-agnostic
        $builder->connect('/chat/escalate/{id}', ['controller' => 'Chat', 'action' => 'escalate'], ['_name' => 'api.v1.chat.escalate', 'id' => '\d+', 'pass' => ['id']]);
        $builder->connect('/chat/assign/{id}', ['controller' => 'Chat', 'action' => 'assign'], ['_name' => 'api.v1.chat.assign', 'id' => '\d+', 'pass' => ['id']]);
        $builder->connect('/chat/handoff-back/{id}', ['controller' => 'Chat', 'action' => 'handoffBack'], ['_name' => 'api.v1.chat.handoff_back', 'id' => '\d+', 'pass' => ['id']]);
        $builder->connect('/chat/human-reply/{id}', ['controller' => 'Chat', 'action' => 'humanReply'], ['_name' => 'api.v1.chat.human_reply', 'id' => '\d+', 'pass' => ['id']]);
        $builder->connect('/chat/inbox', ['controller' => 'Chat', 'action' => 'inbox'], ['_name' => 'api.v1.chat.inbox']);

        // Roles
        $builder->connect('/roles', ['controller' => 'Roles', 'action' => 'index'], ['_name' => 'api.v1.roles.index']);
        $builder->connect('/roles/update-permissions/{id}', ['controller' => 'Roles', 'action' => 'updatePermissions'], ['_method' => 'POST', '_name' => 'api.v1.roles.update_permissions', 'id' => '\d+', 'pass' => ['id']]);

        // Profile
        $builder->connect('/profile', ['controller' => 'Profile', 'action' => 'view'], ['_name' => 'api.v1.profile.view']);
        $builder->connect('/profile/update', ['controller' => 'Profile', 'action' => 'update'], ['_method' => 'POST', '_name' => 'api.v1.profile.update']);
        $builder->connect('/profile/change-password', ['controller' => 'Profile', 'action' => 'changePassword'], ['_method' => 'POST', '_name' => 'api.v1.profile.change_password']);

        // GitHub Integrations
        $builder->connect('/github-integrations', ['controller' => 'GithubIntegrations', 'action' => 'index'], ['_name' => 'api.v1.github_integrations.index']);
        $builder->connect('/github-integrations/create', ['controller' => 'GithubIntegrations', 'action' => 'create'], ['_name' => 'api.v1.github_integrations.create']);
        $builder->connect('/github-integrations/view/{id}', ['controller' => 'GithubIntegrations', 'action' => 'view'], ['_name' => 'api.v1.github_integrations.view', 'id' => '\d+', 'pass' => ['id']]);
        $builder->connect('/github-integrations/update/{id}', ['controller' => 'GithubIntegrations', 'action' => 'update'], ['_name' => 'api.v1.github_integrations.update', 'id' => '\d+', 'pass' => ['id']]);
        $builder->connect('/github-integrations/delete/{id}', ['controller' => 'GithubIntegrations', 'action' => 'delete'], ['_name' => 'api.v1.github_integrations.delete', 'id' => '\d+', 'pass' => ['id']]);
    });

    /*
     * Webhook endpoints — third-party providers (Meta, Mailgun, ...) post here.
     * No authentication, no CSRF. The signature header inside each request is the auth.
     *
     * Uses prefix() instead of scope() so CakePHP resolves controller classes from
     * src/Controller/Webhooks/ automatically (scope() does not set the controller namespace).
     */
    $routes->prefix('Webhooks', ['path' => '/webhooks'], function (RouteBuilder $builder): void {
        $builder->connect(
            '/whatsapp',
            ['controller' => 'WhatsApp', 'action' => 'verify'],
            ['_method' => 'GET', '_name' => 'webhooks.whatsapp.verify'],
        );
        $builder->connect(
            '/whatsapp',
            ['controller' => 'WhatsApp', 'action' => 'receive'],
            ['_method' => 'POST', '_name' => 'webhooks.whatsapp.receive'],
        );
        // Slack — one endpoint handles url_verification challenge and event_callback.
        $builder->connect(
            '/slack',
            ['controller' => 'Slack', 'action' => 'receive'],
            ['_method' => 'POST', '_name' => 'webhooks.slack.receive'],
        );
    });

    /*
     * If you need a different set of middleware or none at all,
     * open new scope and define routes there.
     *
     * ```
     * $routes->scope('/api', function (RouteBuilder $builder): void {
     *     // No $builder->applyMiddleware() here.
     *
     *     // Parse specified extensions from URLs
     *     // $builder->setExtensions(['json', 'xml']);
     *
     *     // Connect API actions here.
     * });
     * ```
     */
};
