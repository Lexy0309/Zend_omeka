<?php
namespace Ebsco\Media\Ingester;

use External\Mvc\Controller\Plugin\ImportThumbnail;
use Omeka\Api\Request;
use Omeka\Entity\Media;
use Omeka\File\Downloader;
use Omeka\Job\Dispatcher as JobDispatcher;
use Omeka\Media\Ingester\IngesterInterface;
use Omeka\Stdlib\ErrorStore;
use Zend\Form\Element\Url as UrlElement;
use Zend\Math\Rand;
use Zend\Uri\Http as HttpUri;
use Zend\View\Renderer\PhpRenderer;

class Ebsco implements IngesterInterface
{
    /**
     * @var Downloader
     */
    protected $downloader;

    /**
     * @var ImportThumbnail
     */
    protected $importThumbnail;

    /**
     * @var JobDispatcher
     */
    protected $jobDispatcher;

    /**
     * @param Downloader $downloader
     * @param ImportThumbnail $importThumbnail
     * @param JobDispatcher $jobDispatcher
     */
    public function __construct(
        Downloader $downloader,
        ImportThumbnail $importThumbnail,
        JobDispatcher $jobDispatcher
    ) {
        $this->downloader = $downloader;
        $this->importThumbnail = $importThumbnail;
        $this->jobDispatcher = $jobDispatcher;
    }

    public function getLabel()
    {
        return 'Ebsco'; // @translate
    }

    public function getRenderer()
    {
        return 'ebsco';
    }

    public function ingest(Media $media, Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        if (!isset($data['o:source'])) {
            $errorStore->addError('o:source', 'No Ebsco URL specified');
            return;
        }
        $uri = new HttpUri($data['o:source']);
        if (!($uri->isValid() && $uri->isAbsolute())) {
            $errorStore->addError('o:source', 'Invalid Ebsco URL specified');
            return;
        }

        // TODO Add some check of the url.
        $query = $uri->getQueryAsArray();

        if (!isset($query['db']) && !isset($query['DB'])) {
            $errorStore->addError('o:source', 'Invalid Ebsco URL specified, missing "db" parameter');
            return;
        }
        if (!isset($query['AN']) && !isset($query['an'])) {
            $errorStore->addError('o:source', 'Invalid Ebsco URL specified, missing "AN" parameter');
            return;
        }

        // May fix some strange issues.
        $db = isset($query['db']) ? $query['db'] : $query['DB'];
        $an = isset($query['AN']) ? $query['AN'] : $query['an'];
        $mediaData = ['db' => $db, 'AN' => $an];
        $media->setData($mediaData);

        $thumbnailUrl = isset($data['thumbnail_url']) ? $data['thumbnail_url'] : null;
        // @see \External\Media\Ingester
        if ($thumbnailUrl) {
            $thumbnailUri = new HttpUri($thumbnailUrl);
            if (!($thumbnailUri->isValid() && $thumbnailUri->isAbsolute())) {
                $errorStore->addError('thumbnail_url', 'Invalid thumbnail URL');
            } else {
                $storageId = $media->getStorageId();
                if (!$storageId) {
                    // See \Omeka\File\TempFile::getStorageId()
                    $storageId = bin2hex(Rand::getBytes(20));
                    $media->setStorageId($storageId);
                }

                // May use a job since the creation of thumbnails is very long (one
                // to three seconds or more).
                if (empty($data['thumbnail_job'])) {
                    $importThumbnail = $this->importThumbnail;
                    $hasThumbnails = $importThumbnail($thumbnailUrl, $storageId, $errorStore);
                } else {
                    $jobArgs = [];
                    $jobArgs['thumbnail_url'] = $thumbnailUrl;
                    $jobArgs['storage_id'] = $storageId;
                    $dispatcher = $this->jobDispatcher;
                    $dispatcher->dispatch(\External\Job\ImportThumbnail::class, $jobArgs);
                    // The status is updated in job in case of an issue.
                    $hasThumbnails = true;
                }
                $media->setHasThumbnails($hasThumbnails);
            }
        }
    }

    public function form(PhpRenderer $view, array $options = [])
    {
        $urlInput = new UrlElement('o:media[__index__][o:source]');
        $urlInput->setOptions([
            'label' => 'Ebsco URL', // @translate
            'info' => 'URL for the ebsco ebook to embed.', // @translate
        ]);
        $urlInput->setAttributes([
            'id' => 'media-ebsco-source-__index__',
            'required' => true,
        ]);
        return $view->formRow($urlInput);
    }
}
