<?php
declare(strict_types=1);

namespace DevOpsOrchestrator;

use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;

class DevOpsOrchestratorPlugin extends BasePlugin
{
    protected bool $bootstrapEnabled = true;
    protected bool $routesEnabled = true;
    protected bool $middlewareEnabled = false;
    protected bool $consoleEnabled = true;

    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);
    }
}
