<?php
namespace DownloadManager\Controller\Site;

use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManager;
use DownloadManager\Api\Representation\DownloadRepresentation;
use DownloadManager\Entity\Download;
use DownloadManager\Mvc\Controller\Plugin\DetermineAccessPath;
use Omeka\Api\Exception\RuntimeException;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\AbstractResourceRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Entity\User;
use Omeka\Permissions\Acl;
use Omeka\Stdlib\Message;
use Zend\Http\Response;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

class DownloadController extends AbstractActionController
{
    // This is set in VatiruLibrary/config/module.ini. This is the minimum
    // version required by the server.
    // const APP_MIN_VERSION = '3.5.8';
    const APP_MIN_VERSION = '3.2.1';
    // This is the version number provided by the app. Normally, the server
    // should not care of it, only the client has.
    const APP_VERSION = 50;

    /**
     * Allow admins and librarians (default roles, not guests) to manage files.
     *
     * Here, files are not encrypted for admins because there are acceded
     * directly, unlike when they are accessed via their hash. So this is mainly
     * to use in the admin board, because guest user and visitors can't know the
     * true hashed filename of files.
     *
     * @todo Factorize with CheckRightToDownload.
     * @todo Convert to a selectable list via main config.
     * @todo Convert to a regular acl rule/filter.
     * @todo Sign files for librarians, without encryption)?
     * @todo Use "view-all" instead of this list, and define rights for author and researcher.
     * @todo Reorganize this controller, that is built with old specifications.
     * @todo Check for protected or not.
     *
     * @var array
     */
    protected $bypassRoles = [
        Acl::ROLE_GLOBAL_ADMIN,
        Acl::ROLE_SITE_ADMIN,
        Acl::ROLE_EDITOR,
        Acl::ROLE_REVIEWER,
        Acl::ROLE_AUTHOR,
        Acl::ROLE_RESEARCHER,
    ];

    /**
     * List of type of files that are protected, included files processed by the
     * module MediaQuality. The htaccess may need to be updated, and the route
     * in the file "module.config.php" too.
     *
     * @var array
     */
    protected $protectedFileTypes = [
        'original' => 'original',
        'median' => 'median',
        'low' => 'low',
    ];

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var array
     */
    protected $config;

    /**
     * @todo Make the systemOwner and the systemGroup a parameter.
     * @var string
     */
    protected $systemOwner = 'www-data';

    /**
     * @var string
     */
    protected $systemGroup = 'www-data';

    /**
     * @param EntityManager $entityManager
     * @param string $basePath
     * @param array $config
     */
    public function __construct(EntityManager $entityManager, $basePath, array $config)
    {
        $this->entityManager = $entityManager;
        $this->basePath = $basePath;
        $this->config = $config;
    }

    /**
     * Get the keys for a user.
     *
     * @return \Zend\View\Model\JsonModel
     */
    public function userKeyAction()
    {
        $user = $this->identity();
        if (empty($user)) {
            return $this->redirectToLogin('user-key');
        }

        $userEmail = $this->params('email');
        if (empty($userEmail)) {
            $id = $this->params('id');
            if (!empty($id)) {
                $userTo = $this->api()->read('users', $id, [], ['responseContent' => 'resource']);
                if (!$userTo) {
                    return $this->jsonError('notFound');
                }
            }
        } else {
            $userTo = $this->api()->searchOne('users', ['email' => $userEmail], ['responseContent' => 'resource']);
            if (!$userTo) {
                return $this->jsonError('notFound');
            }
        }
        if (!empty($userTo)) {
            // TODO Access to the key of a user.
            return $this->jsonError('internal');
        }

        $keys = $this->userApiKeys($user);
        if (empty($keys)) {
            return $this->jsonError('internal');
        }

        $sendKeys = [];
        $sendKeys['user_id'] = $user->getId();
        $sendKeys['identity'] = $keys['main']['key_identity'];
        $sendKeys['credential'] = $keys['main']['key_credential'];
        $sendKeys['crypt'] = $keys['user_key']['key_credential'];
        // For debug only on dev server.
        // This is not secure to send a user mail with credential.
        // $sendKeys['o:user'] = [
        //     'o:name' => $user->getName(),
        //     'o:email' => $user->getEmail(),
        // ];
        return new JsonModel($sendKeys);
    }

    public function serverKeyAction()
    {
        // The server key is available via filehash (Omeka hash of file) or via
        // the download item hash (useless: no route).
        $hash = $this->params('hash');
        if (empty($hash)) {
            return $this->jsonError('missingParameter');
        }

        // This check should be done before to find media in order to set the
        // authentication service according to session or credential.
        $user = $this->identity();
        if (empty($user)) {
            return $this->jsonError('unidentified');
        }

        if (!$this->isRecentApp()) {
            return $this->jsonError('appRequireUpgrade');
        }

        if (!$this->userAgreedTerms($user)) {
            return $this->jsonError('termsNotAgreed');
        }

        /** @var \Omeka\Api\Representation\MediaRepresentation $media */
        if (strlen($hash) > 10) {
            $media = $this->findMediaFromFilename($hash, false);
            if (empty($media)) {
                // Use of the item hash.
                $result = $this->checkDownload($hash);
                if (is_object($result)) {
                    return $result;
                }
                // Here, the check is already done.
                // $user = $result['user'];
                $media = $result['media'];
            }
        } else {
            $resource = $this->api()->searchOne('items', ['id' => $hash])->getContent();
            if (empty($resource)) {
                return $this->jsonError('notFound');
            }

            $download = $this->getCurrentDownload($resource, $user);
            $result = $this->dataDownload($resource, $user, null, $download);
            if (is_object($result)) {
                return $result;
            }
            // Here, the check is already done.
            // $user = $result['user'];
            $media = $result['media'];
        }

        // TODO Clarify access to server key.
        // $result = $this->checkMediaRights($media, $user, true);
        $result = $this->checkMediaRights($media, $user);
        // Result is true when the user is admin, so he can't get a server key.
        if ($result === true) {
            return $this->jsonError('userDontNeedServerKey');
        }
        if ($result instanceof JsonModel) {
            return $result;
        }

        // There is no key for external unfetchable media.
        if ($media->renderer() === 'ebsco') {
            return new JsonModel(null);
        }

        // Prepare external file if it's an external media without fetched file.
        $hasOriginal = $this->prepareExternal($media);
        if (!$hasOriginal) {
            $message = 'The file is not available.'; // @translate;
            $this->logger()->err(new Message(
                'Media #%d: File is not available.', // @translate
                $media->id()));
            return $this->jsonError($message, Response::STATUS_CODE_500);
        }

        $filepath = $this->basePath . '/original/' . $media->filename();
        if (!file_exists($filepath)) {
            $message = 'File not available.'; // @translate;
            $this->logger()->err(new Message(
                'Media #%d: File isn’t available: "%s"', // @translate
                $media->id(), $filepath));
            return $this->jsonError($message, Response::STATUS_CODE_500);
        }

        // TODO Remove the location from the server key (required to avoid a query by the app).
        $basePathHelper = $this->viewHelpers()->get('basePath');
        $baseFolder = '/files';
        $tempBasePath = $this->determineAccessPath($media, 'original', $user);
        $location = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME']
            . $basePathHelper() . $baseFolder . '/' . $tempBasePath;

        // Check if crypted file is ready in the case of a mass upload job was
        // done last night or when this is an external source.
        $tempPath = $this->basePath . '/' . $tempBasePath;
        if (file_exists($tempPath)) {
            // Check if the size is the same to check if this is a raw file.
            $isRawFile = filesize($filepath) === filesize($tempPath);
        } else {
            // TODO Factorize with returnFile().
            $isExtract = false;
            $resulting = $this->protectFile($filepath, $media, $user, $isExtract);
            $isRawFile = is_null($resulting);
            if (is_array($resulting)) {
                $sendClear = $this->settings()->get('downloadmanager_send_unencryptable');
                if (!$sendClear) {
                    return $this->jsonError($resulting['message'], Response::STATUS_CODE_403);
                }
                $isRawFile = true;
            }

            // Make the file available via the web server.

            // Prepare the folder where the encrypted file will be saved.
            $dirPath = dirname($tempPath);
            $this->prepareDir($dirPath);

            // No added protection.
            if ($isRawFile) {
                $resulting = symlink($filepath, $tempPath);
            } else {
                $protectedTempPath = $resulting;
                $resulting = rename($protectedTempPath, $tempPath);
            }

            if (!$resulting) {
                $message = 'Error when preparing the file to access.'; // @translate;
                $this->logger()->err(new Message(
                    'Error when preparing file of media #%d (%s, %s).', // @translate
                    $media->id(), $isRawFile ? 'raw file' : 'encrypted file', $isExtract ? 'extract' : 'link'));
                return $this->viewError($message, Response::STATUS_CODE_500);
            }
        }

        // Allow to ask the server key before the file (http allows it).
        // TODO Add a limit to prevent batch hacking.
        if ($result === null) {
            $expiration = $this->determineExpiration();
            $data = [];
            $data['o:status'] = Download::STATUS_DOWNLOADED;
            $data['o:resource']['o:id'] = $media->item()->id();
            $data['o:owner']['o:id'] = $user->getId();
            $data['o-module-access:expire'] = $expiration;
            $log = [];
            $log[] = ['action' => 'downloaded', 'date' => (new \DateTime('now'))->format('Y-m-d H:i:s')];
            $log[] = ['action' => 'expiration', 'date' => $expiration->format('Y-m-d H:i:s')];
            $data['o-module-access:log'] = $log;
            $download = $this->api()
                ->create('downloads', $data)
                ->getContent();
            if (!$download) {
                return $this->jsonError('internal');
            }
        }
        // This is a DownloadRepresentation, so the file is already downloaded.
        else {
            /** @var DownloadRepresentation $download */
            $download = $result;
            if (!$download->isDownloaded()) {
                $expiration = $this->determineExpiration();
                $data = [];
                $data['o:status'] = Download::STATUS_DOWNLOADED;
                $data['o-module-access:expire'] = $expiration;
                $log = $download->log() ?: [];
                $log[] = ['action' => 'downloaded', 'date' => (new \DateTime('now'))->format('Y-m-d H:i:s')];
                $log[] = ['action' => 'expiration', 'date' => $expiration->format('Y-m-d H:i:s')];
                $data['o-module-access:log'] = $log;
                $response = $this->api()
                    ->update('downloads', $download->id(), $data, [], ['isPartial' => true]);
                if (!$response) {
                    throw new RuntimeException('An internal error occurred.'); // @translate
                }
            }
        }

        $serverKey = $this->createServerKey($download, $user);
        if (empty($serverKey)) {
            return $this->jsonError('noApiKey');
        }

        $headers = $this->getResponse()->getHeaders();
        $headers->addHeaderLine('Expires', $download->expire()->format('D, d M Y H:i:s \G\M\T'));

        // TODO Remove the document key from the header of the server key.
        $documentKey = $this->createDocumentKey($media, $download);
        $authorization = 'Basic ' . base64_encode($documentKey);
        $headers->addHeaderLine('Authorization', $authorization);

        $output = [];
        $output['serverKey'] = $serverKey;
        if ($isRawFile) {
            // Required by the app, that can't display a clear pdf if it doesn't
            // know it is a raw file.
            $output['protected'] = false;
        }

        // TODO Remove the management of old users.
        $useUniqueKeys = $this->useUniqueKeys($user);
        if (!$useUniqueKeys) {
            return new JsonModel($output);
        }

        // TODO To be removed.
        $output['location'] = $location;

        return new JsonModel($output);
    }

