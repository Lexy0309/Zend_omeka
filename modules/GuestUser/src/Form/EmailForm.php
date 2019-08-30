<?php
namespace GuestUser\Form;

use Zend\Form\Element;
use Zend\Form\Form;

class EmailForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'o:email',
            'type' => Element\Email::class,
            'options' => [
                'label' => 'Email', // @translate
            ],
            'attributes' => [
                'id' => 'email',
                'required' => true,
            ],
        ]);
    }
}
