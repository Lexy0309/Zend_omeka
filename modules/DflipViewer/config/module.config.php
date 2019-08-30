<?php
namespace DflipViewer;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'file_renderers' => [
        'invokables' => [
            'dflipviewer' => Media\FileRenderer\Pdf::class,
        ],
        'aliases' => [
//           'application/pdf' => 'dflipviewer',
//           'pdf' => 'dflipviewer',
        ],
    ],
    'form_elements' => [
        'factories' => [
            Form\ConfigForm::class => Service\Form\ConfigFormFactory::class,
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
    'dflipviewer' => [
        'config' => [
            'dflipviewer_pdf_style' => 'height: 600px; height: 70vh;',
        ],
        'site_settings' => [
            'dflipviewer_pdf_style' => 'height: 600px; height: 70vh;',
        ],
    ],
];