    /**
     * Info about the version of the library.
     */
    public function versionAction()
    {
        // $requestApiVersion = $this->requestVatiruApiVersion();
        $apiVersion = $this->vatiruApiVersion();

        $appVersion = $this->params()->fromQuery('app-version');
        $upgrade = empty($appVersion) || ($appVersion < self::APP_VERSION);

        return new JsonModel([
            'api' => $apiVersion,
            'upgrade' => $upgrade ? 'required' : false,
            // // TODO To be removed.
            // 'com.vatiru' => [
            //     'minVersionCode' => (string) self::APP_VERSION,
            // ],
        ]);
    }

    /**
     * List public sites quickly and in one list without details or pagination.
     *
     * It simplifies access for app authentification.
     */
    public function sitesAction()
    {
        $conn = $this->entityManager->getConnection();
        $qb = $conn->createQueryBuilder()
            ->select('slug', 'title')
            ->from('site', 'site')
            ->where('site.is_public = 1');
        $stmt = $conn->executeQuery($qb);
        $result = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        return new JsonModel($result);
    }

    /**
     * Action to show an item via the viewer.
     */
    public function showAction()
    {
        $site = $this->currentSite();
        // TODO Currently, only items can be held and downloaded.
        $response = $this->api()->read('items', $this->params('id'));
        /** @var \Omeka\Api\Representation\ItemRepresentation $item */
        $resource = $item = $response->getContent();
        /** @var \Omeka\Api\Representation\MediaRepresentation $media */
        $media = $item->primaryMedia();
        $isEbscoEbook = $media->renderer() === 'ebsco';

        $pages = null;
        $isSample = (bool) $this->params()->fromQuery('sample');
        if ($isSample) {
            $pages = $this->getSamplePages($item);
            if (empty($pages)) {
                $message = 'No pages defined for sample.'; // @translate
                return $this->viewError($message, Response::STATUS_CODE_400);
            }
        } else {
            $pages = $this->params()->fromQuery('pages');
        }

        // Check if it is already downloaded, else set it downloaded (for web).
        $user = $this->identity();
        if (!$user) {
            if ($isEbscoEbook) {
                // Unauthorized access.
                // This resource is not available to visitors.
                return $this->redirectToLogin(null, true);
            }
            if ($this->totalAvailable($resource) !== -1) {
                return $this->redirectToLogin(null, true);
            }
            // Forbid anything when not identified, not only ebsco.
            return $this->redirectToLogin(null, true);
        } else {
            if (!$this->isRecentApp()) {
                throw new RuntimeException('The app must be upgraded.'); // @translate
            }

            if (!$this->userAgreedTerms($user)) {
                throw new RuntimeException('The user didn’t approve the terms and conditions.'); // @translate
            }

            $result = $this->checkRightToDownload($resource, $user);
            if (is_array($result)) {
                throw new RuntimeException($result['message']);
            }
            if (!$result) {
                throw new RuntimeException('The user hasn’t the right to view this item.'); // @translate
            }
            // TODO There may be an issue when the download is expired.
            $result = $this->isResourceAvailableForUser($resource, $user);
            if (!$result) {
                throw new RuntimeException('This resource is not available for this user.'); // @translate
            }
        }

        // Prepare external file if it's an external media without fetched file.
        if ($media) {
            if (!$isEbscoEbook) {
                $hasOriginal = $this->prepareExternal($media);
                if (!$hasOriginal) {
                    $message = 'The file is not available.'; // @translate;
                    $this->logger()->err(new Message(
                        'Media #%d: File is not available.', // @translate
                        $media->id()));
                    return $this->viewError($message, Response::STATUS_CODE_500);
                }
            }
        }

        if ($user) {
            $this->prepareDownload($resource, $user, $isSample);
        }

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('item', $item);
        $view->setVariable('resource', $item);
        $view->setVariable('pages', $pages);
        $terminal = $this->settings()->get('downloadmanager_show_media_terminal');
        $view->setVariable('terminal', $terminal);
        if ($media && $terminal) {
            $view->setTerminal(true);
            $view->setTemplate('download-manager/site/download/show-full');
        }
        return $view;
    }

