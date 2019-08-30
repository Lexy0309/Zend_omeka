<?php
namespace External\Mvc\Controller\Plugin;

use Omeka\Stdlib\Message;
use Zend\Http\Client;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Controller\PluginManager;

class SearchExternal extends AbstractPlugin
{
    /**
     * @var string
     */
    protected $requestType = \Zend\Http\Request::METHOD_POST;

    /**
     * Max pagination per page for the external search engine.
     *
     * @var int
     */
    protected $maxPaginationPerPageExternal = 100;

    /**
     * Max page number for the external search engine, according to the
     * max pagination per page.
     *
     * The maximum of results is 300 (api MaxRecordJumpAhead is 250 and max
     * result per page is 100). So modify the page number if needed.
     *
     * @var int
     */
    protected $maxNumberOfPagesExternal = 3;

    /**
     * Max number of pages to merge.
     *
     * Warning:
     * To use this feature, maxPaginationPerPageExternal should be the highest
     * allowed (100 for ebsco).
     *
     * Furthermore, it implies a lot of memory, so it should be set in php.ini.
     *
     * @var int
     */
    protected $maxPagesToMerge = 1;

    /**
     * @var Client
     */
    protected $httpClient;

    /**
     * @var PluginManager
     */
    protected $plugins;

    /**
     * @param Client $httpClient
     * @param PluginManager $plugins
     */
    public function __construct(Client $httpClient, PluginManager $plugins)
    {
        $this->httpClient = $httpClient;
        $this->plugins = $plugins;
        $settings = $plugins->get('settings');
        $settings = $settings();
        $this->maxPaginationPerPageExternal = (int) $settings->get('external_pagination_per_page') ?: $this->maxPaginationPerPageExternal;
        $this->maxNumberOfPagesExternal = (int) $settings->get('external_number_of_pages') ?: $this->maxNumberOfPagesExternal;
    }

    /**
     * Get items from an external search engine.
     *
     * For Ebsco, the documentation is not public, neither the open source code:
     * everything should be requested to the support, and it may accept or not.
     *
     * When there is no search string, Ebsco doesn't return anything, so a
     * mitigation is currently done with a search on "e", that is the most
     * frequent letter.
     * TODO Ebsco search without query (get all results).
     *
     * If there is a low level of output results, all the results are returned
     * in one time (so multiple queries are done and merged). This is the case
     * with ebsco, where results are limited to 300.
     *
     * @see vufind/module/VuFindSearch/src/VuFindSearch/Backend/EDS/Base.php
     *
     * @param array $request The api params part of the Omeka request.
     * @return array External items.
     */
    public function __invoke(array $request)
    {
        // If there is a low level of output results, get all of them.
        if ($this->maxPagesToMerge >= $this->maxNumberOfPagesExternal) {
            // Return the results only for the first page.
            // TODO The calculation of the number of pages should be fixed for external query.
            $page = isset($request['page']) ? (int) $request['page'] : 1;
            if ($page > 1) {
                return [];
            }

            $result = [];
            // Checks avoid useless queries.
            for ($page = 1; $page <= $this->maxPagesToMerge; $page++) {
                if ($page === 1) {
                    $result = $this->processOneQuery($request, $page);
                    if (empty($result)
                        || empty($result['SearchResult']['Data']['Records'])
                        || empty($result['SearchResult']['Statistics']['TotalHits'])
                    ) {
                        break;
                    }
                    $totalRecords = count($result['SearchResult']['Data']['Records']);
                    if ($totalRecords < $this->maxPaginationPerPageExternal) {
                        break;
                    }
                    $totalResults = $result['SearchResult']['Statistics']['TotalHits'];
                    if ($totalRecords >= $totalResults) {
                        break;
                    }
                } else {
                    $r = $this->processOneQuery($request, $page);
                    if (empty($r)
                        || empty($r['SearchResult']['Data']['Records'])
                        || empty($r['SearchResult']['Statistics']['TotalHits'])
                    ) {
                        break;
                    }
                    $result['SearchResult']['Data']['Records'] = array_merge(
                        $result['SearchResult']['Data']['Records'],
                        $r['SearchResult']['Data']['Records']
                    );
                    $totalRecords = count($r['SearchResult']['Data']['Records']);
                    if (count($r['SearchResult']['Data']['Records']) < $this->maxPaginationPerPageExternal) {
                        break;
                    }
                    $totalRecords = count($result['SearchResult']['Data']['Records']);
                    if ($totalRecords >= $totalResults) {
                        break;
                    }
                }
            }
            // TODO Cache the merged results.
        } else {
            $result = $this->processOneQuery($request);
        }
        return $result;
    }

