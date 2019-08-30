<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use mikehaertl\pdftk\Pdf;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class ExtractPages extends AbstractPlugin
{
    /**
     * Extract pages of a pdf (via the original pdf of via the prepared burst).
     *
     * @param string $filepath
     * @param string|int $pages A range or a number.
     * @param string $dirpath
     * @return string|null The filepath if succeed.
     */
    public function __invoke($filepath, $pages, $dirpath = null)
    {
        if (!file_exists($filepath) || !filesize($filepath) || !is_readable($filepath)) {
            return;
        }

        $numberOfPages = $this->numberOfPages($filepath);
        if (empty($numberOfPages)) {
            return;
        }

        if (strpos($pages, '-') !== false) {
            list($start, $end) = explode('-', $pages);
            if (empty($start)) {
                $start = 1;
            }
        } else {
            $start = $pages;
            $end = null;
        }

        $outputpath = tempnam(sys_get_temp_dir(), 'omk_pdf_');

        $result = $this->concatenate($dirpath, $filepath, $start, $end, $outputpath);
        if (!$result) {
            $result = $this->extract($filepath, $start, $end, $outputpath);
        }

        return $result;
    }

    /**
     * Extract the specified pages of a pdf.
     *
     * @param string $filepath
     * @param string|int $start
     * @param string|int $end
     * @param string $outputpath
     * @return void|string
     */
    protected function extract($filepath, $start, $end, $outputpath)
    {
        $pdf = new Pdf($filepath);
        $pdf->cat($start, $end);

        $result = $pdf->saveAs($outputpath);
        if (!$result) {
            if (file_exists($outputpath)) {
                unlink($outputpath);
            }
            return;
        }

        return $outputpath;
    }

    /**
     * Concatenate the specified pages of a pdf.
     *
     * @param string $dirpath
     * @param string $filepath
     * @param string|int $start
     * @param string|int $end
     * @param string $outputpath
     * @return void|string
     */
    protected function concatenate($dirpath, $filepath, $start, $end, $outputpath)
    {
        if (!$dirpath || !file_exists($dirpath) || !is_dir($dirpath) || !is_readable($dirpath)) {
            return;
        }

        $files = scandir($dirpath);
        if (!$files) {
            return;
        }

        if (empty($end)) {
            $end = $start;
        } elseif ($end === 'end') {
            $end = $this->numberOfPages($filepath);
        }

        $filename = pathinfo($filepath, PATHINFO_FILENAME);
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        $dotExtension = strlen($extension) ? '.' . $extension : '';

        $allPagesReady = true;
        $filesToConcatenate = [];

        for ($i = $start; $i <= $end; $i++) {
            $fileToAppend = $filename . '-' . $i . $dotExtension;
            if (!in_array($fileToAppend, $files)) {
                $allPagesReady = true;
                break;
            }
            $filesToConcatenate[] = $dirpath . DIRECTORY_SEPARATOR . $fileToAppend;
        }
        if (!$allPagesReady) {
            return;
        }

        $pdf = new Pdf($filepath);
        $letter = 'A';
        foreach ($filesToConcatenate as $fileToAppend) {
            $pdf->addFile($fileToAppend, $letter++);
        }

        $result = $pdf->saveAs($outputpath);
        if (!$result) {
            if (file_exists($outputpath)) {
                unlink($outputpath);
            }
            return;
        }

        return $outputpath;
    }

    protected function numberOfPages($filepath)
    {
        static $numberPages = [];

        if (!isset($numberPages[$filepath])) {
            $result = 0;
            $pdf = new Pdf($filepath);
            $data = (string) $pdf->getData();
            if ($data) {
                $regex = '~^NumberOfPages: (\d+)$~m';
                $matches = [];
                preg_match($regex, $data, $matches);
                if ($matches[1]) {
                    $result = $matches[1];
                }
            }
            $numberPages[$filepath] = $result;
        }

        return $numberPages[$filepath];
    }
}
