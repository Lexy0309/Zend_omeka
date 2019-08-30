<?php
namespace GuestUser\Form;

use Zend\Form\Element;
use Zend\Form\Form;

class PhoneForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'o:phone',
            'type' => Element\Tel::class,
            'options' => [
                'label' => 'Phone', // @translate
            ],
            'attributes' => [
                'id' => 'phone',
                'required' => true,
            ],
        ]);
    }
}
