<?php

namespace CoinsTest\Controller;

use OmekaTestHelper\Controller\OmekaControllerTestCase;

abstract class CoinsControllerTestCase extends OmekaControllerTestCase
{
    protected $items;
    protected $site;

    public function setUp()
    {
        parent::setUp();

        $this->loginAsAdmin();

        $response = $this->api()->create('sites', [
            'o:title' => 'Test site',
            'o:slug' => 'test',
            'o:theme' => 'default',
        ]);
        $this->site = $response->getContent();

        for ($i = 0; $i < 10; $i++) {
            $response = $this->api()->create('items', [
                'dcterms:title' => [
                    [
                        'type' => 'literal',
                        'property_id' => 1,
                        '@value' => sprintf('Test item %d', $i),
                    ],
                ],
            ]);
            $this->items[] = $response->getContent();
        }
    }

    public function tearDown()
    {
        foreach ($this->items as $item) {
            $this->api()->delete('items', $item->id());
        }
        $this->api()->delete('sites', $this->site->id());
    }
}
