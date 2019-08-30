<?php
namespace VatiruLibrary\Form;

use Group\Form\Element\GroupSelect;
use Zend\Form\Element;
use Zend\Form\Form;
use Zend\View\Helper\Url;

class ConfigForm extends Form
{
    /**
     * @var Url
     */
    protected $urlHelper;

    public function init()
    {
        $urlHelper = $this->getUrlHelper();

        $this->add([
            'name' => 'vatiru_default_groups',
            'type' => GroupSelect::class,
            'options' => [
                'label' => 'Default groups of new guests', // @translate
                'info' => 'These groups are set to all new users.', // @translate
                'resource_value_options' => [
                    'resource' => 'groups',
                    'query' => [],
                    'option_text_callback' => function ($group) {
                        return $group->name();
                    },
                ],
            ],
            'attributes' => [
                'id' => 'select-group',
                'class' => 'chosen-select',
                'required' => false,
                'multiple' => true,
                'data-placeholder' => 'Select groups', // @translate
                'data-api-base-url' => $urlHelper('api/default', ['resource' => 'groups']),
            ],
        ]);

        $this->add([
            'name' => 'vatiru_message_user_activation',
            'type' => Element\Textarea::class,
            'options' => [
                'label' => 'Message for user activation',
            ],
            'attributes' => [
                'id' => 'vatiru_message_user_activation',
                'placeholder' => 'Activate!', // @translate
                'rows' => 10,
            ],
        ]);
    }

    /**
     * @param Url $urlHelper
     */
    public function setUrlHelper(Url $urlHelper)
    {
        $this->urlHelper = $urlHelper;
    }

    /**
     * @return Url
     */
    public function getUrlHelper()
    {
        return $this->urlHelper;
    }
}
