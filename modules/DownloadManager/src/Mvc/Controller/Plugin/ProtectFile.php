<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Entity\User;
use Omeka\Stdlib\Message;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Controller\PluginManager;

class ProtectFile extends AbstractPlugin
{
    /**
     * @var PluginManager
     */
    protected $plugins;

    /**
     * @param PluginManager $plugins
     */
    public function __construct(PluginManager $plugins)
    {
        $this->plugins = $plugins;
    }

    /**
     * Protect a file if possible.
     *
     * @todo Currently, only pdf files are encrypted.
     *
     * @param string $sourcePath May be the media filepath or a temp path.
     * @param MediaRepresentation $media
     * @param User $user
     * @param bool $isExtract When true, don't append sign block in last page.
     * @return string|array|null The filepath if needed, or an array if error.
     * Null is returned when there is no added protection.
     */
    public function __invoke(
        $sourcePath,
        MediaRepresentation $media,
        User $user = null,
        $isExtract = false
    ) {
        $plugins = $this->plugins;
        $settings = $plugins->get('settings');
        $settings = $settings();
        $logger = $plugins->get('logger');
        $logger = $logger();

        $isTempFile = false;

        $mediaType = $media->mediaType();
        switch ($mediaType) {
            case 'application/pdf':
                $signerPath = $settings->get('downloadmanager_signer_path');
                if (empty($signerPath)) {
                    $toSign = false;
                } else {
                    // Don't sign if there is a unique user / server key.
                    $useUniqueKeys = $plugins->get('useUniqueKeys');
                    $useUniqueKeys = empty($user) || $useUniqueKeys($user);
                    if ($useUniqueKeys) {
                        $toSign = false;
                    } else {
                        // Check to sign.
                        $isSigned = $this->isFileSigned($sourcePath);
                        if ($isSigned) {
                            $logger->notice(new Message(
                                'The file "%s" (media #%d) is already signed and cannot be signed again.', // @translate
                                    basename($sourcePath), $media->id()
                            ));
                            if (!$settings->get('downloadmanager_skip_signing_signed_file')) {
                                return [
                                    'result' => 'error',
                                    'message' => 'Unable to sign a signed file.', // @translate
                                ];
                            }
                        }
                        $certificate = $settings->get('downloadmanager_certificate_path');
                        $toSign = !$isSigned
                            && $certificate && file_exists($certificate);
                    }
                }

                // Check to encrypt.
                $masterPassword = $settings->get('downloadmanager_owner_password');
                $toEncrypt = $masterPassword && $settings->get('downloadmanager_encrypter_path');

                // Sign the pdf.
                if ($toSign) {
                    $args = [
                        $user->getName() . ' <' . $user->getEmail() . '>',
                        @$_SERVER['SERVER_NAME'],
                        (new \DateTime('now'))->format('Y-m-d H:i:s'),
                    ];
                    $options = [];
                    $options['overwrite'] = false;
                    // $options['owner_password'] = $settings->get('downloadmanager_owner_password');
                    $options['certificate_path'] = $certificate;
                    $options['pfx_password'] = $settings->get('downloadmanager_pfx_password');
                    if (!$isExtract) {
                        $options['reason'] = @vsprintf($settings->get('downloadmanager_sign_reason'), $args);
                        $options['location'] = $settings->get('downloadmanager_sign_location');
                        $options['block'] = $settings->get('downloadmanager_sign_append_block');
                        $options['comment'] = @vsprintf($settings->get('downloadmanager_sign_comment'), $args);
                        $options['image'] = $settings->get('downloadmanager_sign_image_path');
                    }
                    $signPdf = $plugins->get('signPdf');
                    $outputpath = $signPdf($sourcePath, $options);
                    if (empty($outputpath)) {
                        $logger->warn(new Message(
                            'Unable to sign file "%s" (media #%d).', // @translate
                            basename($sourcePath), $media->id()
                        ));
                        return [
                            'result' => 'error',
                            'message' => 'Unable to sign the file.', // @translate
                        ];
                    }
                    $sourcePath = $outputpath;
                    $isTempFile = true;
                }

                // Encrypt the pdf.
                if ($toEncrypt) {
                    $createCryptKey = $plugins->get('createCryptKey');
                    $userPassword = $createCryptKey($media, $user);
                    if (empty($userPassword)) {
                        $logger->warn(new Message(
                            'Unable to encrypt file "%s" (media #%d): user password empty.', // @translate
                            basename($sourcePath), $media->id()
                        ));
                        return [
                            'result' => 'error',
                            'message' => 'Unable to encrypt the file.', // @translate
                        ];
                    }

                    $options = [];
                    $options['overwrite'] = false;
                    $options['owner_password'] = $masterPassword;
                    $options['permissions'] = $settings->get('downloadmanager_pdf_permissions');
                    $options['user_password'] = $userPassword;
                    $options['encryption'] = 128;
                    $options['keepId'] = 'first';
                    $protectPdf = $plugins->get('protectPdf');
                    $outputpath = $protectPdf($sourcePath, $options);
                    if (empty($outputpath)) {
                        $logger->warn(new Message(
                            'Unable to protect file "%s" (media #%d).', // @translate
                            basename($sourcePath), $media->id()
                        ));
                        return [
                            'result' => 'error',
                            'message' => 'Unable to protect the file.', // @translate
                        ];
                    }
                    $sourcePath = $outputpath;
                    $isTempFile = true;
                }
                break;

            default:
                $logger->warn(new Message(
                    'Unable to protect file "%s" (media #%d): the media type is not managed.', // @translate
                    basename($sourcePath), $media->id()
                ));
                return [
                    'result' => 'error',
                    'message' => 'Unable to protect a file: the media type is not managed.', // @translate
                ];
        }
        if ($isTempFile) {
            return $sourcePath;
        }
    }

    /**
     * Quick check if a file (pdf) is signed or crypted.
     *
     * @todo Find a proper way to check if a signature is set.
     * @link https://stackoverflow.com/questions/36603497/php-how-to-check-if-pdf-is-digitally-signed
     *
     * @param string $filepath
     * @return bool
     */
    protected function isFileSigned($filepath)
    {
        $signed = false;
        foreach (['adbe.pkcs7.detached', '>]Encrypt ', /* '>]/Encrypt ',*/ '/Encrypt '] as $string) {
            $signed = $this->isStringInFile($filepath, $string);
            if ($signed) {
                break;
            }
        }
        return $signed;
    }

    protected function isStringInFile($filepath, $string)
    {
        $result = false;
        $handle = fopen($filepath, 'r');
        while (($buffer = fgets($handle)) !== false) {
            if (strpos($buffer, $string) !== false) {
                $result = true;
                break;
            }
        }
        fclose($handle);
        return $result;
    }
}
