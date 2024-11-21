<?php

namespace NeuroFoxPro\Swoole\Runner\Http;

use ErrorException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Throwable;
use Yiisoft\Definitions\Exception\InvalidConfigException;
use Yiisoft\ErrorHandler\ErrorHandler;
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;
use Yiisoft\ErrorHandler\Renderer\HtmlRenderer;
use Yiisoft\Http\Method;
use Yiisoft\Yii\Http\Application;
use Yiisoft\Yii\Http\Handler\ThrowableHandler;
use Yiisoft\Yii\Runner\ApplicationRunner;

class HttpApplicationRunner extends ApplicationRunner
{

    private ?ErrorHandler $temporaryErrorHandler;

    public function __construct(
        string $rootPath,
        bool $debug = false,
        bool $checkEvents = false,
        ?string $environment = null,
        string $bootstrapGroup = 'bootstrap-web',
        string $eventsGroup = 'events-web',
        string $diGroup = 'di-web',
        string $diProvidersGroup = 'di-providers-web',
        string $diDelegatesGroup = 'di-delegates-web',
        string $diTagsGroup = 'di-tags-web',
        string $paramsGroup = 'params-web',
        array $nestedParamsGroups = ['params'],
        array $nestedEventsGroups = ['events'],
        array $configModifiers = [],
        string $configDirectory = 'config',
        string $vendorDirectory = 'vendor',
        string $configMergePlanFile = '.merge-plan.php',
        private readonly ?LoggerInterface $logger = null,
        private readonly ?int $bufferSize = null,
    )
    {
        parent::__construct(
            $rootPath,
            $debug,
            $checkEvents,
            $environment,
            $bootstrapGroup,
            $eventsGroup,
            $diGroup,
            $diProvidersGroup,
            $diDelegatesGroup,
            $diTagsGroup,
            $paramsGroup,
            $nestedParamsGroups,
            $nestedEventsGroups,
            $configModifiers,
            $configDirectory,
            $vendorDirectory,
            $configMergePlanFile,
        );
    }

    public function withTemporaryErrorHandler(ErrorHandler $temporaryErrorHandler): self
    {
        $new = clone $this;
        $new->temporaryErrorHandler = $temporaryErrorHandler;
        return $new;
    }

    /**
     * @throws ErrorException
     * @throws InvalidConfigException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function run(): void
    {
        $temporaryErrorHandler = $this->createTemporaryErrorHandler();
        $this->registerErrorHandler($temporaryErrorHandler);

        $container = $this->getContainer();

        $this->runBootstrap();
        $this->checkEvents();

        $server = new Server("0.0.0.0", 80);


        $requestFactory = $container->get(RequestFactory::class);
        $application = $container->get(Application::class);

        $server->on(
            'request',
            function (Request $request, Response $response) use ($requestFactory, $container, $application) {
                $serverRequest = $requestFactory->create($request);

                try {
                    $application->start();
                    $serverResponse = $application->handle($serverRequest);
                    $this->emit($serverRequest, $serverResponse, $response);
                } catch (Throwable $throwable) {
                    $handler = new ThrowableHandler($throwable);
                    $serverResponse = $container->get(ErrorCatcher::class)->process($serverRequest, $handler);
                    $this->emit($serverRequest, $serverResponse, $response);
                } finally {
                    $application->afterEmit($serverResponse ?? null);
                    $application->shutdown();
                }

            }
        );

        $server->start();
    }

    private function createTemporaryErrorHandler(): ErrorHandler
    {
        return $this->temporaryErrorHandler ?? new ErrorHandler(
            $this->logger ?? new NullLogger(), new HtmlRenderer(),
        );
    }

    /**
     * @param ServerRequestInterface $serverRequest
     * @param ResponseInterface      $serverResponse
     * @param Response               $response
     * @return void
     */
    private function emit(
        ServerRequestInterface $serverRequest,
        ResponseInterface $serverResponse,
        Response $response
    ): void
    {
        (new SapiEmitter($response, $this->bufferSize))->emit(
            $serverResponse,
            $serverRequest->getMethod() === Method::HEAD
        );
    }

    /**
     * @throws ErrorException
     */
    private function registerErrorHandler(ErrorHandler $registered, ErrorHandler $unregistered = null): void
    {
        $unregistered?->unregister();

        if ($this->debug) {
            $registered->debug();
        }

        $registered->register();
    }
}
