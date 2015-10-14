<?php

namespace Drupal\Tests\lingotek\Unit\Remote;

use Drupal\lingotek\Entity\LingotekProfile;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\Remote\LingotekApi;
use Drupal\lingotek\Remote\LingotekHttp;
use Drupal\lingotek\Remote\LingotekHttpInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\lingotek\Remote\LingotekApi
 * @group lingotek
 * @preserveGlobalState disabled
 */

class LingotekApiUnitTest extends UnitTestCase {

  /**
   * @var \Drupal\lingotek\Remote\LingotekApi
   */
  protected $lingotek_api;

  /**
   * @var \Drupal\lingotek\Remote\LingotekHttpInterface
   */
  protected $client;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->client = $this->getMockBuilder('\Drupal\lingotek\Remote\LingotekHttpInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $this->lingotek_api = new LingotekApi($this->client);
  }

  /**
   * @covers ::getVaults
   */
  public function testGetVaults() {
    // Ensure that the limit is set and the community_id is ignored.
    $response = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $this->client->expects($this->once())
      ->method('get')
      ->with('/api/vault', ['limit' => 100])
      ->will($this->returnValue($response));

    $this->lingotek_api->getVaults('community_id');
  }

}
