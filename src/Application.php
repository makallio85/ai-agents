<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     3.3.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App;

use App\Middleware\HostHeaderMiddleware;
use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\AuthenticationServiceProviderInterface;
use Authentication\Middleware\AuthenticationMiddleware;
use Authorization\AuthorizationService;
use Authorization\AuthorizationServiceInterface;
use Authorization\AuthorizationServiceProviderInterface;
use Authorization\Middleware\AuthorizationMiddleware;
use Authorization\Policy\MapResolver;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Datasource\FactoryLocator;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Event\EventManagerInterface;
use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Http\ServerRequest;
use Cake\ORM\Locator\TableLocator;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Application setup class.
 *
 * This defines the bootstrapping logic and middleware layers you
 * want to use in your application.
 *
 * @extends \Cake\Http\BaseApplication<\App\Application>
 */
class Application extends BaseApplication implements
    AuthenticationServiceProviderInterface,
    AuthorizationServiceProviderInterface
{
    /**
     * Load all the application configuration and bootstrap logic.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        // Call parent to load bootstrap from files.
        parent::bootstrap();

        // By default, does not allow fallback classes.
        FactoryLocator::add('Table', (new TableLocator())->allowFallbackClass(false));
    }

    /**
     * Setup the middleware queue your application will use.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to setup.
     * @return \Cake\Http\MiddlewareQueue The updated middleware queue.
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue
            // Catch any exceptions in the lower layers,
            // and make an error page/response
            ->add(new ErrorHandlerMiddleware(Configure::read('Error'), $this))

            // Validate Host header to prevent Host Header Injection attacks.
            // In production, ensures App.fullBaseUrl is configured and validates
            // the incoming Host header against it.
            ->add(new HostHeaderMiddleware())

            // Handle plugin/theme assets like CakePHP normally does.
            ->add(new AssetMiddleware([
                'cacheTime' => Configure::read('Asset.cacheTime'),
            ]))

            // Add routing middleware.
            // If you have a large number of routes connected, turning on routes
            // caching in production could improve performance.
            // See https://github.com/CakeDC/cakephp-cached-routing
            ->add(new RoutingMiddleware($this))

            // Parse various types of encoded request bodies so that they are
            // available as array through $request->getData()
            // https://book.cakephp.org/5/en/controllers/middleware.html#body-parser-middleware
            ->add(new BodyParserMiddleware())

            // Cross Site Request Forgery (CSRF) Protection Middleware
            // https://book.cakephp.org/5/en/security/csrf.html#cross-site-request-forgery-csrf-middleware
            // API routes are excluded — they are stateless JSON endpoints authenticated
            // via session cookie set at login, not browser form submissions. Including
            // CSRF on API routes would require every fetch() call to read and send the
            // token, while providing no additional security for JSON-only endpoints.
            // Note: skipCheckCallback must be set via method call, not constructor config.
            ->add((new CsrfProtectionMiddleware(['httponly' => true]))
                ->skipCheckCallback(function (ServerRequestInterface $request): bool {
                    $path = (string)$request->getUri()->getPath();
                    // API routes are stateless JSON; webhook routes are signed by the
                    // provider (Meta, Mailgun, ...) which doesn't speak our CSRF token.
                    return str_contains($path, '/api/') || str_contains($path, '/webhooks/');
                })
            )

            // Authentication middleware — identifies the current user
            ->add(new AuthenticationMiddleware($this))

            // Authorization middleware — enforces access policies
            ->add(new AuthorizationMiddleware($this, [
                'requireAuthorizationCheck' => false,
            ]));

        return $middlewareQueue;
    }

    /**
     * Returns the authentication service.
     */
    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $service = new AuthenticationService();

        $fields = [
            \Authentication\Identifier\PasswordIdentifier::CREDENTIAL_USERNAME => 'email',
            \Authentication\Identifier\PasswordIdentifier::CREDENTIAL_PASSWORD => 'password',
        ];

        // API requests get 401 JSON; web requests redirect to login page
        $isApiRequest = str_starts_with((string)$request->getUri()->getPath(), '/api/');
        if (!$isApiRequest) {
            $service->setConfig([
                'unauthenticatedRedirect' => ['controller' => 'Auth', 'action' => 'login'],
                'queryParam' => 'redirect',
            ]);
        }

        $service->loadIdentifier('Authentication.Password', [
            'fields' => $fields,
            'resolver' => [
                'className' => 'Authentication.Orm',
                'userModel' => 'Users',
                'finder' => 'active',
            ],
        ]);

        $service->loadAuthenticator('Authentication.Session');
        // loginUrl must be a regex, not a plain string.
        // DefaultUrlChecker prepends the request base attribute before comparing,
        // so '/api/v1/auth/login' becomes '/ai-agents/api/v1/auth/login' in a
        // subdirectory install and never matches the plain string. The regex
        // anchors to the end of the path so it matches regardless of base prefix.
        $service->loadAuthenticator('Authentication.Form', [
            'fields' => $fields,
            'loginUrl' => '#/api/v1/auth/login$#',
            'urlChecker' => ['useRegex' => true],
        ]);

        return $service;
    }

    /**
     * Returns the authorization service.
     */
    public function getAuthorizationService(ServerRequestInterface $request): AuthorizationServiceInterface
    {
        $resolver = new MapResolver();

        return new AuthorizationService($resolver);
    }

    /**
     * Register application container services.
     *
     * @param \Cake\Core\ContainerInterface $container The Container to update.
     * @return void
     * @link https://book.cakephp.org/5/en/development/dependency-injection.html#dependency-injection
     */
    public function services(ContainerInterface $container): void
    {
        // Allow your Tables to be dependency injected
        //$container->delegate(new \Cake\ORM\Locator\TableContainer());

        // ---------- Messaging core ----------
        $container->addShared(\App\Service\AgentLogService::class);
        $container->addShared(\App\Service\ChatSessionService::class);
        $container->addShared(\App\Integration\Llm\LlmClientFactory::class);
        $container->addShared(\App\Service\LlmService::class)
            ->addArgument(\App\Integration\Llm\LlmClientFactory::class)
            ->addArgument(\App\Service\AgentLogService::class);

        // ---------- Speech (Google STT + TTS) ----------
        $container->addShared(\App\Integration\Speech\SpeechToTextInterface::class, function (): \App\Integration\Speech\SpeechToTextInterface {
            return \App\Integration\Speech\GoogleSpeechToTextClient::fromConfigure();
        });
        $container->addShared(\App\Integration\Speech\TextToSpeechInterface::class, function (): \App\Integration\Speech\TextToSpeechInterface {
            return \App\Integration\Speech\GoogleTextToSpeechClient::fromConfigure();
        });

        $container->addShared(\App\Messaging\Service\MessageDispatcher::class);

        // The default handler is LlmHandler — every agent with an LLM provider
        // configured gets WhatsApp / email / future channels for free.
        $container->addShared(\App\Messaging\Service\LlmHandler::class)
            ->addArgument(\App\Service\LlmService::class)
            ->addArgument(\App\Service\ChatSessionService::class)
            ->addArgument(\App\Messaging\Service\MessageDispatcher::class)
            ->addArgument(\App\Service\AgentLogService::class);

        $container->addShared(\App\Messaging\Service\MessageHandlerRegistry::class)
            ->addArgument(\App\Messaging\Service\LlmHandler::class);

        $container->addShared(\App\Messaging\Service\InboundDispatchService::class)
            ->addArgument(\App\Messaging\Service\MessageHandlerRegistry::class)
            ->addArgument(\App\Messaging\Service\MessageDispatcher::class)
            ->addArgument(\App\Service\AgentLogService::class);

        // ---------- WhatsApp channel ----------
        $container->addShared(\App\Channels\WhatsApp\WhatsAppClientInterface::class, \App\Channels\WhatsApp\WhatsAppClient::class);
        $container->addShared(\App\Channels\WhatsApp\Service\WhatsAppConfigService::class);
        $container->addShared(\App\Channels\WhatsApp\Service\WhatsAppOnboardingService::class)
            ->addArgument(\App\Channels\WhatsApp\WhatsAppClientInterface::class)
            ->addArgument(\App\Channels\WhatsApp\Service\WhatsAppConfigService::class);
        $container->addShared(\App\Channels\WhatsApp\WhatsAppTransport::class)
            ->addArgument(\App\Channels\WhatsApp\WhatsAppClientInterface::class)
            ->addArgument(\App\Channels\WhatsApp\Service\WhatsAppConfigService::class)
            ->addArgument(\App\Channels\WhatsApp\Service\WhatsAppOnboardingService::class);

        // ---------- Slack channel ----------
        $container->addShared(\App\Channels\Slack\SlackClientInterface::class, \App\Channels\Slack\SlackClient::class);
        $container->addShared(\App\Channels\Slack\Service\SlackConfigService::class);
        $container->addShared(\App\Channels\Slack\Service\SlackOnboardingService::class)
            ->addArgument(\App\Channels\Slack\SlackClientInterface::class)
            ->addArgument(\App\Channels\Slack\Service\SlackConfigService::class);
        $container->addShared(\App\Channels\Slack\SlackTransport::class)
            ->addArgument(\App\Channels\Slack\SlackClientInterface::class)
            ->addArgument(\App\Channels\Slack\Service\SlackConfigService::class)
            ->addArgument(\App\Channels\Slack\Service\SlackOnboardingService::class);

        // ChannelRegistry is constructed eagerly so we can register transports here;
        // the container then hands the same instance to anyone who asks for it.
        $registry = new \App\Messaging\Service\ChannelRegistry();
        $container->addShared(\App\Messaging\Service\ChannelRegistry::class, $registry);
        $registry->register($container->get(\App\Channels\WhatsApp\WhatsAppTransport::class));
        $registry->register($container->get(\App\Channels\Slack\SlackTransport::class));

        // ---------- Queue jobs ----------
        $container->add(\App\Messaging\Job\ProcessInboundMessageJob::class)
            ->addArgument(\App\Messaging\Service\ChannelRegistry::class)
            ->addArgument(\App\Messaging\Service\InboundDispatchService::class)
            ->addArgument(\App\Service\AgentLogService::class);
        $container->add(\App\Messaging\Job\SendMessageJob::class)
            ->addArgument(\App\Messaging\Service\ChannelRegistry::class)
            ->addArgument(\App\Integration\Speech\TextToSpeechInterface::class)
            ->addArgument(\App\Service\AgentLogService::class);
        $container->add(\App\Messaging\Job\TranscribeAudioJob::class)
            ->addArgument(\App\Messaging\Service\ChannelRegistry::class)
            ->addArgument(\App\Integration\Speech\SpeechToTextInterface::class)
            ->addArgument(\App\Messaging\Service\InboundDispatchService::class)
            ->addArgument(\App\Messaging\Service\MessageDispatcher::class)
            ->addArgument(\App\Service\AgentLogService::class);
    }

    /**
     * Register custom event listeners here
     *
     * @param \Cake\Event\EventManagerInterface $eventManager
     * @return \Cake\Event\EventManagerInterface
     * @link https://book.cakephp.org/5/en/core-libraries/events.html#registering-listeners
     */
    public function events(EventManagerInterface $eventManager): EventManagerInterface
    {
        // $eventManager->on(new SomeCustomListenerClass());

        return $eventManager;
    }
}
