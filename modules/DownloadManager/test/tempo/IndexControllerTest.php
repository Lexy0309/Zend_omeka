<?php

namespace DownloadManagerTest\Controller;

class IndexControllerTest extends DownloadControllerTestCase
{
    public function testIndexActionCanBeAccessed()
    {
        $this->dispatch('/admin/download');
        $this->assertResponseStatusCode(200);
    }

    public function testIndexActionCannotBeAccessedInPublic()
    {
        $this->dispatch('/download');
        $this->assertResponseStatusCode(404);
    }
}
