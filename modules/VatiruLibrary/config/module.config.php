<?php
namespace VatiruLibrary;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
            // Used for the error template only.
            // TODO Remove this path (fix Omeka index.php).
            // Warning: it works only with the theme vatiru or polaris.
            // If not set, it will fallback to Omeka anyway.
            dirname(dirname(dirname(__DIR__))) . '/themes/polaris',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'vatiruApiVersion' => Service\ViewHelper\VatiruApiVersionFactory::class,
            'guestUserForm' => Service\ViewHelper\GuestUserFormFactory::class,
            'paginationDefaultOrder' => Service\ViewHelper\PaginationDefaultOrderFactory::class,
        ],
    ],
    'form_elements' => [
        'factories' => [
            Form\BasicForm::class => Service\Form\BasicFormFactory::class,
            Form\BasicFormConfigFieldset::class => Service\Form\BasicFormConfigFieldsetFactory::class,
            Form\ConfigForm::class => Service\Form\ConfigFormFactory::class,
        ],
    ],
    'block_layouts' => [
        'invokables' => [
            'searchBox' => Site\BlockLayout\SearchBox::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\Site\VatiruController::class => Service\Controller\Site\VatiruControllerFactory::class,
            'Omeka\Controller\Api' => Service\Controller\ApiControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'isRequestedWithVatiruLibraryApp' => Mvc\Controller\Plugin\IsRequestedWithVatiruLibraryApp::class,
            'requestVatiruApiVersion' => Mvc\Controller\Plugin\RequestVatiruApiVersion::class,
        ],
        'factories' => [
            'vatiruApiVersion' => Service\ControllerPlugin\VatiruApiVersionFactory::class,
            'siteOfCurrentUser' => Service\ControllerPlugin\SiteOfCurrentUserFactory::class,
            'siteOfUserEntity' => Service\ControllerPlugin\SiteOfUserEntityFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            'Omeka\Mailer' => Service\MailerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'vatiru-library' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/vatiru[/:action]',
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'VatiruLibrary\Controller\Site',
                                'controller' => Controller\Site\VatiruController::class,
                                'action' => 'auth',
                            ],
                        ],
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
    'csvimport' => [
        'mappings' => [
            'sites' => [
                'label' => 'Sites', // @translate
                'mappings' => [
                    Mapping\SiteMapping::class,
                ],
            ],
        ],
        'automapping' => [
            'site' => [
                'name' => 'site',
                'value' => 1,
                'label' => 'Site [%s]', // @translate
                'class' => 'site',
            ],
        ],
        'user_settings' => [
            'csvimport_automap_user_list' => [
                'site' => 'site {o:title}',
                'title' => 'site {o:title}',
                'name' => 'site {o:title}',
                'site name' => 'site {o:title}',
                'site title' => 'site {o:title}',
                'slug' => 'site {o:slug}',
                'site slug' => 'site {o:slug}',
                'theme' => 'site {o:theme}',
                'site theme' => 'site {o:theme}',
                'owner' => 'site {o:owner}',
                'site owner' => 'site {o:owner}',
                'visibility' => 'site {o:is_public}',
                'site visibility' => 'site {o:is_public}',
                /*
                'owner' => 'owner_email',
                'site owner' => 'owner_email',
                'visibility' => 'is_public',
                'site visibility' => 'is_public',
                'item set' => 'site {o:item_set}',
                'item sets' => 'site {o:item_set}',
                'collection' => 'site {o:item_set}',
                'collections' => 'site {o:item_set}',
                'item set id' => 'site {o:item_set}',
                'collection id' => 'site {o:item_set}',
                'site item set' => 'site {o:item_set}',
                'site item sets' => 'site {o:item_set}',
                'site collection' => 'site {o:item_set}',
                'site collections' => 'site {o:item_set}',
                'site item set id' => 'site {o:item_set}',
                'site collection id' => 'site {o:item_set}',
                */
            ],
        ],
    ],
    'search_form_adapters' => [
        'invokables' => [
            'basicVatiru' => FormAdapter\BasicFormAdapter::class,
        ],
    ],
    'vatirulibrary' => [
        'config' => [
            'vatiru_default_groups' => [],
            'vatiru_message_user_activation' => 'Greetings!

A user has been created for you on %5$s at %1$s

Your username is your email: %2$s

Click this link to set a password and begin using Vatiru Digital Library:
%3$s

Your activation link will expire on %4$s. If you have not completed the user activation process by the time the link expires, you will need to request another activation email from your site administrator.'
        ],
        'site_settings' => [
            'vatiru_current_site_group' => true,
            'vatiru_site_groups' => [],
        ],
        'dependencies' => [
            'required' => [
                'DownloadManager',
                'Group',
                'GuestUser',
                'MediaQuality',
                'External',
            ],
            'optional' => [
                // This is not required, since there are individual users.
                'Saml',
            ],
        ],
    ],
    'documentviewer' => [
        'site_settings' => [
            'documentviewer_pdf_mode' => 'inline',
        ],
    ],
    'mediaquality' => [
        'site_settings' => [
            'mediaquality_append_item_show' => false,
            'mediaquality_append_media_show' => false,
        ],
    ],
];
