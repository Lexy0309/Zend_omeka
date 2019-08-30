<?php
namespace External\Mvc\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Session\Container;

class CacheExternal extends AbstractPlugin
{
    /**
     * Expiration seconds (one day by default: databases are updated the night
     * or once a week).
     *
     * @var int
     */
    protected $expirationSeconds = 86400;

    /**
     * Session cache for the results of queries, by page.
     *
     * @var Container
     */
    protected $sessionContainer;

    /**
     * @param Container $sessionContainer
     */
    public function __construct(Container $sessionContainer)
    {
        $this->sessionContainer = $sessionContainer;
    }

    /**
     * Manage the cache of the queries.
     *
     * @return self
     */
    public function __invoke()
    {
        return $this;
    }

    /**
     * Cache the result of a query for a page.
     *
     * @param array $query Query without page number.
     * @param int $requestedPage Requested page for internal search.
     * @param mixed $result
     */
    public function cacheQuery(array $query, int $requestedPage, $result)
    {
        unset($query['pagenumber']);
        $jsonQuery = json_encode($query, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $md5Query = md5($jsonQuery);

        $container = $this->sessionContainer;
        if (!isset($container->queries)) {
            $container->queries = [];
        }
        $container->queries[$md5Query][$requestedPage] = [
            'time' => time(),
            'result' => $result,
        ];
    }

    /**
     * Get the cached result for a query and a page.
     *
     * @param array $query Query without page number.
     * @param int $requestedPage Requested page for internal search, else all.
     * @return array|null
     */
    public function cachedQuery(array $query, int $requestedPage = null)
    {
        unset($query['pagenumber']);
        $jsonQuery = json_encode($query, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $md5Query = md5($jsonQuery);

        $container = $this->sessionContainer;
        if (!isset($container->queries)) {
            return null;
        }

        if (empty($container->queries[$md5Query])) {
            return null;
        }

        // Return null if one of the results for the query time is too old.
        $return = [];
        $time = time();
        foreach ($container->queries[$md5Query] as $page => $result) {
            if (($result['time'] + $this->expirationSeconds) < $time) {
                unset($container->queries[$md5Query]);
                return null;
            }
            $return[$page] = $result['result'];
        }

        // Return all pages.
        if (is_null($requestedPage)) {
            return $return;
        }

        // Return one page only.
        return isset($return[$requestedPage])
            ? $return[$requestedPage]
            : null;
    }
}
