<?php
namespace VatiruLibrary\Form;

use Zend\Form\Element;
use Zend\Form\Form;

class BasicForm extends Form
{
    public function init()
    {
        // TODO Keep csrf in psl search form. Seem not required without pubtype.
        $this->remove('csrf');

        $this->add([
            'name' => 'pubtype',
            'type' => Element\Radio::class,
            'options' => [
                // 'label' => 'Publication type', // @translate
                // 'info' => 'Select the publication type you want to search.', // @translate
                'value_options' => [
                    'book' => 'eBooks', // @translate
                    'article' => 'Articles', // @translate
                    'all' => 'All', // @translate
                ],
            ],
            'attributes' => [
                'required' => false,
            ],
        ]);

        $this->add([
            'name' => 'q',
            'type' => Element\Text::class,
            // 'options' => [
            // 'label' => 'Search', // @translate
            // ],
            'attributes' => [
                'placeholder' => 'Search by title, author, ISBN, DOI…', // @translate
                'class' => 'form-control search2',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'pubtype',
            'required' => false,
        ]);
    }
}
