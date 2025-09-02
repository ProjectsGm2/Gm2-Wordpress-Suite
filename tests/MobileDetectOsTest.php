<?php
require_once __DIR__ . '/../includes/Psr/SimpleCache/CacheInterface.php';
require_once __DIR__ . '/../includes/Psr/SimpleCache/CacheException.php';
require_once __DIR__ . '/../includes/Psr/SimpleCache/InvalidArgumentException.php';
require_once __DIR__ . '/../includes/Detection/Cache/Cache.php';
require_once __DIR__ . '/../includes/Detection/Cache/CacheException.php';
require_once __DIR__ . '/../includes/Detection/Cache/CacheInvalidArgumentException.php';
require_once __DIR__ . '/../includes/Detection/Exception/MobileDetectException.php';
require_once __DIR__ . '/../includes/Detection/Exception/MobileDetectExceptionCode.php';
require_once __DIR__ . '/../includes/MobileDetect.php';

use Detection\MobileDetect;
use PHPUnit\Framework\TestCase;

class MobileDetectOsTest extends TestCase {
    public function test_detects_meego() {
        $ua = 'Mozilla/5.0 (MeeGo; NokiaN9) AppleWebKit/534.13 (KHTML, like Gecko) NokiaBrowser/8.5.0 Mobile Safari/534.13';
        $detect = new MobileDetect(null, ['autoInitOfHttpHeaders' => false]);
        $detect->setUserAgent($ua);
        $this->assertTrue($detect->isMeeGoOS());
    }

    public function test_detects_maemo() {
        $ua = 'Opera/9.80 (Linux armv7l; Maemo; Opera Mobi/14; U; en) Presto/2.9.201 Version/11.50';
        $detect = new MobileDetect(null, ['autoInitOfHttpHeaders' => false]);
        $detect->setUserAgent($ua);
        $this->assertTrue($detect->isMaemoOS());
    }
}
