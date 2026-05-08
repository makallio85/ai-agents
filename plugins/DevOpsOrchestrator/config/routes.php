<?php
declare(strict_types=1);

use Cake\Routing\RouteBuilder;

/** @var \Cake\Routing\RouteBuilder $routes */
$routes->plugin(
    'DevOpsOrchestrator',
    ['path' => '/api/v1/devops'],
    function (RouteBuilder $builder): void {
        $builder->prefix('Api/V1', ['path' => '/'], function (RouteBuilder $builder): void {
            $builder->fallbacks(\Cake\Routing\Route\DashedRoute::class);
        });
    }
);
