<?php
namespace External\Service\ControllerPlugin;

use External\Mvc\Controller\Plugin\ConvertExternalRecord;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ConvertExternalRecordFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        $settings = $services->get('Omeka\Settings');
        $externalCreate = $settings->get('external_create_item');
        $api = $plugins->get('api');
        $sourceRepositoryId = $api
            ->searchOne('properties', ['term' => 'vatiru:sourceRepository'], ['returnScalar' => 'id'])
            ->getContent();
        $isExternalId = $api
            ->searchOne('properties', ['term' => 'vatiru:isExternal'], ['returnScalar' => 'id'])
            ->getContent();
        $externalDataId = $api
            ->searchOne('properties', ['term' => 'vatiru:externalData'], ['returnScalar' => 'id'])
            ->getContent();
        $externalSourceId = $api
            ->searchOne('properties', ['term' => 'vatiru:externalSource'], ['returnScalar' => 'id'])
            ->getContent();
        $resourcePriorityId = $api
            ->searchOne('properties', ['term' => 'vatiru:resourcePriority'], ['returnScalar' => 'id'])
            ->getContent();
        $publicationTypeId = $api
            ->searchOne('properties', ['term' => 'vatiru:publicationType'], ['returnScalar' => 'id'])
            ->getContent();
        return new ConvertExternalRecord(
            $plugins,
            $externalCreate,
            [
                'vatiru:sourceRepository' => $sourceRepositoryId,
                'vatiru:isExternal' => $isExternalId,
                'vatiru:externalData' => $externalDataId,
                'vatiru:externalSource' => $externalSourceId,
                'vatiru:resourcePriority' => $resourcePriorityId,
                'vatiru:publicationType' => $publicationTypeId,
            ]
        );
    }
}
