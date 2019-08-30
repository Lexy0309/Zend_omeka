<?php
namespace DownloadManager\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\Query\Expr\Join;
use DownloadManager\Api\Representation\DownloadRepresentation;
use DownloadManager\Entity\Download;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\Resource;
use Omeka\Entity\User;
use Omeka\Stdlib\ErrorStore;
use Omeka\Stdlib\Message;

class DownloadAdapter extends AbstractEntityAdapter
{
    use QueryBuilderTrait;

    protected $sortFields = [
        'id' => 'id',
        'status' => 'status',
        'resource_id' => 'resource',
        'owner_id' => 'owner',
        'expire' => 'expire',
        'hash' => 'hash',
        'salt' => 'salt',
        'created' => 'created',
        'modified' => 'modified',
        // For info.
        // 'owner_name' => 'owner',
        // // 'resource_title' => 'resource',
    ];

    protected $statuses = [
        Download::STATUS_READY,
        Download::STATUS_HELD,
        Download::STATUS_DOWNLOADED,
        Download::STATUS_PAST,
    ];

    public function getResourceName()
    {
        return 'downloads';
    }

    public function getRepresentationClass()
    {
        return DownloadRepresentation::class;
    }

    public function getEntityClass()
    {
        return Download::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        // TODO array_key_exists() works fine to search null, etc., but harder to manage in view. 0 is null, but "" may be a string.
        // TODO A simple isset in fact if we use 0 as null in the view or api. So check all queries.
        if (array_key_exists('id', $query)) {
            $this->buildQueryIdsItself($qb, $query['id'], 'id');
        }

        if (isset($query['status'])) {
            $query['status'] = is_array($query['status'])
                ? $query['status']
                : array_filter(array_map('trim', explode(',', $query['status'])));
            $this->buildQueryValuesItself($qb, $query['status'], 'status');
        }

        if (isset($query['hash'])) {
            $this->buildQueryValuesItself($qb, $query['hash'], 'hash');
        }

        if (isset($query['salt'])) {
            $this->buildQueryValuesItself($qb, $query['salt'], 'salt');
        }

        // All downloads with any entities ("OR"). If multiple, mixed with "AND".
        foreach ([
            'resource_id' => 'resource',
            'owner_id' => 'owner',
            'item_set_id' => 'resource',
            'item_id' => 'resource',
            'media_id' => 'resource',
        ] as $queryKey => $column) {
            if (array_key_exists($queryKey, $query)) {
                $this->buildQueryIds($qb, $query[$queryKey], $column, 'id');
            }
        }

        // Join site via owners: all users attached to site permissions.
        if (array_key_exists('site_id', $query)) {
            $alias = $this->createAlias();
            $qb->innerJoin(
                \Omeka\Entity\SitePermission::class,
                $alias,
                Join::WITH,
                $alias . '.user = ' . $this->getEntityClass() . '.owner'
            );
            $qb->andWhere($qb->expr()->eq(
                $alias . '.site',
                $this->createNamedParameter($qb, $query['site_id']))
            );
        }

        // Join group via owners.
        if (array_key_exists('group', $query)) {
            $groupUserAlias = $this->createAlias();
            $qb->innerJoin(
                \Group\Entity\GroupUser::class,
                $groupUserAlias,
                Join::WITH,
                $groupUserAlias . '.user = ' . $this->getEntityClass() . '.owner'
            );
            $groupAlias = $this->createAlias();
            $qb->innerJoin(
                \Group\Entity\Group::class,
                $groupAlias,
                Join::WITH,
                $groupAlias . '.id = ' . $groupUserAlias . '.group'
            );
            // TODO Manage multiple Groups to search download.
            $qb->andWhere($qb->expr()->eq(
                $groupAlias . '.name',
                $this->createNamedParameter($qb, $query['group']))
            );
        }

        if (isset($query['created']) && strlen($query['created'])) {
            $this->buildQueryDateComparison($qb, $query, $query['created'], 'created');
        }

        if (isset($query['modified']) && strlen($query['modified'])) {
            $this->buildQueryDateComparison($qb, $query, $query['modified'], 'modified');
        }
    }

