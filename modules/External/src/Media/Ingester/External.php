<?php
namespace External\Media\Ingester;

use External\Mvc\Controller\Plugin\ConvertExternalRecord;
use External\Mvc\Controller\Plugin\ImportThumbnail;
use External\Mvc\Controller\Plugin\RetrieveExternal;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Api\Request;
use Omeka\Entity\Media;
use Omeka\File\Downloader;
use Omeka\File\Validator;
use Omeka\Job\Dispatcher as JobDispatcher;
use Omeka\Media\Ingester\MutableIngesterInterface;
use Omeka\Stdlib\ErrorStore;
use Zend\Form\Element\Checkbox;
use Zend\Form\Element\Url as UrlElement;
use Zend\Math\Rand;
use Zend\Uri\Http as HttpUri;
use Zend\View\Renderer\PhpRenderer;

/**
 * @todo Manage the deletion of the media when not downloaded.
 */
class External implements MutableIngesterInterface
{
    /**
     * @var Downloader
     */
    protected $downloader;

    /**
     * @var Validator
     */
    protected $validator;

    /**
     * @var RetrieveExternal
     */
    protected $retrieveExternal;

    /**
     * @var ConvertExternalRecord
     */
    protected $convertExternalRecord;

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
     * @param Validator $validator
     * @param RetrieveExternal $retrieveExternal
     * @param ConvertExternalRecord $convertExternalRecord
     * @param ImportThumbnail $importThumbnail
     * @param JobDispatcher $jobDispatcher
     */
    public function __construct(
        Downloader $downloader,
        Validator $validator,
        RetrieveExternal $retrieveExternal,
        ConvertExternalRecord $convertExternalRecord,
        ImportThumbnail $importThumbnail,
        JobDispatcher $jobDispatcher
    ) {
        $this->downloader = $downloader;
        $this->validator = $validator;
        $this->retrieveExternal = $retrieveExternal;
        $this->convertExternalRecord = $convertExternalRecord;
        $this->importThumbnail = $importThumbnail;
        $this->jobDispatcher = $jobDispatcher;
    }

    public function getLabel()
    {
        return 'External'; // @translate
    }

    public function getRenderer()
    {
        return 'file';
    }

    /**
     * Ingest from a URL.
     *
     * Accepts the following non-prefixed keys:
     *
     * + ingest_url: (required) The URL to ingest. The idea is that some URLs
     *   contain sensitive data that should not be saved to the database, such
     *   as private keys. To preserve the URL, remove sensitive data from the
     *   URL and set it to o:source.
     * + store_original: (optional, default true) Whether to store an original
     *   file. This is helpful when you want the media to have thumbnails but do
     *   not need the original file.
     * + media_type: (optional) allow to store the media_type without the
     *   original file.
     * + extension: (optional) allow to store the extension without the original
     *   file.
     *
     * The data can contain an array of options:
     *
     * + thumbnail_url: (optional) Store the thumbnail if the url is available.
     * + is_record_url: (optional) Specify that the url is not a file, but
     *    another record url, that has probably a link to the file.
     * + file_url: (optional) Save the file url. Only used internally.
     *
     * Same as ingester Url, but without creating the thumbnails if provided
     * @see \Omeka\Media\Ingester\Url
     *
     * {@inheritDoc}
     */
    public function ingest(Media $media, Request $request, ErrorStore $errorStore)
    {
        $this->process($media, $request, $errorStore, true);
    }

    public function update(Media $media, Request $request, ErrorStore $errorStore)
    {
        // Only the ingest of the original url can be done by update. The source
        // must not be overridden
        $params = $request->getContent();
        $params['ingest_url'] = $media->getSource();
        $params['store_original'] = true;
        $params['data'] = $media->getData() ?: [];
        $params['data']['thumbnail_url'] = '';
        $request->setContent($params);
        $this->process($media, $request, $errorStore, false);
    }

