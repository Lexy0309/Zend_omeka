<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Omeka\Stdlib\Cli;
use Omeka\Stdlib\Message;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class SignPdf extends AbstractPlugin
{
    protected $cli;
    protected $signCommand;

    public function __construct(Cli $cli, $signCommand)
    {
        $this->cli = $cli;
        $this->signCommand = $signCommand;
    }

    /**
     * Sign a pdf with a certificate.
     *
     * @param string $filepath
     * @param array $options
     *   - overwrite (bool)
     *   - owner_password (string)
     *   - certificate_path (string)
     *   - pfx_password (string)
     *   - reason (string)
     *   - location (string)
     *   - block (string)
     *   - comment (string)
     *   - image (string)
     *   - finalize_signature (bool)
     * @return string|null The filepath if succeed.
     */
    public function __invoke($filepath, $options = [])
    {
        if (empty($this->signCommand)) {
            return;
        }

        if (empty($options['certificate_path'])
            || !file_exists($options['certificate_path'])
            || !is_readable($options['certificate_path'])
        ) {
            return;
        }

        $logger = $this->getController()->logger();
        if (!file_exists($filepath) || !filesize($filepath) || !is_readable($filepath)) {
            $logger->err(new Message('Unable to sign: Filepath "%s" empty or not readable.', $filepath));
            return;
        }

        if (!empty($options['overwrite']) && !is_writeable($filepath)) {
            $logger->err(new Message('Unable to sign: Filepath "%s" not writeable.', $filepath));
            return;
        }

        // PortableSigner -n -t input.pdf -o output.pdf  -ownerpwd pdfpassword -s certificate.pfx -p password -r "Reason" -l "Location" -b en -c "Comment" -i image.png

        $args = [];
        $args[] = '-n';
        $args[] = '-t ' . escapeshellarg($filepath);
        $outputpath = tempnam(sys_get_temp_dir(), 'omk_pdf_');
        $args[] = '-o ' . escapeshellarg($outputpath);
        // The encryption is done after the signature, so the master password is
        // useless for now, except if the original is encrypted already.
        if (!empty($options['owner_password'])) {
            $args[] = ' -ownerpwd ' . escapeshellarg($options['owner_password']);
        }
        if (!empty($options['finalize_signature'])) {
            // Option "-f": If this is set, the document is NOT finalized.
            $args[] = '-f';
        }
        $args[] = '-s ' . escapeshellarg($options['certificate_path']);
        $args[] = '-p ' . escapeshellarg($options['pfx_password']);
        if (!empty($options['reason'])) {
            $args[] = '-r ' . escapeshellarg($options['reason']);
        }
        if (!empty($options['location'])) {
            $args[] = '-l ' . escapeshellarg($options['location']);
        }
        if (!empty($options['block'])) {
            $args[] = '-b ' . escapeshellarg($options['block']);
        }
        if (!empty($options['comment'])) {
            $args[] = '-c ' . escapeshellarg($options['comment']);
        }
        if (!empty($options['image']) && file_exists($options['image']) && is_readable($options['image'])) {
            $args[] = '-i ' . escapeshellarg($options['image']);
        }

        $args = implode(' ', $args);
        $command = $this->signCommand . ' ' . $args;
        $this->cli->execute($command);

        if (!file_exists($outputpath)) {
            $logger->err(new Message('Unable to sign: the command failed for file "%s".', $filepath));
            return false;
        }
        if (!filesize($outputpath)) {
            $logger->err(new Message('Unable to sign: the command returned an empty file for file "%s".', $filepath));
            unlink($outputpath);
            return false;
        }

        if (!empty($options['overwrite'])) {
            // TODO For security, move the original file before removing.
            unlink($filepath);
            rename($outputpath, $filepath);
            $outputpath = $filepath;
        }

        return $outputpath;
    }
}
