<?php
namespace EbscoViewer\Media\FileRenderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\FileRenderer\RendererInterface;
use Zend\View\Renderer\PhpRenderer;

class Ebsco implements RendererInterface
{
    /**
     * These options are used only when the player is called outside of a site
     * or when the site settings are not set.
     *
     * @var array
     */
    protected $defaultOptions = [
        'attributes' => 'allowfullscreen="1"',
        'style' => 'height: 600px; 70vh',
    ];

    /**
     * @var PhpRenderer
     */
    protected $view;

    /**
     * Render a ebsco media via the proprietary Ebsco viewer in an iframe.
     *
     * @param PhpRenderer $view,
     * @param MediaRepresentation $media
     * @param array $options These options are managed for sites:
     *   - attributes: set the attributes to add
     *   - style: set the inline style
     * @return string
     */
    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        $this->setView($view);

        $isSite = $view->params()->fromRoute('__SITE__');
        // For admin board.
        if (empty($isSite)) {
            $attributes = $this->defaultOptions['attributes'];
            $style = $view->setting('ebscoviewer_style', $this->defaultOptions['style']);
        }
        // For sites.
        else {
            $attributes = isset($options['attributes'])
                ? $options['attributes']
                : $view->siteSetting('ebscoviewer_attributes', $this->defaultOptions['attributes']);

            $style = isset($options['style'])
                ? $options['style']
                : $view->siteSetting('ebscoviewer_style', $this->defaultOptions['style']);
        }

        $html = '<iframe height="100%%" width="100%%" %1$s%2$s src="%3$s">%4$s</iframe>';

        return vsprintf($html, [$attributes, $style ? ' style="' . $style . '"' : '', $media->originalUrl(), $this->fallback($media)]);
    }

    /**
     * @param PhpRenderer $view
     */
    protected function setView(PhpRenderer $view)
    {
        $this->view = $view;
    }

    /**
     * @return PhpRenderer
     */
    protected function getView()
    {
        return $this->view;
    }
}