    public function sortQuery(QueryBuilder $qb, array $query)
    {
        if (is_string($query['sort_by'])) {
            switch ($query['sort_by']) {
                case 'owner_name':
                    $alias = $this->createAlias();
                    $qb->leftJoin('DownloadManager\Entity\Download.owner', $alias)
                        ->addOrderBy($alias . '.name', $query['sort_order']);
                    break;
                default:
                    parent::sortQuery($qb, $query);
                    break;
            }
        }
    }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        $data = $request->getContent();

        switch ($request->getOperation()) {
            case Request::CREATE:
                $resource = $this->getResourceFromData($data);
                $entity->setResource($resource);

                // Note: hydrateOwner() is not usable, because a simple user has no
                // right to change owner and when created, the old owner is null, of
                // course, so it is always changed.
                $owner = $this->getOwnerFromData($data);
                $entity->setOwner($owner);

                $status = empty($data['o:status']) ? Download::STATUS_READY : $data['o:status'];
                $entity->setStatus($status);

                if (!empty($data['o-module-access:expire'])) {
                    $expire = $data['o-module-access:expire'];
                    $entity->setExpire($expire);
                }

                if (isset($data['o-module-access:log'])) {
                    $entity->setLog($data['o-module-access:log']);
                }

                $media = $this->getMediaForResource($resource);

                // The hash password is a copy of the hash, or the previous hash
                // in the case it was already downloaded. It allows the user to
                // keep the previous file in memory of his device and avoids to
                // download it one more time. Of course, this is less secure.
                // The same for the salt, used for the server key.

                // This rule doesn't apply in case of unique keys.
                $pluginManager = $this->getServiceLocator()->get('ControllerPluginManager');
                /** @var \DownloadManager\Mvc\Controller\Plugin\CreateMediaHash $createMediaHash */
                $createMediaHash = $pluginManager->get('createMediaHash');
                /** @var \DownloadManager\Mvc\Controller\Plugin\CreateServerSalt $createServerSalt */
                $createServerSalt = $pluginManager->get('createServerSalt');
                /** @var \DownloadManager\Mvc\Controller\Plugin\UseUniqueKeys $useUniqueKeys */
                // $useUniqueKeys = $pluginManager->get('useUniqueKeys');

                // TODO Now, all users use unique keys.
                // $useUniqueKeys = $useUniqueKeys($owner);
                $useUniqueKeys = true;
                if ($useUniqueKeys) {
                    $hash = $createMediaHash($media);
                    $hashPassword = $hash;
                    $salt = $createServerSalt(true);
                } else {
                    $previous = $this->findPreviousDownload($resource, $owner);
                    $hash = $createMediaHash($media, $owner);
                    if ($previous) {
                        $hashPassword = $previous->getHashPassword();
                        $salt = $previous->getSalt();
                    } else {
                        $hashPassword = $hash;
                        $salt = $createServerSalt(false);
                    }
                }

                $entity->setHash($hash);
                $entity->setHashPassword($hashPassword);
                $entity->setSalt($salt);
                break;

            case Request::UPDATE:
                // Only status and log are updatable. Expiration is updatable
                // only when the status is updated to downloaded.
                $status = empty($data['o:status']) ? Download::STATUS_READY : $data['o:status'];
                $currentStatus = $entity->getStatus();
                if ($this->shouldHydrate($request, 'o:status')) {
                    if ($status !== $currentStatus) {
                        switch ($status) {
                            // It's not possible to go back when a resource was
                            // downloaded. Instead, expire it, then move it to log,
                            // remove it, and create a new one.
                            case Download::STATUS_READY:
                            case Download::STATUS_HELD:
                                if (!in_array($currentStatus, [Download::STATUS_DOWNLOADED, Download::STATUS_PAST])) {
                                    $entity->setStatus($status);
                                }
                                break;
                            case Download::STATUS_DOWNLOADED:
                                $entity->setStatus($status);
                                break;
                            case Download::STATUS_PAST:
                                if ($currentStatus === Download::STATUS_DOWNLOADED) {
                                    $entity->setStatus($status);
                                }
                                break;
                        }
                    }
                }

                // Extend the expriation only when the status is downloaded.
                if ($this->shouldHydrate($request, 'o-module-access:expire')) {
                    if ($status === Download::STATUS_DOWNLOADED
                        || (empty($data['o:status']) && $currentStatus === Download::STATUS_DOWNLOADED)
                    ) {
                        $entity->setExpire($data['o-module-access:expire']);
                    }
                }

                if ($this->shouldHydrate($request, 'o-module-access:log')) {
                    $entity->setLog($request->getValue('o-module-access:log'));
                }
                break;
        }