    protected function process(Media $media, Request $request, ErrorStore $errorStore, $create = true)
    {
        // Note: the file url may be a temp url when it's not the record url.

        $data = $request->getContent();

        if (empty($data['ingest_url'])) {
            // TODO "file_url" is probably useless (check all cases with store_original).
            if (empty($data['data']['file_url'])) {
                $errorStore->addError('error', 'No ingest URL specified'); // @translate
                return;
            }
            $url = $data['data']['file_url'];
            $isFileUrl = true;
        } else {
            $url = $data['ingest_url'];
            $isFileUrl = false;
        }

        $isRecordUrl = !empty($data['data']['is_record_url']);
        if ($isRecordUrl && !$create) {
            $url = $this->extractFileUrl($url);
            if (empty($url)) {
                $errorStore->addError('ingest_url', 'Invalid ingest record URL: no result.'); // @translate
                return;
            }
        }

        $uri = new HttpUri($url);
        if (!($uri->isValid() && $uri->isAbsolute())) {
            $errorStore->addError('ingest_url', 'Invalid ingest URL'); // @translate
            return;
        }

        // Get / set the storage id.
        // If the media has a storage id, it must be kept to save the file.
        // It allows too to update the media without change of the urls (as
        // long as the extension was correct).
        $storageId = $media->getStorageId();
        if (!$storageId) {
            // See \Omeka\File\TempFile::getStorageId()
            $storageId = bin2hex(Rand::getBytes(20));
            $media->setStorageId($storageId);
        }

        $storeOriginal = !isset($data['store_original']) || $data['store_original'];

        if ($storeOriginal) {
            // TODO To be removed.
            if ($isFileUrl) {
                // TODO Add a statement to authenticate user, or a session token, or an ip auth.
                $uri = new HttpUri($url);
                if (!($uri->isValid() && $uri->isAbsolute())) {
                    $errorStore->addError('ingest_url', 'Invalid ingest external URL'); // @translate
                    return;
                }
            }

            // TODO Fix download url with redirection (see https://docs.zendframework.com/zend-http/client/advanced/#http-redirections)
            // Example: doesn't manage redirections of http://www.fas.org/irp/congress/2008_rpt/crs-china.pdf
            $tempFile = $this->downloader->download($uri, $errorStore);
            if (!$tempFile) {
                return;
            }
            $tempFile->setStorageId($storageId);
            $tempFile->setSourceName($uri->getPath());
            // TODO Reenable the validation of the downloaded files.
            // The validation of the extension may fail, because the basename
            // may be "ContentServer.asp" and this is not an asp, but a pdf.
            // if (!$this->validator->validate($tempFile, $errorStore)) {
            //     return;
            // }
            // TODO Move all set data after the thumbnail process.
            $media->setData(null);
            if ($isRecordUrl) {
                $media->setSource($uri);
            }
        } else {
            if ($isFileUrl) {
                $media->setData(['file_url' => $url]);
            } elseif ($isRecordUrl) {
                if (!$create) {
                    $media->setData(null);
                    $media->setSource($uri);
                }
            }
        }

        // External thumbnail is saved by default, but can be overridden when
        // the file is stored. Note: Omeka cannot create image for epub.
        // Don't stop ingest when there is an issue with the external thumbnail.
        $externalThumbnail = !empty($data['thumbnail_url']);
        if ($externalThumbnail) {
            $thumbnailUrl = $data['thumbnail_url'];
            $thumbnailUri = new HttpUri($thumbnailUrl);
            if (!($thumbnailUri->isValid() && $thumbnailUri->isAbsolute())) {
                $errorStore->addError('thumbnail_url', 'Invalid thumbnail URL');
            } else {
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

        // TODO Remove option store_original from the external ingester?
        if ($storeOriginal) {
            $media->setExtension($tempFile->getExtension());
            $media->setMediaType($tempFile->getMediaType());
            $media->setSha256($tempFile->getSha256());
            $media->setSize($tempFile->getSize());
            if (!$externalThumbnail) {
                $hasThumbnails = $tempFile->storeThumbnails();
                $media->setHasThumbnails($hasThumbnails);
            }
            $tempFile->storeOriginal();
            // There is an original, but it may be not stored.
            $media->setHasOriginal(true);
            $tempFile->delete();
        }
        // Specific metadata will be updated when the file will be downloaded,
        // except the storage id.
        else {
            // TODO Important: Currently, the extension is forced to "pdf" for external files.
            $mediaType = isset($data['media_type']) ? $data['media_type'] : 'application/pdf';
            if (isset($data['extension'])) {
                $extension = $data['extension'];
            } else {
                $mapMediaTypeToExtensions = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/tiff' => 'tiff',
                    'image/gif' => 'gif',
                    'application/pdf' => 'pdf',
                    'application/epub+zip' => 'epub',
                    'image/jp2' => 'jp2',
                    'image/webp' => 'webp',
                ];
                $extension = isset($mapMediaTypeToExtensions[$mediaType])
                    ? $mapMediaTypeToExtensions[$mediaType]
                    : '';
            }
            $media->setExtension($extension);
            $media->setMediaType($mediaType);
            // See \Omeka\File\TempFile::getSha256()
            $sha256 = hash('sha256', $uri);
            $media->setSha256($sha256);
            // $media->setSize(0);
        }

        // All checks are ok, so save the data.
        if (!array_key_exists('o:source', $data)) {
            $media->setSource($uri);
        }
    }

    public function form(PhpRenderer $view, array $options = [])
    {
        return $this->getForm($view, 'media-external-__index__');
    }

    public function updateForm(PhpRenderer $view, MediaRepresentation $media,
        array $options = []
    ) {
        // TODO Currently, no update can be done from the manual external form.
        return '';

        return $this->getForm($view, 'media-external', [
            'ingest_url' => $media->source(),
        ]);
    }

    /**
     * Get the HTML form.
     *
     * @param PhpRenderer $view
     * @param string $id HTML ID for the textarea
     * @param array $value Values to pre-fill
     * @return string
     */
    protected function getForm(PhpRenderer $view, $id, $values = [])
    {
        $html = '';

        $create = empty($values) ? '-__index__' : '';
        if ($create) {
            $values = [
                'ingest_url' => '',
            ];
        }

        $urlInput = new UrlElement('o:media[__index__][ingest_url]');
        $urlInput->setOptions([
            'label' => 'URL', // @translate
            'info' => 'A URL to the media.', // @translate
        ]);
        $urlInput->setAttributes([
            'id' => 'media-external-ingest-url' . $create,
            'required' => true,
            'value' => $values['ingest_url'],
        ]);
        $html .= $view->formRow($urlInput);

        $urlInput = new UrlElement('o:media[__index__][thumbnail_url]');
        $urlInput->setOptions([
            'label' => 'Thumbnail URL', // @translate
            'info' => 'A URL to the thumbnail, if any.', // @translate
        ]);
        $urlInput->setAttributes([
            'id' => 'media-external-thumbnail-url' . $create,
            'required' => false,
        ]);
        $html .= $view->formRow($urlInput);

        $storeOriginalInput = new Checkbox('o:media[__index__][store_original]');
        $storeOriginalInput->setOptions([
            'label' => 'Store original file', // @translate
            'info' => 'If checked, the original file will be stored: it is not saved if unchecked.', // @translate
        ]);
        $storeOriginalInput->setAttributes([
            'id' => 'media-external-store-original' . $create,
            'required' => false,
        ]);
        $html .= $view->formRow($storeOriginalInput);

        return $html;
    }

    /**
     * Retrieve the file url from external record data.
     *
     * @todo Find where the files are when the parsing mode is needed.
     *
     * @param string $url
     * @return string
     */
    protected function extractFileUrl($url)
    {
        $retrieveExternal = $this->retrieveExternal;
        $result = $retrieveExternal('ebsco', ['url' => $url]);
        if (empty($result)) {
            return;
        }

        $convertExternalRecord = $this->convertExternalRecord;
        // TODO Save the full metadata of the item.
        $item = $convertExternalRecord('ebsco', $result['Record']);
        if (empty($item['o:media'][0]['ingest_url'])) {
            return;
        }
        return $item['o:media'][0]['ingest_url'];
    }
}
