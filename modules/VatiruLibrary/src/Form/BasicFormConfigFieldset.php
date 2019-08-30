<?php
namespace VatiruLibrary\Form;

use Zend\Form\Element;
use Zend\Form\Fieldset;

class BasicFormConfigFieldset extends Fieldset
{
    public function init()
    {
        $fieldOptions = $this->getFieldsOptions();

        $this->add([
            'name' => 'vatiru:publicationType',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Field Vatiru Publication type', // @translate
                'value_options' => $fieldOptions,
                'empty_option' => 'None', // @translate
            ],
            'attributes' => [
                'required' => true,
                'class' => 'chosen-select',
            ],
        ]);
    }

    protected function getAvailableFields()
    {
        $searchPage = $this->getOption('search_page');
        $searchAdapter = $searchPage->index()->adapter();
        return $searchAdapter->getAvailableFields($searchPage->index());
    }

    protected function getFieldsOptions()
    {
        $options = [];
        foreach ($this->getAvailableFields() as $name => $field) {
            $options[$name] = isset($field['label'])
                ? sprintf('%s (%s)', $field['label'], $name)
                : $name;
        }
        return $options;
    }
}
