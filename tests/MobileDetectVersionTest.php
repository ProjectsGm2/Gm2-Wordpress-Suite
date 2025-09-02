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

class MobileDetectVersionTest extends TestCase {
    public function test_prepare_version_strips_non_digit_minor() {
        $detect = new MobileDetect();
        $this->assertSame(1.2, $detect->prepareVersionNo('1.2.beta'));
    }

    public function test_prepare_version_returns_zero_for_invalid() {
        $detect = new MobileDetect();
        $this->assertSame(0.0, $detect->prepareVersionNo('beta'));
    }

    public function test_prepare_version_compacts_three_part_version() {
        $detect = new MobileDetect();
        $this->assertSame(1.23, $detect->prepareVersionNo('1.2.3'));
    }
}
