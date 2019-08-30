<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use mikehaertl\pdftk\Pdf;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class ProtectPdf extends AbstractPlugin
{
    /**
     * Protect a pdf.
     *
     * @param string $filepath
     * @param array $options
     *   - overwrite (bool)
     *   - owner_password (string): The password used to set permissions
     *   - user_password (string): The password used to read the document
     *   - permissions (array|string): List or comma or space separated values:
     *     Printing, DegradedPrinting, ModifyContents, Assembly, CopyContents,
     *     ScreenReaders, ModifyAnnotations, FillIn, AllFeatures)
     *     Default to none if the owner password is set (so user can only read).
     *   - encryption (int): 40 or 128 bits (default)
     *   - keep_id (string): "first", "last" or "new" (default)
     * @return string|null The filepath if succeed.
     */
    public function __invoke($filepath, $options = [])
    {
        if (!file_exists($filepath) || !filesize($filepath) || !is_readable($filepath)) {
            return;
        }

        if (!empty($options['overwrite']) && !is_writeable($filepath)) {
            return;
        }

        $pdf = new Pdf($filepath);

        if (!empty($options['owner_password'])) {
            $pdf->setPassword($options['owner_password']);
            if (!empty($options['permissions'])) {
                $permissions = is_string($options['permissions'])
                    ? array_filter(array_map('trim', explode(' ', str_replace(',', ' ', $options['permissions']))))
                    : $options['permissions'];
                foreach ($permissions as $permission) {
                    $pdf->allow($permission);
                }
            }
        }

        if (!empty($options['user_password'])) {
            $pdf->setUserPassword($options['user_password']);
        }

        if (!empty($options['owner_password']) || !empty($options['user_password'])) {
            if (!empty($options['encryption'])) {
                $encryption = $options['encryption'] == 40 ? 40 : 128;
                $pdf->passwordEncryption($encryption);
            }
        }

        if (!empty($options['keep_id']) && in_array($options['keep_id'], ['first', 'last'])) {
            $pdf->keepId($options['keep_id']);
        }

        $outputpath = tempnam(sys_get_temp_dir(), 'omk_pdf_');

        $result = $pdf->saveAs($outputpath);
        if (!$result) {
            if (file_exists($outputpath)) {
                unlink($outputpath);
            }
            return;
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
