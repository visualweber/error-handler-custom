<?php

namespace ErrorHandlerCustom\Middleware;

use Doctrine\ORM\EntityManager;
use ErrorHandlerCustom\Handler\Logging;
use Zend\Db\Adapter\Adapter;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\ServiceManager\ServiceManager;

class ExpressiveFactory
{
    /**
     * @param ServiceManager $container
     *
     * @return Expressive
     */
    public function __invoke($container)
    {
        $configuration = $container->get('config');

        if ($container->has(EntityManager::class) && ! isset($configuration['db'])) {
            $entityManager          = $container->get(EntityManager::class);
            $doctrineDBALConnection = $entityManager->getConnection();

            $params        = $doctrineDBALConnection->getParams();
            $driverOptions = (isset($params['driverOptions'])) ? $params['driverOptions'] : [];

            $dbConfiguration = [
                'username'       => $doctrineDBALConnection->getUsername(),
                'password'       => $doctrineDBALConnection->getPassword(),
                'driver'         => $doctrineDBALConnection->getDriver()->getName(),
                'database'       => $doctrineDBALConnection->getDatabase(),
                'host'           => $doctrineDBALConnection->getHost(),
                'port'           => $doctrineDBALConnection->getPort(),
                'driver_options' => $driverOptions,
            ];

            $allowOverride = $container->getAllowOverride();
            $container->setAllowOverride(true);

            $adapterName = 'Zend\Db\Adapter\Adapter';
            $writers     = $configuration['log']['ErrorHandlerCustomLogger']['writers'];
            foreach ($writers as $key => $writer) {
                if ($writer['name'] === 'db') {
                    $adapterName = $writer['options']['db'];
                    break;
                }
            }

            $container->setService($adapterName, new Adapter($dbConfiguration));
            $container->setAllowOverride($allowOverride);
        }

        return new Expressive(
            $configuration['error-handler-custom'],
            $container->get(Logging::class),
            $container->get(TemplateRendererInterface::class)
        );
    }
}
