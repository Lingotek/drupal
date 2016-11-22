<?php

namespace Drupal\Tests\lingotek\Unit\Remote;

use Drupal\lingotek\Entity\LingotekProfile;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\Remote\LingotekApi;
use Drupal\lingotek\Remote\LingotekHttp;
use Drupal\lingotek\Remote\LingotekHttpInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Response;

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
    $logger = $this->getMockBuilder('\Psr\Log\LoggerInterface')->getMock();

    $this->lingotek_api = new LingotekApi($this->client, $logger);
  }

  /**
   * @covers ::getDocument
   */
  public function testGetDocument() {
    // Ensure that the right call is done for testing if a document is imported.
    $response = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $this->client->expects($this->at(0))
      ->method('get')
      ->with('/api/document/fancy-document-id/status')
      ->will($this->returnValue($response));

    $this->lingotek_api->getDocument('fancy-document-id');
  }


  /**
   * @covers ::addTranslation
   */
  public function testAddTranslation() {
    // Ensure that the workflow is set when it's need to be.
    $response = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $this->client->expects($this->at(0))
      ->method('post')
      ->with('/api/document/fancy-document-id/translation', ['locale_code' => 'es_ES', 'workflow_id' => 'my_workflow'])
      ->will($this->returnValue($response));

    $this->client->expects($this->at(1))
      ->method('post')
      ->with('/api/document/fancy-document-id/translation', ['locale_code' => 'es_ES'])
      ->will($this->returnValue($response));

    $this->lingotek_api->addTranslation('fancy-document-id', 'es_ES', 'my_workflow');
    $this->lingotek_api->addTranslation('fancy-document-id', 'es_ES', NULL);
  }

  /**
   * @covers ::deleteDocument
   */
  public function testDeleteDocument() {
    $response = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_ACCEPTED);

    $this->client->expects($this->at(0))
      ->method('delete')
      ->with('/api/document/fancy-document-id')
      ->will($this->returnValue($response));

    $response = $this->lingotek_api->deleteDocument('fancy-document-id');
    $this->assertEquals($response->getStatusCode(), Response::HTTP_ACCEPTED);
  }

  /**
   * @covers ::deleteDocument
   */
  public function testDeleteDocumentThatDoesntExist() {
    $this->client->expects($this->at(0))
      ->method('delete')
      ->with('/api/document/fancy-document-id')
      ->will($this->throwException(new \Exception('', Response::HTTP_NOT_FOUND)));

    $response = $this->lingotek_api->deleteDocument('fancy-document-id');
    $this->assertEquals($response->getStatusCode(), Response::HTTP_NOT_FOUND);
  }

  /**
   * @covers ::getCommunities
   */
  public function testGetCommunities() {
    // Ensure that the limit is set.
    $response = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $this->client->expects($this->once())
      ->method('get')
      ->with('/api/community', ['limit' => 100])
      ->will($this->returnValue($response));

    $this->lingotek_api->getCommunities();
  }

  /**
   * @covers ::getProjects
   */
  public function testGetProjects() {
    // Ensure that the limit and the community_id are set.
    $response = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $this->client->expects($this->once())
      ->method('get')
      ->with('/api/project', ['community_id' => 'my_community_id', 'limit' => 1000])
      ->will($this->returnValue($response));

    $this->lingotek_api->getProjects('my_community_id');
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
      ->with('/api/vault', ['limit' => 100, 'is_owned' => 'TRUE'])
      ->will($this->returnValue($response));

    $this->lingotek_api->getVaults('community_id');
  }

  /**
   * @covers ::getWorkflows
   */
  public function testGetWorkflows() {
    $community_id = 'my_community_id';
    // Ensure that the limit is set.
    $response = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $this->client->expects($this->once())
      ->method('get')
      ->with('/api/workflow', ['community_id' => $community_id, 'limit' => 1000])
      ->will($this->returnValue($response));

    $this->lingotek_api->getWorkflows($community_id);
  }

}
