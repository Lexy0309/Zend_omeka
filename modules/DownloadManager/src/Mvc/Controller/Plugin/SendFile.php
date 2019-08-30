<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Zend\Http\Response;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Send a file as response.
 */
class SendFile extends AbstractPlugin
{
    /**
     * Helper to send file as stream or attachment.
     *
     * @url https://olegkrivtsov.github.io/using-zend-framework-3-book/html/en/Model_View_Controller/Disabling_the_View_Rendering.html
     *
     * If the specific header "Location" is set (recommended, in particular for
     * big files), let the server manages the upload, else the process is done
     * directly via php.
     *
     * @param string $filepath The path should be already checked.
     * @param string $mediaType
     * @param string $mode "inline" by default, or "attachment".
     * @param string $filename
     * @param array $headers
     * @param bool $removeFile
     * @return Response
     */
    public function __invoke(
        $filepath,
        $mediaType = 'application/octet-stream',
        $mode = 'inline',
        $filename = null,
        $specificHeaders = [],
        $removeFile = false
    ) {
        if (is_null($filename)) {
            $filename = basename($filepath);
        } else {
            $filename = basename($filename);
        }

        $controller = $this->getController();

        $fileSize = filesize($filepath);

        // Write HTTP headers.
        $response = $controller->getResponse();
        /** @var \Zend\Http\Headers $headers */
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', $mediaType);
        $headers->addHeaderLine('Content-Disposition', $mode . '; filename="' . $filename . '"');
        $headers->addHeaderLine('Content-Transfer-Encoding', 'binary');
        $headers->addHeaderLine('Content-length', $fileSize);
        $headers->addHeaderLine('Cache-control', 'private');
        $headers->addHeaderLine('Content-Description', 'File Transfer');

        $headers->addHeaders($specificHeaders);

        if (empty($specificHeaders['Location'])) {
            $fileContent = file_get_contents($filepath);
            if ($fileContent === false) {
                $response->setStatusCode(500);
                return;
            }
            $response->setContent($fileContent);
            if ($removeFile) {
                @unlink($filepath);
            }
        } else {
            $response->setStatusCode(302);
        }

        // Return Response to avoid default view rendering.
        return $response;
    }
}
