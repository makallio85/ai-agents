<?php
declare(strict_types=1);

namespace DevOpsOrchestrator;

use App\Messaging\Service\LlmHandler;
use App\Messaging\Service\MessageDispatcher;
use App\Messaging\Service\MessageHandlerRegistry;
use Cake\Core\BasePlugin;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use DevOpsOrchestrator\Messaging\IssueIntakeHandler;

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

    /**
     * Registers the plugin's handler so inbound messages addressed to agents
     * with plugin = "DevOpsOrchestrator" route through IssueIntakeHandler
     * instead of the default LlmHandler.
     */
    public function services(ContainerInterface $container): void
    {
        $container->addShared(IssueIntakeHandler::class)
            ->addArgument(LlmHandler::class)
            ->addArgument(MessageDispatcher::class);

        $registry = $container->get(MessageHandlerRegistry::class);
        $registry->register('DevOpsOrchestrator', $container->get(IssueIntakeHandler::class));
    }
}
