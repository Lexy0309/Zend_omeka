<?php
namespace External;

return [
    // The default timeout of Zend/Omeka is 1000 (10 seconds), but to fetch
    // external data can make the timeout occurs too early.
    'timeout' => 1800,
    'view_helpers' => [
        'invokables' => [
            'publicationType' => View\Helper\PublicationType::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
        ],
    ],
    'media_ingesters' => [
        'factories' => [
            'external' => Service\Media\Ingester\ExternalFactory::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'authenticateExternal' => Service\ControllerPlugin\AuthenticateExternalFactory::class,
            'cacheExternal' => Service\ControllerPlugin\CacheExternalFactory::class,
            'convertExternalRecord' => Service\ControllerPlugin\ConvertExternalRecordFactory::class,
            'existingIdentifiers' => Service\ControllerPlugin\ExistingIdentifiersFactory::class,
            'externalSearchPage' => Service\ControllerPlugin\ExternalSearchPageFactory::class,
            'filterExistingExternalRecords' => Service\ControllerPlugin\FilterExistingExternalRecordsFactory::class,
            'importThumbnail' => Service\ControllerPlugin\ImportThumbnailFactory::class,
            'prepareExternal' => Service\ControllerPlugin\PrepareExternalFactory::class,
            'quickBatchCreate' => Service\ControllerPlugin\QuickBatchCreateFactory::class,
            'retrieveExternal' => Service\ControllerPlugin\RetrieveExternalFactory::class,
            'searchExternal' => Service\ControllerPlugin\SearchExternalFactory::class,
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'external' => [
        'config' => [
            'external_create_item' => 'record_url',
            'external_ebsco_token' => null,
            'external_ebsco_username' => '',
            'external_ebsco_password' => '',
            'external_ebsco_organization_identifier' => '',
            'external_ebsco_profile' => '',
            'external_ebsco_filter' => [],
            'external_ebsco_query_parameters' => '',
            'external_ebsco_ebook' => false,
            'external_pagination_per_page' => 100,
            'external_number_of_pages' => 3,
            'external_ebsco_max_fetch_items' => 0,
            'external_ebsco_max_fetch_jobs' => 0,
            'external_ebsco_process_existing' => false,
            'external_ebsco_disable' => false,
        ],
        'site_settings' => [
            'external_access_resources' => true,
            'external_access_books' => true,
            'external_access_articles' => true,
        ],
    ],
];
