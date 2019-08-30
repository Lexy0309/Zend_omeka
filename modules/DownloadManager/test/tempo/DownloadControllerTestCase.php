<?php

namespace DownloadManagerTest\Controller;

use OmekaTestHelper\Controller\OmekaControllerTestCase;

abstract class DownloadControllerTestCase extends OmekaControllerTestCase
{
    public function setUp()
    {
        $this->loginAsAdmin();
    }
}
