<?php
namespace DocumentViewer\Media\FileRenderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\FileRenderer\RendererInterface;
use Omeka\Stdlib\Message;
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
        'mode' => 'inline',
        'attributes' => 'allowfullscreen="1"',
        'style' => 'height: 600px; 70vh',
    ];

    /**
     * @var PhpRenderer
     */
    protected $view;

    /**
     * Render a pdf via the Mozilla library pdf.js.
     *
     * @param PhpRenderer $view,
     * @param MediaRepresentation $media
     * @param array $options These options are managed for sites:
     *   - mode: set the rendering mode: "inline" (default), "object" (via the
     *   browser reader), "embed", "iframe", or "object_iframe" (for old
     *   compatibiliy).
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
            $mode = $view->setting('documentviewer_pdf_mode', $this->defaultOptions['mode']);
            $attributes = $this->defaultOptions['attributes'];
            $style = $view->setting('documentviewer_pdf_style', $this->defaultOptions['style']);
        }
        // For sites.
        else {
            $mode = isset($options['mode'])
                ? $options['mode']
                : $view->siteSetting('documentviewer_pdf_mode', $this->defaultOptions['mode']);

            $attributes = isset($options['attributes'])
                ? $options['attributes']
                : $view->siteSetting('documentviewer_pdf_attributes', $this->defaultOptions['attributes']);

            $style = isset($options['style'])
                ? $options['style']
                : $view->siteSetting('documentviewer_pdf_style', $this->defaultOptions['style']);
        }

        switch ($mode) {
            case 'inline':
                return $view->partial('common/pdf-viewer-inline', [
                    'media' => $media,
                    'attributes' => $attributes,
                    'style' => $style,
                ]);

            case 'object':
                $html = '<object height="100%%" width="100%%" %1$s%2$s data="%3$s" type="application/pdf">%4$s</object>';
                break;

            case 'embed':
                $html = '<embed height="100%%" width="100%%" %1$s%2$s src="%3$s" type="application/pdf" />';
                break;

            case 'iframe':
                $html = '<iframe height="100%%" width="100%%" %1$s%2$s src="%3$s">%4$s</iframe>';
                break;

            case 'object_iframe':
                $html = '<object height="100%%" width="100%%" %1$s%2$s data="%3$s" type="application/pdf">'
                    . '<iframe height="100%%" width="100%%" %1$s%2$s src="%3$s">%4$s</iframe>'
                    . '</object>';
                break;

            case 'dflipviewer':
            case 'dflipviewer_raw':
                $view->headLink()->appendStylesheet($view->assetUrl('vendor/dflip/css/dflip.min.css', 'DflipViewer'));
                $view->headLink()->appendStylesheet($view->assetUrl('vendor/dflip/css/themify-icons.css', 'DflipViewer'));
                $view->headScript()->appendFile($view->assetUrl('vendor/jquery/jquery.min.js', 'Omeka'));
                $view->headScript()->appendFile($view->assetUrl('vendor/dflip/js/dflip.min.js', 'DflipViewer'));
                // TODO Add $this->fallback($media).
                // The choice of the formats depends on the class and the id
                // (_df_book, _df_thumb…).
                // $html = '<div class="_df_book" id="df_manual_book" source="%1$s" %2$s%3$s></div>';
                // return vsprintf($html, [$media->originalUrl(), $attributes, $style ? ' style="' . $style . '"' : '']);
                // Nevertheless, it may be simpler to use jQuery.
                $html =  '<div id="flipbookContainer"></div>';
                // See https://flipbookplugin.com/docs/jquery/documentation.html for the available options.
                $js = <<<'JS'
var dFlipLocation = %1$s;
jQuery(document).ready(function () {
    var pdf = %2$s;
    var options = {
        enableDownload: false,
        duration: 800,
        backgroundColor: '#404040'
    };
    var flipBook = $("#flipbookContainer").flipBook(pdf, options);
});
JS;
                // TODO Add $this->fallback($media).
                $js = vsprintf($js, [
                    json_encode($view->assetUrl('vendor/dflip/', 'DflipViewer', false, false), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    json_encode($media->originalUrl(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                ]);
                $view->headScript()->appendScript($js);
                return vsprintf($html, [$attributes, $style ? ' style="' . $style . '"' : '']);

            case 'pspdfkit':
                $view->headScript()->appendFile($view->assetUrl('js/pspdfkit.js', 'PspdfKit'));
                $js = <<<'JS'
PSPDFKit.load({
    container: "#pspdfkit",
    pdf: fetchContentAsArrayValue(%1$s),
    licenseKey: %2$s
}).then(function(instance) {
    console.log("PSPDFKit loaded", instance);
}).catch(function(error) {
    console.error(error.message);
});
JS;
                // No break.
            case 'pspdfkit_raw':
                $view->headScript()->appendFile($view->assetUrl('vendor/pspdfkit/pspdfkit.js', 'PspdfKit'));
                $apiKey = $view->setting('pspdfkit_api_key');
                $html = '<div id="pspdfkit" %1$s%2$s"></div>';
                // Key pdf requires a url or an ArrayBuffer. Only uncrypted file. Don't manage linearized files.
                if (empty($js)) {
                    $js = <<<'JS'
PSPDFKit.load({
    container: "#pspdfkit",
    pdf: %1$s,
    licenseKey: %2$s
}).then(function(instance) {
    console.log("PSPDFKit loaded", instance);
}).catch(function(error) {
    console.error(error.message);
});
JS;
                }

                // TODO Add $this->fallback($media).
                $js = vsprintf($js, [
                    json_encode($media->originalUrl(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    json_encode($apiKey, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]);
                $view->headScript()->appendScript($js);
                return vsprintf($html, [$attributes, $style ? ' style="' . $style . '"' : '']);

            case 'custom_dflip':
                return $view->partial('common/document-viewer-dflip', [
                    'media' => $media,
                    'attributes' => $attributes,
                    'style' => $style,
                ]);
                break;

            case 'custom':
                return $view->partial('common/document-viewer', [
                    'media' => $media,
                    'attributes' => $attributes,
                    'style' => $style,
                ]);
                break;

            default:
                return new Message('The mode "%s" is not managed by the pdf viewer.', $mode); // @translate
        }
        return vsprintf($html, [$attributes, $style ? ' style="' . $style . '"' : '', $media->originalUrl(), $this->fallback($media)]);
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
