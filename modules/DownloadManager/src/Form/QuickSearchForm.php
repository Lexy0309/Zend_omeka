<?php

namespace DownloadManager\Form;

use Group\Form\Element\GroupSelect;
use Omeka\Form\Element\ResourceSelect;
use Zend\Form\Element;
use Zend\Form\Form;
use Zend\View\Helper\Url;

class QuickSearchForm extends Form
{
    /**
     * @var Url
     */
    protected $urlHelper;

    public function init()
    {
        $this->setAttribute('method', 'get');

        // No csrf: see main search form.
        $this->remove('csrf');

        $urlHelper = $this->getUrlHelper();

        $this->add([
            'type' => Element\Text::class,
            'name' => 'created',
            'options' => [
                'label' => 'Created', // @translate
            ],
            'attributes' => [
                'placeholder' => 'Set a date with optional comparator…', // @translate
            ],
        ]);

        $valueOptions = [
            \DownloadManager\Entity\Download::STATUS_READY => 'Ready', // @translate
            \DownloadManager\Entity\Download::STATUS_HELD => 'Held', // @translate
            \DownloadManager\Entity\Download::STATUS_DOWNLOADED => 'Downloaded', // @translate
            \DownloadManager\Entity\Download::STATUS_PAST => 'Past', // @translate
        ];
        $this->add([
            'name' => 'status',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Status', // @translate
                'value_options' => $valueOptions,
                'empty_option' => 'Select status…', // @translate
            ],
            'attributes' => [
                'placeholder' => 'Select status…', // @translate
            ],
        ]);

        $this->add([
            'name' => 'owner_id',
            'type' => ResourceSelect::class,
            'options' => [
                'label' => 'User', // @translate
                'resource_value_options' => [
                    'resource' => 'users',
                    'query' => [],
                    'option_text_callback' => function ($user) {
                        return $user->name();
                    },
                ],
                'empty_option' => '',
            ],
            'attributes' => [
                'id' => 'owner_id',
                'class' => 'chosen-select',
                'data-placeholder' => 'Select a user…', // @translate
                'data-api-base-url' => $urlHelper('api/default', ['resource' => 'users']),
            ],
        ]);

        $this->add([
            'name' => 'site_id',
            'type' => ResourceSelect::class,
            'options' => [
                'label' => 'Site', // @translate
                'resource_value_options' => [
                    'resource' => 'sites',
                    'query' => [],
                    'option_text_callback' => function ($site) {
                        return $site->title();
                    },
                ],
                'empty_option' => '',
            ],
            'attributes' => [
                'id' => 'site_id',
                'class' => 'chosen-select',
                'data-placeholder' => 'Select a site…', // @translate
                'data-api-base-url' => $urlHelper('api/default', ['resource' => 'sites']),
            ],
        ]);

        $this->add([
            'name' => 'group',
            'type' => GroupSelect::class,
            'options' => [
                'label' => 'Groups', // @translate
                'chosen' => true,
                'name_as_value' => true,
                'empty_option' => '',
            ],
            'attributes' => [
                'multiple' => false,
                'data-placeholder' => 'Select a group…', // @translate
            ],
        ]);

        $this->add([
            'type' => Element\Number::class,
            'name' => 'resource_id',
            'options' => [
                'label' => 'Resource', // @translate
            ],
            'attributes' => [
                'placeholder' => 'Set a resource id…', // @translate
            ],
        ]);

        $this->add([
            'name' => 'submit',
            'type' => Element\Submit::class,
            'attributes' => [
                'value' => 'Search', // @translate
                'type' => 'submit',
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
     * @return \Zend\View\Helper\Url
     */
    public function getUrlHelper()
    {
        return $this->urlHelper;
    }
}
