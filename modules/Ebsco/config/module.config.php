<?php
namespace Ebsco;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'media_ingesters' => [
        'factories' => [
            'ebsco' => Service\Media\Ingester\EbscoFactory::class,
        ],
    ],
    'media_renderers' => [
        'invokables' => [
            'ebsco' => Media\Renderer\Ebsco::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
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
    'ebsco' => [
        'config' => [
            'ebsco_style' => 'height: 600px; height: 70vh;',
        ],
        'site_settings' => [
            'ebsco_style' => 'height: 600px; height: 70vh;',
        ],
    ],
];
