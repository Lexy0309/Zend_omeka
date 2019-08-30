<?php
namespace Ebsco\Media\Renderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Renderer\RendererInterface;
use Zend\View\Renderer\PhpRenderer;

class Ebsco implements RendererInterface
{
    const WIDTH = 420;
    const HEIGHT = 315;
    const ALLOWFULLSCREEN = true;

    public function render(PhpRenderer $view, MediaRepresentation $media,
        array $options = []
    ) {
        if (!isset($options['width'])) {
            $options['width'] = self::WIDTH;
        }
        if (!isset($options['height'])) {
            $options['height'] = self::HEIGHT;
        }
        if (!isset($options['allowfullscreen'])) {
            $options['allowfullscreen'] = self::ALLOWFULLSCREEN;
        }

        // Compose the ebsco embed URL and build the markup.
        $url = $media->source();
        $embed = sprintf(
            '<iframe width="%s" height="%s" src="%s" frameborder="0"%s></iframe>',
            $view->escapeHtml($options['width']),
            $view->escapeHtml($options['height']),
            $view->escapeHtml($url),
            $options['allowfullscreen'] ? ' allowfullscreen' : ''
        );
        return $embed;
    }
}
