<?php

namespace DownloadManagerTest\Controller;

use OmekaTestHelper\Controller\OmekaControllerTestCase;

class DownloadControllerTest extends OmekaControllerTestCase
{
    public $pdfSample = 'http://www.stluciadance.com/prospectus_file/sample.pdf';

    protected $site;
    protected $item;
    protected $guest1;
    protected $guest2;

    public function setUp()
    {
        parent::setUp();

        $this->loginAsAdmin();
        $this->site = $this->createSite('test', 'test');
        $this->item = $this->createUrlItem('Item #1');
        $this->guest1 = $this->createUser('guest1@bar.com', 'guest1', 'guest');
        $this->guest2 = $this->createUser('guest2@bar.com', 'guest2', 'guest');
    }

    public function tearDown()
    {
        $this->loginAsAdmin();
        $downloadItems = $this->api()->search('downloads')->getContent();
        foreach ($downloadItems as $downloadItem) {
            $this->api()->delete('downloads', $downloadItem->id());
        }
        $this->api()->delete('items', $this->item->id());
        $this->api()->delete('sites', $this->site->id());
        $this->api()->delete('users', $this->guest1->id());
        $this->api()->delete('users', $this->guest2->id());
    }

    /**
     * @test
     */
    public function itemAction()
    {
//             $this->dispatch('/s/test/download/add/' . $this->item->id());

//             $downloadItems = $this->api()->search('downloads', [
//                 'resource_id' => $this->item->id(),
//                 'owner_id' => $this->user->id(),
//             ])->getContent();

//             $this->assertCount(1, $downloadItems);
    }

//     /**
//      * @test
//      */
//     public function additemToDownloadShouldStoreDownloadForUser()
//     {
//         $this->dispatch('/s/test/download/add/' . $this->item->id());

//         $downloadItems = $this->api()->search('downloads', [
//             'resource_id' => $this->item->id(),
//             'owner_id' => $this->user->id(),
//         ])->getContent();

//         $this->assertCount(1, $downloadItems);
//     }

//     /**
//      * @test
//      */
//     public function addmediaToDownloadShouldStoreDownloadForUSer()
//     {
//         $media = $this->item->primaryMedia();
//         $this->dispatch('/s/test/download/add/' . $media->id());

//         $downloadItems = $this->api()->search('downloads', [
//             'resource_id' => $media->id(),
//             'owner_id' => $this->user->id(),
//         ])->getContent();

//         $this->assertCount(1, $downloadItems);
//     }

//     /**
//      * @test
//      */
//     public function addExistingItemShouldNotUpdateDownload()
//     {
//         $this->addToDownload($this->item);
//         $this->dispatch('/s/test/download/add/' . $this->item->id());

//         $downloadItems = $this->api()->search('downloads', [
//             'owner_id' => $this->user->id(),
//         ])->getContent();

//         $this->assertCount(1, $downloadItems);
//     }

//     /**
//      * @test
//      */
//     public function removeItemToDownloadShouldRemoveDownloadForUser()
//     {
//         $this->addToDownload($this->item);
//         $this->dispatch('/s/test/download/delete/' . $this->item->id());
//         $this->assertResponseStatusCode(200);

//         $downloadItems = $this->api()->search('downloads', [
//             'owner_id' => $this->user->id(),
//         ])->getContent();

//         $this->assertEmpty($downloadItems);
//     }

//     /**
//      * @test
//      */
//     public function displayDownloadShouldDisplayItems()
//     {
//         $this->addToDownload($this->item);
//         $this->dispatch('/s/test/download');
//         $this->assertXPathQueryContentContains('//h4', 'First Item');
//     }

//     /**
//      * @test
//      */
//     public function displayDownloadShouldDisplayMedia()
//     {
//         $media = $this->item->primaryMedia();
//         $this->addToDownload($media);
//         $this->dispatch('/s/test/download');
//         $this->assertResponseStatusCode(200);
//         $this->assertQueryContentRegex('.property .value', '/media1/');
//     }

    protected function createUrlItem($title)
    {
        $item = $this->api()->create('items', [
            'dcterms:identifier' => [
                [
                    'type' => 'literal',
                    'property_id' => '10',
                    '@value' => 'item1',
                ],
            ],
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => '1',
                    '@value' => $title,
                ],
            ],
            'o:media' => [
                [
                    'o:ingester' => 'url',
                    'ingest_url' => $this->pdfSample,
                    'dcterms:identifier' => [
                        [
                            'type' => 'literal',
                            'property_id' => 10,
                            '@value' => 'Pdf Sample',
                        ],
                    ],
                ],
            ],
        ])->getContent();

        return $item;
    }

    protected function createSite($slug, $title)
    {
        $site = $this->api()->create('sites', [
            'o:slug' => $slug,
            'o:theme' => 'default',
            'o:title' => $title,
            'o:is_public' => '1',
        ])->getContent();

        return $site;
    }

    protected function createUser($login, $password, $role = 'global_admin')
    {
        $user = $this->api()->create('users', [
            'o:email' => $login,
            'o:name' => $login,
            'o:role' => $role,
            'o:is_active' => true,
        ])->getContent();

        $em = $this->getEntityManager();
        $userEntity = $em->find('Omeka\Entity\User', $user->id());
        $userEntity->setPassword($password);
        $em->flush();

        return $user;
    }

    protected function addToDownload($resource)
    {
        $this->api()->create('downloads', [
            'o:resource' => $resource->id(),
            'o:owner' => $this->user->id(),
        ]);
    }
}
