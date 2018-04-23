<?php

namespace ErrorHandlerCustom\Handler\Writer;

use ReflectionProperty;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;
use Zend\Log\Writer\Db as DbWriter;

class Db
{
    /**
     * @var DbWriter
     */
    private $dbWriter;

    /**
     * @var array
     */
    private $configLoggingSettings;

    /**
     * @var array
     */
    private $logWritersConfig;

    /**
     * @param DbWriter $dbWriter
     * @param array    $configLoggingSettings
     * @param array    $logWritersConfig
     */
    public function __construct(
        DbWriter $dbWriter,
        array    $configLoggingSettings,
        array    $logWritersConfig
    ) {
        $this->dbWriter              = $dbWriter;
        $this->configLoggingSettings = $configLoggingSettings;
        $this->logWritersConfig      = $logWritersConfig;
    }

    /**
     * @param string $errorFile
     * @param int    $errorLine
     * @param string $errorMessage
     * @param string $errorUrl
     * @param string $errorType
     *
     * @return bool
     */
    public function isExists($errorFile, $errorLine, $errorMessage, $errorUrl, $errorType)
    {
        // db definition
        $reflectionProperty = new ReflectionProperty($this->dbWriter, 'db');
        $reflectionProperty->setAccessible(true);
        $db = $reflectionProperty->getValue($this->dbWriter);

        foreach ($this->logWritersConfig as $writerConfig) {
            if ($writerConfig['name'] === 'db') {
                // table definition
                $table = $writerConfig['options']['table'];

                // columns definition
                $timestamp  = $writerConfig['options']['column']['timestamp'];
                $message    = $writerConfig['options']['column']['message'];
                $file       = $writerConfig['options']['column']['extra']['file'];
                $line       = $writerConfig['options']['column']['extra']['line'];
                $url        = $writerConfig['options']['column']['extra']['url'];
                $error_type = $writerConfig['options']['column']['extra']['error_type'];

                $tableGateway = new TableGateway($table, $db, null, new ResultSet());
                $select       = $tableGateway->getSql()->select();
                $select->columns([$timestamp]);
                $select->where([
                    $message    => $errorMessage,
                    $line       => $errorLine,
                    $url        => $errorUrl,
                    $file       => $errorFile,
                    $error_type => $errorType,
                ]);
                $select->order($timestamp.' DESC');
                $select->limit(1);

                /** @var ResultSet $result */
                $result = $tableGateway->selectWith($select);
                if (! ($current = $result->current())) {
                    return false;
                }

                $first = $current[$timestamp];
                $last  = \date('Y-m-d H:i:s');

                $diff = \strtotime($last) - \strtotime($first);
                if ($diff <= $this->configLoggingSettings['same-error-log-time-range']) {
                    return true;
                }
                break;
            }
        }

        return false;
    }
}
