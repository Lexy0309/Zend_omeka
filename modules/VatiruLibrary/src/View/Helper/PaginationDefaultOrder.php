<?php
namespace VatiruLibrary\View\Helper;

use Omeka\View\Helper\Pagination;

/**
 * View helper for rendering pagination without sort order.
 */
class PaginationDefaultOrder extends Pagination
{
    /**
     * @var array
     */
    protected $query;

    public function __invoke($partialName = null, $totalCount = null, $currentPage = null,
        $perPage = null
    ) {
        $this->paginator = $this->getView()->pagination()->getPaginator();
        $this->query = $this->getView()->params()->fromQuery();
        unset($this->query['sort_by']);
        unset($this->query['sort_order']);

        return parent::__invoke($partialName, $totalCount, $currentPage, $perPage);
    }

    public function __toString()
    {
        $paginator = $this->getPaginator();

        // Page count
        $pageCount = $paginator->getPageCount();

        // Current page number cannot be more than page count
        if ($paginator->getCurrentPage() > $pageCount) {
            $paginator->setCurrentPage($pageCount);
        }

        return $this->getView()->partial(
            $this->partialName,
            [
                'totalCount' => $paginator->getTotalCount(),
                'perPage' => $paginator->getPerPage(),
                'currentPage' => $paginator->getCurrentPage(),
                'previousPage' => $paginator->getPreviousPage(),
                'nextPage' => $paginator->getNextPage(),
                'pageCount' => $pageCount,
                'query' => $this->query,
                'firstPageUrl' => $this->getUrl(1),
                'previousPageUrl' => $this->getUrl($paginator->getPreviousPage()),
                'nextPageUrl' => $this->getUrl($paginator->getNextPage()),
                'lastPageUrl' => $this->getUrl($pageCount),
                'pagelessUrl' => $this->getPagelessUrl(),
                'offset' => $paginator->getOffset(),
            ]
        );
    }

    protected function getUrl($page)
    {
        $query = $this->query;
        $query['page'] = (int) $page;
        $options = ['query' => $query];
        if (is_string($this->fragment)) {
            $options['fragment'] = $this->fragment;
        }
        return $this->getView()->url(null, [], $options, true);
    }

    protected function getPagelessUrl()
    {
        $query = $this->query;
        unset($query['page']);
        $options = ['query' => $query];
        if (is_string($this->fragment)) {
            $options['fragment'] = $this->fragment;
        }
        return $this->getView()->url(null, [], $options, true);
    }
}
