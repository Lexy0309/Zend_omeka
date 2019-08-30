<?php
namespace External;

/**
 * @var Module $this
 * @var \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $oldVersion
 * @var string $newVersion
 */
$services = $serviceLocator;

/**
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Omeka\Api\Manager $api
 * @var array $config
 */
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$api = $services->get('Omeka\ApiManager');
$config = require dirname(dirname(__DIR__)) . '/config/module.config.php';

if (version_compare($oldVersion, '3.0.2', '<')) {
    // Settings to remove.
    $settings->delete('external_create_item_without_file');
    $settings->delete('external_ebsco_limit_to_pdf');
    $settings->set('external_pagination_per_page',
        $config['external']['config']['external_pagination_per_page']);
    $settings->set('external_number_of_pages',
        $config['external']['config']['external_number_of_pages']);
}

if (version_compare($oldVersion, '3.0.3', '<')) {
    $siteSettings = $services->get('Omeka\Settings\Site');
    /** @var \Omeka\Api\Representation\SiteRepresentation[] $sites */
    $sites = $api->search('sites')->getContent();
    foreach ($sites as $site) {
        $siteSettings->setTargetId($site->id());
        $siteSettings->set('external_access_resources',
            $config['external']['site_settings']['external_access_resources']);
        $siteSettings->set('external_access_books',
            $config['external']['site_settings']['external_access_books']);
        $siteSettings->set('external_access_articles',
            $config['external']['site_settings']['external_access_articles']);
    }
}
