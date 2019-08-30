<?php
namespace Maintenance;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'controllers' => [
        'invokables' => [
            'Omeka\Controller\Maintenance' => Controller\MaintenanceController::class,
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
    'maintenance' => [
        'config' => [
            'maintenance_status' => false,
            'maintenance_text' => 'This site is down for maintenance. Please contact the site administrator for more information.', // @translate
        ],
    ],
];
