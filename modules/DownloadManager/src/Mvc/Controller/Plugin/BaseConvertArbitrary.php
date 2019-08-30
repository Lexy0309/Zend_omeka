<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class BaseConvertArbitrary extends AbstractPlugin
{
    /**
     * Convert a number between bases.
     *
     * @link https://stackoverflow.com/questions/5301034/how-to-generate-random-64-bit-value-as-decimal-string-in-php/5302533#5302533
     *
     * @param string $number
     * @param int $fromBase
     * @param int $toBase
     * @return string
     */
    public function __invoke($number, $fromBase, $toBase)
    {
        $digits = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $length = strlen($number);
        $result = '';

        $nibbles = [];
        for ($i = 0; $i < $length; ++$i) {
            $nibbles[$i] = strpos($digits, $number[$i]);
        }

        do {
            $value = 0;
            $newlen = 0;
            for ($i = 0; $i < $length; ++$i) {
                $value = $value * $fromBase + $nibbles[$i];
                if ($value >= $toBase) {
                    $nibbles[$newlen++] = (int) ($value / $toBase);
                    $value %= $toBase;
                } elseif ($newlen > 0) {
                    $nibbles[$newlen++] = 0;
                }
            }
            $length = $newlen;
            $result = $digits[$value].$result;
        } while ($newlen != 0);

        return $result;
    }
}
