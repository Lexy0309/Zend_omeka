<?php
namespace DownloadManager\Service\ControllerPlugin;

use DownloadManager\Mvc\Controller\Plugin\CheckDownloadStatus;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class checkDownloadStatusFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        $checkResourceToDownload = $plugins->get('checkResourceToDownload');
        $checkRightToDownload = $plugins->get('checkRightToDownload');
        $getCurrentDownload = $plugins->get('getCurrentDownload');
        $isResourceAvailableForUser = $plugins->get('isResourceAvailableForUser');
        $totalAvailablePlugin = $plugins->get('totalAvailable');
        // $createMediaHash = $plugins->get('createMediaHash');
        $api = $plugins->get('api');
        $viewHelpers = $plugins->get('viewHelpers');
        $urlHelper = $viewHelpers()->get('url');
        return new CheckDownloadStatus(
            $checkResourceToDownload,
            $checkRightToDownload,
            $getCurrentDownload,
            $isResourceAvailableForUser,
            $totalAvailablePlugin,
            // $createMediaHash,
            $api,
            $urlHelper
        );
    }
}