        $this->updateTimestamps($request, $entity);
    }

    public function validateRequest(Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();

        if (isset($data['o:status'])) {
            $result = $this->validateStatus($data['o:status'], $errorStore);

            if ($result) {
                if ($data['o:status'] === Download::STATUS_DOWNLOADED) {
                    if (!isset($data['o-module-access:expire'])) {
                        $errorStore->addError(
                            'o-module-access:expire',
                            'The expiration date should be set when the resource is downloaded.' // @translate
                        );
                    }
                }
            }
        }

        $resource = null;
        $representation = null;
        $owner = null;
        $isDownloadable = false;
        // $hasRightToDownload = false;
        switch ($request->getOperation()) {
            case Request::CREATE:
                $controllerPluginManager = $this->getServiceLocator()->get('ControllerPluginManager');

                if (empty($data['o:status'])) {
                    $errorStore->addError(
                        'o:status',
                        'The status should be set when a download is prepared.'); // @translate
                } elseif ($data['o:status'] === Download::STATUS_PAST) {
                    $errorStore->addError(
                        'o:status',
                        'The status cannot be set to "past" when a download is prepared.'); // @translate
                }

                if (empty($data['o:resource'])) {
                    $errorStore->addError(
                        'o:resource',
                        'The item to be held or borrowed must be set.'); // @translate
                }
                // Validate the file of the resource.
                else {
                    $resource = $this->getResourceFromData($data);
                    if (empty($resource)) {
                        $errorStore->addError(
                            'o:resource',
                            'The item to hold or to borrow is not set.'); // @translate
                    } else {
                        $representation = $this->getResourceRepresentation($resource);
                        /** @var \DownloadManager\Mvc\Controller\Plugin\CheckResourceToDownload $checkResourceToDownload */
                        $checkResourceToDownload = $controllerPluginManager->get('checkResourceToDownload');
                        $isDownloadable = $checkResourceToDownload($representation, $errorStore);
                    }
                }

                if (empty($data['o:owner'])) {
                    $owner = $this->getServiceLocator()->get('Omeka\AuthenticationService')
                        ->getIdentity();
                    if (empty($owner)) {
                        $errorStore->addError(
                            'o:owner',
                            'The user who wants to hold or to borrow must be set.'); // @translate
                    } else {
                        // TODO Check if the owner is really the owner?

                        if ($isDownloadable) {
                            $resource = $resource ?: $this->getResourceFromData($data);
                            $representation = $representation ?: $this->getResourceRepresentation($resource);
                            $checkRightToDownload = $controllerPluginManager->get('checkRightToDownload');
                            // $hasRightToDownload = $checkRightToDownload($representation, $owner, $errorStore);
                            $checkRightToDownload($representation, $owner, $errorStore);
                        }
                    }
                }

                // Check if the user has already downloaded this resource.
                if ($resource && $owner) {
                    $representation = $representation ?: $this->getResourceRepresentation($resource);
                    $getCurrentDownload = $controllerPluginManager->get('getCurrentDownload');
                    $download = $getCurrentDownload($representation, $owner);
                    if ($download) {
                        $errorStore->addError(
                            'o:resource',
                            'The user has already borrowed this item.'); // @translate
                    }
                }
                break;

            case Request::UPDATE:
                // Currently, nothing is updatable, except the status and the
                // expiration.
                break;
        }
    }

    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore)
    {
        $status = $entity->getStatus();
        $this->validateStatus($status, $errorStore);

        // Validate uniqueness of Ready/Held/Downloaded, but allow many Expired.
        $resource = $entity->getResource();
        $owner = $entity->getOwner();
        if ($resource && $resource instanceof Resource
            && $owner && $owner instanceof User
        ) {
            if ($status !== Download::STATUS_PAST) {
                if (!$this->isUniqueCurrentDownload($entity)) {
                    $errorStore->addError('o-module-access:download', new Message(
                        'The user #%d has already held or borrowed the item #%d.', // @translate
                        $owner->getId(), $resource->getId()
                    ));
                }
            }
        } else {
            $errorStore->addError('o-module-access:download',
                'The user and the item to borrow must be set.'); // @translate
        }

        $hash = $entity->getHash();
        // TODO Check uniqueness of the hash (but this is a sha256).
        if (empty($hash)) {
            $errorStore->addError('o-module-access:download',
                'The hash of the link to download is not set.' // @translate
            );
        }

        $salt = $entity->getSalt();
        if (empty($salt)) {
            $errorStore->addError('o-module-access:download',
                'The salt of the download is not set.' // @translate
            );
        }

        $expire = $entity->getExpire();
        if ($status === Download::STATUS_DOWNLOADED && empty($expire)) {
            $errorStore->addError('o-module-access:download',
                'The expiration date should be set when the resource is downloaded.' // @translate
            );
        }
    }

    /**
     * Helper to get the resource from the request data.
     *
     * @param array $data
     * @return Resource|null
     */
    protected function getResourceFromData(array $data)
    {
        $resource = null;
        if (empty($data['o:resource'])) {
            // Nothing to do.
        } elseif (is_object($data['o:resource'])) {
            $resource = $data['o:resource'] instanceof Resource
                ? $data['o:resource']
                : null;
        } elseif (is_numeric($data['o:resource']['o:id'])) {
            $resource = $this->getAdapter('resources')
               ->findEntity($data['o:resource']['o:id']);
        }
        return $resource;
    }

    /**
     * Helper to get the owner from the request data.
     *
     * @param array $data
     * @return User|null
     */
    protected function getOwnerFromData(array $data)
    {
        $owner = null;
        if (isset($data['o:owner'])) {
            if (is_object($data['o:owner'])) {
                $owner = $data['o:owner'] instanceof User
                    ? $data['o:owner']
                    : null;
            } elseif (isset($data['o:owner']['o:id']) && is_numeric($data['o:owner']['o:id'])) {
                $owner = $this->getAdapter('users')
                    ->findEntity($data['o:owner']['o:id']);
            }
        } else {
            $owner = $this->getServiceLocator()->get('Omeka\AuthenticationService')
                ->getIdentity();
        }
        return $owner;
    }

    protected function validateStatus($status, ErrorStore $errorStore)
    {
        if (!in_array($status, $this->statuses, true)) {
            $errorStore->addError(
                'o:status',
                sprintf('The status "%s" is unknown.', $status)); // @translate
            return false;
        }
        return true;
    }

    public function delete(Request $request)
    {
        $this->logDownload($request);
        return parent::delete($request);
    }

    /**
     * Helper to log Download before processing deletion.
     *
     * TODO Check/process automatic cascade deletion when a resource or owner is removed.
     *
     * @param Request $request
     */
    protected function logDownload(Request $request)
    {
        $download = $this->findEntity(['id' => $request->getId()], $request);
        if ($download) {
            $api = $this->getServiceLocator()->get('Omeka\ApiManager');
            $api->create('download_logs', [
                'o-module-access:download' => ['o:id' => $download->getId()],
                'o:status' => $download->getStatus(),
                'o:resource' => ['o:id' => $download->getResource()->getId()],
                'o:owner' => ['o:id' => $download->getOwner()->getId()],
                'o-module-access:expire' => $download->getExpire(),
                'o-module-access:log' => $download->getLog(),
                'o-module-access:hash' => $download->getHash(),
                'o-module-access:hash_password' => $download->getHashPassword(),
                'o-module-access:salt' => $download->getSalt(),
                'o:created' => $download->getCreated(),
                'o:modified' => $download->getModified(),
            ]);
        }
    }

    /**
     * Check for uniqueness of a Download (resource, owner and status except
     * past).
     *
     * If the status is past, the download can be multiple, so return true.
     *
     * @see AbstractEntityAdapter::isUnique()
     *
     * @param Download $download
     * @return bool
     */
    protected function isUniqueCurrentDownload(Download $download)
    {
        $status = $download->getStatus();
        if ($status === Download::STATUS_PAST) {
            return true;
        }

        $resource = $download->getResource();
        $owner = $download->getOwner();
        if (empty($resource) || empty($owner)) {
            return true;
        }
        $criteria = [
            'resource' => $resource->getId(),
            'owner' => $owner->getId(),
        ];
        $entity = $download;

        $this->index = 0;
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('e.id')
            ->from($this->getEntityClass(), 'e');

        // Exclude the passed entity from the query if it has an persistent
        // indentifier.
        if ($entity->getId()) {
            $qb->andWhere($qb->expr()->neq(
                'e.id',
                $this->createNamedParameter($qb, $entity->getId())
            ));
        }

        foreach ($criteria as $field => $value) {
            $qb->andWhere($qb->expr()->eq(
                "e.$field",
                $this->createNamedParameter($qb, $value)
            ));
        }

        $qb
            ->andWhere($qb->expr()->neq('e.status', ':status'))
            ->setParameter('status', Download::STATUS_PAST);

        return null === $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Helper to find the previous past download.
     *
     * @param Resource $item
     * @param User $owner
     * @return Download
     */
    protected function findPreviousDownload(Resource $item, User $owner)
    {
        // Check if the item was already downloaded by the user.
        // TODO Use qb directly.
        $request = new Request(Request::SEARCH, $this->getResourceName());
        $request
            ->setContent([
                'owner_id' => $owner->getId(),
                'resource_id' => $item->getId(),
                // Because the hash password is set during the creation and cannot
                // be updated, only the past download is searched, whatever it was
                // (read, download, sample, etc.).
                'status' => [Download::STATUS_PAST],
                'sort_by' => 'id',
                'sort_order' => 'desc',
                'limit' => 1,
            ])
            ->setOption(['responseContent' => 'resource']);
        $response = $this->search($request);
        $previous = $response->getContent();
        return empty($previous) ? null : reset($previous);
    }

    /**
     * Helper to get the representation of a resource of Download.
     *
     * @param Resource $resource
     * @return AbstractResourceEntityRepresentation
     */
    protected function getResourceRepresentation(Resource $resource)
    {
        return $this
            ->getAdapter($resource->getResourceName())
            ->getRepresentation($resource);
    }

    /**
     * Helper to get the primary file of a resource.
     *
     * @param Resource $resource
     * @return MediaRepresentation|null
     */
    protected function getMediaForResource(Resource $resource)
    {
        $services = $this->getServiceLocator();
        $controllerPlugins = $services->get('ControllerPluginManager');
        $primaryOriginal = $controllerPlugins->get('primaryOriginal');
        $representation = $this->getResourceRepresentation($resource);
        return $primaryOriginal($representation, false);
    }

    /**
     * Add a comparison condition to query from a value containing an operator.
     *
     * @param QueryBuilder $qb
     * @param array $query
     * @param string $value
     * @param string $column
     */
    protected function buildQueryDateComparison(QueryBuilder $qb, array $query, $value, $column)
    {
        // TODO Format the date into a standard mysql datetime.
        $matches = [];
        preg_match('/^[^\d]+/', $value, $matches);
        if (!empty($matches[0])) {
            $operators = [
                '>=' => Comparison::GTE,
                '>' => Comparison::GT,
                '<' => Comparison::LT,
                '<=' => Comparison::LTE,
                '<>' => Comparison::NEQ,
                '=' => Comparison::EQ,
                'gte' => Comparison::GTE,
                'gt' => Comparison::GT,
                'lt' => Comparison::LT,
                'lte' => Comparison::LTE,
                'neq' => Comparison::NEQ,
                'eq' => Comparison::EQ,
                'ex' => 'IS NOT NULL',
                'nex' => 'IS NULL',
            ];
            $operator = trim($matches[0]);
            $operator = isset($operators[$operator])
                ? $operators[$operator]
                : Comparison::EQ;
            $value = substr($value, strlen($matches[0]));
        } else {
            $operator = Comparison::EQ;
        }
        $value = trim($value);

        // By default, sql replace missing time by 00:00:00, but this is not
        // clear for the user. And it doesn't allow partial date/time.
        // See module Advanced Search Plus.

        // $qb->andWhere(new Comparison(
        //     $this->getEntityClass() . '.' . $column,
        //     $operator,
        //     $this->createNamedParameter($qb, $value)
        // ));
        // return;

        $field = $this->getEntityClass() . '.' . $column;
        switch ($operator) {
            case Comparison::GT:
                if (strlen($value) < 19) {
                    $value = substr_replace('9999-12-31 23:59:59', $value, 0, strlen($value) - 19);
                }
                $param = $this->createNamedParameter($qb, $value);
                $predicateExpr = $qb->expr()->gt($field, $param);
                break;
            case Comparison::GTE:
                if (strlen($value) < 19) {
                    $value = substr_replace('0000-01-01 00:00:00', $value, 0, strlen($value) - 19);
                }
                $param = $this->createNamedParameter($qb, $value);
                $predicateExpr = $qb->expr()->gte($field, $param);
                break;
            case Comparison::EQ:
                if (strlen($value) < 19) {
                    $valueFrom = substr_replace('0000-01-01 00:00:00', $value, 0, strlen($value) - 19);
                    $valueTo = substr_replace('9999-12-31 23:59:59', $value, 0, strlen($value) - 19);
                    $paramFrom = $this->createNamedParameter($qb, $valueFrom);
                    $paramTo = $this->createNamedParameter($qb, $valueTo);
                    $predicateExpr = $qb->expr()->between($field, $paramFrom, $paramTo);
                } else {
                    $param = $this->createNamedParameter($qb, $value);
                    $predicateExpr = $qb->expr()->eq($field, $param);
                }
                break;
            case Comparison::NEQ:
                if (strlen($value) < 19) {
                    $valueFrom = substr_replace('0000-01-01 00:00:00', $value, 0, strlen($value) - 19);
                    $valueTo = substr_replace('9999-12-31 23:59:59', $value, 0, strlen($value) - 19);
                    $paramFrom = $this->createNamedParameter($qb, $valueFrom);
                    $paramTo = $this->createNamedParameter($qb, $valueTo);
                    $predicateExpr = $qb->expr()->not(
                        $qb->expr()->between($field, $paramFrom, $paramTo)
                        );
                } else {
                    $param = $this->createNamedParameter($qb, $value);
                    $predicateExpr = $qb->expr()->neq($field, $param);
                }
                break;
            case Comparison::LTE:
                if (strlen($value) < 19) {
                    $value = substr_replace('9999-12-31 23:59:59', $value, 0, strlen($value) - 19);
                }
                $param = $this->createNamedParameter($qb, $value);
                $predicateExpr = $qb->expr()->lte($field, $param);
                break;
            case Comparison::LT:
                if (strlen($value) < 19) {
                    $value = substr_replace('0000-01-01 00:00:00', $value, 0, strlen($value) - 19);
                }
                $param = $this->createNamedParameter($qb, $value);
                $predicateExpr = $qb->expr()->lt($field, $param);
                break;
            case 'IS NOT NULL':
                $predicateExpr = $qb->expr()->isNotNull($field);
                break;
            case 'IS NULL':
                $predicateExpr = $qb->expr()->isNull($field);
                break;
            default:
                return;
        }

        $qb->andWhere($predicateExpr);
    }
}
