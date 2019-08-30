<?php
namespace DownloadManager\Form;

use Zend\Form\Form;
use Zend\Form\FormElementManager\FormElementManagerV3Polyfill as FormElementManager;
use Zend\View\Helper\Url;

class DownloadForm extends Form
{
    /**
     * @var Url
     */
    protected $urlHelper;

    /**
     * @var FormElementManager
     */
    protected $formElementManager;

    protected $options = [
        'site_slug' => null,
        'download' => null,
        'owner_id' => null,
        'resource_id' => null,
        'is_identified' => false,
    ];

    public function init()
    {
        $urlHelper = $this->getUrlHelper();
        $resourceId = $this->getOption('resource_id');
        $siteSlug = $this->getOption('site_slug');
        $isPublic = (bool) strlen($siteSlug);
        if ($this->options['is_identified']) {
            $action = $isPublic
                ? $urlHelper('site/download-id', ['site-slug' => $siteSlug, 'action' => 'hold', 'id' => $resourceId])
                : $urlHelper('admin/download-id', ['action' => 'hold', 'id' => $resourceId]);
        } else {
            $action = $urlHelper('login');
        }

        $this->setAttribute('id', 'download-form-' . $resourceId);
        $this->setAttribute('action', $action);
        $this->setAttribute('class', 'download-form');
        $this->setAttribute('data-resource-id', $resourceId);

        $this->add([
            'type' => 'hidden',
            'name' => 'resource_id',
            'attributes' => [
                'value' => $resourceId,
                'required' => true,
            ],
        ]);

        $this->add([
            'type' => 'csrf',
            'name' => sprintf('csrf_%s', $resourceId),
            'options' => [
                'csrf_options' => ['timeout' => 3600],
            ],
        ]);

        if ($this->options['is_identified']) {
            $this->add([
                'type' => 'button',
                'name' => 'submit',
                'options' => [
                    'label' => $this->options['download']
                        ? 'Remove my hold' // @translate
                        : 'Place a hold!', // @translate
                ],
                'attributes' => [
                    'class' => $this->options['download']
                        ? 'fa fa-hand-o-down'
                        : 'fa fa-hand-o-up',
                ],
            ]);
        } else {
            $this->add([
                'type' => 'button',
                'name' => 'submit',
                'options' => [
                    'label' => 'Login to place a hold', // @translate
                ],
                'attributes' => [
                    'class' => 'fa fa-right',
                ],
            ]);
        }
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

    /**
     * @param FormElementManager $formElementManager
     */
    public function setFormElementManager($formElementManager)
    {
        $this->formElementManager = $formElementManager;
    }

    /**
     * @return FormElementManager
     */
    public function getFormElementManager()
    {
        return $this->formElementManager;
    }
}
