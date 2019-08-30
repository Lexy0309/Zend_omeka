<?php
namespace DflipViewer\Media\FileRenderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\FileRenderer\RendererInterface;
use Zend\View\Renderer\PhpRenderer;

class Pdf implements RendererInterface
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
     * Render a pdf via the proprietary Dflip jQuery library.
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

        $view->headLink()->appendStylesheet($view->assetUrl('vendor/dflip/css/dflip.min.css', 'DflipViewer'));
        $view->headLink()->appendStylesheet($view->assetUrl('vendor/dflip/css/themify-icons.css', 'DflipViewer'));
        $view->headScript()->appendFile($view->assetUrl('vendor/jquery/jquery.min.js', 'Omeka'));
        $view->headScript()->appendFile($view->assetUrl('vendor/dflip/js/dflip.min.js', 'DflipViewer'));

        $isSite = $view->params()->fromRoute('__SITE__');
        // For admin board.
        if (empty($isSite)) {
            $attributes = $this->defaultOptions['attributes'];
            $style = $view->setting('dflipviewer_pdf_style', $this->defaultOptions['style']);
        }
        // For sites.
        else {
            $attributes = isset($options['attributes'])
                ? $options['attributes']
                : $view->siteSetting('dflipviewer_pdf_attributes', $this->defaultOptions['attributes']);

            $style = isset($options['style'])
                ? $options['style']
                : $view->siteSetting('dflipviewer_pdf_style', $this->defaultOptions['style']);
        }

        // TODO Add $this->fallback($media).
        // The choice of the formats depends on the class and the id
        // (_df_book, _df_thumb…).
        // $html = '<div class="_df_book" id="df_manual_book" source="%1$s" %2$s%3$s></div>';
        // return vsprintf($html, [$media->originalUrl(), $attributes, $style ? ' style="' . $style . '"' : '']);
        // Nevertheless, it may be simpler to use jQuery.
        $html = '<div id="flipbookContainer"></div>';
        // See https://flipbookplugin.com/docs/jquery/documentation.html for the available options.
        $js = <<<'JS'
var dFlipLocation = %1$s;
jQuery(document).ready(function () {
    var pdf = %2$s;
    var options = {
        enableDownload: false,
        duration: 500,
        backgroundColor: '#393939'
    };
    var flipBook = $("#flipbookContainer").flipBook(pdf, options);
});
JS;
        // TODO Add $this->fallback($media).
        $js = vsprintf($js, [
            json_encode($view->assetUrl('vendor/dflip/', 'DflipViewer', false, false), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            json_encode($media->originalUrl(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        $view->headScript()->appendScript($js);
        return vsprintf($html, [$attributes, $style ? ' style="' . $style . '"' : '']);
    }

    protected function fallback($media)
    {
        $view = $this->getView();
        $text = $view->escapeHtml($view->translate('This browser does not support PDF.'))
            . ' ' . sprintf($view->translate('You may %sdownload it%s to view it offline.'), // @translate
                '<a href="' . $media->originalUrl() . '">', '</a>');
        $html = '<p>' . $text . '</p>'
            . '<img src="' . $media->thumbnailUrl('large') . '" height="600px" />';
        return $html;
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
