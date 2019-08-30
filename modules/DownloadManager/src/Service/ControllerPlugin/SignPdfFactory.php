<?php
namespace DownloadManager\Service\ControllerPlugin;

use DownloadManager\Mvc\Controller\Plugin\SignPdf;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class SignPdfFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $cli = $services->get('Omeka\Cli');
        $settings = $services->get('Omeka\Settings');

        $signCommand = $settings->get('downloadmanager_signer_path');
        // Validate command.
        if ($signCommand) {
            $signCommand = strpos('/', $signCommand) === false
                ? $cli->getCommandPath($signCommand)
                : $cli->validateCommand($signCommand);
            $signCommand = (string) $signCommand;
        }

        $plugin = new SignPdf($cli, $signCommand);
        return $plugin;
    }
}
