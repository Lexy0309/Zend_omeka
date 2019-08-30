<?php
namespace MediaQuality\View\Helper;

use Zend\View\Helper\AbstractHelper;

class FormatDigitalUnits extends AbstractHelper
{
    /**
     * Convert a file size to a human size.
     *
     * @param int $bytes
     * @param int $decimals
     * @param bool $systemInternational
     * @return string
     */
    public function __invoke($bytes, $decimals = 0, $systemInternational = true)
    {
        if ($systemInternational) {
            if ($bytes >= 1000000000) {
                $bytes = number_format($bytes / 1000000000, $decimals);
                $unit = $this->getView()->translate('GB'); // @translate
            } elseif ($bytes >= 1000000) {
                $bytes = number_format($bytes / 1000000, $decimals);
                $unit = $this->getView()->translate('MB'); // @translate
            } elseif ($bytes >= 1000) {
                $bytes = number_format($bytes / 1000, $decimals);
                $unit = $this->getView()->translate('KB'); // @translate
            } elseif ($bytes > 1) {
                $bytes = $bytes;
                $unit = $this->getView()->translate('bytes'); // @translate
            } elseif ($bytes == 1) {
                $unit = $this->getView()->translate('byte'); // @translate
            } else {
                $unit = $this->getView()->translate('bytes'); // @translate
            }
        } else {
            if ($bytes >= 1073741824) {
                $bytes = number_format($bytes / 1073741824, $decimals);
                $unit = $this->getView()->translate('GiB'); // @translate
            } elseif ($bytes >= 1048576) {
                $bytes = number_format($bytes / 1048576, $decimals);
                $unit = $this->getView()->translate('MiB'); // @translate
            } elseif ($bytes >= 1024) {
                $bytes = number_format($bytes / 1024, $decimals);
                $unit = $this->getView()->translate('KiB'); // @translate
            } elseif ($bytes > 1) {
                $bytes = $bytes;
                $unit = $this->getView()->translate('bytes'); // @translate
            } elseif ($bytes == 1) {
                $unit = $this->getView()->translate('byte'); // @translate
            } else {
                $unit = $this->getView()->translate('bytes'); // @translate
            }
        }
        return sprintf('%s %s', $bytes, $unit);
    }
}