    protected function processOneQuery(array $request, int $page = null)
    {
        $plugins = $this->plugins;
        $settings = $plugins->get('settings');
        $settings = $settings();

        $query = $this->convertParamsToQuery($request);
        if (empty($query)) {
            return [];
        }

        $paginationPerPage = (int) $settings->get('pagination_per_page');
        // Ebsco is limited to 100 results per page. The max avoids queries.
        $paginationPerPageExternal = $this->maxPaginationPerPageExternal;
        $query['RetrievalCriteria']['ResultsPerPage'] = $paginationPerPageExternal;

        $isParameterPage = is_null($page);
        if ($isParameterPage) {
            $requestedPage = isset($request['page']) ? (int) $request['page'] : 1;
        } else {
            $requestedPage = $page;
        }

        // Check if the result is cached.
        /** @var \External\Mvc\Controller\Plugin\CacheExternal $cacheExternal */
        $cacheExternal = $plugins->get('cacheExternal');
        $cacheExternal = $cacheExternal();
        $cached = $cacheExternal->cachedQuery($query, $requestedPage);
        if ($cached) {
            return $cached;
        }

        // Determine the external page number.
        if ($isParameterPage) {
            /** @var \External\Mvc\Controller\Plugin\ExternalSearchPage $externalSearchPage */
            $externalSearchPage = $plugins->get('externalSearchPage');
            $pageNumber = $externalSearchPage($query, $requestedPage, $paginationPerPage, $paginationPerPageExternal);
            if (empty($pageNumber)) {
                return [];
            }
        } else {
            $pageNumber = $requestedPage;
        }
        $pageNumber = min($pageNumber, $this->maxNumberOfPagesExternal);
        $query['RetrievalCriteria']['PageNumber'] = $pageNumber;

        $result = $this->processQuery($query);

        // Cache result for next page.
        $cacheExternal->cacheQuery($query, $requestedPage, $result);

        return $result;
    }

    /**
     * Convert Omeka search params into Ebsco ones, except page number.
     *
     * @param array $params
     * @return array
     */
    protected function convertParamsToQuery(array $params)
    {
        $plugins = $this->plugins;
        $settings = $plugins->get('settings');
        $settings = $settings();

        // Create query.
        // TODO Manage complex queries from Omeka to Ebsco ? via Vufind ?
        $query = [];
        $queries = [];

        $queries[] = ['BooleanOperator' => 'And', 'Term' => empty($params['search']) ? 'e' : $params['search']];

        $pubtype = empty($params['pubtype']) ? null : $params['pubtype'];
        // Books are not yet managed.
        switch ($pubtype) {
            case 'book':
                $queries[] = [
                    'BooleanOperator' => 'And',
                    'Term' => 'PT eBook',
                ];
                break;
            case 'article':
            // TODO Remove "articles", that was a mistake in app before version 32.
            case 'articles':
                // See http://edswiki.ebscohost.com/Field_Codes
                $queries[] = [
                    'BooleanOperator' => 'And',
                    'Term' => '(PT Article OR PT Journal Article OR ZT Magazines OR PT Serial OR PT Trade OR PT Periodical OR ZT Periodical OR PT News OR ZT News OR PT Newspaper OR PT Academic Journal OR PT Journal OR ZT Academic Journal OR PT Conference Materials OR RV Y)',
                ];
                break;
            case 'academic':
                // See http://edswiki.ebscohost.com/Field_Codes
                $queries[] = [
                    'BooleanOperator' => 'And',
                    'Term' => '(PT Journal Article OR PT Academic Journal OR PT Journal OR ZT Academic Journal OR PT Conference Materials OR RV Y)',
                ];
                break;
            case 'news':
                // See http://edswiki.ebscohost.com/Field_Codes
                $queries[] = [
                    'BooleanOperator' => 'And',
                    'Term' => '(PT Article OR ZT Magazines OR PT Serial OR PT Trade OR PT Periodical OR ZT Periodical OR PT News OR ZT News OR PT Newspaper)',
                ];
                break;
            case 'all':
            default:
                break;
        }

        if ($pubtype !== 'book') {
            $filters = $settings->get('external_ebsco_filter', []);
            if (in_array('pdf', $filters)) {
                $queries[] = ['BooleanOperator' => 'And', 'Term' => 'FM P'];
            }
            if (in_array('ebook', $filters)) {
                $queries[] = ['BooleanOperator' => 'And', 'Term' => 'PT ebook'];
            }
            if (in_array('fulltext', $filters)) {
                $queries[] = ['BooleanOperator' => 'And', 'Term' => 'FT y'];
            }
        }

        $query['SearchCriteria'] = [
            'Queries' => $queries,
            // May be "all" (default), "any", "bool" or "smart".
            'SearchMode' => 'all',
            'IncludeFacets' => 'n',
            'Sort' => 'relevance',
            'AutoSuggest' => 'n',
            'AutoCorrect' => 'n',
        ];
        $query['RetrievalCriteria'] = [
            'View' => 'detailed',
            'ResultsPerPage' => $this->maxPaginationPerPageExternal,
            'PageNumber' => 1,
            'Highlight' => 'n',
            'IncludeImageQuickView' => 'y',
        ];
        $query['Actions'] = null;
        // Don't search full text: it won't be available in Omeka, so the item
        // won't be searchable.
        // Limit and expand to full text.
        // $query['limiter'] = 'FT:y';
        // $query['expander'] = 'fulltext';

        // According to docs, "last one processed overrides the previous" (?).
        // TODO Parse query parameters for post.
        $queryParameters = $settings->get('external_ebsco_query_parameters');
        if ($queryParameters) {
            $parsedQueryParameters = [];
            parse_str($queryParameters, $parsedQueryParameters);
            // $query += $parsedQueryParameters;
        }
        return $query;
    }

