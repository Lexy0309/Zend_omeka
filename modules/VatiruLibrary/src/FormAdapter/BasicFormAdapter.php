<?php
namespace VatiruLibrary\FormAdapter;

use Search\Query;

class BasicFormAdapter implements \Search\FormAdapter\FormAdapterInterface
{
    public function getLabel()
    {
        return 'Basic Vatiru';
    }

    public function getFormClass()
    {
        return \VatiruLibrary\Form\BasicForm::class;
    }

    public function getFormPartial()
    {
        return null;
    }

    public function getConfigFormClass()
    {
        return \VatiruLibrary\Form\BasicFormConfigFieldset::class;
    }

    public function toQuery(array $request, array $formSettings)
    {
        $query = new Query();

        if (isset($request['q'])) {
            $query->setQuery($request['q']);
        }

        if (isset($request['pubtype']) && in_array($request['pubtype'], ['book', 'article'])) {
            $field = $formSettings['vatiru:publicationType'];
            $query->addFilter($field, $request['pubtype']);
        }

        return $query;
    }
}
