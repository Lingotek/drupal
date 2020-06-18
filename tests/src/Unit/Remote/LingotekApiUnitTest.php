<?php

namespace Drupal\Tests\lingotek\Unit\Remote;

use Drupal\lingotek\Remote\LingotekApi;
use Drupal\lingotek\Remote\LingotekHttpInterface;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
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
  protected function setUp(): void {
    $this->client = $this->getMockBuilder(LingotekHttpInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

    $this->lingotek_api = new LingotekApi($this->client, $logger);
  }

  /**
   * @covers ::addTranslation
   */
  public function testAddTranslation() {
    // Ensure that the workflow is set when it's need to be.
    $response = $this->getMockBuilder(ResponseInterface::class)
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

    $this->client->expects($this->at(2))
      ->method('post')
      ->with('/api/document/fancy-document-id/translation', ['locale_code' => 'es_ES', 'workflow_id' => 'my_workflow', 'vault_id' => 'my_vault'])
      ->will($this->returnValue($response));

    $this->lingotek_api->addTranslation('fancy-document-id', 'es_ES', 'my_workflow');
    $this->lingotek_api->addTranslation('fancy-document-id', 'es_ES', NULL);
    $this->lingotek_api->addTranslation('fancy-document-id', 'es_ES', 'my_workflow', 'my_vault');
  }

  /**
   * @covers ::addTranslation
   */
  public function testAddTranslationWithException() {
    // Ensure that the workflow is set when it's need to be.
    $request = $this->getMockBuilder(RequestInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_BAD_REQUEST);
    $response->expects($this->any())
      ->method('getBody')
      ->willReturn(json_encode(['messages' => ['Translation (es_ES) already exists.']]));
    $this->client->expects($this->at(0))
      ->method('post')
      ->with('/api/document/fancy-document-id/translation', ['locale_code' => 'es_ES', 'workflow_id' => 'my_workflow'])
      ->will($this->throwException(new ClientException(
        'Client error: `POST https://myaccount.lingotek.com/api/document/700e102b-b0ad-4ddf-9da1-73c62d587abc/translation` resulted in a `400 Bad Request` response:
{"messages":["Translation (es_ES) already exists."]}',
        $request,
        $response
      )));

    $response = $this->lingotek_api->addTranslation('fancy-document-id', 'es_ES', 'my_workflow');
    $this->assertEquals($response->getStatusCode(), Response::HTTP_CREATED, 'If the translation existed, we succeed instead of failing.');
  }

  /**
   * @covers ::cancelDocument
   */
  public function testCancelDocument() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_NO_CONTENT);

    $this->client->expects($this->at(0))
      ->method('post')
      ->with('/api/document/fancy-document-id/cancel', [
        'id' => 'fancy-document-id',
        'cancelled_reason' => 'CANCELLED_BY_AUTHOR',
      ])
      ->will($this->returnValue($response));

    $response = $this->lingotek_api->cancelDocument('fancy-document-id');
    $this->assertEquals($response->getStatusCode(), Response::HTTP_NO_CONTENT);
  }

  /**
   * @covers ::cancelDocument
   */
  public function testCancelDocumentThatDoesntExist() {
    $this->client->expects($this->at(0))
      ->method('post')
      ->with('/api/document/fancy-document-id/cancel', [
        'id' => 'fancy-document-id',
        'cancelled_reason' => 'CANCELLED_BY_AUTHOR',
      ])
      ->will($this->throwException(new \Exception('', Response::HTTP_NOT_FOUND)));

    $response = $this->lingotek_api->cancelDocument('fancy-document-id');
    $this->assertEquals($response->getStatusCode(), Response::HTTP_NOT_FOUND);
  }

  /**
   * @covers ::cancelDocument
   */
  public function testCancelDocumentWithoutAuthorization() {
    $this->expectException('\Drupal\lingotek\Exception\LingotekApiException');
    $this->expectExceptionMessage('Failed to cancel document');

    $this->client->expects($this->at(0))
      ->method('post')
      ->with('/api/document/fancy-document-id/cancel', [
        'id' => 'fancy-document-id',
        'cancelled_reason' => 'CANCELLED_BY_AUTHOR',
      ])
      ->will($this->throwException(new \Exception('', Response::HTTP_FORBIDDEN)));

    $response = $this->lingotek_api->cancelDocument('fancy-document-id');
    $this->assertEquals($response->getStatusCode(), Response::HTTP_FORBIDDEN);
  }

  /**
   * @covers ::cancelDocumentTarget
   */
  public function testCancelDocumentTarget() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_NO_CONTENT);

    $this->client->expects($this->at(0))
      ->method('post')
      ->with('/api/document/fancy-document-id/translation/es_ES/cancel', [
        'id' => 'fancy-document-id',
        'locale' => 'es_ES',
        'cancelled_reason' => 'CANCELLED_BY_AUTHOR',
        'mark_invoiceable' => 'true',
      ])
      ->will($this->returnValue($response));

    $response = $this->lingotek_api->cancelDocumentTarget('fancy-document-id', 'es_ES');
    $this->assertEquals($response->getStatusCode(), Response::HTTP_NO_CONTENT);
  }

  /**
   * @covers ::cancelDocumentTarget
   */
  public function testCancelDocumentTargetThatDoesntExist() {
    $this->client->expects($this->at(0))
      ->method('post')
      ->with('/api/document/fancy-document-id/translation/es_ES/cancel', [
        'id' => 'fancy-document-id',
        'locale' => 'es_ES',
        'cancelled_reason' => 'CANCELLED_BY_AUTHOR',
        'mark_invoiceable' => 'true',
      ])
      ->will($this->throwException(new \Exception('', Response::HTTP_NOT_FOUND)));

    $response = $this->lingotek_api->cancelDocumentTarget('fancy-document-id', 'es_ES');
    $this->assertEquals($response->getStatusCode(), Response::HTTP_NOT_FOUND);
  }

  /**
   * @covers ::cancelDocumentTarget
   */
  public function testCancelDocumentTargetWithoutAuthorization() {
    $this->expectException('\Drupal\lingotek\Exception\LingotekApiException');
    $this->expectExceptionMessage('Failed to cancel document');

    $this->client->expects($this->at(0))
      ->method('post')
      ->with('/api/document/fancy-document-id/translation/es_ES/cancel', [
        'id' => 'fancy-document-id',
        'locale' => 'es_ES',
        'cancelled_reason' => 'CANCELLED_BY_AUTHOR',
        'mark_invoiceable' => 'true',
      ])
      ->will($this->throwException(new \Exception('', Response::HTTP_FORBIDDEN)));

    $response = $this->lingotek_api->cancelDocumentTarget('fancy-document-id', 'es_ES');
    $this->assertEquals($response->getStatusCode(), Response::HTTP_FORBIDDEN);
  }

  /**
   * @covers ::getCommunities
   */
  public function testGetCommunities() {
    // Ensure that the limit is set.
    $response = $this->getMockBuilder(ResponseInterface::class)
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
    $response = $this->getMockBuilder(ResponseInterface::class)
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
    $response = $this->getMockBuilder(ResponseInterface::class)
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
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->client->expects($this->once())
      ->method('get')
      ->with('/api/workflow', ['community_id' => $community_id, 'limit' => 1000])
      ->will($this->returnValue($response));

    $this->lingotek_api->getWorkflows($community_id);
  }

  public function testGetTranslation() {
    // Ensure that the useSource is set when it needs to be.
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->client->expects($this->at(0))
      ->method('get')
      ->with('/api/document/fancy-document-id/content', ['locale_code' => 'es_ES', 'use_source' => 'false'])
      ->will($this->returnValue($response));

    $this->client->expects($this->at(1))
      ->method('get')
      ->with('/api/document/fancy-document-id/content', ['locale_code' => 'es_ES', 'use_source' => 'false'])
      ->will($this->returnValue($response));

    $this->client->expects($this->at(2))
      ->method('get')
      ->with('/api/document/fancy-document-id/content', ['locale_code' => 'es_ES', 'use_source' => 'true'])
      ->will($this->returnValue($response));

    $this->lingotek_api->getTranslation('fancy-document-id', 'es_ES');
    $this->lingotek_api->getTranslation('fancy-document-id', 'es_ES', FALSE);
    $this->lingotek_api->getTranslation('fancy-document-id', 'es_ES', TRUE);
  }

}
