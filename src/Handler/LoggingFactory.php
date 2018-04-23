<?php

namespace ErrorHandlerCustom\Handler;

use Interop\Container\ContainerInterface;
use RuntimeException;
use Zend\Console\Console;
use Zend\Console\Request as ConsoleRequest;
use Zend\Mail\Message;
use Zend\Mail\Transport\TransportInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class LoggingFactory
{
    /**
     * @param ContainerInterface|ServiceLocatorInterface $container
     *
     * @throws RuntimeException when mail config is enabled but mail-message config is not a service instance of Message
     * @throws RuntimeException when mail config is enabled but mail-transport config is not a service instance of TransportInterface
     *
     * @return Logging
     */
    public function __invoke($container)
    {
        if (! Console::isConsole()) {
            $serverUrlHelper = $container->get('ViewHelperManager')->get('ServerUrl');
            if ($container->has('Request')) {
                $serverUrl  = $serverUrlHelper->__invoke();
                $request    = $container->get('Request');
                $requestUri = $request->getRequestUri();
            } else {
                $serverUrl  = $serverUrlHelper->__invoke(true);
                $request    = null;
                $requestUri = '';
            }
        } else {
            $serverUrl  = \php_uname('n');
            $request    = new ConsoleRequest();
            $requestUri = ':'. \basename(\getcwd())  .' ' . \get_current_user() . '$ php ' . $request->getScriptName() . ' ' . $request->toString();
        }

        $config                = $container->get('config');
        $errorHandlerCustomLogger = $container->get('ErrorHandlerCustomLogger');

        $errorHandlerCustomLocalConfig = $config['error-handler-custom'];
        $logWritersConfig           = $config['log']['ErrorHandlerCustomLogger']['writers'];

        $mailConfig           = $errorHandlerCustomLocalConfig['email-notification-settings'];
        $mailMessageService   = null;
        $mailMessageTransport = null;

        if ($mailConfig['enable'] === true) {
            $mailMessageService   = $container->get($mailConfig['mail-message']);
            if (! $mailMessageService instanceof Message) {
                throw new RuntimeException('You are enabling email log writer, your "mail-message" config must be instanceof '.Message::class);
            }

            $mailMessageTransport = $container->get($mailConfig['mail-transport']);
            if (! $mailMessageTransport instanceof TransportInterface) {
                throw new RuntimeException('You are enabling email log writer, your "mail-transport" config must implements '.TransportInterface::class);
            }
        }

        return new Logging(
            $errorHandlerCustomLogger,
            $serverUrl,
            $request,
            $requestUri,
            $errorHandlerCustomLocalConfig,
            $logWritersConfig,
            $mailMessageService,
            $mailMessageTransport
        );
    }
}
