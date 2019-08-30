<?php
namespace DocumentViewer\Form;

use Zend\Form\Element\Select;
use Zend\Form\Element\Text;
use Zend\Form\Form;
use Zend\I18n\Translator\TranslatorAwareInterface;
use Zend\I18n\Translator\TranslatorAwareTrait;

class ConfigForm extends Form implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    public function init()
    {
        $valueOptions = [
            'inline' => 'Inline (easily customizable)', // @translate
            'object' => 'Object', // @translate
            'embed' => 'Embed', // @translate
            'iframe' => 'Iframe (most common)', // @translate
            'object_iframe' => 'Object + iframe (max compatibility)', // @translate
            'dflipviewer' => 'Dflip (proprietary jQuery filpbook with pdfjs)', // @translate
            'dflipviewer_raw' => 'Dflip (proprietary jQuery filpbook with pdfjs), for unencrypted files', // @translate
            'pspdfkit' => 'PspdfKit (proprietary code in iframe)', // @translate
            'pspdfkit_raw' => 'PspdfKit (proprietary code in iframe), for unencrypted files', // @translate
            'custom' => 'Custom (via the partial common/document-viewer)', // @translate
        ];
        $this->add([
            'name' => 'documentviewer_pdf_mode',
            'type' => Select::class,
            'options' => [
                'label' => 'Integration mode', // @translate
                'info' => $this->translate('According to the needed compatibility level, the pdf viewer can be embedded in multiple ways.') // @translate
                    . ' '  . $this->translate('These options are used in the admin board.'), // @translate
                'value_options' => $valueOptions,
            ],
            'attributes' => [
                'required' => 'true',
            ],
        ]);

        $this->add([
            'name' => 'documentviewer_pdf_style',
            'type' => Text::class,
            'options' => [
                'label' => 'Inline style', // @translate
                'info' => $this->translate('If any, this style will be added to the main div of the pdf.') // @translate
                    . ' ' . $this->translate('The height may be required.'), // @translate
            ],
        ]);
    }

    protected function translate($args)
    {
        $translator = $this->getTranslator();
        return $translator->translate($args);
    }
}
