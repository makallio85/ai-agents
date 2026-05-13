<?php
declare(strict_types=1);

namespace DevOpsOrchestrator;

use App\Messaging\Service\LlmHandler;
use App\Messaging\Service\MessageDispatcher;
use App\Messaging\Service\MessageHandlerRegistry;
use App\Service\ChatSessionService;
use Cake\Core\BasePlugin;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use DevOpsOrchestrator\Messaging\AgenticLlmHandler;

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
     * with plugin = "DevOpsOrchestrator" route through AgenticLlmHandler
     * instead of the default LlmHandler.
     *
     * AgenticLlmHandler runs the full ReAct tool-calling loop (OpenAI + GitHub)
     * for conversational issue management and code navigation. It falls back to
     * plain LlmHandler when the agent is not OpenAI-backed or no GitHub integration
     * is configured.
     */
    public function services(ContainerInterface $container): void
    {
        $container->addShared(AgenticLlmHandler::class)
            ->addArgument(LlmHandler::class)
            ->addArgument(MessageDispatcher::class)
            ->addArgument(ChatSessionService::class);

        $registry = $container->get(MessageHandlerRegistry::class);
        $registry->register('DevOpsOrchestrator', $container->get(AgenticLlmHandler::class));
    }
}
