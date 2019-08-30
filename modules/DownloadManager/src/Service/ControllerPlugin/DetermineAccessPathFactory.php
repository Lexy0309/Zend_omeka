<?php
namespace DownloadManager\Service\ControllerPlugin;

use DownloadManager\Mvc\Controller\Plugin\DetermineAccessPath;
use Interop\Container\ContainerInterface;
use Omeka\Mvc\Controller\Plugin\Settings;
use Zend\ServiceManager\Factory\FactoryInterface;

class DetermineAccessPathFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        $settings = $plugins->get('settings');
        $baseHash = $this->getBaseHash($settings);
        $useUniqueKeys = $plugins->get('useUniqueKeys');
        $baseConvertArbitrary = $plugins->get('baseConvertArbitrary');
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        return new DetermineAccessPath(
            $baseHash,
            $useUniqueKeys,
            $baseConvertArbitrary,
            $basePath
        );
    }

    /**
     * Random string to calculate the hash of the temp files.
     *
     * @param Settings $settings
     * @return string
     */
    private function getBaseHash(Settings $settings)
    {
        $filepath = $settings()->get('downloadmanager_credential_key_path');
        return sha1_file($filepath);
    }
}
