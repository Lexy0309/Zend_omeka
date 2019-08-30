<?php
namespace DownloadManager\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use DownloadManager\Api\Representation\DownloadLogRepresentation;
use DownloadManager\Entity\DownloadLog;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class DownloadLogAdapter extends AbstractEntityAdapter
{
    use QueryBuilderTrait;

    protected $sortFields = [
        'id' => 'id',
        'status' => 'status',
        'resource_id' => 'resource_id',
        'owner_id' => 'owner_id',
        'expire' => 'expire',
        'hash' => 'hash',
        'salt' => 'salt',
        'created' => 'created',
        'modified' => 'modified',
        // For info.
        // 'owner_name' => 'owner',
        // // 'resource_title' => 'resource',
    ];

    public function getResourceName()
    {
        return 'download_logs';
    }

    public function getRepresentationClass()
    {
        return DownloadLogRepresentation::class;
    }

    public function getEntityClass()
    {
        return DownloadLog::class;
    }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        switch ($request->getOperation()) {
            case Request::CREATE:
                $data = $request->getContent();
                $entity->setId($data['o-module-access:download']['o:id']);
                $entity->setStatus($data['o:status']);
                $entity->setResourceIdentifier($data['o:resource']['o:id']);
                $entity->setOwnerId($data['o:owner']['o:id']);
                $entity->setExpire($data['o-module-access:expire']);
                $entity->setLog($data['o-module-access:log']);
                $entity->setHash($data['o-module-access:hash']);
                $entity->setHashPassword($data['o-module-access:hash_password']);
                $entity->setSalt($data['o-module-access:salt']);
                $entity->setCreated($data['o:created']);
                $entity->setModified($data['o:modified']);
                break;
        }
    }

    public function validateRequest(Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();

        switch ($request->getOperation()) {
            case Request::CREATE:
                if (empty($data['o-module-access:download']['o:id'])) {
                    $errorStore->addError(
                        'o-module-access:download',
                        'The Download to log is not defined.'); // @translate
                }
                $status = empty($data['o:status']) ? null : $data['o:status'];
                if (empty($status)) {
                    $errorStore->addError(
                        'o:status',
                        'The status of the Download to log is not defined.'); // @translate
                }
                if (empty($data['o:resource']['o:id'])) {
                    $errorStore->addError(
                        'o:resource',
                        'The resource of the Download to log is not defined.'); // @translate
                }
                if (empty($data['o:owner']['o:id'])) {
                    $errorStore->addError(
                        'o:owner',
                        'The owner of the Download to log is not defined.'); // @translate
                }
                // if (!array_key_exists('o-module-access:expire', $data)) {
                //     $errorStore->addError(
                //         'o-module-access:expire',
                //         'The expiration of the Download to log is not set.'); // @translate
                // }
                if (empty($data['o-module-access:hash'])) {
                    $errorStore->addError(
                        'o-module-access:hash',
                        'The hash of the Download to log is not defined.'); // @translate
                }
                if (empty($data['o-module-access:hash_password'])) {
                    $errorStore->addError(
                        'o-module-access:hash_password',
                        'The hash of the password of the Download to log is not defined.'); // @translate
                }
                if (empty($data['o-module-access:salt'])) {
                    $errorStore->addError(
                        'o-module-access:salt',
                        'The salt of the Download to log is not defined.'); // @translate
                }
                if (empty($data['o:created'])) {
                    $errorStore->addError(
                        'o:created',
                        'The date of creation of the Download to log is not defined.'); // @translate
                }
                if (!array_key_exists('o:modified', $data)) {
                    $errorStore->addError(
                        'o:modified',
                        'The modification date of the Download to log is not set.'); // @translate
                }
                break;

            case Request::UPDATE:
                $errorStore->addError(
                    'o-module-access:download',
                    'The Download log is not updatable.'); // @translate
                break;

            case Request::DELETE:
                $errorStore->addError(
                    'o-module-access:download',
                    'The Download log is not deletable.'); // @translate
                break;
        }
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (array_key_exists('id', $query)) {
            $this->buildQueryIdsItself($qb, $query['id'], 'id');
        }

        if (isset($query['download-id'])) {
            $this->buildQueryValuesItself($qb, $query['download-id'], 'download-id');
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
            'resource_id' => 'resource_id',
            'owner_id' => 'owner_id',
            'item_set_id' => 'resource_id',
            'item_id' => 'resource_id',
            'media_id' => 'resource_id',
        ] as $queryKey => $column) {
            if (array_key_exists($queryKey, $query)) {
                $this->buildQueryIds($qb, $query[$queryKey], $column, 'id');
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function sortQuery(QueryBuilder $qb, array $query)
    {
        if (is_string($query['sort_by'])) {
            switch ($query['sort_by']) {
                case 'owner_name':
                    $alias = $this->createAlias();
                    $qb->leftJoin('DownloadManager\Entity\DownloadLog.owner', $alias)
                        ->addOrderBy($alias . '.name', $query['sort_order']);
                    break;
                default:
                    parent::sortQuery($qb, $query);
                    break;
            }
        }
    }
}