    public function browseAction()
    {
        $user = $this->identity();
        if (empty($user)) {
            return $this->jsonError('unidentified');
        }

        if (!$this->isRecentApp()) {
            return $this->jsonError('appRequireUpgrade');
        }

        if (!$this->userAgreedTerms($user)) {
            return $this->jsonError('termsNotAgreed');
        }

        $this->setBrowseDefaults('created');

        $params = $this->params()->fromQuery();

        // In public side, force the owner.
        $params['owner_id'] = $user->getId();

        // In public side, never display empty downloads ("ready"), but display
        // past ones.
        if (empty($params['status'])) {
            $params['status'] = [Download::STATUS_HELD, Download::STATUS_DOWNLOADED, Download::STATUS_PAST];
        } else {
            if (!is_array($params['status'])) {
                $params['status'] = array_map('trim', explode(',', $params['status']));
            }
            $params['status'] = array_intersect(
                $params['status'],
                [Download::STATUS_HELD, Download::STATUS_DOWNLOADED, Download::STATUS_PAST]
            );
        }

        $response = $this->api()->search('downloads', $params);
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $view = new ViewModel();
        $downloads = $response->getContent();
        $view->setVariable('downloads', $downloads);
        return $view;
    }

    /**
     * Replace the direct access to original files in order to control rights.
     *
     * Files may be encrypted.
     * The query args "type", "pages" and "sample" are managed.
     * "quality" is the argument for items and "type" is the one for files, in
     * order to allow use of standard thumbnails.
     *
     * @todo Use media public/private + group instead of a redirect.
     *
     * @return \Zend\View\Model\ViewModel|\Zend\View\Model\JsonModel|mixed
     */
    public function filesAction()
    {
        // Quick return when file is not securized.
        $type = $this->params('type');
        $filename = $this->params('filename');

        // Don't process thumbnails.
        if (!in_array($type, $this->protectedFileTypes)) {
            return $this->returnRawFile($filename, $type);
        }

        // Check if this is a lost or a managed file.
        $media = $this->findMediaFromFilename($filename);
        if (empty($media)) {
            return $this->jsonError('notFound');
        }

        $isSample = (bool) $this->params()->fromQuery('sample');
        if ($isSample) {
            $pages = $this->getSamplePages($media->item());
            if (empty($pages)) {
                $message = 'No pages defined for sample.'; // @translate
                return $this->jsonError($message, Response::STATUS_CODE_400);
            }
        } else {
            $pages = $this->params()->fromQuery('pages');
        }

        if ($media->isPublic() && $media->item()->isPublic()) {
            // Now, the catalog is public, but the media are not, even if public.
            $publicVisibility = $this->settings()->get('downloadmanager_public_visibility');
            if ($publicVisibility) {
                return $this->returnRawFile($filename, $type, $media->mediaType(), $pages, $isSample, $media);
            }
        }

        $user = $this->identity();

        if (!$this->isRecentApp()) {
            return $this->jsonError('appRequireUpgrade');
        }

        if (!$this->userAgreedTerms($user)) {
            return $this->jsonError('termsNotAgreed');
        }

        if ($this->debugCheckUser($user)) {
            return $this->returnRawFile($filename, $type);
        }

        $result = $this->checkRightToDownload($media->item(), $user);
        if (is_array($result)) {
            return $this->jsonError($result['message'], $result['statusCode']);
        }
        if (!$result) {
            return $this->jsonError('The user hasn’t the right to view this item.', // @translate
                Response::STATUS_CODE_403);
        }

        $result = $this->checkMediaRights($media, $user);
        if ($result === true) {
            return $this->returnRawFile($filename, $type, $media->mediaType(), $pages, $isSample, $media);
        }
        if ($result instanceof JsonModel) {
            $response = $this->getResponse();
            return $this->jsonError($result->getVariable('message'), $response->getStatusCode());
        }

        $download = $result;
        if ($download) {
            if (!$download->isDownloaded()) {
                $expiration = $this->determineExpiration();
                $data = [];
                $data['o:status'] = Download::STATUS_DOWNLOADED;
                $data['o-module-access:expire'] = $expiration;
                $log = $download->log() ?: [];
                $log[] = ['action' => 'downloaded', 'date' => (new \DateTime('now'))->format('Y-m-d H:i:s')];
                $log[] = ['action' => 'expiration', 'date' => $expiration->format('Y-m-d H:i:s')];
                $data['o-module-access:log'] = $log;
                $response = $this->api()
                    ->update('downloads', $download->id(), $data, [], ['isPartial' => true]);
                if (!$response) {
                    return $this->jsonError('internal');
                }
            }
        } elseif ($user) {
            $resourceId = $media->item()->id();
            $expiration = $this->determineExpiration();
            $data = [];
            $data['o:status'] = Download::STATUS_DOWNLOADED;
            $data['o:resource']['o:id'] = $resourceId;
            $data['o:owner']['o:id'] = $user->getId();
            $data['o-module-access:expire'] = $expiration;
            $log = [];
            $log[] = ['action' => 'downloaded', 'date' => (new \DateTime('now'))->format('Y-m-d H:i:s')];
            $log[] = ['action' => 'expiration', 'date' => $expiration->format('Y-m-d H:i:s')];
            $data['o-module-access:log'] = $log;
            $download = $this->api()
                ->create('downloads', $data)
                ->getContent();
            if (!$download) {
                return $this->jsonError('internal');
            }
        }
        // Else an anonymous wants to see an open original file.
        else {
            if (!empty($GLOBALS['globalIsTest'])) {
                $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
                $this->logger()->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
            }
            return $this->returnRawFile($filename, $type, $media->mediaType(), $pages, $isSample, $media);
        }

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $this->logger()->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
        }
        return $this->returnEncryptedFile($media, $user, $download, $type, $pages, $isSample);
    }

    /**
     * Action to download an item.
     *
     * For a pdf, check the api key, then sign, encrypt and send the first file.
     */
    public function itemAction()
    {
        $hash = $this->params('hash');
        if (empty($hash)) {
            return $this->jsonError('missingParameter');
        }

        $quality = $this->params()->fromQuery('quality');

        if (strlen($hash) > 10) {
            $result = $this->checkDownload($hash, $quality);
            if (is_object($result)) {
                return $result;
            }
        } else {
            $user = $this->identity();
            if (empty($user)) {
                return $this->jsonError('unidentified');
            }

            $resource = $this->api()->searchOne('items', ['id' => $hash])->getContent();
            if (empty($resource)) {
                return $this->jsonError('notFound');
            }

            $download = $this->getCurrentDownload($resource, $user);
            $result = $this->dataDownload($resource, $user, $quality, $download);
            if (is_object($result)) {
                return $result;
            }
        }

        $user = $result['user'];
        $media = $result['media'];
        // $filepath = $result['filepath'];
        // $filename = $result['filename'];
        $resource = $result['resource'];
        $download = $result['download'];
        $type = $result['type'];

        if (!$this->isRecentApp()) {
            return $this->jsonError('appRequireUpgrade');
        }

        if (!$this->userAgreedTerms($user)) {
            return $this->jsonError('TermsNotAgreed');
        }

        $result = $this->checkRightToDownload($resource, $user);
        if (is_array($result)) {
            return $this->jsonError($result['message'], $result['statusCode']);
        }
        if (!$result) {
            return $this->jsonError('The user hasn’t the right to view this item.', // @translate
                Response::STATUS_CODE_403);
        }

        // Prepare external file if it's an external media without fetched file.
        if ($media) {
            $hasOriginal = $this->prepareExternal($media);
            if (!$hasOriginal) {
                return $this->jsonError('The file is not available.', // @translate
                    Response::STATUS_CODE_404);
            }
        }

        $isSample = (bool) $this->params()->fromQuery('sample');
        if ($isSample) {
            $pages = $this->getSamplePages($media->item());
            if (empty($pages)) {
                $message = 'No pages defined for sample.'; // @translate
                return $this->jsonError($message, Response::STATUS_CODE_400);
            }
        } else {
            $pages = $this->params()->fromQuery('pages');
        }

        // Update the status of the item.
        if (!$download) {
            $expiration = $this->determineExpiration();
            $data = [];
            $data['o:status'] = Download::STATUS_DOWNLOADED;
            $data['o:resource']['o:id'] = $resource->id();
            $data['o:owner']['o:id'] = $user->getId();
            $data['o-module-access:expire'] = $expiration;
            $log = [];
            $log[] = ['action' => 'downloaded', 'date' => (new \DateTime('now'))->format('Y-m-d H:i:s')];
            $log[] = ['action' => 'expiration', 'date' => $expiration->format('Y-m-d H:i:s')];
            $data['o-module-access:log'] = $log;
            $download = $this->api()
                ->create('downloads', $data);
            if (!$download) {
                return $this->jsonError('internal');
            }
            $download = $download->getContent();
        } elseif (!$download->isDownloaded()) {
            $expiration = $this->determineExpiration();
            $data = [];
            $data['o:status'] = Download::STATUS_DOWNLOADED;
            $data['o-module-access:expire'] = $expiration;
            $log = $download->log() ?: [];
            $log[] = ['action' => 'downloaded', 'date' => (new \DateTime('now'))->format('Y-m-d H:i:s')];
            $log[] = ['action' => 'expiration', 'date' => $expiration->format('Y-m-d H:i:s')];
            $data['o-module-access:log'] = $log;
            $response = $this->api()
                ->update('downloads', $download->id(), $data, [], ['isPartial' => true]);
            if (!$response) {
                throw new RuntimeException('An internal error occurred.'); // @translate
            }
        }

        return $this->returnEncryptedFile($media, $user, $download, $type, $pages, $isSample);
    }

    /**
     * Get the status of an item.
     *
     * @return \Zend\View\Model\JsonModel
     */
    public function statusAction()
    {
        return $this->statusHold('status');
    }

    /**
     * Manage the holding status and the right to download an item.
     *
     * Hold is a switch: if held two times, the held is cancelled.
     *
     * @return \Zend\View\Model\JsonModel
     */
    public function holdAction()
    {
        return $this->statusHold('hold');
    }

    /**
     * Manage the status/holding of an item.
     *
     * @param string $action
     * @return \Zend\View\Model\JsonModel
     */
    protected function statusHold($action)
    {
        $user = $this->identity();
        if (empty($user)) {
            $isModuleActive = $this->viewHelpers()->get('isModuleActive');
            // return $this->jsonError('unidentified);
            return new JsonModel([
                'status' => 'login',
                'url' => $isModuleActive('GuestUser')
                    ? $this->url()->fromRoute('site/guest-user', ['action' => 'login'], true)
                    : $this->url()->fromRoute('login'),
            ]);
        }

        if (!$this->isRecentApp()) {
            return $this->jsonError('appRequireUpgrade');
        }

        if (!$this->userAgreedTerms($user)) {
            return $this->jsonError('termsNotAgreed');
        }

        $resourceId = $this->params('id');
        if (empty($resourceId)) {
            return $this->jsonError('notFound');
        }

        try {
            // TODO Currently, only items can be held and downloaded.
            $resource = $this->api()->read('items', $resourceId)->getContent();
        } catch (NotFoundException $e) {
            return $this->jsonError('notFound');
        }

        $result = $this->checkDownloadStatus($resource, $user);

        if ($action === 'hold') {
            // The item is not available, so remove or set the holding. In the
            // other cases, keep the result.
            if ($result['available'] === false && $result['status'] !== 'error') {
                $download = $this->getCurrentDownload($resource, $user);
                if ($download) {
                    if ($download->isHeld()) {
                        $data = [];
                        $data['o:status'] = Download::STATUS_READY;
                        $data['o-module-access:expire'] = null;
                        $log = $download->log() ?: [];
                        $log[] = ['action' => 'unheld', 'date' => (new \DateTime('now'))->format('Y-m-d H:i:s')];
                        $data['o-module-access:log'] = $log;
                        $response = $this->api()
                            ->update('downloads', $download->id(), $data, [], ['isPartial' => true]);
                        if (!$response) {
                            return $this->jsonError('internal');
                        }
                        $result['status'] = 'removed';
                    } else {
                        $data = [];
                        $data['o:status'] = Download::STATUS_HELD;
                        $data['o-module-access:expire'] = null;
                        $log = $download->log() ?: [];
                        $log[] = ['action' => 'held', 'date' => (new \DateTime('now'))->format('Y-m-d H:i:s')];
                        $data['o-module-access:log'] = $log;
                        $response = $this->api()
                            ->update('downloads', $download->id(), $data, [], ['isPartial' => true]);
                        if (!$response) {
                            return $this->jsonError('internal');
                        }
                        $result['status'] = Download::STATUS_HELD;
                    }
                } else {
                    $data = [];
                    $data['o:status'] = Download::STATUS_HELD;
                    $data['o:resource']['o:id'] = $resourceId;
                    $data['o:owner']['o:id'] = $user->getId();
                    $log = [];
                    $log[] = ['action' => 'held', 'date' => (new \DateTime('now'))->format('Y-m-d H:i:s')];
                    $data['o-module-access:log'] = $log;
                    $response = $this->api()
                        ->create('downloads', $data);
                    if (!$response) {
                        return $this->jsonError('internal');
                    }
                    $download = $response->getContent();
                    $result['status'] = Download::STATUS_HELD;
                }
            }
        }

        return new JsonModel($result);
    }

    /**
     * Release a downloaded item.
     *
     * @return \Zend\View\Model\JsonModel
     */
    public function releaseAction()
    {
        $user = $this->identity();
        if (empty($user)) {
            return $this->jsonError('unidentified');
        }

        if (!$this->isRecentApp()) {
            return $this->jsonError('appRequireUpgrade');
        }

        if (!$this->userAgreedTerms($user)) {
            return $this->jsonError('termsNotAgreed');
        }

        $resourceId = $this->params('id');
        if (empty($resourceId)) {
            return $this->jsonError('notFound');
        }

        try {
            // TODO Currently, only items can be held and downloaded.
            $resource = $this->api()->read('items', $resourceId)->getContent();
        } catch (NotFoundException $e) {
            return $this->jsonError('notFound');
        }

        $download = $this->getCurrentDownload($resource, $user);
        if (empty($download)) {
            $message = 'Expired or not downloaded.'; // @translate
            return $this->jsonError($message, Response::STATUS_CODE_400);
        }

        if (!$download->isDownloaded()) {
            $message = 'This item is not downloaded.'; // @translate
            return $this->jsonError($message, Response::STATUS_CODE_400);
        }

        // TODO Factorize release download (see admin).

        // Remove protected files, because the password is no more unique, but
        // the space on the server is limited.
        $media = $resource->primaryMedia();
        $this->removeAccessFiles($user, $media);

        // Set data as past.
        $data = [];
        $data['o:status'] = Download::STATUS_PAST;
        $log = $download->log() ?: [];
        $log[] = ['action' => 'released', 'date' => (new \DateTime('now'))->format('Y-m-d H:i:s')];
        $data['o-module-access:log'] = $log;
        $response = $this->api()
            ->update('downloads', $download->id(), $data, [], ['isPartial' => true]);
        if (!$response) {
            return $this->jsonError('internal');
        }

        // Launch a background job to warn the first user in the list.
        $download = $response->getContent();
        $jobArgs = ['downloadId' => $download->id()];
        $this->jobDispatcher()->dispatch(\DownloadManager\Job\NotificationAvailability::class, $jobArgs);

        $result = ['status' => 'released'];
        return new JsonModel($result);
    }

    /**
     * Extend the date of a downloaded item.
     *
     * @return \Zend\View\Model\JsonModel
     */
    public function extendAction()
    {
        // TODO Factorize first part with releaseAction().
        $user = $this->identity();
        if (empty($user)) {
            return $this->jsonError('unidentified');
        }

        if (!$this->isRecentApp()) {
            return $this->jsonError('appRequireUpgrade');
        }

        if (!$this->userAgreedTerms($user)) {
            return $this->jsonError('termsNotAgreed');
        }

        $resourceId = $this->params('id');
        if (empty($resourceId)) {
            return $this->jsonError('notFound');
        }

        try {
            // TODO Currently, only items can be held and downloaded.
            $resource = $this->api()->read('items', $resourceId)->getContent();
        } catch (NotFoundException $e) {
            return $this->jsonError('notFound');
        }

        /** @var DownloadRepresentation $download */
        $download = $this->getCurrentDownload($resource, $user);
        if (empty($download)) {
            $message = 'Expired or not downloaded.'; // @translate
            return $this->jsonError($message, Response::STATUS_CODE_400);
        }

        if (!$download->isDownloaded()) {
            $message = 'This item is not downloaded.'; // @translate
            return $this->jsonError($message, Response::STATUS_CODE_400);
        }

        // Set the default extension to the default expiration.
        $max = $this->settings()->get('downloadmanager_download_expiration');
        // The absolute value is a security hack.
        $extension = (int) abs($this->params()->fromQuery('extension', $max));
        $extension = min($extension, $max) ?: $max;
        $expiration = clone $download->expire();
        $expiration = $expiration->add(new DateInterval('PT' . $extension . 'S'));

        // The new expiration cannot be greater than the current time plus the
        // default expiration (avoids successive extensions).
        $maxExpiration = $this->determineExpiration();
        $expiration = $expiration < $maxExpiration ? $expiration : $maxExpiration;

        $data = [];
        $data['o-module-access:expire'] = $expiration;
        $log = $download->log() ?: [];
        $log[] = ['action' => 'extended', 'date' => (new \DateTime('now'))->format('Y-m-d H:i:s')];
        $log[] = ['action' => 'expiration', 'date' => $expiration->format('Y-m-d H:i:s')];
        $data['o-module-access:log'] = $log;
        $response = $this->api()
            ->update('downloads', $download->id(), $data, [], ['isPartial' => true]);
        if (!$response) {
            return $this->jsonError('internal');
        }

        $result = [
            'result' => 'extended',
            'o-module-access:expire' => $download->expire()->format('Y-m-d H:i:s'),
        ];
        return new JsonModel($result);
    }

    /**
     * Allows to access to terms via json when unidentified.
     *
     * @return \Zend\View\Model\JsonModel
     */
    public function readTermsAction()
    {
        $result = [
            'terms' => $this->settings()->get('guestuser_terms_text'),
        ];
        return new JsonModel($result);
    }

    /**
     * Duplicate guest-user/accept-terms, but via json.
     *
     * @return \Zend\View\Model\JsonModel
     */
    public function acceptTermsAction()
    {
        $user = $this->identity();
        if (empty($user)) {
            return $this->jsonError('unidentified');
        }

        $userSettings = $this->userSettings();

        $acceptTerms = $this->params()->fromQuery('accept');
        if (!is_null($acceptTerms)) {
            $userSettings->set('guestuser_agreed_terms', (bool) $acceptTerms, $user->getId());
        }

        $agreed = (bool) $userSettings->get('guestuser_agreed_terms', false, $user->getId());
        $terms = $this->settings()->get('guestuser_terms_text');

        $result = [
            'agreed' => $agreed,
            'terms' => $terms,
            // // TODO Kept for compatibility, to be removed soon.
            // 'accept_terms' => $agreed,
            // 'terms_and_conditions' => $terms,
        ];
        return new JsonModel($result);
    }

    /**
     * Helper to determine the expiration date from now.
     *
     * @return DateTime
     */
    protected function determineExpiration()
    {
        $expiration = $this->settings()->get('downloadmanager_download_expiration') ?: 86400;
        $expire = new DateTime('now');
        $expire->add(new DateInterval('PT' . $expiration . 'S'));
        return $expire;
    }

    /**
     * Check if the app more recent than the required version.
     *
     * This check is required for third parties that bypass all the checks and
     * use only the api.
     *
     * @return bool If false, an upgrade may be required.
     */
    protected function isRecentApp()
    {
        static $isRecentApp;

        if (!is_null($isRecentApp)) {
            return $isRecentApp;
        }

        $isExternalApp = $this->isExternalApp();
        if (!$isExternalApp) {
            $isRecentApp = true;
            return true;
        }

        $requestApiVersion = $this->requestVatiruApiVersion();
        // $apiVersion = $this->vatiruApiVersion();
        $isRecentApp = version_compare($requestApiVersion, self::APP_MIN_VERSION, '>=');
        return $isRecentApp;
    }

    /**
     * Check if a user agreed terms.
     *
     * This check is required for third parties that bypass all the checks and
     * use only the api.
     * @todo Add the status directly in the json of the items/downloads?
     * Only the guest should agreed terms.
     *
     * @param User|null $user
     * @return bool The result is always true for visitors, since the check is
     * done via acl and private status.
     */
    protected function userAgreedTerms(User $user = null)
    {
        if (empty($user)) {
            return true;
        }
        if ($user->getRole() !== \GuestUser\Permissions\Acl::ROLE_GUEST) {
            return true;
        }

        return (bool) $this->userSettings()
            ->get('guestuser_agreed_terms', false, $user->getId());
    }

    /**
     * Prepare the download of a resource for a user.
     *
     * @param AbstractResourceRepresentation $resource
     * @param User $user
     * @param bool $isSample
     * @throws RuntimeException
     */
    protected function prepareDownload(AbstractResourceRepresentation $resource, User $user, $isSample = false)
    {
        $download = $this->getCurrentDownload($resource, $user);
        if ($download) {
            if ($isSample) {
                $data = [];
                $log = $download->log() ?: [];
                $log[] = ['action' => 'read_sample', 'date' => (new \DateTime('now'))->format('Y-m-d H:i:s')];
                $data['o-module-access:log'] = $log;
                $response = $this->api()
                    ->update('downloads', $download->id(), $data, [], ['isPartial' => true]);
                if (!$response) {
                    throw new RuntimeException('An internal error occurred.'); // @translate
                }
            } elseif (!$download->isDownloaded()) {
                $expiration = $this->determineExpiration();
                $data = [];
                $data['o:status'] = Download::STATUS_DOWNLOADED;
                $data['o-module-access:expire'] = $expiration;
                $log = $download->log() ?: [];
                $log[] = ['action' => 'downloaded', 'date' => (new \DateTime('now'))->format('Y-m-d H:i:s')];
                $log[] = ['action' => 'expiration', 'date' => $expiration->format('Y-m-d H:i:s')];
                $data['o-module-access:log'] = $log;
                $response = $this->api()
                    ->update('downloads', $download->id(), $data, [], ['isPartial' => true]);
                if (!$response) {
                    throw new RuntimeException('An internal error occurred.'); // @translate
                }
            }
        } else {
            $data = [];
            $data['o:resource']['o:id'] = $resource->id();
            $data['o:owner']['o:id'] = $user->getId();
            if ($isSample) {
                $data['o:status'] = Download::STATUS_READY;
                $log = [];
                $log[] = ['action' => 'read_sample', 'date' => (new \DateTime('now'))->format('Y-m-d H:i:s')];
                $data['o-module-access:log'] = $log;
                $data['o-module-access:expire'] = null;
            } else {
                $expiration = $this->determineExpiration();
                $data['o:status'] = Download::STATUS_DOWNLOADED;
                $data['o-module-access:expire'] = $expiration;
                $log = [];
                $log[] = ['action' => 'downloaded', 'date' => (new \DateTime('now'))->format('Y-m-d H:i:s')];
                $log[] = ['action' => 'expiration', 'date' => $expiration->format('Y-m-d H:i:s')];
                $data['o-module-access:log'] = $log;
            }
            $response = $this->api()
                ->create('downloads', $data);
            if (!$response) {
                throw new RuntimeException('An internal error occurred.'); // @translate
            }
        }
    }

    /**
     * Check a download from the hash.
     *
     * @todo Merge with checkMediaRights() and checkRightToDownload() and dataDownload().
     * Returns only the last download, since the hash is unique.
     *
     * @param string $hash
     * @param string $quality
     * @return JsonModel|array
     */
    protected function checkDownload($hash, $quality = 'original')
    {
        $user = $this->identity();
        if (empty($user)) {
            return $this->jsonError('unidentified');
        }

        // Get the download link object.
        $args = [
            'hash' => $hash,
            'status' => [Download::STATUS_READY, Download::STATUS_HELD, Download::STATUS_DOWNLOADED],
            // Get the last only. Anyway, it's unique, unlike the hash password.
            'sort_by' => 'id',
            'sort_order' => 'desc',
            'limit' => 1,
        ];
        // When there is a unique key for all users, get the specific download.
        $useUniqueKeys = $this->useUniqueKeys($user);
        if ($useUniqueKeys) {
            $args['owner_id'] = $user->getId();
        }
        $download = $this->api()->search('downloads', $args)->getContent();
        if (empty($download)) {
            return $this->jsonError('keyNotFound');
        }
        $download = reset($download);

        // Check if the link is expired (normally never, because of cron task).
        if ($download->isExpiring()) {
            return $this->jsonError('expiredLink');
        }

        $resource = $download->resource();

        return $this->dataDownload($resource, $user, $quality, $download);
    }

    /**
     * Get data for a resource to download.
     *
     * @param AbstractResourceRepresentation $resource
     * @param User $user
     * @param string $quality
     * @param DownloadRepresentation $download
     * @return JsonModel|array
     */
    protected function dataDownload(AbstractResourceRepresentation $resource, User $user, $quality = 'original', DownloadRepresentation $download = null)
    {
        // Because there is an automatic deletion recursion in the database, the
        // resource and the owner are always available, but check anyway.
        if (empty($resource)) {
            return $this->jsonError('internal');
        }

        if ($download) {
            $owner = $download->owner();
            if (empty($owner)) {
                return $this->jsonError('internal');
            }
        } else {
            $owner = null;
        }

        $result = $this->checkResourceToDownload($resource);
        if (is_array($result)) {
            return $this->jsonError($result['message'], $result['statusCode']);
        }

        $isAdmin = in_array($user->getRole(), $this->bypassRoles);

        // Check if the owner of the download is the current user.
        // Admins have rights to download any file.
        if (!$isAdmin && $owner && $owner->id() !== $user->getId()) {
            $this->logger()->warn(new Message(
                'The user #%d (%s) is trying to access download #%s owned by #%d (%s).', // @translate
                    $user->getId(), $user->getEmail(), $download ? $download->id() : 'null', $owner->id(), $owner->email()));
            return $this->jsonError('userIsNotOwner');
        }

        // Avoid issue when the quality is empty.
        $quality = $quality ?: 'original';

        // Check if the resource has really a primary media.
        $media = $resource->primaryMedia();
        $isExternalWithoutFile = ($media->ingester() === 'external' && !$media->hasOriginal())
            || $media->renderer() === 'ebsco';
        if ($isExternalWithoutFile) {
            // No management of media qualities for external files: it will be
            // too long.
            // TODO Manage media qualities for
            if ($quality === 'original') {
                $mediaQuality = [
                    'quality' => 'original',
                    'storagePath' => '',
                ];
            }
        } elseif ($download) {
            $mediaQualities = $this->viewHelpers()->get('mediaQualities');
            $mediaQuality = $mediaQualities($media, $quality);
            if (empty($mediaQuality)) {
                return $this->jsonError('noFile');
            }
        } else {
            $mediaQualities = $this->viewHelpers()->get('mediaQualities');
            $mediaQuality = $mediaQualities($media, $quality);
            if (empty($mediaQuality)) {
                return $this->jsonError('noFile');
            }
        }

        // Check if the media is a pdf file.
        // if ($media->mediaType() !== 'application/pdf') {
        //     return $this->jsonError('notEbook);
        // }

        return [
            'user' => $user,
            'media' => $media,
            'filepath' => $this->basePath . DIRECTORY_SEPARATOR . $mediaQuality['storagePath'],
            'filename' => basename($mediaQuality['storagePath']),
            'resource' => $resource,
            'download' => $download,
            'type' => $mediaQuality['quality'],
        ];
    }

    /**
     * Helper to check rights on a media for a user.
     *
     * @todo Merge with checkDownload() and checkRightsToDownload().
     *
     * @param MediaRepresentation $media
     * @param User $user
     * @param bool $requireDownload
     * @return bool|DownloadRepresentation|null|JsonModel If the user is allowed
     * to access, returns true for raw access, else the Download object if any,
     * else null if not yet downloaded or held, else returns an error as json.
     */
    protected function checkMediaRights(MediaRepresentation $media, User $user = null, $requireDownload = false)
    {
        if (empty($user)) {
            return $this->jsonError('unauthorized');
        }

        $isAdmin = in_array($user->getRole(), $this->bypassRoles);
        if ($isAdmin) {
            if (!$requireDownload) {
                return true;
            }
        } elseif ($this->debugCheckUser($user)) {
            if (!$requireDownload) {
                return true;
            }
        }

        if (!$this->userIsAllowed(Download::class, 'create')) {
            return $this->jsonError('unauthorized');
        }

        // Process the checks in case of an old or a shared link.
        // These checks are required to avoid bypass of the view.

        $resource = $media->item();
        $result = $this->checkResourceToDownload($resource);
        if (is_array($result)) {
            return $this->jsonError($result['message'], $result['statusCode']);
        }

        $result = $this->checkRightToDownload($resource, $user);
        if (is_array($result)) {
            // With the default acl rules, a visitor can't even access this action.
            if (empty($user)) {
                $message = new Message('You don‘t have the rights to download this file.. %sLogin%s to go ahead.', // @translate
                    '<a href="' . url('login') . '">', '</a>');
            } else {
                $message = 'You don‘t have the rights to download this file.'; // @translate;
            }
            return $this->jsonError($message, Response::STATUS_CODE_403);
        }

        $download = $this->getCurrentDownload($resource, $user);
        if ($download && $download->isDownloaded()) {
            // The access to file is open (the file may need to be crypted).
            return $download;
        }

        $isAvailableForUser = $this->isResourceAvailableForUser($resource, $user);
        if (!$isAvailableForUser) {
            if ($download && $download->isHeld()) {
                $message = 'This item is not available. You have to wait that a copy becomes available.'; // @translate
            } else {
                $message = 'This item is not available. You may place a hold on it.'; // @translate
            }
            return $this->jsonError($message, Response::STATUS_CODE_400);
        }

        return $download;
    }

    /**
     * Check if a user is a debug user.
     *
     * @param User $user
     * @return bool
     */
    protected function debugCheckUser(User $user = null)
    {
        if (empty($user)) {
            return false;
        }
        $debugSites = array_filter(explode(' ',
            str_replace(',', ' ', $this->settings()->get('downloadmanager_debug_disable_encryption_sites'))
        ));
        if ($debugSites) {
            $site = $this->currentSite();
            // Sometime, there is no site (api call), so check user main site.
            if (!$site) {
                $userRepresentation = $this->api()->read('users', $user->getId())->getContent();
                $sitePermissions = $userRepresentation->sitePermissions();
                $role = $userRepresentation->role();
                $hasSite = $sitePermissions && $role === \GuestUser\Permissions\Acl::ROLE_GUEST;
                if ($hasSite) {
                    $sitePermission = reset($sitePermissions);
                    /** \Omeka\Api\Representation\SiteRepresentation $site */
                    $site = $this->api()->read('sites', $sitePermission->site()->id())->getContent();
                }
            }
            if ($site && in_array($site->slug(), $debugSites)) {
                return true;
            }
        }

        $debugGroups = array_filter(explode(' ',
            str_replace(',', ' ', $this->settings()->get('downloadmanager_debug_disable_encryption_groups'))
        ));
        if ($debugGroups) {
            $groups = $this->api()
                ->search('groups', ['user_id' => $user->getId()], ['returnScalar' => 'name'])
                ->getContent();
            if ($groups && array_intersect($debugGroups, $groups)) {
                return true;
            }
        }

        return false;
    }

    protected function getSamplePages(AbstractResourceRepresentation $resource)
    {
        $result = trim($resource->value('download:samplePages', ['type' => 'literal', 'all' => false]));
        return $result;
    }

    /**
     * Helper to get the media object from the filename (storage id).
     *
     * @param string $filename Omeka filename (storage id).
     * @param bool $withExtension Indicates that the filename has its extension.
     * @return MediaRepresentation|null
     */
    protected function findMediaFromFilename($filename, $withExtension = true)
    {
        // Manage names with "/".
        if ($withExtension) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $basename = strlen($extension) ? substr($filename, 0, strrpos($filename, '.')) : $filename;
        } else {
            $basename = $filename;
        }
        // A query is required, because the api doesn't manage search of storage_id
        // or extension.
        // $params['storage_id'] = $basename;
        // $params['extension'] = $extension;
        // $params['limit'] = 1;
        // $media = $this->api()->search('media', $params)->getContent();
        // if ($media) {
        //     return reset($media);
        // }
        $conn = $this->entityManager->getConnection();
        $qb = $conn->createQueryBuilder()
            ->select('id')
            ->from('media', 'media')
            ->where('media.storage_id = :storage_id')
            ->setParameter(':storage_id', $basename)
            ->setMaxResults(1);
        if ($withExtension) {
            $qb
                ->andWhere('media.extension = :extension')
                ->setParameter(':extension', $extension);
        }
        $stmt = $conn->executeQuery($qb, $qb->getParameters());
        $result = $stmt->fetch(\PDO::FETCH_COLUMN);
        if ($result) {
            $media = $this->api()->read('media', $result)->getContent();
            return $media;
        }
    }

    /**
     * Helper to return a raw file managed by Omeka.
     *
     * This intermediate method may be used for managed or non-managed files.
     *
     * @param string $filename
     * @param string $type May be original, medium, etc.
     * @param string $mediaType May be set for quick process when known.
     * @param string $pages
     * @param bool $isSample
     * @param MediaRepresentation $media
     * @return \Zend\View\Model\ViewModel|\Zend\Http\Response
     */
    protected function returnRawFile(
        $filename,
        $type,
        $mediaType = null,
        $pages = null,
        $isSample = false,
        MediaRepresentation $media = null
    ) {
        if (is_null($mediaType)) {
            if ($type === 'original') {
                if (empty($media)) {
                    $media = $this->findMediaFromFilename($filename);
                    if (empty($media)) {
                        $message = 'Media not found.'; // @translate
                        return $this->viewError($message, Response::STATUS_CODE_404);
                    }
                }
                $mediaType = $media->mediaType();
            }
            // For now, Media Quality creates only pdf.
            // TODO Get the true media type from a media quality and a filename.
            elseif (in_array($type, $this->protectedFileTypes)) {
                $mediaType = 'application/pdf';
            }
            // Derivative are always image/jpg, so avoids a query.
            else {
                $mediaType = 'image/jpeg';
            }
        }
        return $this->returnFile($filename, $type, $mediaType, $media, null, false, null, $pages, $isSample);
    }

    protected function returnEncryptedFile(
        MediaRepresentation $media,
        User $user,
        DownloadRepresentation $download,
        $type,
        $pages = null,
        $isSample = false
    ) {
        return $this->returnFile($media->filename(), $type, $media->mediaType(), $media, $user, true, $download, $pages, $isSample);
    }

    /**
     * Helper to return a file managed by Omeka.
     *
     * This intermediate method may be used for managed or non-managed files.
     *
     * Files are saved locally and a redirect may be used.
     *
     * @param string $filename
     * @param string $type May be original, medium, etc.
     * @param string $mediaType
     * @param MediaRepresentation $media
     * @param User $user
     * @param bool $protect
     * @param DownloadRepresentation $download
     * @param string|int $pages
     * @param bool $isSample
     * @return \Zend\View\Model\ViewModel|\Zend\Http\Response
     */
    protected function returnFile(
        $filename,
        $type,
        $mediaType,
        MediaRepresentation $media = null,
        User $user = null,
        $protect = false,
        DownloadRepresentation $download = null,
        $pages = null,
        $isSample = false
    ) {
        // $apiVersion = $this->params()->fromQuery('api');
        // $oldApi = empty($apiVersion) || version_compare($apiVersion, '3.2', '<');
        // TODO Remove code that manages old api. Note: the page range still uses the old api.
        $oldApi = false;

        if ($user && !$this->useUniqueKeys($user)) {
            $oldApi = true;
        }

        // TODO Remove the hard coded "/files" (see base_uri in config) and "/p".
        $baseFolder = '/files';
        $accessFolder = DetermineAccessPath::ACCESS_PATH;
        $pagesFolder = 'p';

        // Check if the file exists really.
        $baseDir = $this->basePath . '/' . $type;
        $filepath = $baseDir . '/' . $filename;

        $mode = 'inline';

        $isRawFile = !$protect || empty($download) || empty($media) || empty($user);
        $isExtract = $pages || $isSample;

        // TODO Remove this bypass, required for now because the 302 is not managed by third party app.
        if ($isExtract) {
            $oldApi = true;
        }

        // Check the original file.
        if (!file_exists($filepath) || !is_readable($filepath)) {
            $message = $media
                ? new Message('File not found (%s, media #%d)', $filepath, $media->id()) // @translate
                : new Message('File not found (%s)', $filepath); // @translate
            $this->logger()->err($message);
            $message = 'File not found.'; // @translate
            return $this->viewError($message, Response::STATUS_CODE_404);
        }

        // A security.
        if ($filepath !== realpath($filepath)) {
            $message = $media
                ? new Message('Incorrect filepath (%s, media #%d)', $filepath, $media->id()) // @translate
                : new Message('Incorrect filepath (%s)', $filepath); // @translate
            $this->logger()->err($message);
            $message = 'Unauthorized access to file.'; // @translate;
            return $this->viewError($message, Response::STATUS_CODE_403);
        }

        $headers = [];

        $tempBasePath = $this->determineAccessPath($media, $type, $user, $pages, $isRawFile, $filename);
        $tempPath = $this->basePath . DIRECTORY_SEPARATOR . $tempBasePath;
        if (!$oldApi && file_exists($tempPath)) {
            if (is_readable($tempPath) && filesize($tempPath)) {
                $basePathHelper = $this->viewHelpers()->get('basePath');
                $location = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME']
                    . $basePathHelper() . $baseFolder . '/' . $tempBasePath;
                $documentKey = $isRawFile ? null : $this->createDocumentKey($media, $download);
                $headers['Authorization'] = 'Basic ' . base64_encode($documentKey);
                if ($download && $download->expire()) {
                    $headers['Expires'] = $download->expire()->format('D, d M Y H:i:s \G\M\T');
                }
                $headers['Location'] = $location;
                $removeFile = false;
                if (!empty($GLOBALS['globalIsTest'])) {
                    $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
                    $this->logger()->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
                }
                return $this->sendFile($tempPath, $mediaType, $mode, $filename, $headers, $removeFile);
            }

            @unlink($tempPath);
            if (file_exists($tempPath)) {
                $message = 'Error when preparing file.'; // @translate;
                $this->logger()->err(new Message(
                    'Error when removing file %s.', // @translate
                    $tempPath));
                return $this->viewError($message, Response::STATUS_CODE_500);
            }
        }
        // The file doesn't exist, so prepare it if needed. It will be a
        // hard-link or a saved temp file.

        if ($isExtract) {
            // Get the burst directory.
            $dirpathBurst = $this->basePath
                . DIRECTORY_SEPARATOR . $accessFolder
                . DIRECTORY_SEPARATOR . $pagesFolder
                . DIRECTORY_SEPARATOR . $media->id()
                . DIRECTORY_SEPARATOR . $type;
            $filepathPages = $this->extractPages($filepath, $pages, $dirpathBurst);
            if (empty($filepathPages)) {
                $message = 'Error when extracting pages.'; // @translate;
                $this->logger()->err(new Message(
                    'Error when extracting pages "%s" for file %s.', // @translate
                    $pages, $filepath));
                return $this->viewError($message, Response::STATUS_CODE_500);
            }
            $filepath = $filepathPages;
        }

        // Sign and encrypt the file if required and if possible.
        if (!$isRawFile) {
            // The encryption is made with the current user key.
            $result = $this->protectFile($filepath, $media, $user, $isExtract);

            $isRawFile = is_null($result);
            if ($isExtract && !$isRawFile) {
                @unlink($filepathPages);
            }

            if (is_array($result)) {
                $sendClear = $this->settings()->get('downloadmanager_send_unencryptable');
                if (!$sendClear) {
                    return $this->jsonError($result['message'], Response::STATUS_CODE_403);
                }
                $isRawFile = true;
            }

            if (!$isRawFile) {
                $filepath = $result;
            }
            // Else keep the current filepath. In most cases, an error occurs
            // above (file already protected), else send raw file.
        }

        // Send the file directly via php.
        if ($oldApi) {
            // This is an original file, or a specific thumbnail, that is not
            // protectable or not to be protected.
            if ($isRawFile) {
                $documentKey = null;
                $removeFile = $isExtract;
            }
            // Else this is an original crypted file.
            else {
                $documentKey = $this->createDocumentKey($media, $download);
                $removeFile = true;
            }
            $tempPath = $filepath;
            $location = null;
            $filepath = null;
            $result = true;
        }
        // Make the file available via the web server.
        else {
            // Prepare the folder where the encrypted file will be saved.
            $dirPath = dirname($tempPath);
            $this->prepareDir($dirPath);

            // This is an original file, or a specific thumbnail, that is not
            // protectable or not to be protected, so hard link it.
            // It may avoid the loop too with the htaccess rules that redirect
            // original files here.
            if ($isRawFile) {
                $documentKey = null;
                if ($isExtract) {
                    $result = rename($filepath, $tempPath);
                }
                // Even if open, the htaccess redirect here, so the file should be
                // hard linked to be sent directly.
                else {
                    $result = symlink($filepath, $tempPath);
                }
            }
            // Else this is an original crypted file, so it's saved for a future use.
            else {
                $documentKey = $this->createDocumentKey($media, $download);
                $result = rename($filepath, $tempPath);
            }

            if (!$result) {
                $message = 'Error when preparing the file to access.'; // @translate;
                $this->logger()->err(new Message(
                    'Error when preparing file "%s" (%s, %s, Type: %s, Media: %s, Download: %s).', // @translate
                    $filepath,
                    $isRawFile ? 'raw file' : 'encrypted file', // @translate
                    $isExtract ? 'extract' : 'link', //  @translate
                    $type,
                    $media ? ' #' . $media->id() : 'unknown', // @translate
                    $download ? '#' . $download->id() : 'none' // @translate
                ));
                return $this->viewError($message, Response::STATUS_CODE_500);
            }

            // Url base path.
            $basePathHelper = $this->viewHelpers()->get('basePath');
            $location = $basePathHelper() . $baseFolder . '/' . $tempBasePath;
            $removeFile = false;
        }

        if (!empty($documentKey)) {
            $headers['Authorization'] = 'Basic '. base64_encode($documentKey);
        }
        if ($download && $download->expire()) {
            $headers['Expires'] = $download->expire()->format('D, d M Y H:i:s \G\M\T');
        }
        if (!empty($location)) {
            $headers['Location'] = $location;
        }

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $this->logger()->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
        }
        return $this->sendFile($tempPath, $mediaType, $mode, $filename, $headers, $removeFile);
    }

    /**
     * Prepare a directory.
     *
     * @param string $dirPath
     */
    protected function prepareDir($dirPath)
    {
        if (file_exists($dirPath)) {
            return;
        }
        $result = mkdir($dirPath, 0775, true);
        if (!$result) {
            $message = 'Error when preparing the folder to access.'; // @translate;
            $this->logger()->err(new Message(
                'Error when preparing folder "%s".', // @translate
                $dirPath));
            return $this->viewError($message, Response::STATUS_CODE_500);
        }
        @chown($dirPath, $this->systemOwner);
        @chgrp($dirPath, $this->systemGroup);
    }

    /**
     * Redirect to login via Saml or Guest user or normal route.
     *
     * @todo The redirect to Saml should be used only for the action user-key.
     * This point is currently useless, because redirectToLogin() is only used
     * by userKeyAction(). See View\Helper\UrlLogin too.
     *
     * @param string $action
     * @return \Zend\Http\Response
     */
    protected function redirectToLogin($action = null, $noSaml = false)
    {
        $isModuleActive = $this->viewHelpers()->get('isModuleActive');
        if ($isModuleActive('Saml') && $this->siteSettings()->get('saml_enabled', false)) {
            $options = $action
                ? ['query' => ['redirect' => $this->url()->fromRoute(null, ['action' => $action], true)]]
                : [];
            return $this->redirect()->toRoute('site/saml',
                ['site-slug' => $this->params('site-slug'), 'action' => 'login'],
                $options
            );
        } elseif ($isModuleActive('GuestUser')) {
            return $this->redirect()->toRoute('site/guest-user',
                ['site-slug' => $this->params('site-slug'), 'action' => 'login']
            );
        } else {
            return $this->redirect()->toRoute('login');
        }
    }

    /**
     * Helper to return a message of error as json.
     *
     * @param string $messageId
     * @param int $statusCode
     * @return \Zend\View\Model\JsonModel
     */
    protected function jsonError($messageId, $statusCode = null)
    {
        $messages = [
            'unidentified' => [
                'status' => Response::STATUS_CODE_403,
                'message' => 'Unidentified.', // @translate
            ],
            'appRequireUpgrade' => [
                'status' => Response::STATUS_CODE_400,
                'message' => 'An upgrade of the app is required.', // @translate
            ],
            'termsNotAgreed' => [
                'status' => Response::STATUS_CODE_403,
                'message' => 'The user didn’t approve the terms and conditions.', // @translate
            ],
            'userIsNotOwner' => [
                'status' => Response::STATUS_CODE_403,
                'message' => 'The current user is not the owner.', // @translate
            ],
            'userDontNeedServerKey' => [
                'status' => Response::STATUS_CODE_400,
                'message' => 'The current user doesn’t need a server key.', // @translate
            ],
            'downloadNotYetAvailable' => [
                'status' => Response::STATUS_CODE_400,
                'message' => 'The server did not seem ready: try again from item page.', // @translate
            ],
            'unauthorized' => [
                'status' => Response::STATUS_CODE_403,
                'message' => 'Unauthorized access.', // @translate
            ],
            'notFound' => [
                'status' => Response::STATUS_CODE_404,
                'message' => 'Not found.', // @translate
            ],
            'noFile' => [
                'status' => Response::STATUS_CODE_400,
                'message' => 'No file.', // @translate
            ],
            'notEbook' => [
                'status' => Response::STATUS_CODE_400,
                'message' => 'Not an ebook.', // @translate
            ],
            'missingParameter' => [
                'status' => Response::STATUS_CODE_403,
                'message' => 'Missing parameter.', // @translate
            ],
            'keyNotFound' => [
                'status' => Response::STATUS_CODE_400,
                'message' => 'Key not found.', // @translate
            ],
            'expiredLink' => [
                'status' => Response::STATUS_CODE_403,
                'message' => 'Expired link.', // @translate
            ],
            'missingEmail' => [
                'status' => Response::STATUS_CODE_403,
                'message' => 'Missing email.', // @translate
            ],
            'notDownloaded' => [
                'status' => Response::STATUS_CODE_403,
                'message' => 'Document not downloaded.', // @translate
            ],
            'noApiKey' => [
                'status' => Response::STATUS_CODE_403,
                'message' => 'No api key.', // @translate
            ],
            'internal' => [
                'status' => Response::STATUS_CODE_500,
                'message' => 'An internal error occurred.', // @translate
            ],
        ];

        if (isset($messages[$messageId])) {
            $message = $messages[$messageId]['message'];
            $statusCode = $messages[$messageId]['status'];
        } else {
            $message = $messageId;
            $statusCode = $statusCode?: Response::STATUS_CODE_500;
        }

        $response = $this->getResponse();
        $response->setStatusCode($statusCode);
        return new JsonModel([
            'result' => 'error',
            'message' => $message,
        ]);
    }

    /**
     * Helper to return a message of error as normal view.
     *
     * @param string $message
     * @param string $statusCode
     * @return \Zend\View\Model\ViewModel
     */
    protected function viewError($message, $statusCode)
    {
        $response = $this->getResponse();
        $response->setStatusCode($statusCode);
        $view = new ViewModel;
        $view->setTemplate('download-manager/site/download/error');
        $view->setVariable('message', $message);
        return $view;
    }

    /**
     * Check if a request is done via an external application, specified in the
     * config.
     *
     * @todo Factorize. Copied from GuestUserController.
     *
     * @return bool
     */
    protected function isExternalApp()
    {
        $requestedWith = $this->params()->fromHeader('X-Requested-With');
        if (empty($requestedWith)) {
            return false;
        }

        $checkRequestedWith = $this->settings()->get('guestuser_check_requested_with');
        if (empty($checkRequestedWith)) {
            return false;
        }

        $requestedWith = $requestedWith->getFieldValue();
        return strpos($requestedWith, $checkRequestedWith) === 0;
    }
}
