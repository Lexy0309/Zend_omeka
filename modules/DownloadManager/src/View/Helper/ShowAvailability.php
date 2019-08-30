<?php
namespace DownloadManager\View\Helper;

use DownloadManager\Entity\Download;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Entity\User;
use Zend\Mvc\Controller\PluginManager;
use Zend\View\Helper\AbstractHelper;

/**
 * Helper to display the availability of a resource for the current user.
 */
class ShowAvailability extends AbstractHelper
{
    /**
     * @var PluginManager
     */
    protected $plugins;

    /**
     * @param PluginManager $plugins
     */
    public function __construct(PluginManager $plugins)
    {
        $this->plugins = $plugins;
    }

    /**
     * Returns the view for the availability of a resource for the current user.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $options May be used to manage the display.
     * @return string|array The partial view, or an array with error if no rights.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $options = [])
    {
        $view = $this->getView();
        $user = $view->identity();

        // Check rights before anything.

        /** @var \DownloadManager\View\Helper\CheckResourceToDownload $checkResourceToDownload */
        $checkResourceToDownload = $this->plugins->get('checkResourceToDownload');
        $result = $checkResourceToDownload($resource);
        if (is_array($result)) {
            return $this->returnError($result, $resource, $user, $options);
        }

        /** @var \DownloadManager\View\Helper\CheckRightToDownload $checkRightToDownload */
        $checkRightToDownload = $this->plugins->get('checkRightToDownload');
        $result = $checkRightToDownload($resource, $user);
        if (is_array($result)) {
            return $this->returnError($result, $resource, $user, $options);
        }

        $getCurrentDownload = $this->plugins->get('getCurrentDownload');
        $download = $getCurrentDownload($resource, $user);
        $totalAvailable = $this->plugins->get('totalAvailable');
        $totalAvailable = $totalAvailable($resource);
        $isResourceAvailableForUser = $this->plugins->get('isResourceAvailableForUser');
        $isAvailable = $isResourceAvailableForUser($resource, $user);
        $totalExemplars = $this->plugins->get('totalExemplars');
        $totalExemplars = $totalExemplars($resource);
        $totalDownloaded = $this->totalDownloaded($resource);
        $totalHoldings = $this->totalHoldings($resource);

        // TODO The form is ready, but not used. Check if the csrf is needed (no: the identity is checked?).
        // $options = [
        //     'site_slug' => $view->params()->fromRoute('site-slug'),
        //     'download' => $download,
        //     'resource_id' => $resource->id(),
        //     'owner_id' => $user ? $user->getId() : null,
        //     'is_identified' => !empty($user),
        // ];
        // $form = $services->get('FormElementManager')->get(DownloadForm::class);
        // $form->setOptions($options);
        // $form->init();
        // $view->vars()->offsetSet('downloadForm', $form);

        $values = [
            'result' => true,
            'site_slug' => $view->params()->fromRoute('site-slug'),
            'download' => $download,
            'resource' => $resource,
            'resource_id' => $resource->id(),
            'owner_id' => $user ? $user->getId() : null,
            'is_identified' => !empty($user),
            'is_available' => $isAvailable,
            'total_exemplars' => $totalExemplars,
            'total_downloaded' => $totalDownloaded,
            'total_holdings' => $totalHoldings,
            'total_available' => $totalAvailable,
            'options' => $options,
        ];
        return $view->partial('common/availability', $values);
    }

    // TODO Create a view helper or a controller plugin for next methods (see controller / module).

    protected function totalHoldings(AbstractResourceEntityRepresentation $resource)
    {
        $total = $this->getView()->api()->search('downloads', [
            'resource_id' => $resource->id(),
            'status' => Download::STATUS_HELD,
        ])->getTotalResults();
        return $total;
    }

    protected function totalDownloaded(AbstractResourceEntityRepresentation $resource)
    {
        $total = $this->getView()->api()->search('downloads', [
            'resource_id' => $resource->id(),
            'status' => Download::STATUS_DOWNLOADED,
        ])->getTotalResults();
        return $total;
    }

    /**
     * Helper to return an error during the availability check.
     *
     * @param array $result
     * @param AbstractResourceEntityRepresentation $resource
     * @param User $user
     * @param array $options
     * @return string
     */
    protected function returnError(
        array $result,
        AbstractResourceEntityRepresentation $resource,
        User $user = null,
        array $options = []
    ) {
        $view = $this->getView();
        $values = $result + [
            'site_slug' => $view->params()->fromRoute('site-slug'),
            'download' => null,
            'resource' => $resource,
            'resource_id' => $resource->id(),
            'owner_id' => $user ? $user->getId() : null,
            'is_identified' => !empty($user),
            'is_available' => false,
            'total_exemplars' => 0,
            'total_downloaded' => 0,
            'total_holdings' => 0,
            'total_available' => 0,
            'options' => $options,
        ];
        return $view->partial('common/availability', $values);
    }
}
