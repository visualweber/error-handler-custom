<?php

namespace ErrorHandlerCustom\Listener;

use ErrorHandlerCustom\Handler\Logging;
use ErrorHandlerCustom\ErrorHanlderCustomTrait;
use Seld\JsonLint\JsonParser;
use Zend\Console\Console;
use Zend\Console\Response as ConsoleResponse;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\Event;
use Zend\EventManager\EventManagerInterface;
use Zend\Http\PhpEnvironment\Request;
use Zend\Http\PhpEnvironment\Response as HttpResponse;
use Zend\Mvc\MvcEvent;
use Zend\Text\Table;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;

class Mvc extends AbstractListenerAggregate {

    use ErrorHanlderCustomTrait;

    /**
     * @param array       $errorHandlerCustomConfig
     * @param Logging     $logging
     * @param PhpRenderer $renderer
     */
    public function __construct(
    array $errorHandlerCustomConfig, Logging $logging, PhpRenderer $renderer
    ) {
        $this->errorHandlerCustomConfig = $errorHandlerCustomConfig;
        $this->logging = $logging;
        $this->renderer = $renderer;
    }

    /**
     * @param EventManagerInterface $events
     * @param int                   $priority
     *
     * @return void
     */
    public function attach(EventManagerInterface $events, $priority = 1) {
        if (!$this->errorHandlerCustomConfig['enable']) {
            return;
        }

        // exceptions
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER_ERROR, [$this, 'exceptionError']);
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, [$this, 'exceptionError'], 100);

        // php errors
        $this->listeners[] = $events->attach(MvcEvent::EVENT_BOOTSTRAP, [$this, 'phpError']);
    }

    /**
     * @param Event $e
     *
     * @return void
     */
    public function phpError(Event $e) {
        \register_shutdown_function([$this, 'execOnShutdown']);
        \set_error_handler([$this, 'phpErrorHandler']);
    }

    /**
     * @param Event $e
     *
     * @return void
     */
    public function exceptionError(Event $e) {
        $exception = $e->getParam('exception');
        if (!$exception) {
            return;
        }

        $exceptionClass = \get_class($exception);
        if (isset($this->errorHandlerCustomConfig['display-settings']['exclude-exceptions']) &&
                \in_array($exceptionClass, $this->errorHandlerCustomConfig['display-settings']['exclude-exceptions'])) {
            // rely on original mvc process
            return;
        }

        $this->logging->handleErrorException(
                $exception
        );

        $displayErrors = $this->errorHandlerCustomConfig['display-settings']['display_errors'];
        if ($displayErrors) {
            // rely on original mvc process
            return;
        }

        $this->showDefaultViewWhenDisplayErrorSetttingIsDisabled();
    }

    /**
     * It show default view if display_errors setting = 0.
     *
     * @return mixed
     */
    private function showDefaultViewWhenDisplayErrorSetttingIsDisabled() {
        if (!Console::isConsole()) {
            $response = new HttpResponse();
            $response->setStatusCode(500);

            $request = new Request();
            $isXmlHttpRequest = $request->isXmlHttpRequest();
            if (($this->errorHandlerCustomConfig['display-settings']['force-display-json'] === true && isset($this->errorHandlerCustomConfig['display-settings']['force-display-json'])) ||
                    ($isXmlHttpRequest === true && isset($this->errorHandlerCustomConfig['display-settings']['ajax']['message']))
            ) {
                $content = $this->errorHandlerCustomConfig['display-settings']['ajax']['message'];
                $contentType = ((new JsonParser())->lint($content) === null) ? 'application/problem+json' : 'text/html';

                $response->getHeaders()->addHeaderLine('Content-type', $contentType);
                $response->setContent($content);

                $response->send();
                exit(-1);
            }

            $view = new ViewModel();
            $view->setTemplate($this->errorHandlerCustomConfig['display-settings']['template']['view']);

            $layout = new ViewModel();
            $layout->setTemplate($this->errorHandlerCustomConfig['display-settings']['template']['layout']);
            $layout->setVariable('content', $this->renderer->render($view));

            $response->getHeaders()->addHeaderLine('Content-type', 'text/html');
            $response->setContent($this->renderer->render($layout));

            $response->send();
            exit(-1);
        }

        $response = new ConsoleResponse();
        $response->setErrorLevel(-1);

        $table = new Table\Table([
            'columnWidths' => [150],
        ]);
        $table->setDecorator('ascii');
        $table->appendRow([$this->errorHandlerCustomConfig['display-settings']['console']['message']]);

        $response->setContent($table->render());
        $response->send();
    }

}
