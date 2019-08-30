<?php
namespace External\Mvc\Controller\Plugin;

use Omeka\Stdlib\Message;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Controller\PluginManager;

class ExternalSearchPage extends AbstractPlugin
{
    /**
     * Disable cache (mainly for debug).
     * @var bool
     */
    protected $disableCache = false;

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
     * Determine the page number to search externally.
     *
     * @param array $query The specific query for the external search engine,
     * prepared from the params of the query for Omeka.
     * @param int $requestedPage Requested page number for the internal search.
     * @param int $paginationPerPageLocal Number of results for internal search.
     * @param int $paginationPerPageExternal Number of results for external search.
     * @return int
     */
    public function __invoke(
        array $query,
        int $requestedPage,
        int $paginationPerPageLocal,
        int $paginationPerPageExternal
    ) {
        if (empty($query) || empty($paginationPerPageExternal)) {
            return 0;
        }

        if ($requestedPage <= 1) {
            return 1;
        }

        if ($this->disableCache) {
            return $paginationPerPageExternal <= $paginationPerPageLocal
                ? $requestedPage
                : (int) ceil($requestedPage * ($paginationPerPageLocal / $paginationPerPageExternal));
        }

        $cacheExternal = $this->plugins->get('cacheExternal');
        $cached = $cacheExternal()->cachedQuery($query);

        // If no query are cached, return the first.
        if (empty($cached)) {
            return 1;
        }

        // Forbid to go to next pages before the first.
        // TODO Check if the call to next pages before the first can be allowed.
        if (empty($cached[1])) {
            return 1;
        }

        $first = $cached[1];

        // The total of results may change when a higher page is requested.
        // TODO Check if the total of results changed between results (this is common).
        $totalResultsExternal = $first['SearchResult']['Statistics']['TotalHits'];

        // If no results, don't do another search.
        if (empty($totalResultsExternal)) {
            return 0;
        }

        // If number of results is lower than pagination, don't do a new search.
        if ($totalResultsExternal <= $paginationPerPageExternal) {
            return 0;
        }

        // Check if all results were fetched.
        $fetchedTotalResults = 0;
        foreach ($cached as $result) {
            $fetchedTotalResults += count($result['SearchResult']['Data']['Records']);
        }
        if ($fetchedTotalResults >= $totalResultsExternal) {
            return 0;
        }

        // Here, some results are not fetched and $page contains the last page.
        // TODO Ideally, the total of local results should be taken in account, but it is probably useless.
        // For now, just evaluate with the number of results by page, because
        // results are fetched locally and the full total is managed too.
        if ($paginationPerPageExternal === $paginationPerPageLocal) {
            $remotePage = $requestedPage;
        } elseif ($paginationPerPageExternal > $paginationPerPageLocal) {
            $remotePage = (int) ceil($requestedPage * ($paginationPerPageLocal / $paginationPerPageExternal));
        } else {
            // This case is not managed: the local pagination should be lower
            // than the remote pagination.
            $logger = $this->plugins->get('logger');
            $logger()->warn(new Message('Some results might miss: The pagination per page of the external search (%d) should be equal or greater than the local one (%d).',  // @translate
                $paginationPerPageExternal, $paginationPerPageLocal));
            $remotePage = $requestedPage;
        }

        // Check if the external page exists.
        if (($remotePage - 1) * $paginationPerPageExternal >= $totalResultsExternal) {
            return 0;
        }

        return $remotePage;
    }
}
