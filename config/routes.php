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
        $builder->connect('/conversations', ['controller' => 'Conversations', 'action' => 'index']);
        $builder->connect('/conversations/view/{id}', ['controller' => 'Conversations', 'action' => 'view'], ['id' => '\d+', 'pass' => ['id']]);
        $builder->connect('/labels', ['controller' => 'Labels', 'action' => 'index']);
        $builder->connect('/github-integrations', ['controller' => 'GithubIntegrations', 'action' => 'index']);
        $builder->connect('/logs', ['controller' => 'Logs', 'action' => 'index']);

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
        $builder->connect('/agents/view/{id}', ['controller' => 'Agents', 'action' => 'view'], ['_name' => 'api.v1.agents.view', 'id' => '\d+']);
        $builder->connect('/agents/update/{id}', ['controller' => 'Agents', 'action' => 'update'], ['_name' => 'api.v1.agents.update', 'id' => '\d+']);
        $builder->connect('/agents/delete/{id}', ['controller' => 'Agents', 'action' => 'delete'], ['_name' => 'api.v1.agents.delete', 'id' => '\d+']);
        $builder->connect('/agents/logs/{id}', ['controller' => 'Agents', 'action' => 'logs'], ['_name' => 'api.v1.agents.logs', 'id' => '\d+']);

        // Conversation CRUD
        $builder->connect('/conversations', ['controller' => 'Conversations', 'action' => 'index'], ['_name' => 'api.v1.conversations.index']);
        $builder->connect('/conversations/create', ['controller' => 'Conversations', 'action' => 'create'], ['_name' => 'api.v1.conversations.create']);
        $builder->connect('/conversations/view/{id}', ['controller' => 'Conversations', 'action' => 'view'], ['_name' => 'api.v1.conversations.view', 'id' => '\d+']);
        $builder->connect('/conversations/delete/{id}', ['controller' => 'Conversations', 'action' => 'delete'], ['_name' => 'api.v1.conversations.delete', 'id' => '\d+']);

        // Labels
        $builder->connect('/labels', ['controller' => 'Labels', 'action' => 'index'], ['_name' => 'api.v1.labels.index']);
        $builder->connect('/labels/create', ['controller' => 'Labels', 'action' => 'create'], ['_name' => 'api.v1.labels.create']);
        $builder->connect('/labels/view/{id}', ['controller' => 'Labels', 'action' => 'view'], ['_name' => 'api.v1.labels.view', 'id' => '\d+']);
        $builder->connect('/labels/update/{id}', ['controller' => 'Labels', 'action' => 'update'], ['_name' => 'api.v1.labels.update', 'id' => '\d+']);
        $builder->connect('/labels/delete/{id}', ['controller' => 'Labels', 'action' => 'delete'], ['_name' => 'api.v1.labels.delete', 'id' => '\d+']);

        // Logs (cross-agent view)
        $builder->connect('/logs', ['controller' => 'Logs', 'action' => 'index'], ['_name' => 'api.v1.logs.index']);

        // GitHub Integrations
        $builder->connect('/github-integrations', ['controller' => 'GithubIntegrations', 'action' => 'index'], ['_name' => 'api.v1.github_integrations.index']);
        $builder->connect('/github-integrations/create', ['controller' => 'GithubIntegrations', 'action' => 'create'], ['_name' => 'api.v1.github_integrations.create']);
        $builder->connect('/github-integrations/view/{id}', ['controller' => 'GithubIntegrations', 'action' => 'view'], ['_name' => 'api.v1.github_integrations.view', 'id' => '\d+']);
        $builder->connect('/github-integrations/update/{id}', ['controller' => 'GithubIntegrations', 'action' => 'update'], ['_name' => 'api.v1.github_integrations.update', 'id' => '\d+']);
        $builder->connect('/github-integrations/delete/{id}', ['controller' => 'GithubIntegrations', 'action' => 'delete'], ['_name' => 'api.v1.github_integrations.delete', 'id' => '\d+']);
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