    /**
     * Process an external search query.
     *
     * @param array $query
     * @return array
     */
    protected function processQuery(array $query)
    {
        $plugins = $this->plugins;

        $authenticateExternal = $plugins->get('authenticateExternal');
        $authentication = $authenticateExternal('ebsco');
        if (empty($authentication)) {
            return [];
        }

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Accept-Encoding' => 'gzip,deflate',
        ];
        $headers += $authentication;

        $client = $this->httpClient;
        $client->resetParameters();
        $client->setHeaders($headers);
        $client->setEncType('application/json; charset=utf-8');

        if ($this->requestType === \Zend\Http\Request::METHOD_POST) {
            $client->setMethod($this->requestType);
            $client->setRawBody(json_encode($query));
        } else {
            $query = $this->convertQueryToGet($query);
            $client->setParameterGet($query);
        }

        $edsApiHost = 'https://eds-api.ebscohost.com/edsapi/rest';
        $client->setUri($edsApiHost . '/search');

        $response = $client->send();
        $result = json_decode($response->getBody(), true);
        if (!$response->isSuccess()) {
            // throw new \SearchExternalException($response);
            $logger = $plugins->get('logger');
            $logger()->err(new Message('External search error (%s): %s',  // @translate
                $result['ErrorNumber'], $result['DetailedErrorDescription']));
            return [];
        }

        return $result;
    }

    /**
     * Convert the query to the format required for a post for ebsco.
     *
     * @param array $query
     * @return object
     */
    protected function convertQueryToPost(array $query)
    {
        $result = [];

        $queries = [];
        $queries[] = ['Term' => $query['query-1']];
        if (isset($query['query-2'])) {
            $queries[] = ['Term' => $query['query-2']];
        }
        if (isset($query['query-3'])) {
            $queries[] = ['Term' => $query['query-3']];
        }

        $result['SearchCriteria'] = [
            'Queries' => $queries,
            'SearchMode' => $query['searchmode'],
            'IncludeFacets' => $query['includefacets'],
            'Sort' => $query['sort'],
            'AutoSuggest' => $query['autosuggest'],
            'AutoCorrect' => $query['autocorrect'],
        ];
        $result['RetrievalCriteria'] = [
            'View' => $query['view'],
            'ResultsPerPage' => $query['resultsperpage'],
            'PageNumber' => $query['pagenumber'],
            'Highlight' => $query['highlight'],
            'IncludeImageQuickView' => $query['includeimagequickview'],
        ];
        $result['Actions'] = null;

        return $result;
    }

    /**
     * Convert the query to the format required for a get for ebsco.
     *
     * @todo Create the query as full standard array, and convert to get if needed (json_encode create object automatically).
     *
     * @param array $query
     * @return object
     */
    protected function convertQueryToGet(array $query)
    {
        // TODO Convert query to get.
        $result = [];
        return $result;
    }
}
