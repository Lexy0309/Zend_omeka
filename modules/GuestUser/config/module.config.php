<?php
namespace GuestUser;

return [
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
            'guestUserWidget' => View\Helper\GuestUserWidget::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\AcceptTermsForm::class => Form\AcceptTermsForm::class,
            Form\ConfigForm::class => Form\ConfigForm::class,
            Form\EmailForm::class => Form\EmailForm::class,
            Form\PhoneForm::class => Form\PhoneForm::class,
        ],
        'factories' => [
            // TODO To remove after merge of pull request https://github.com/omeka/omeka-s/pull/1138.
            \Omeka\Form\UserForm::class => Service\Form\UserFormFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\Site\GuestUserController::class => Service\Controller\Site\GuestUserControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'createGuestUserToken' => Service\ControllerPlugin\CreateGuestUserTokenFactory::class,
            'sendEmail' => Service\ControllerPlugin\SendEmailFactory::class,
            'sendSms' => Service\ControllerPlugin\SendSmsFactory::class,
            'userSites' => Service\ControllerPlugin\UserSitesFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            'Omeka\AuthenticationService' => Service\AuthenticationServiceFactory::class,
        ],
    ],
    'navigation_links' => [
        'invokables' => [
            'register' => Site\Navigation\Link\Register::class,
            'login' => Site\Navigation\Link\Login::class,
            'logout' => Site\Navigation\Link\Logout::class,
        ],
    ],
    'navigation' => [
        'site' => [
            [
                'label' => 'User information', // @translate
                'route' => 'site/guest-user',
                'controller' => Controller\Site\GuestUserController::class,
                'action' => 'me',
                'useRouteMatch' => true,
                'visible' => false,
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'guest-user' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/guest-user[/:action]',
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'GuestUser\Controller\Site',
                                'controller' => Controller\Site\GuestUserController::class,
                                'action' => 'me',
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
    'guestuser' => [
        'config' => [
            'guestuser_open' => false,
            'guestuser_recaptcha' => false,
            'guestuser_login_text' => 'Login', // @translate
            'guestuser_register_text' => 'Register', // @translate
            'guestuser_dashboard_label' => 'My Account', // @translate
            'guestuser_capabilities' => '',
            'guestuser_short_capabilities' => '',
            'guestuser_message_confirm_email' => '<p>Hi {user_name},</p>
<p>You have registered for an account on {main_title} / {site_title} ({site_url}).</p>
<p>Please confirm your registration by following this link: {token_url}.</p>
<p>If you did not request to join {main_title} please disregard this email.</p>', // @translate
            'guestuser_message_update_email' => '<p>Hi {user_name},</p>
<p>You have requested to update email on {main_title} / {site_title} ({site_url}).</p>
<p>Please confirm your email by following this link: {token_url}.</p>
<p>If you did not request to update your email on {main_title}, please disregard this email.</p>', // @translate
            'guestuser_terms_text' => 'I agree the terms and conditions.', // @translate
            'guestuser_terms_page' => 'terms-and-conditions',
            'guestuser_terms_redirect' => 'site',
            'guestuser_terms_request_regex' => '',
            'guestuser_terms_force_agree' => true,
            'guestuser_check_requested_with' => '',
            'guestuser_phone_url' => '',
            'guestuser_phone_api_key' => '',
            'guestuser_phone_token_bypass' => '',
        ],
        'user_settings' => [
            'guestuser_agreed_terms' => false,
        ],
    ],
];
