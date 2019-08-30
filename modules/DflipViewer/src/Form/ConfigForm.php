<?php
namespace DflipViewer\Form;

use Zend\Form\Element;
use Zend\Form\Form;
use Zend\I18n\Translator\TranslatorAwareInterface;
use Zend\I18n\Translator\TranslatorAwareTrait;

class ConfigForm extends Form implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    public function init()
    {
        $this->add([
            'name' => 'dflipviewer_pdf_style',
            'type' => Element\Text::class,
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
