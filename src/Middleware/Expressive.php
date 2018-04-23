<?php

namespace ErrorHandlerCustom\Middleware;

use Error;
use ErrorHandlerCustom\Handler\Logging;
use ErrorHandlerCustom\ErrorHanlderCustomTrait;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionProperty;
use Seld\JsonLint\JsonParser;
use Zend\Diactoros\Response;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Expressive\Application;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\View\Model\ViewModel;

class Expressive
{
    use ErrorHanlderCustomTrait;

    /**
     * @var ServerRequestInterface
     */
    private $request;

    /**
     * @param array                     $errorHandlerCustomConfig
     * @param Logging                   $logging
     * @param TemplateRendererInterface $renderer
     */
    public function __construct(
        array            $errorHandlerCustomConfig,
        Logging          $logging,
        TemplateRendererInterface $renderer
    ) {
        $this->errorHandlerCustomConfig = $errorHandlerCustomConfig;
        $this->logging               = $logging;
        $this->renderer              = $renderer;
    }

    /**
     * @param  ServerRequestInterface $request
     * @param  ResponseInterface      $response
     * @param  callable               $next
     *
     * @return ResponseInterface|void
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        if (! $this->errorHandlerCustomConfig['enable']) {
            return $next($request, $response);
        }

        try {
            $this->request = $request;
            $this->logging->setServerRequestandRequestUri($request);

            $this->phpError();

            return $next($request, $response);
        } catch (Error $e) {
        } catch (Exception $e) {
        }

        return $this->exceptionError($e, $request);
    }

    /**
     *
     * @return void
     */
    public function phpError()
    {
        \register_shutdown_function([$this, 'execOnShutdown']);
        \set_error_handler([$this, 'phpErrorHandler']);
    }

    /**
     * @param  Error|Exception $e
     * @throws Error      when 'display_errors' config is 1 and Error has thrown
     * @throws Exception  when 'display_errors' config is 1 and Exception has thrown
     *
     * @return ResponseInterface
     */
    public function exceptionError($e, $request)
    {
        $exceptionClass = \get_class($e);
        if (isset($this->errorHandlerCustomConfig['display-settings']['exclude-exceptions']) &&
            \in_array($exceptionClass, $this->errorHandlerCustomConfig['display-settings']['exclude-exceptions'])
        ) {
            throw $e;
        }

        $this->logging->handleErrorException(
            $e
        );

        if ($this->errorHandlerCustomConfig['display-settings']['display_errors']) {
            throw $e;
        }

        return $this->showDefaultViewWhenDisplayErrorSetttingIsDisabled();
    }

    /**
     * It show default view if display_errors setting = 0.
     *
     * @return ResponseInterface
     */
    private function showDefaultViewWhenDisplayErrorSetttingIsDisabled()
    {
        $isXmlHttpRequest = $this->request->hasHeader('X-Requested-With');

        if ($isXmlHttpRequest === true &&
            isset($this->errorHandlerCustomConfig['display-settings']['ajax']['message'])
        ) {
            $content     = $this->errorHandlerCustomConfig['display-settings']['ajax']['message'];
            $contentType = ((new JsonParser())->lint($content) === null) ? 'application/problem+json' : 'text/html';

            $response = new Response();
            $response->getBody()->write($content);
            $response = $response->withHeader('Content-type', $contentType);
            $response = $response->withStatus(500);

            return $response;
        }

        $layout = new ViewModel();
        $layout->setTemplate($this->errorHandlerCustomConfig['display-settings']['template']['layout']);

        $r = new ReflectionProperty($this->renderer, 'layout');
        $r->setAccessible(true);
        $r->setValue($this->renderer, $layout);

        $response =  new HtmlResponse(
            $this->renderer->render($this->errorHandlerCustomConfig['display-settings']['template']['view']),
            500
        );
        return $response;
    }
}
