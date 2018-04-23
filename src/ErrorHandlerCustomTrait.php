<?php

namespace ErrorHandlerCustom;

use ErrorException;
use ErrorHandlerCustom\Handler\Logging;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\View\Renderer\PhpRenderer;

trait ErrorHanlderCustomTrait
{
    /**
     * @var array
     */
    private $errorHandlerCustomConfig;

    /**
     * @var Logging
     */
    private $logging;

    /**
     * @var PhpRenderer|TemplateRendererInterface
     */
    private $renderer;

    /**
     * @return void
     */
    public function execOnShutdown()
    {
        $error = \error_get_last();
        if (! $error) {
            return;
        }

        $this->phpErrorHandler($error['type'], $error['message'], $error['file'], $error['line']);
    }

    /**
     * @param int    $errorType
     * @param string $errorMessage
     * @param string $errorFile
     * @param int    $errorLine
     *
     * @throws ErrorException when php error happen and error type is not excluded in the config
     *
     * @return mixed
     */
    public function phpErrorHandler($errorType, $errorMessage, $errorFile, $errorLine)
    {
        if (! $errorLine) {
            return;
        }

        if (! $this->errorHandlerCustomConfig['display-settings']['display_errors']) {
            \error_reporting(\E_ALL | \E_STRICT);
            \ini_set('display_errors', 0);
        }

        if (\in_array($errorType, $this->errorHandlerCustomConfig['display-settings']['exclude-php-errors'])) {
            return;
        }

        throw new ErrorException($errorMessage, 500, $errorType, $errorFile, $errorLine);
    }
}
