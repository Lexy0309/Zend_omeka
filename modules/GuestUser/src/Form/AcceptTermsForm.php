<?php
namespace GuestUser\Form;

use Zend\Form\Element;
use Zend\Form\Form;

class AcceptTermsForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'guestuser_agreed_terms',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'I agree with terms and conditions.', // @translate
            ],
            'attributes' => [
                'required' => !empty($this->getOption('forced')),
            ],
        ]);
    }
}
