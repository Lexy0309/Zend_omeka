<?php
namespace DownloadManager;

return [
    'service_manager' => [
        'invokables' => [
            'Omeka\MvcListeners' => Mvc\MvcListeners::class,
        ],
        'factories' => [
            'Omeka\AuthenticationService' => Service\AuthenticationServiceFactory::class,
        ],
    ],
    'api_adapters' => [
        'invokables' => [
            'downloads' => Api\Adapter\DownloadAdapter::class,
            'download_logs' => Api\Adapter\DownloadLogAdapter::class,
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'downloadSearchFilters' => View\Helper\DownloadSearchFilters::class,
            'urlLogin' => View\Helper\UrlLogin::class,
        ],
        'factories' => [
            'checkResourceToDownload' => Service\ViewHelper\CheckResourceToDownloadFactory::class,
            'checkRightToDownload' => Service\ViewHelper\CheckRightToDownloadFactory::class,
            'isModuleActive' => Service\ViewHelper\IsModuleActiveFactory::class,
            'primaryOriginal' => Service\ViewHelper\PrimaryOriginalFactory::class,
            'readDocumentKey' => Service\ViewHelper\ReadDocumentKeyFactory::class,
            'readDownloadHash' => Service\ViewHelper\ReadDownloadHashFactory::class,
            'readDownloadSalt' => Service\ViewHelper\ReadDownloadSaltFactory::class,
            'readUserKey' => Service\ViewHelper\ReadUserKeyFactory::class,
            'siteOfUser' => Service\ViewHelper\SiteOfUserFactory::class,
            'showAvailability' => Service\ViewHelper\ShowAvailabilityFactory::class,
            'totalAvailable' => Service\ViewHelper\TotalAvailableFactory::class,
            'totalExemplars' => Service\ViewHelper\TotalExemplarsFactory::class,
        ],
    ],
    'form_elements' => [
        'factories' => [
            Form\ConfigForm::class => Service\Form\ConfigFormFactory::class,
            Form\DownloadForm::class => Service\Form\DownloadFormFactory::class,
            Form\QuickSearchForm::class => Service\Form\QuickSearchFormFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\Admin\DownloadController::class => Service\Controller\Admin\DownloadControllerFactory::class,
            Controller\Site\DownloadController::class => Service\Controller\Site\DownloadControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'baseConvertArbitrary' => Mvc\Controller\Plugin\BaseConvertArbitrary::class,
            'createMediaHash' => Mvc\Controller\Plugin\CreateMediaHash::class,
            'extractPages' => Mvc\Controller\Plugin\ExtractPages::class,
            'primaryOriginal' => Mvc\Controller\Plugin\PrimaryOriginal::class,
            'protectPdf' => Mvc\Controller\Plugin\ProtectPdf::class,
            'sendFile' => Mvc\Controller\Plugin\SendFile::class,
        ],
        'factories' => [
            'apiKeyFromUserAndLabel' => Service\ControllerPlugin\ApiKeyFromUserAndLabelFactory::class,
            'checkDownloadStatus' => Service\ControllerPlugin\CheckDownloadStatusFactory::class,
            'checkResourceToDownload' => Service\ControllerPlugin\CheckResourceToDownloadFactory::class,
            'checkRightToDownload' => Service\ControllerPlugin\CheckRightToDownloadFactory::class,
            'createCryptKey' => Service\ControllerPlugin\CreateCryptKeyFactory::class,
            'createDocumentKey' => Service\ControllerPlugin\CreateDocumentKeyFactory::class,
            'createServerKey' => Service\ControllerPlugin\CreateServerKeyFactory::class,
            'createServerSalt' => Service\ControllerPlugin\CreateServerSaltFactory::class,
            'createUserKey' => Service\ControllerPlugin\CreateUserKeyFactory::class,
            'determineAccessPath' => Service\ControllerPlugin\DetermineAccessPathFactory::class,
            'getCurrentDownload' => Service\ControllerPlugin\GetCurrentDownloadFactory::class,
            'hasUniqueKeys' => Service\ControllerPlugin\HasUniqueKeysFactory::class,
            'holdingRank' => Service\ControllerPlugin\HoldingRankFactory::class,
            'holdingRanksItem' => Service\ControllerPlugin\HoldingRanksItemFactory::class,
            'identityBySessionOrKey' => Service\ControllerPlugin\IdentityBySessionOrKeyFactory::class,
            'isResourceAvailableForUser' => Service\ControllerPlugin\IsResourceAvailableForUserFactory::class,
            'protectFile' => Service\ControllerPlugin\ProtectFileFactory::class,
            'removeAccessFiles' => Service\ControllerPlugin\RemoveAccessFilesFactory::class,
            'signPdf' => Service\ControllerPlugin\SignPdfFactory::class,
            'siteOfUser' => Service\ControllerPlugin\SiteOfUserFactory::class,
            'totalAvailable' => Service\ControllerPlugin\TotalAvailableFactory::class,
            'totalExemplars' => Service\ControllerPlugin\TotalExemplarsFactory::class,
            'uniqueServerKey' => Service\ControllerPlugin\UniqueServerKeyFactory::class,
            'uniqueUserKey' => Service\ControllerPlugin\UniqueUserKeyFactory::class,
            'userApiKeys' => Service\ControllerPlugin\UserApiKeysFactory::class,
            'useUniqueKeys' => Service\ControllerPlugin\UseUniqueKeysFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'download' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/access[/:action]',
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'DownloadManager\Controller\Site',
                                'controller' => Controller\Site\DownloadController::class,
                                'action' => 'hold',
                            ],
                        ],
                    ],
                    'download-id' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/access/:id[/:action]',
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'id' => '\d+',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'DownloadManager\Controller\Site',
                                'controller' => Controller\Site\DownloadController::class,
                                'action' => 'show',
                            ],
                        ],
                    ],
                ],
            ],
            'admin' => [
                'child_routes' => [
                    'site' => [
                        'child_routes' => [
                            'slug' => [
                                'child_routes' => [
                                    'download' => [
                                        'type' => \Zend\Router\Http\Segment::class,
                                        'options' => [
                                            'route' => '/download[/:action]',
                                            'constraints' => [
                                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                            ],
                                            'defaults' => [
                                                '__NAMESPACE__' => 'DownloadManager\Controller\Admin',
                                                'controller' => Controller\Admin\DownloadController::class,
                                                'action' => 'stats',
                                            ],
                                        ],
                                        'may_terminate' => true,
                                        'child_routes' => [
                                            'term' => [
                                                'type' => \Zend\Router\Http\Segment::class,
                                                'options' => [
                                                    'route' => '/:term',
                                                    'constraints' => [
                                                        'term' => '[a-zA-Z][a-zA-Z0-9_-]*:[a-zA-Z][a-zA-Z0-9_-]*',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'download' => [
                        'type' => \Zend\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/download-manager',
                            'defaults' => [
                                '__NAMESPACE__' => 'DownloadManager\Controller\Admin',
                                'controller' => Controller\Admin\DownloadController::class,
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'id' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:id[/:action]',
                                    'constraints' => [
                                        'id' => '\d+',
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                ],
                            ],
                            'default' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                ],
                            ],
                            'stats' => [
                                'type' => \Zend\Router\Http\Literal::class,
                                'options' => [
                                    'route' => '/stats',
                                    'defaults' => [
                                        'action' => 'stats',
                                    ],
                                ],
                                'may_terminate' => true,
                                'child_routes' => [
                                    'property' => [
                                        'type' => \Zend\Router\Http\Segment::class,
                                        'options' => [
                                            'route' => '/by-property/:property',
                                            'constraints' => [
                                                'term' => '[a-zA-Z][a-zA-Z0-9_-]*:[a-zA-Z][a-zA-Z0-9_-]*',
                                            ],
                                            'defaults' => [
                                                'action' => 'by-property-term',
                                                'property' => 'dcterms:subject',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'download-version' => [
                'type' => \Zend\Router\Http\Literal::class,
                'options' => [
                    'route' => '/access/version',
                    'defaults' => [
                        '__NAMESPACE__' => 'DownloadManager\Controller\Site',
                        'controller' => Controller\Site\DownloadController::class,
                        'action' => 'version',
                    ],
                ],
            ],
            'download' => [
                'type' => \Zend\Router\Http\Segment::class,
                'options' => [
                    'route' => '/access/:action/:hash',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        // Manage complex hash.
                        'hash' => '[a-zA-Z0-9/._-]+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'DownloadManager\Controller\Site',
                        'controller' => Controller\Site\DownloadController::class,
                    ],
                ],
            ],
            'download-file' => [
                'type' => \Zend\Router\Http\Segment::class,
                'options' => [
                    'route' => '/download/files/:type/:filename',
                    'constraints' => [
                        'type' => 'original|median|low',
                        'filename' => '[a-zA-Z0-9/._-]+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'DownloadManager\Controller\Site',
                        'controller' => Controller\Site\DownloadController::class,
                        'action' => 'files',
                        'type' => 'original',
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Download Manager', // @translate
                'route' => 'admin/download/default',
                'controller' => Controller\Admin\DownloadController::class,
                'action' => 'dashboard',
                'pages' => [
                    [
                        'label' => 'Dashboard', // @translate
                        'route' => 'admin/download/default',
                        'controller' => Controller\Admin\DownloadController::class,
                        'action' => 'dashboard',
                    ],
                    [
                        'label' => 'By item', // @translate
                        'route' => 'admin/download/default',
                        'controller' => Controller\Admin\DownloadController::class,
                        'action' => 'by-item',
                    ],
                    [
                        'label' => 'By user', // @translate
                        'route' => 'admin/download/default',
                        'controller' => Controller\Admin\DownloadController::class,
                        'action' => 'by-user',
                    ],
                    [
                        'label' => 'By download', // @translate
                        'route' => 'admin/download/default',
                        'controller' => Controller\Admin\DownloadController::class,
                        'action' => 'by-download',
                    ],
                    [
                        'label' => 'By site', // @translate
                        'route' => 'admin/download/default',
                        'controller' => Controller\Admin\DownloadController::class,
                        'action' => 'by-site',
                    ],
                    [
                        'label' => 'By property', // @translate
                        'route' => 'admin/download/default',
                        'controller' => Controller\Admin\DownloadController::class,
                        'action' => 'by-property',
                    ],
                    [
                        'route' => 'admin/download/default',
                        'controller' => Controller\Admin\DownloadController::class,
                        'action' => 'browse',
                        'visible' => false,
                    ],
                    [
                        'route' => 'admin/download/default',
                        'controller' => Controller\Admin\DownloadController::class,
                        'action' => 'by-resource',
                        'visible' => false,
                    ],
                ],
            ],
        ],
        'site' => [
            [
                'label' => 'Stats', // @translate
                'route' => 'admin/site/slug/download',
                'controller' => Controller\Admin\DownloadController::class,
                'action' => 'stats',
                'useRouteMatch' => true,
                'pages' => [
                    [
                        'label' => 'By item', // @translate
                        'route' => 'admin/site/slug/download',
                        'controller' => Controller\Admin\DownloadController::class,
                        'action' => 'by-item',
                        'useRouteMatch' => true,
                    ],
                    [
                        'label' => 'By user', // @translate
                        'route' => 'admin/site/slug/download',
                        'controller' => Controller\Admin\DownloadController::class,
                        'action' => 'by-user',
                        'useRouteMatch' => true,
                    ],
                    [
                        'label' => 'By download', // @translate
                        'route' => 'admin/site/slug/download',
                        'controller' => Controller\Admin\DownloadController::class,
                        'action' => 'by-download',
                        'useRouteMatch' => true,
                    ],
                    [
                        'label' => 'By property', // @translate
                        'route' => 'admin/site/slug/download',
                        'controller' => Controller\Admin\DownloadController::class,
                        'action' => 'by-property',
                        'useRouteMatch' => true,
                    ],
                    [
                        'route' => 'admin/site/slug/download',
                        'controller' => Controller\Admin\DownloadController::class,
                        'action' => 'browse',
                        'useRouteMatch' => true,
                        'visible' => false,
                    ],
                    [
                        'route' => 'admin/site/slug/download',
                        'controller' => Controller\Admin\DownloadController::class,
                        'action' => 'by-resource',
                        'useRouteMatch' => true,
                        'visible' => false,
                    ],
                ],
            ],
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
    'js_translate_strings' => [
        'Place a hold', // @translate
        'Remove a hold', // @translate
        'Request too long to process.', // @translate
        'The resource doesn’t exist.', // @translate
    ],
    'downloadmanager' => [
        'config' => [
            // Unique user key for encryption. If empty, a single key will be
            // used for each book.
            // It should be set here, because it is not available in the config
            // form for security and stability reasons.
            // With unique keys, the files are not signed, only encrypted.
            'downloadmanager_unique_user_key' => 'XzVh6JiZ4IHm5cYID3QChFwKHXpkuUs7',
            'downloadmanager_unique_server_key' => 'SqjIK6Ui6jn0IdLxgkSZ6a2ZMMLpTAR8',

            // Are public items without total exemplars free for visitors?
            // Else, empty means allowed for identifier users only.
            'downloadmanager_public_visibility' => false,
            // Automatic display of the partial for availability.
            'downloadmanager_show_availability' => true,
            // Forbid access to media in the front board.
            'downloadmanager_access_route_site_media' => true,
            // Show media in full page (without layout).
            'downloadmanager_show_media_terminal' => true,

            // Number of seconds before expiration of a downloaded file (30 days).
            'downloadmanager_download_expiration' => 2592000,
            // Number of seconds before expiration of an offline file link (1 day).
            // 'downloadmanager_offline_link_expiration' =>  86400,
            // Maximum number of downloaded for a user.
            'downloadmanager_max_copies_by_user' => 200,
            // Maximum number of copies for a user at the same time.
            'downloadmanager_max_simultaneous_copies_by_user' => 50,

            // Path to the file used to crypt the credential.
            'downloadmanager_credential_key_path' => 'files/access_key.php',
            // Path to the certificate used to sign a pdf.
            'downloadmanager_certificate_path' => '',
            'downloadmanager_pfx_password' => '',
            'downloadmanager_sign_reason' => 'Available for reading by %1$s (downloaded from %2$s on %3$s)',
            'downloadmanager_sign_location' => 'My Location',
            'downloadmanager_sign_append_block' => 'en',
            'downloadmanager_sign_comment' => 'Downloaded from %2$s by %1$s on %3$s',
            'downloadmanager_sign_image_path' => '',
            // Path to the tool used to sign a pdf.
            'downloadmanager_signer_path' => 'PortableSigner',
            // Allow to encrypt signed files (so no signature will set/added).
            'downloadmanager_skip_signing_signed_file' => true,
            // Password to encrypt the files (master).
            'downloadmanager_owner_password' => '',
            // Permissions for the user to use a pdf.
            'downloadmanager_pdf_permissions' => '',
            // Send unencryptable pdf (not standard, already signed, or too big).
            'downloadmanager_send_unencryptable' => false,
            // Path to the tool used to encrypt a pdf.
            'downloadmanager_encrypter_path' => 'pdftk',

            // Cron tasks and report.
            'downloadmanager_server_url' => '',
            'downloadmanager_report_recipients' => [],
            'downloadmanager_report_threshold_limit' => 90,

            // Notification of user.
            'downloadmanager_notification_availability_subject' => "[{site_name}] New book available", // @translate
            'downloadmanager_notification_availability_message' => "Hi {user_name},\n\n An item you hold is available: {resource_title} ({resource_link}). If you still want it, download it now, as long as it is still available.\n\n{site_name}\n{site_link}.", // @translate

            // Miscellaneous.
            'downloadmanager_item_set_top_pick' => null,
            'downloadmanager_item_set_trending' => null,

            // Debug.
            'downloadmanager_debug_disable_encryption_sites' => '',
            'downloadmanager_debug_disable_encryption_groups' => '',
        ],
    ],
];
