<?php
namespace Ebsco\Form;

use Zend\Form\Element;
use Zend\Form\Form;

class ConfigForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'ebsco_style',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Inline style', // @translate
                'info' => 'If any, this style will be added to the main div of the viewer. The height may be required.', // @translate
            ],
            'attributes' => [
                'id' => 'ebsco_style',
            ],
        ]);
    }
}
