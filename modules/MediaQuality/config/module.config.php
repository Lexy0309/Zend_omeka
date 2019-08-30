<?php
namespace MediaQuality;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'formatDigitalUnits' => View\Helper\FormatDigitalUnits::class,
        ],
        'factories' => [
            'mediaQualities' => Service\ViewHelper\MediaQualitiesFactory::class,
        ],
    ],
    'mediaquality' => [
        'site_settings' => [
            'mediaquality_append_item_show' => true,
            'mediaquality_append_media_show' => true,
        ],
        // The processors are managed by media type and the conversion can be
        // made at different levels of quality, from bigger to lower.
        'processors' => [
            'application/pdf' => [
                'median' => [
                    'dir' => 'median',
                    'mediaType' => 'application/pdf',
                    'extension' => 'pdf',
                    'command' => 'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.7 -dPDFSETTINGS=/ebook -dNOPAUSE -dQUIET -dBATCH -o %2$s %1$s',
                ],
                'low' => [
                    'dir' => 'low',
                    'mediaType' => 'application/pdf',
                    'extension' => 'pdf',
                    'command' => 'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.7 -dPDFSETTINGS=/screen -dNOPAUSE -dQUIET -dBATCH -o %2$s %1$s',
                ],
            ],
        ],
    ],
];
