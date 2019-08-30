<?php
namespace VatiruLibrary\Controller;

use Omeka\View\Model\ApiJsonModel;

class ApiController extends \Omeka\Controller\ApiController
{
    protected $sitePerPage = 200;

    /**
     * Modify the browse search (api json search) for external application.
     *
     * - Items are ordered by created when simple browsing (no search).
     * - Browse and search are limited to local books for pubtype book.
     *
     * {@inheritDoc}
     * @see \Omeka\Controller\ApiController::getList()
     */
    public function getList()
    {
        $params = $this->params();
        $resource = $params->fromRoute('resource');

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $this->logger()->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
        }

        // Check if the search is made via api called by app for items or sites.
        switch ($resource) {
            case 'items':
                if (!$this->isRequestedWithVatiruLibraryApp($params)) {
                    return parent::getList();
                }
                break;
            case 'sites':
                if (!$this->isRequestedWithVatiruLibraryApp($params)) {
                    return parent::getList();
                }
                $this->paginator->setPerPage($this->sitePerPage);
                break;
            default:
                return parent::getList();
        }

        // Here, this is an app request for items or sites.

        // In case of a search, the most relevant results are first.
        // It allows to manage external searches.
        // TODO Manage mix of already downloaded results and new results.
        $search = $params->fromQuery('search');
        $isBook = $this->getPluginManager()->has('apiSearch')
            && $params->fromQuery('pubtype') === 'book';
        if (!$isBook) {
            if ($search) {
                // This is the default for the api.
                $this->setBrowseDefaults('id', 'asc');
            } else {
                // Set the same default order than web public view (site controller).
                $this->setBrowseDefaults('created');
            }
        }

        // Note: The query was updated by the browse defaults event.
        $query = $params->fromQuery();
        $query = $this->adaptSearchQueryForApp($resource, $query, $isBook);

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $this->logger()->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
            $this->logger()->debug(json_encode($query, 320));
        }

        // If there is an external search engine (Solr), use it.
        // TODO Use a different index for app?
        // apiSearch() everywhere: no more distinction between book and article.
        $response = $this->apiSearch($resource, $query);
        if ($isBook) {
            if (!empty($GLOBALS['globalIsTest'])) {
                $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
                $this->logger()->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
            }
        } else {
            if (!empty($GLOBALS['globalIsTest'])) {
                $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
                $this->logger()->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
            }
        }

        // Copy of the parent method.

        $this->paginator->setCurrentPage($query['page']);
        $this->paginator->setTotalCount($response->getTotalResults());

        // Add Link header for pagination.
        $links = [];
        $pages = [
            'first' => 1,
            'prev' => $this->paginator->getPreviousPage(),
            'next' => $this->paginator->getNextPage(),
            'last' => $this->paginator->getPageCount(),
        ];
        foreach ($pages as $rel => $page) {
            if ($page) {
                $query['page'] = $page;
                $url = $this->url()->fromRoute(null, [],
                    ['query' => $query, 'force_canonical' => true], true);
                $links[] = sprintf('<%s>; rel="%s"', $url, $rel);
            }
        }

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $this->logger()->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
        }

        $this->getResponse()->getHeaders()
            ->addHeaderLine('Link', implode(', ', $links));
        return new ApiJsonModel($response, $this->getViewOptions());
    }

    protected function adaptSearchQueryForApp($resource, array $query, $isBook = false)
    {
        switch ($resource) {
            case 'items':
/*
+>>>>>>> 3f5557b... TEST Returned all ebooks to app.
                // In all cases, no external ebooks on app.
                // They are named "book" or "ebook".
                $property = [
                    'joiner' => 'and',
                    'property' => 'dcterms:type',
                    'type' => 'neq',
                    'text' => 'ebsco: Book',
                ];
                $query['property'][] = $property;
                $property = [
                    'joiner' => 'and',
                    'property' => 'dcterms:type',
                    'type' => 'neq',
                    'text' => 'ebsco: eBook',
                ];
                $query['property'][] = $property;
                // Because property above may not work with Solr api search.
                if ($isBook) {
                    $property = [
                        'joiner' => 'and',
                        'property' => 'vatiru:isExternal',
                        'type' => 'neq',
                        'text' => '1',
                    ];
                    $query['property'][] = $property;
                }
*/
                // The site is added globally, via the event api.search.pre.
                break;
            case 'sites':
                $query['per_page'] = $this->sitePerPage;
                break;
        }
        return $query;
    }
}
