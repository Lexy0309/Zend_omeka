<?php
namespace DownloadManager\View\Helper;

use Omeka\Api\Exception\NotFoundException;
use Zend\View\Helper\AbstractHelper;

/**
 * View helper for rendering search filters.
 */
class DownloadSearchFilters extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/search-filters';

    /**
     * Render filters from search query.
     *
     * @return array
     */
    public function __invoke($partialName = null)
    {
        $partialName = $partialName ?: self::PARTIAL_NAME;

        $view = $this->getView();
        $translate = $view->plugin('translate');

        $filters = [];
        $api = $view->api();
        $query = $view->params()->fromQuery();

        $statuses = [
            \DownloadManager\Entity\Download::STATUS_READY => 'ready', // @translate
            \DownloadManager\Entity\Download::STATUS_HELD => 'held', // @translate
            \DownloadManager\Entity\Download::STATUS_DOWNLOADED => 'downloaded', // @translate
            \DownloadManager\Entity\Download::STATUS_PAST => 'past', // @translate
        ];

        foreach ($query as $key => $value) {
            if (is_null($value) || $value === '') {
                continue;
            }
            switch ($key) {
                case 'created':
                    $filterLabel = $translate('Created'); // @translate
                    $filterValue = $value;
                    $filters[$filterLabel][] = $filterValue;
                    break;

                case 'modified':
                    $filterLabel = $translate('Modified'); // @translate
                    $filterValue = $value;
                    $filters[$filterLabel][] = $filterValue;
                    break;

                case 'status':
                    $filterLabel = $translate('Status'); // @translate
                    $filterValue = isset($statuses[$value]) ? $statuses[$value] : $value;
                    $filters[$filterLabel][] = $filterValue;
                    break;

                case 'owner_id':
                    $filterLabel = $translate('User'); // @translate
                    try {
                        $filterValue = $api->read('users', $value)->getContent()->name();
                    } catch (NotFoundException $e) {
                        $filterValue = $translate('Unknown user');
                    }
                    $filters[$filterLabel][] = $filterValue;
                    break;

                case 'site_id':
                    $filterLabel = $translate('Site'); // @translate
                    try {
                        $filterValue = $api->read('sites', $value)->getContent()->title();
                    } catch (NotFoundException $e) {
                        $filterValue = $translate('Unknown site');
                    }
                    $filters[$filterLabel][] = $filterValue;
                    break;

                case 'group':
                    $filterLabel = $translate('Group'); // @translate
                    try {
                        $filterValue = $api->read('groups', ['name' => $value])->getContent()->name();
                    } catch (NotFoundException $e) {
                        $filterValue = $translate('Unknown group');
                    }
                    $filters[$filterLabel][] = $filterValue;
                    break;

                case 'resource_id':
                    $filterLabel = $translate('Resource'); // @translate
                    try {
                        $filterValue = $api->read('jobs', $value)->getContent()->id();
                    } catch (NotFoundException $e) {
                        $filterValue = $translate('Unknown job'); // @translate
                    }
                    $filters[$filterLabel][] = $filterValue;
                    break;
            }
        }

        $result = $this->getView()->trigger(
            'view.search.filters',
            ['filters' => $filters, 'query' => $query],
            true
        );
        $filters = $result['filters'];

        return $this->getView()->partial(
            $partialName,
            [
                'filters' => $filters,
            ]
        );
    }
}
