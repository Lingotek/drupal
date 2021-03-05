<?php

namespace Drupal\Tests\lingotek\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\language\ConfigurableLanguageInterface;
use Drupal\lingotek\Entity\LingotekProfile;
use Drupal\lingotek\Exception\LingotekDocumentArchivedException;
use Drupal\lingotek\Exception\LingotekDocumentLockedException;
use Drupal\lingotek\Exception\LingotekPaymentRequiredException;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekFilterManagerInterface;
use Drupal\lingotek\Remote\LingotekApiInterface;
use Drupal\Tests\UnitTestCase;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @coversDefaultClass \Drupal\lingotek\Lingotek
 * @group lingotek
 * @preserveGlobalState disabled
 */
class LingotekUnitTest extends UnitTestCase {

  /**
   * @var \Drupal\lingotek\Lingotek
   */
  protected $lingotek;

  /**
   * The language-locale mapper.
   *
   * @var \Drupal\lingotek\LanguageLocaleMapperInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $languageLocaleMapper;

  /**
   * @var \Drupal\lingotek\Remote\LingotekHttpInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $api;

  /**
   * @var \Drupal\Core\Config\Config|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $config;

  /**
   * @var \Drupal\Core\Config\Config|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $configEditable;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $configFactory;

  /**
   * The Lingotek Filter manager.
   *
   * @var \Drupal\lingotek\LingotekFilterManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $lingotekFilterManager;

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $lingotekConfiguration;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->api = $this->createMock(LingotekApiInterface::class);
    $this->languageLocaleMapper = $this->createMock(LanguageLocaleMapperInterface::class);
    $this->config = $this->getMockBuilder(Config::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->configEditable = $this->getMockBuilder(Config::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->lingotekFilterManager = $this->createMock(LingotekFilterManagerInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('lingotek.settings')
      ->will($this->returnValue($this->config));
    $this->configFactory->expects($this->any())
      ->method('getEditable')
      ->with('lingotek.settings')
      ->will($this->returnValue($this->configEditable));

    $this->lingotekConfiguration = $this->createMock(LingotekConfigurationServiceInterface::class);

    $this->lingotek = new Lingotek($this->api, $this->languageLocaleMapper, $this->configFactory, $this->lingotekFilterManager, $this->lingotekConfiguration);
  }

  /**
   * @covers ::getVaults
   */
  public function testGetVaultsWithData() {
    // No call is performed when getting vaults without forcing.
    $this->config->expects($this->once())
      ->method('get')
      ->with('account.resources.vault')
      ->will($this->returnValue(['a_vault' => 'A vault']));
    $this->api->expects($this->never())
      ->method('getVaults');

    $vaults = $this->lingotek->getVaults(FALSE);
    $this->assertArrayEquals($vaults, ['a_vault' => 'A vault']);
  }

  /**
   * @covers ::getVaults
   */
  public function testGetVaultsWithNoData() {
    // A call is performed when getting vaults and there are none locally.
    $this->config->expects($this->at(0))
      ->method('get')
      ->with('account.resources.vault')
      ->will($this->returnValue([]));
    $this->config->expects($this->at(1))
      ->method('get')
      ->with('default.community')
      ->will($this->returnValue(['my_community']));

    $this->config->expects($this->at(2))
      ->method('get')
      ->with('default.vault')
      ->will($this->returnValue(NULL));

    // Ensure the call will be made.
    $this->api->expects($this->once())
      ->method('getVaults')
      ->will($this->returnValue(['a_vault' => 'A vault']));

    // And the results will be stored.
    $this->configEditable->expects($this->at(0))
      ->method('set')
      ->with('account.resources.vault', ['a_vault' => 'A vault'])
      ->will($this->returnSelf());

    $this->configEditable->expects($this->at(1))
      ->method('save');

    $this->configEditable->expects($this->at(2))
      ->method('set')
      ->with('default.vault', 'a_vault')
      ->will($this->returnSelf());

    $this->configEditable->expects($this->at(3))
      ->method('save');

    $vaults = $this->lingotek->getVaults(FALSE);
    $this->assertArrayEquals($vaults, ['a_vault' => 'A vault']);
  }

  /**
   * @covers ::getVaults
   */
  public function testGetVaultsWithDataButForcing() {
    // A call is performed when forced even if there are vaults locally.
    $this->config->expects($this->at(0))
      ->method('get')
      ->with('account.resources.vault')
      ->will($this->returnValue(['a_vault' => 'A vault']));

    $this->config->expects($this->at(1))
      ->method('get')
      ->with('default.community')
      ->will($this->returnValue(['my_community']));

    $this->config->expects($this->at(2))
      ->method('get')
      ->with('default.vault')
      ->will($this->returnValue(NULL));

    // Ensure the call will be made.
    $this->api->expects($this->once())
      ->method('getVaults')
      ->will($this->returnValue(['a_vault' => 'A vault']));

    // And the results will be stored.
    $this->configEditable->expects($this->at(0))
      ->method('set')
      ->with('account.resources.vault', ['a_vault' => 'A vault'])
      ->will($this->returnSelf());

    $this->configEditable->expects($this->at(1))
      ->method('save');

    $this->configEditable->expects($this->at(2))
      ->method('set')
      ->with('default.vault', 'a_vault')
      ->will($this->returnSelf());

    $this->configEditable->expects($this->at(3))
      ->method('save');

    $vaults = $this->lingotek->getVaults(TRUE);
    $this->assertArrayEquals($vaults, ['a_vault' => 'A vault']);
  }

  /**
   * @covers ::getFilters
   */
  public function testGetFiltersWithData() {
    // No call is performed when getting vaults without forcing.
    $this->config->expects($this->once())
      ->method('get')
      ->with('account.resources.filter')
      ->will($this->returnValue(['a_filter' => 'A filter']));
    $this->api->expects($this->never())
      ->method('getFilters');
    $this->lingotek->getFilters(FALSE);
  }

  /**
   * @covers ::getFilters
   */
  public function testGetFiltersWithNoData() {
    // A call is performed when getting filters and there are none locally.
    $this->config->expects($this->at(0))
      ->method('get')
      ->with('account.resources.filter')
      ->will($this->returnValue([]));
    $this->config->expects($this->at(1))
      ->method('get')
      ->with('default.community')
      ->will($this->returnValue(['my_community']));

    $this->config->expects($this->at(2))
      ->method('get')
      ->with('default.filter')
      ->will($this->returnValue(NULL));

    // Ensure the call will be made.
    $this->api->expects($this->once())
      ->method('getFilters')
      ->will($this->returnValue(['a_filter' => 'A filter']));

    // And the results will be stored.
    $this->configEditable->expects($this->at(0))
      ->method('set')
      ->with('account.resources.filter', ['a_filter' => 'A filter'])
      ->will($this->returnSelf());

    $this->configEditable->expects($this->at(1))
      ->method('save');

    $this->configEditable->expects($this->at(2))
      ->method('set')
      ->with('default.filter', 'a_filter')
      ->will($this->returnSelf());

    $this->configEditable->expects($this->at(3))
      ->method('save');

    $filters = $this->lingotek->getFilters(FALSE);
    $this->assertArrayEquals($filters, ['a_filter' => 'A filter']);
  }

  /**
   * @covers ::getFilters
   */
  public function testGetFiltersWithDataButForcing() {
    // A call is performed when forced even if there are filters locally.
    $this->config->expects($this->at(0))
      ->method('get')
      ->with('account.resources.filter')
      ->will($this->returnValue(['a_filter' => 'A filter']));

    $this->config->expects($this->at(1))
      ->method('get')
      ->with('default.community')
      ->will($this->returnValue(['my_community']));

    $this->config->expects($this->at(2))
      ->method('get')
      ->with('default.filter')
      ->will($this->returnValue(NULL));

    // Ensure the call will be made.
    $this->api->expects($this->once())
      ->method('getFilters')
      ->will($this->returnValue(['a_filter' => 'A filter']));

    // And the results will be stored.
    $this->configEditable->expects($this->at(0))
      ->method('set')
      ->with('account.resources.filter', ['a_filter' => 'A filter'])
      ->will($this->returnSelf());

    $this->configEditable->expects($this->at(1))
      ->method('save');

    $this->configEditable->expects($this->at(2))
      ->method('set')
      ->with('default.filter', 'a_filter')
      ->will($this->returnSelf());

    $this->configEditable->expects($this->at(3))
      ->method('save');

    $filters = $this->lingotek->getFilters(TRUE);
    $this->assertArrayEquals($filters, ['a_filter' => 'A filter']);
  }

  /**
   * @covers ::getProjects
   */
  public function testGetProjectsWithData() {
    // No call is performed when getting projects without forcing.
    $this->config->expects($this->once())
      ->method('get')
      ->with('account.resources.project')
      ->will($this->returnValue(['a_project' => 'A project']));
    $this->api->expects($this->never())
      ->method('getProjects');
    $projects = $this->lingotek->getProjects(FALSE);
    $this->assertArrayEquals($projects, ['a_project' => 'A project']);
  }

  /**
   * @covers ::getProjects
   */
  public function testGetProjectsWithNoData() {
    // A call is performed when getting projects and there are none locally.
    $this->config->expects($this->at(0))
      ->method('get')
      ->with('account.resources.project')
      ->will($this->returnValue([]));
    $this->config->expects($this->at(1))
      ->method('get')
      ->with('default.community')
      ->will($this->returnValue(['my_community']));

    $this->config->expects($this->at(2))
      ->method('get')
      ->with('default.project')
      ->will($this->returnValue(NULL));

    // Ensure the call will be made.
    $this->api->expects($this->once())
      ->method('getProjects')
      ->will($this->returnValue(['a_project' => 'A project']));

    // And the results will be stored.
    $this->configEditable->expects($this->at(0))
      ->method('set')
      ->with('account.resources.project', ['a_project' => 'A project'])
      ->will($this->returnSelf());

    $this->configEditable->expects($this->at(1))
      ->method('save');

    $this->configEditable->expects($this->at(2))
      ->method('set')
      ->with('default.project', 'a_project')
      ->will($this->returnSelf());

    $this->configEditable->expects($this->at(3))
      ->method('save');

    $projects = $this->lingotek->getProjects(FALSE);
    $this->assertArrayEquals($projects, ['a_project' => 'A project']);
  }

  /**
   * @covers ::getProjects
   */
  public function testGetProjectsWithDataButForcing() {
    // A call is performed when forced even if there are projects locally.
    $this->config->expects($this->at(0))
      ->method('get')
      ->with('account.resources.project')
      ->will($this->returnValue(['a_project' => 'A project']));

    $this->config->expects($this->at(1))
      ->method('get')
      ->with('default.community')
      ->will($this->returnValue(['my_community']));

    $this->config->expects($this->at(2))
      ->method('get')
      ->with('default.project')
      ->will($this->returnValue(NULL));

    // Ensure the call will be made.
    $this->api->expects($this->once())
      ->method('getProjects')
      ->will($this->returnValue(['a_project' => 'A project']));

    // And the results will be stored.
    $this->configEditable->expects($this->at(0))
      ->method('set')
      ->with('account.resources.project', ['a_project' => 'A project'])
      ->will($this->returnSelf());

    $this->configEditable->expects($this->at(1))
      ->method('save');

    $this->configEditable->expects($this->at(2))
      ->method('set')
      ->with('default.project', 'a_project')
      ->will($this->returnSelf());

    $this->configEditable->expects($this->at(3))
      ->method('save');

    $projects = $this->lingotek->getProjects(TRUE);
    $this->assertArrayEquals($projects, ['a_project' => 'A project']);
  }

  /**
   * @covers ::uploadDocument
   */
  public function testUploadDocument() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_ACCEPTED);
    $response->expects($this->any())
      ->method('getBody')
      ->willReturn(json_encode(
        [
          'properties' =>
            [
              'id' => 'my-document-id',
            ],
        ]
      ));

    $this->lingotekFilterManager->expects($this->any())
      ->method('getFilterId')
      ->willReturn('4f91482b-5aa1-4a4a-a43f-712af7b39625');

    $this->lingotekFilterManager->expects($this->any())
      ->method('getSubfilterId')
      ->willReturn('0e79f34d-f27b-4a0c-880e-cd9181a5d265');

    $this->config->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap([['default.project', 'default_project'], ['default.vault', 'default_vault'], ['default.workflow', 'default_workflow']]));

    // Vault id has the original value.
    $this->api->expects($this->at(0))
      ->method('addDocument')
      ->with([
        'title' => 'title',
        'content' => '"content"',
        'locale_code' => 'es',
        'format' => 'JSON',
        'project_id' => 'my_test_project',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'vault_id' => 'my_test_vault',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
      ])
      ->will($this->returnValue($response));

    // Vault id has changed.
    $this->api->expects($this->at(1))
      ->method('addDocument')
      ->with([
        'title' => 'title',
        'content' => '"content"',
        'locale_code' => 'es',
        'format' => 'JSON',
        'project_id' => 'another_test_project',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'vault_id' => 'another_test_vault',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
      ])
      ->will($this->returnValue($response));

    // If there is a profile with default vault, it must be replaced.
    $this->api->expects($this->at(2))
      ->method('addDocument')
      ->with([
        'title' => 'title',
        'content' => '"content"',
        'locale_code' => 'es',
        'format' => 'JSON',
        'project_id' => 'default_project',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'vault_id' => 'default_vault',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
      ])
      ->will($this->returnValue($response));

    // If there is no profile, vault should not be included.
    $this->api->expects($this->at(3))
      ->method('addDocument')
      ->with([
        'title' => 'title',
        'content' => '"content"',
        'locale_code' => 'es',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'format' => 'JSON',
        'project_id' => 'default_project',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
       ])
      ->will($this->returnValue($response));

    // If there is an url, it should be included.
    $this->api->expects($this->at(4))
      ->method('addDocument')
      ->with([
        'title' => 'title',
        'content' => '"content"',
        'locale_code' => 'es',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'format' => 'JSON',
        'project_id' => 'default_project',
        'external_url' => 'http://example.com/node/1',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
      ])
      ->will($this->returnValue($response));

    // If there is a profile using the project default workflow template vault,
    // vault should not be specified.
    $this->api->expects($this->at(5))
      ->method('addDocument')
      ->with([
        'title' => 'title',
        'content' => '"content"',
        'locale_code' => 'es',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'format' => 'JSON',
        'project_id' => 'default_project',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
      ])
      ->will($this->returnValue($response));

    // We upload with array of content.
    $this->api->expects($this->at(6))
      ->method('addDocument')
      ->with([
        'title' => 'title',
        'content' => '{"content":"wedgiePlatypus"}',
        'locale_code' => 'es',
        'format' => 'JSON',
        'project_id' => 'test_project',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'vault_id' => 'test_vault',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
      ])
      ->will($this->returnValue($response));

    // We upload with a job ID.
    $this->api->expects($this->at(7))
      ->method('addDocument')
      ->with([
        'title' => 'title',
        'content' => '{"content":"wedgiePlatypus"}',
        'locale_code' => 'es',
        'format' => 'JSON',
        'project_id' => 'test_project',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'vault_id' => 'test_vault',
        'job_id' => 'my_job_id',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
      ])
      ->will($this->returnValue($response));

    // Default workflow
    $this->api->expects($this->at(8))
      ->method('addDocument')
      ->with([
      'title' => 'title',
      'content' => '"content"',
      'locale_code' => 'es',
      'format' => 'JSON',
      'project_id' => 'default_project',
      'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
      'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
      'vault_id' => 'default_vault',
      'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
    ])
      ->will($this->returnValue($response));

    // Project default workflow should not include translation_workflow_id
    $this->api->expects($this->at(9))
      ->method('addDocument')
      ->with([
       'title' => 'title',
       'content' => '"content"',
       'locale_code' => 'es',
       'format' => 'JSON',
       'project_id' => 'default_project',
       'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
       'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
       'vault_id' => 'default_vault',
       'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
     ])
      ->will($this->returnValue($response));

    $this->api->expects($this->at(10))
      ->method('addDocument')
      ->with([
      'title' => 'title',
      'content' => '{"content":"wedgiePlatypus"}',
      'locale_code' => 'en_US',
      'format' => 'JSON',
      'project_id' => 'test_project',
      'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
      'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
      'vault_id' => 'test_vault',
      'job_id' => 'my_job_id',
      'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
      'translation_locale_code' => ['es_ES', 'ca_ES', 'it_IT'],
      'translation_workflow_id' => ['es_workflow', 'ca_workflow', 'default_workflow'],
      'translation_vault_id'  => ['default_vault', 'ca_vault', 'it_vault'],
     ])
      ->will($this->returnValue($response));

    $this->api->expects($this->at(11))
      ->method('addDocument')
      ->with([
        'title' => 'title',
        'content' => '{"content":"wedgiePlatypus"}',
        'locale_code' => 'en_US',
        'format' => 'JSON',
        'project_id' => 'test_project',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'vault_id' => 'test_vault',
        'job_id' => 'my_job_id',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
        'translation_locale_code' => ['es_ES', 'it_IT'],
        'translation_workflow_id' => ['es_workflow', 'default_workflow'],
        'translation_vault_id'  => ['default_vault', 'it_vault'],
      ])
      ->will($this->returnValue($response));

    // We upload with a profile that has a vault and a project.
    $profile = new LingotekProfile(['id' => 'profile1', 'project' => 'my_test_project', 'vault' => 'my_test_vault'], 'lingotek_profile');
    $doc_id = $this->lingotek->uploadDocument('title', 'content', 'es', NULL, $profile);
    $this->assertEquals('my-document-id', $doc_id);

    // We upload with a profile that has another vault and another project.
    $profile = new LingotekProfile(['id' => 'profile2', 'project' => 'another_test_project', 'vault' => 'another_test_vault'], 'lingotek_profile');
    $doc_id = $this->lingotek->uploadDocument('title', 'content', 'es', NULL, $profile);
    $this->assertEquals('my-document-id', $doc_id);

    // We upload with a profile that has marked to use the default vault and project,
    // so must be replaced.
    $profile = new LingotekProfile(['id' => 'profile2', 'project' => 'default', 'vault' => 'default', 'filter' => 'drupal_default'], 'lingotek_profile');
    $doc_id = $this->lingotek->uploadDocument('title', 'content', 'es', NULL, $profile);
    $this->assertEquals('my-document-id', $doc_id);

    // We upload without a profile.
    $doc_id = $this->lingotek->uploadDocument('title', 'content', 'es', NULL, NULL);
    $this->assertEquals('my-document-id', $doc_id);

    // We upload without a profile, but with url.
    $doc_id = $this->lingotek->uploadDocument('title', 'content', 'es', 'http://example.com/node/1', NULL);
    $this->assertEquals('my-document-id', $doc_id);

    // We upload with a profile that has marked to use the project default
    // workflow template vault, so must be omitted.
    $profile = new LingotekProfile(['id' => 'profile2', 'project' => 'default', 'vault' => 'project_default', 'filter' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265'], 'lingotek_profile');
    $doc_id = $this->lingotek->uploadDocument('title', 'content', 'es', NULL, $profile);
    $this->assertEquals('my-document-id', $doc_id);

    // We upload with content as an array.
    $profile = new LingotekProfile(['id' => 'profile0', 'project' => 'test_project', 'vault' => 'test_vault'], 'lingotek_profile');
    $doc_id = $this->lingotek->uploadDocument('title', ['content' => 'wedgiePlatypus'], 'es', NULL, $profile);
    $this->assertEquals('my-document-id', $doc_id);

    // We upload with a job ID.
    $doc_id = $this->lingotek->uploadDocument('title', ['content' => 'wedgiePlatypus'], 'es', NULL, $profile, 'my_job_id');
    $this->assertEquals('my-document-id', $doc_id);

    // We upload with the default workflow
    $profile = new LingotekProfile(['id' => 'profile1', 'project' => 'default', 'vault' => 'default', 'workflow' => 'default'], 'lingotek_profile');
    $doc_id = $this->lingotek->uploadDocument('title', 'content', 'es', NULL, $profile);
    $this->assertEquals('my-document-id', $doc_id);

    // We upload with the project default workflow
    $profile = new LingotekProfile(['id' => 'profile1', 'project' => 'default', 'vault' => 'default', 'workflow' => 'project_default'], 'lingotek_profile');
    $doc_id = $this->lingotek->uploadDocument('title', 'content', 'es', NULL, $profile);
    $this->assertEquals('my-document-id', $doc_id);

    // We upload with a profile that specifies auto request of targets on upload.
    $english = $this->createMock(ConfigurableLanguageInterface::class);
    $english->expects($this->any())->method('getId')->willReturn('en');
    $spanish = $this->createMock(ConfigurableLanguageInterface::class);
    $spanish->expects($this->any())->method('getId')->willReturn('es');
    $catalan = $this->createMock(ConfigurableLanguageInterface::class);
    $catalan->expects($this->any())->method('getId')->willReturn('ca');
    $italian = $this->createMock(ConfigurableLanguageInterface::class);
    $italian->expects($this->any())->method('getId')->willReturn('it');
    $this->lingotekConfiguration->expects($this->any())
      ->method('getEnabledLanguages')
      ->willReturn(['en' => $english, 'es' => $spanish, 'ca' => $catalan, 'it' => $italian]);
    $this->languageLocaleMapper->expects($this->any())
      ->method('getLocaleForLangcode')
      ->willReturnMap([['es', 'es_ES'], ['ca', 'ca_ES'], ['en', 'en_US'], ['it', 'it_IT']]);

    $profile = new LingotekProfile([
    'id' => 'profile_with_requests',
    'project' => 'test_project',
    'vault' => 'test_vault',
    'workflow' => 'test_workflow',
      'language_overrides' => [
        'es' => ['overrides' => 'custom', 'custom' => ['auto_request' => TRUE, 'workflow' => 'es_workflow', 'vault' => 'default']],
        'ca' => ['overrides' => 'custom', 'custom' => ['auto_request' => TRUE, 'workflow' => 'ca_workflow', 'vault' => 'ca_vault']],
        'it' => ['overrides' => 'custom', 'custom' => ['auto_request' => TRUE, 'workflow' => 'default', 'vault' => 'it_vault']],
      ],
    ], 'lingotek_profile');
    $profile->setAutomaticUpload(TRUE);
    $profile->setAutomaticRequest(TRUE);
    $doc_id = $this->lingotek->uploadDocument('title', ['content' => 'wedgiePlatypus'], 'en_US', NULL, $profile, 'my_job_id');
    $this->assertEquals('my-document-id', $doc_id);

    $profile = new LingotekProfile([
      'id' => 'profile_with_disabled_targets',
      'project' => 'test_project',
      'vault' => 'test_vault',
      'workflow' => 'test_workflow',
      'language_overrides' => [
        'es' => ['overrides' => 'custom', 'custom' => ['auto_request' => TRUE, 'workflow' => 'es_workflow', 'vault' => 'default']],
        'ca' => ['overrides' => 'disabled', 'custom' => ['auto_request' => TRUE, 'workflow' => 'ca_workflow', 'vault' => 'ca_vault']],
        'it' => ['overrides' => 'custom', 'custom' => ['auto_request' => TRUE, 'workflow' => 'default', 'vault' => 'it_vault']],
      ],
    ], 'lingotek_profile');
    $profile->setAutomaticUpload(TRUE);
    $profile->setAutomaticRequest(TRUE);
    $doc_id = $this->lingotek->uploadDocument('title', ['content' => 'wedgiePlatypus'], 'en_US', NULL, $profile, 'my_job_id');
    $this->assertEquals('my-document-id', $doc_id);

  }

  /**
   * @covers ::updateDocument
   */
  public function testUpdateDocumentBC() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_ACCEPTED);
    $response->expects($this->any())
      ->method('getBody')
      // The previous version of the API returned an empty response.
      ->willReturn(json_encode([]));

    $this->lingotekFilterManager->expects($this->any())
      ->method('getFilterId')
      ->willReturn('4f91482b-5aa1-4a4a-a43f-712af7b39625');

    $this->lingotekFilterManager->expects($this->any())
      ->method('getSubfilterId')
      ->willReturn('0e79f34d-f27b-4a0c-880e-cd9181a5d265');

    $this->config->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap([['default.project', 'default_project'], ['default.vault', 'default_vault'], ['default.workflow', 'default_workflow']]));

    // Simplest update.
    $this->api->expects($this->at(0))
      ->method('patchDocument')
      ->with('my_doc_id', [
        'format' => 'JSON',
        'content' => '"content"',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
      ])
      ->will($this->returnValue($response));

    // If there is an url, it should be included.
    $this->api->expects($this->at(1))
      ->method('patchDocument')
      ->with('my_doc_id', [
        'format' => 'JSON',
        'content' => '"content"',
        'external_url' => 'http://example.com/node/1',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
      ])
      ->will($this->returnValue($response));

    // If there is a title, it should be included.
    $this->api->expects($this->at(2))
      ->method('patchDocument')
      ->with('my_doc_id', [
        'format' => 'JSON',
        'content' => '"content"',
        'title' => 'title',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
      ])
      ->will($this->returnValue($response));

    // If there is an url and a title, they should be included.
    $this->api->expects($this->at(3))
      ->method('patchDocument')
      ->with('my_doc_id', [
        'format' => 'JSON',
        'content' => '"content"',
        'external_url' => 'http://example.com/node/1',
        'title' => 'title',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
      ])
      ->will($this->returnValue($response));

    // The content is an array.
    $this->api->expects($this->at(4))
      ->method('patchDocument')
      ->with('my_doc_id', [
        'format' => 'JSON',
        'content' => '{"content":"wedgiePlatypus"}',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
      ])
      ->will($this->returnValue($response));

    // The call includes a job_id and a profile.
    $this->api->expects($this->at(5))
      ->method('patchDocument')
      ->with('my_doc_id', [
        'format' => 'JSON',
        'content' => '"content"',
        'title' => 'title',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
        'job_id' => 'my_job_id',
        'project_id' => 'test_project',
      ])
      ->will($this->returnValue($response));

    $this->api->expects($this->at(6))
      ->method('patchDocument')
      ->with('my_doc_id', [
        'job_id' => 'my_job_id',
        'project_id' => 'test_project',
      ])
      ->will($this->returnValue($response));

    $this->api->expects($this->at(7))
      ->method('patchDocument')
      ->with('my_doc_id', [
        'format' => 'JSON',
        'content' => '"content"',
        'title' => 'title',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
        'project_id' => 'test_project',
        'translation_locale_code' => ['es_ES', 'ca_ES', 'it_IT'],
        'translation_workflow_id' => ['es_workflow', 'ca_workflow', 'default_workflow'],
      ])
      ->will($this->returnValue($response));

    $this->api->expects($this->at(8))
      ->method('patchDocument')
      ->with('my_doc_id', [
        'format' => 'JSON',
        'content' => '"content"',
        'title' => 'title',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
        'project_id' => 'test_project',
        'translation_locale_code' => ['es_ES', 'ca_ES', 'it_IT'],
        'translation_workflow_id' => ['es_workflow', 'test_workflow', 'default_workflow'],
      ])
      ->will($this->returnValue($response));

    $this->api->expects($this->at(9))
      ->method('patchDocument')
      ->with('my_doc_id', [
        'format' => 'JSON',
        'content' => '"content"',
        'title' => 'title',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
        'project_id' => 'test_project',
      ])
      ->will($this->returnValue($response));

    // Simplest update.
    $this->lingotek->updateDocument('my_doc_id', 'content');

    // If there is an url, it should be included.
    $this->lingotek->updateDocument('my_doc_id', 'content', 'http://example.com/node/1');

    // If there is a title, it should be included.
    $this->lingotek->updateDocument('my_doc_id', 'content', NULL, 'title');

    // If there is an url and a title, they should be included.
    $this->lingotek->updateDocument('my_doc_id', 'content', 'http://example.com/node/1', 'title');

    // The content is an array.
    $this->lingotek->updateDocument('my_doc_id', ['content' => 'wedgiePlatypus']);

    // We upload with a profile and a job ID.
    $profile = new LingotekProfile(['id' => 'profile0', 'project' => 'test_project', 'vault' => 'test_vault'], 'lingotek_profile');
    $this->lingotek->updateDocument('my_doc_id', 'content', NULL, 'title', $profile, 'my_job_id');

    // Only update Job ID.
    $this->lingotek->updateDocument('my_doc_id', NULL, NULL, NULL, $profile, 'my_job_id');
    $english = $this->createMock(ConfigurableLanguageInterface::class);
    $english->expects($this->any())->method('getId')->willReturn('en');
    $spanish = $this->createMock(ConfigurableLanguageInterface::class);
    $spanish->expects($this->any())->method('getId')->willReturn('es');
    $catalan = $this->createMock(ConfigurableLanguageInterface::class);
    $catalan->expects($this->any())->method('getId')->willReturn('ca');
    $italian = $this->createMock(ConfigurableLanguageInterface::class);
    $italian->expects($this->any())->method('getId')->willReturn('it');
    $this->lingotekConfiguration->expects($this->any())
      ->method('getEnabledLanguages')
      ->willReturn(['en' => $english, 'es' => $spanish, 'ca' => $catalan, 'it' => $italian]);
    $this->languageLocaleMapper->expects($this->any())
      ->method('getLocaleForLangcode')
      ->willReturnMap([['es', 'es_ES'], ['ca', 'ca_ES'], ['en', 'en_US'], ['it', 'it_IT']]);
    $profile = new LingotekProfile([
      'id' => 'profile0',
      'project' => 'test_project',
      'vault' => 'test_vault',
      'workflow' => 'test_workflow',
      'language_overrides' => [
        'es' => ['overrides' => 'custom', 'custom' => ['auto_request' => TRUE, 'workflow' => 'es_workflow', 'vault' => 'default']],
        'ca' => ['overrides' => 'custom', 'custom' => ['auto_request' => TRUE, 'workflow' => 'ca_workflow', 'vault' => 'ca_vault']],
        'it' => ['overrides' => 'custom', 'custom' => ['auto_request' => TRUE, 'workflow' => 'default', 'vault' => 'it_vault']],
      ],
    ], 'lingotek_profile');
    // If profile contains target specific settings in proper order
    $this->lingotek->updateDocument('my_doc_id', 'content', NULL, 'title', $profile, NULL, 'en_US');
    $profile = new LingotekProfile([
      'id' => 'profile0',
      'project' => 'test_project',
      'vault' => 'test_vault',
      'workflow' => 'test_workflow',
      'language_overrides' => [
        'es' => ['overrides' => 'custom', 'custom' => ['auto_request' => TRUE, 'workflow' => 'es_workflow', 'vault' => 'default']],
        'ca' => ['overrides' => 'custom', 'custom' => ['auto_request' => TRUE, 'vault' => 'ca_vault']],
        'it' => ['overrides' => 'custom', 'custom' => ['auto_request' => TRUE, 'workflow' => 'default', 'vault' => 'it_vault']],
      ],
    ], 'lingotek_profile');
    // If amount of translation_workflow_ids doesn't match amount of translation_locale_codes, use project workflow
    $this->lingotek->updateDocument('my_doc_id', 'content', NULL, 'title', $profile, NULL, 'en_US');
    $profile = new LingotekProfile([
      'id' => 'profile0',
      'project' => 'test_project',
      'vault' => 'test_vault',
      'workflow' => 'test_workflow',
    ], 'lingotek_profile');
    $this->lingotek->updateDocument('my_doc_id', 'content', NULL, 'title', $profile, NULL, 'en_US');
  }

  /**
   * @covers ::addTarget
   */
  public function testAddTarget() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_CREATED);
    $language = $this->createMock(ConfigurableLanguageInterface::class);
    $language->expects($this->any())
      ->method('getId')
      ->will($this->returnValue('es'));

    $this->config->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap([['default.workflow', 'default_workflow']]));
    $this->languageLocaleMapper->expects($this->any())
      ->method('getConfigurableLanguageForLocale')
      ->with('es_ES')
      ->will($this->returnValue($language));

    // Workflow id has the original value.
    $this->api->expects($this->at(0))
      ->method('addTranslation')
      ->with('my_doc_id', 'es_ES', 'my_test_workflow')
      ->will($this->returnValue($response));

    // Workflow id has changed.
    $this->api->expects($this->at(1))
      ->method('addTranslation')
      ->with('my_doc_id', 'es_ES', 'another_test_workflow')
      ->will($this->returnValue($response));

    // If there is a profile with default workflow, it must be replaced.
    $this->api->expects($this->at(2))
      ->method('addTranslation')
      ->with('my_doc_id', 'es_ES', 'default_workflow')
      ->will($this->returnValue($response));

    // If there is a profile with a language override must be used.
    $this->api->expects($this->at(3))
      ->method('addTranslation')
      ->with('my_doc_id', 'es_ES', 'overridden_workflow', 'overridden_vault')
      ->will($this->returnValue($response));

    // If there is a profile with a default workflow as override, it must be replaced.
    $this->api->expects($this->at(4))
      ->method('addTranslation')
      ->with('my_doc_id', 'es_ES', 'default_workflow')
      ->will($this->returnValue($response));

    // If there is no profile, workflow should not be included.
    $this->api->expects($this->at(5))
      ->method('addTranslation')
      ->with('my_doc_id', 'es_ES', NULL)
      ->will($this->returnValue($response));

    // If workflow is project_default, workflow should not be included.
    $this->api->expects($this->at(6))
      ->method('addTranslation')
      ->with('my_doc_id', 'es_ES', NULL)
      ->will($this->returnValue($response));

    // We upload with a profile that has a workflow.
    $profile = new LingotekProfile(['id' => 'profile1', 'workflow' => 'my_test_workflow'], 'lingotek_profile');
    $this->assertTrue($this->lingotek->addTarget('my_doc_id', 'es_ES', $profile));

    // We upload with a profile that has another vault and another project.
    $profile = new LingotekProfile(['id' => 'profile2', 'workflow' => 'another_test_workflow'], 'lingotek_profile');
    $this->assertTrue($this->lingotek->addTarget('my_doc_id', 'es_ES', $profile));

    // We upload with a profile that has marked to use the default vault and project,
    // so must be replaced.
    $profile = new LingotekProfile(['id' => 'profile2', 'workflow' => 'default'], 'lingotek_profile');
    $this->assertTrue($this->lingotek->addTarget('my_doc_id', 'es_ES', $profile));

    // We upload with a profile that has marked to use the default vault and project,
    // but has an override.
    $profile = new LingotekProfile(['id' => 'profile2', 'workflow' => 'default', 'language_overrides' => ['es' => ['overrides' => 'custom', 'custom' => ['workflow' => 'overridden_workflow', 'vault' => 'overridden_vault']]]], 'lingotek_profile');
    $this->assertTrue($this->lingotek->addTarget('my_doc_id', 'es_ES', $profile));

    // We upload with a profile that has another vault and another project, but
    // overridden with a default, so must be replaced.
    $profile = new LingotekProfile(['id' => 'profile2', 'workflow' => 'a_different_test_workflow', 'language_overrides' => ['es' => ['overrides' => 'custom', 'custom' => ['workflow' => 'default', 'vault' => 'default']]]], 'lingotek_profile');
    $this->assertTrue($this->lingotek->addTarget('my_doc_id', 'es_ES', $profile));

    // We upload without a profile
    $this->assertTrue($this->lingotek->addTarget('my_doc_id', 'es_ES', NULL));

    $profile = new LingotekProfile(['id' => 'profile1', 'workflow' => 'project_default'], 'lingotek_profile');
    $this->assertTrue($this->lingotek->addTarget('my_doc_id', 'es_ES', $profile));
  }

  /**
   * @covers ::addTarget
   */
  public function testAddTargetPaymentRequired() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_PAYMENT_REQUIRED);
    $response->expects($this->any())
      ->method('getBody')
      ->willReturn(json_encode(
        [
          'messages' => [
            "Community has been disabled. Please contact support@lingotek.com to re-enable your community.",
          ],
        ]
      ));
    $language = $this->createMock(ConfigurableLanguageInterface::class);
    $language->expects($this->any())
      ->method('getId')
      ->will($this->returnValue('es'));

    $this->config->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap([['default.workflow', 'default_workflow']]));
    $this->languageLocaleMapper->expects($this->any())
      ->method('getConfigurableLanguageForLocale')
      ->with('es_ES')
      ->will($this->returnValue($language));

    $this->api->expects($this->at(0))
      ->method('addTranslation')
      ->with('my_doc_id', 'es_ES', NULL)
      ->will($this->returnValue($response));

    $this->expectException(LingotekPaymentRequiredException::class);
    $this->expectExceptionMessage('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');
    $this->lingotek->addTarget('my_doc_id', 'es_ES', NULL);
  }

  /**
   * @covers ::addTarget
   */
  public function testAddTargetGone() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_GONE);
    $response->expects($this->any())
      ->method('getBody')
      ->willReturn(json_encode(
        [
          'messages' => [
            "Document my_doc_id has been archived.",
          ],
        ]
      ));
    $language = $this->createMock(ConfigurableLanguageInterface::class);
    $language->expects($this->any())
      ->method('getId')
      ->will($this->returnValue('es'));

    $this->config->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap([['default.workflow', 'default_workflow']]));
    $this->languageLocaleMapper->expects($this->any())
      ->method('getConfigurableLanguageForLocale')
      ->with('es_ES')
      ->will($this->returnValue($language));

    $this->api->expects($this->at(0))
      ->method('addTranslation')
      ->with('my_doc_id', 'es_ES', NULL)
      ->will($this->returnValue($response));

    $this->assertFalse($this->lingotek->addTarget('my_doc_id', 'es_ES', NULL));
  }

  /**
   * @covers ::addTarget
   */
  public function testAddTargetLocked() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_LOCKED);
    $response->expects($this->any())
      ->method('getBody')
      ->willReturn(json_encode(
        [
          'next_document_id' => 'my_new_document_id',
          'messages' => [
            'Document my_doc_id has been updated with a new version. Use document my_new_document_id for all future interactions.',
          ],
        ]
      ));
    $language = $this->createMock(ConfigurableLanguageInterface::class);
    $language->expects($this->any())
      ->method('getId')
      ->will($this->returnValue('es'));

    $this->config->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap([['default.workflow', 'default_workflow']]));
    $this->languageLocaleMapper->expects($this->any())
      ->method('getConfigurableLanguageForLocale')
      ->with('es_ES')
      ->will($this->returnValue($language));

    $this->api->expects($this->at(0))
      ->method('addTranslation')
      ->with('my_doc_id', 'es_ES', NULL)
      ->will($this->returnValue($response));

    $this->assertFalse($this->lingotek->addTarget('my_doc_id', 'es_ES', NULL));
  }

  /**
   * @covers ::cancelDocument
   */
  public function testCancelDocument() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->at(0))
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_NO_CONTENT);

    // Test returning an error.
    $response->expects($this->at(1))
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_INTERNAL_SERVER_ERROR);

    $response->expects($this->at(2))
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_NOT_FOUND);

    $this->api->expects($this->any())
      ->method('cancelDocument')
      ->with('my_doc_id')
      ->will($this->returnValue($response));

    $this->assertTrue($this->lingotek->cancelDocument('my_doc_id'));
    $this->assertFalse($this->lingotek->cancelDocument('my_doc_id'));
    $this->assertFalse($this->lingotek->cancelDocument('my_doc_id'));
  }

  /**
   * @covers ::cancelDocumentTarget
   */
  public function testCancelDocumentTarget() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->at(0))
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_NO_CONTENT);

    // Test returning an error.
    $response->expects($this->at(1))
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_INTERNAL_SERVER_ERROR);

    $response->expects($this->at(2))
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_NOT_FOUND);

    $this->api->expects($this->any())
      ->method('cancelDocumentTarget')
      ->with('my_doc_id', 'es_ES')
      ->will($this->returnValue($response));

    $this->assertTrue($this->lingotek->cancelDocumentTarget('my_doc_id', 'es_ES'));
    $this->assertFalse($this->lingotek->cancelDocumentTarget('my_doc_id', 'es_ES'));
    $this->assertFalse($this->lingotek->cancelDocumentTarget('my_doc_id', 'es_ES'));
  }

  /**
   * @covers ::getDocumentTranslationStatus
   */
  public function testGetDocumentTranslationStatus() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_OK);
    $response->expects($this->any())
      ->method('getBody')
      ->willReturn(json_encode(
        [
          'entities' =>
          [
            [
              'properties' =>
                [
                  'locale_code' => 'es-ES',
                  'percent_complete' => 100,
                  'status' => 'READY',
                ],
            ],
            [
              'properties' =>
                [
                  'locale_code' => 'de-DE',
                  'percent_complete' => 50,
                  'status' => 'READY',
                ],
            ],

          ],
        ]
      ));
    $this->api->expects($this->at(0))
      ->method('getDocumentTranslationStatus')
      ->with('my_doc_id', 'es_ES')
      ->will($this->returnValue($response));
    $this->api->expects($this->at(1))
      ->method('getDocumentTranslationStatus')
      ->with('my_doc_id', 'de_DE')
      ->will($this->returnValue($response));
    $this->api->expects($this->at(2))
      ->method('getDocumentTranslationStatus')
      ->with('my_doc_id', 'ca_ES')
      ->will($this->returnValue($response));

    // Assert that a complete translation is reported as completed.
    $result = $this->lingotek->getDocumentTranslationStatus('my_doc_id', 'es_ES');
    $this->assertEquals(TRUE, $result);

    // Assert that an incomplete translation is reported as not completed.
    $result = $this->lingotek->getDocumentTranslationStatus('my_doc_id', 'de_DE');
    $this->assertEquals(50, $result);

    // Assert that an unrequested translation is reported as not completed.
    $result = $this->lingotek->getDocumentTranslationStatus('my_doc_id', 'ca_ES');
    $this->assertEquals(FALSE, $result);
  }

  /**
   * @covers ::getDocumentTranslationStatus
   */
  public function testGetDocumentTranslationStatusCancelled() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_OK);
    $response->expects($this->any())
      ->method('getBody')
      ->willReturn(json_encode(
        [
          'entities' =>
            [
              [
                'properties' =>
                  [
                    'locale_code' => 'es-ES',
                    'percent_complete' => 100,
                    'status' => 'READY',
                  ],
              ],
              [
                'properties' =>
                  [
                    'locale_code' => 'de-DE',
                    'percent_complete' => 50,
                    'status' => 'CANCELLED',
                  ],
              ],

            ],
        ]
      ));
    $this->api->expects($this->at(0))
      ->method('getDocumentTranslationStatus')
      ->with('my_doc_id', 'es_ES')
      ->will($this->returnValue($response));
    $this->api->expects($this->at(1))
      ->method('getDocumentTranslationStatus')
      ->with('my_doc_id', 'de_DE')
      ->will($this->returnValue($response));
    $this->api->expects($this->at(2))
      ->method('getDocumentTranslationStatus')
      ->with('my_doc_id', 'ca_ES')
      ->will($this->returnValue($response));

    // Assert that a complete translation is reported as completed.
    $result = $this->lingotek->getDocumentTranslationStatus('my_doc_id', 'es_ES');
    $this->assertEquals(TRUE, $result);

    // Assert that an incomplete translation is reported as not completed.
    $result = $this->lingotek->getDocumentTranslationStatus('my_doc_id', 'de_DE');
    $this->assertEquals('CANCELLED', $result);

    // Assert that an unrequested translation is reported as not completed.
    $result = $this->lingotek->getDocumentTranslationStatus('my_doc_id', 'ca_ES');
    $this->assertEquals(FALSE, $result);
  }

  /**
   * @covers ::uploadDocument
   */
  public function testUploadWithNoMetadataLeaked() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_ACCEPTED);

    $this->lingotekFilterManager->expects($this->any())
      ->method('getFilterId')
      ->willReturn('4f91482b-5aa1-4a4a-a43f-712af7b39625');

    $this->lingotekFilterManager->expects($this->any())
      ->method('getSubfilterId')
      ->willReturn('0e79f34d-f27b-4a0c-880e-cd9181a5d265');

    $this->config->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap([['default.project', 'default_project'], ['default.vault', 'default_vault']]));

    // Vault id has the original value.
    $this->api->expects($this->at(0))
      ->method('addDocument')
      ->with([
        'title' => 'title',
        'content' => '{"content":"My test content","_lingotek_metadata":{"_entity_id":1}}',
        'locale_code' => 'es',
        'format' => 'JSON',
        'project_id' => 'default_project',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
        'content_type' => 'node',
      ])
      ->will($this->returnValue($response));

    $data = ['content' => 'My test content', '_lingotek_metadata' => ['_entity_id' => 1, '_intelligence' => ['content_type' => 'node']]];
    $this->lingotek->uploadDocument('title', $data, 'es');
  }

  /**
   * @covers ::updateDocument
   */
  public function testUpdateWithNoMetadataLeaked() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_ACCEPTED);

    $this->lingotekFilterManager->expects($this->any())
      ->method('getFilterId')
      ->willReturn('4f91482b-5aa1-4a4a-a43f-712af7b39625');

    $this->lingotekFilterManager->expects($this->any())
      ->method('getSubfilterId')
      ->willReturn('0e79f34d-f27b-4a0c-880e-cd9181a5d265');

    $this->config->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap([['default.project', 'default_project'], ['default.vault', 'default_vault']]));

    // Vault id has the original value.
    $this->api->expects($this->at(0))
      ->method('patchDocument')
      ->with('my_doc_id', [
        'content' => '{"content":"My test content","_lingotek_metadata":{"_entity_id":1}}',
        'format' => 'JSON',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
        'content_type' => 'node',
      ])
      ->will($this->returnValue($response));

    $data = [
      'content' => 'My test content',
      '_lingotek_metadata' => [
        '_entity_id' => 1,
        '_intelligence' => ['content_type' => 'node'],
      ],
    ];
    $result = $this->lingotek->updateDocument('my_doc_id', $data);
    $this->assertTrue($result == TRUE);
  }

  /**
   * @covers ::updateDocument
   */
  public function testUpdateDocument() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->lingotekFilterManager->expects($this->any())
      ->method('getFilterId')
      ->willReturn('4f91482b-5aa1-4a4a-a43f-712af7b39625');

    $this->lingotekFilterManager->expects($this->any())
      ->method('getSubfilterId')
      ->willReturn('0e79f34d-f27b-4a0c-880e-cd9181a5d265');

    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_ACCEPTED);
    $response->expects($this->any())
      ->method('getBody')
      ->willReturn(json_encode(
        [
          'next_document_id' => 'my_new_document_id',
          'messages' => [
            'Document my_doc_id has been updated with a new version. Use document my_new_document_id for all future interactions.',
          ],
        ]
      ));
    $this->api->expects($this->at(0))
      ->method('patchDocument')
      ->with('my_doc_id', [
        'content' => '{"content":"My test content","_lingotek_metadata":{"_entity_id":1}}',
        'format' => 'JSON',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
        'content_type' => 'node',
      ])
      ->will($this->returnValue($response));

    $data = [
      'content' => 'My test content',
      '_lingotek_metadata' => [
        '_entity_id' => 1,
        '_intelligence' => ['content_type' => 'node'],
      ],
    ];

    $result = $this->lingotek->updateDocument('my_doc_id', $data);
    $this->assertEquals($result, 'my_new_document_id');
  }

  /**
   * @covers ::updateDocument
   */
  public function testUpdateDocumentManualProfile() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_ACCEPTED);

    $this->lingotekFilterManager->expects($this->any())
      ->method('getFilterId')
      ->willReturn('4f91482b-5aa1-4a4a-a43f-712af7b39625');

    $this->lingotekFilterManager->expects($this->any())
      ->method('getSubfilterId')
      ->willReturn('0e79f34d-f27b-4a0c-880e-cd9181a5d265');

    $this->config->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap([['default.project', 'default_project'], ['default.vault', 'default_vault'], ['default.workflow', 'default_workflow']]));

    $english = $this->createMock(ConfigurableLanguageInterface::class);
    $english->expects($this->any())->method('getId')->willReturn('en');
    $spanish = $this->createMock(ConfigurableLanguageInterface::class);
    $spanish->expects($this->any())->method('getId')->willReturn('es');
    $catalan = $this->createMock(ConfigurableLanguageInterface::class);
    $catalan->expects($this->any())->method('getId')->willReturn('ca');
    $italian = $this->createMock(ConfigurableLanguageInterface::class);
    $italian->expects($this->any())->method('getId')->willReturn('it');

    $this->lingotekConfiguration->expects($this->any())
      ->method('getEnabledLanguages')
      ->willReturn(['en' => $english, 'es' => $spanish, 'ca' => $catalan, 'it' => $italian]);

    $this->languageLocaleMapper->expects($this->any())
      ->method('getLocaleForLangcode')
      ->willReturnMap([['es', 'es_ES'], ['ca', 'ca_ES'], ['en', 'en_US'], ['it', 'it_IT']]);

    $this->api->expects($this->at(0))
      ->method('patchDocument')
      ->with('my_doc_id', [
        'format' => 'JSON',
        'content' => '"content"',
        'title' => 'title',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
        'project_id' => 'test_project',
      ])
      ->will($this->returnValue($response));

    $profile = new LingotekProfile([
        'id' => 'profile',
        'project' => 'test_project',
        'vault' => 'test_vault',
        'workflow' => 'test_workflow',
        'language_overrides' => [
          'es' => ['overrides' => 'custom', 'custom' => ['auto_request' => FALSE, 'workflow' => 'es_workflow', 'vault' => 'default']],
          'ca' => ['overrides' => 'custom', 'custom' => ['auto_request' => FALSE, 'workflow' => 'ca_workflow', 'vault' => 'ca_vault']],
          'it' => ['overrides' => 'custom', 'custom' => ['auto_request' => FALSE, 'workflow' => 'default', 'vault' => 'it_vault']],
        ],
      ], 'lingotek_profile');

    // Ensure that all automatic settings are turned off
    $profile->setAutomaticDownload(FALSE);
    $profile->setAutomaticUpload(FALSE);
    $profile->setAutomaticRequest(FALSE);
    $this->lingotek->updateDocument('my_doc_id', 'content', NULL, 'title', $profile, NULL, 'en_US');
  }

  /**
   * @covers ::updateDocument
   */
  public function testUpdateDocumentPaymentRequired() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->lingotekFilterManager->expects($this->any())
      ->method('getFilterId')
      ->willReturn('4f91482b-5aa1-4a4a-a43f-712af7b39625');

    $this->lingotekFilterManager->expects($this->any())
      ->method('getSubfilterId')
      ->willReturn('0e79f34d-f27b-4a0c-880e-cd9181a5d265');

    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_PAYMENT_REQUIRED);
    $response->expects($this->any())
      ->method('getBody')
      ->willReturn(json_encode(
        [
          'messages' => [
            "Community has been disabled. Please contact support@lingotek.com to re-enable your community.",
          ],
        ]
      ));
    $this->api->expects($this->at(0))
      ->method('patchDocument')
      ->with('my_doc_id', [
        'content' => '{"content":"My test content","_lingotek_metadata":{"_entity_id":1}}',
        'format' => 'JSON',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
        'content_type' => 'node',
      ])
      ->will($this->returnValue($response));

    $data = [
      'content' => 'My test content',
      '_lingotek_metadata' => [
        '_entity_id' => 1,
        '_intelligence' => ['content_type' => 'node'],
      ],
    ];

    $this->expectException(LingotekPaymentRequiredException::class);
    $this->expectExceptionMessage('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');
    $this->lingotek->updateDocument('my_doc_id', $data);
  }

  /**
   * @covers ::updateDocument
   */
  public function testUpdateDocumentGone() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->lingotekFilterManager->expects($this->any())
      ->method('getFilterId')
      ->willReturn('4f91482b-5aa1-4a4a-a43f-712af7b39625');

    $this->lingotekFilterManager->expects($this->any())
      ->method('getSubfilterId')
      ->willReturn('0e79f34d-f27b-4a0c-880e-cd9181a5d265');

    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_GONE);
    $response->expects($this->any())
      ->method('getBody')
      ->willReturn(json_encode(
        [
          'messages' => [
            "Document my_doc_id has been archived.",
          ],
        ]
      ));
    $this->api->expects($this->at(0))
      ->method('patchDocument')
      ->with('my_doc_id', [
        'content' => '{"content":"My test content","_lingotek_metadata":{"_entity_id":1}}',
        'format' => 'JSON',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
        'content_type' => 'node',
      ])
      ->will($this->returnValue($response));

    $data = [
      'content' => 'My test content',
      '_lingotek_metadata' => [
        '_entity_id' => 1,
        '_intelligence' => ['content_type' => 'node'],
      ],
    ];

    $this->expectException(LingotekDocumentArchivedException::class);
    $this->expectExceptionMessage('Document my_doc_id has been archived.');
    $this->lingotek->updateDocument('my_doc_id', $data);
  }

  /**
   * @covers ::updateDocument
   */
  public function testUpdateDocumentLocked() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->lingotekFilterManager->expects($this->any())
      ->method('getFilterId')
      ->willReturn('4f91482b-5aa1-4a4a-a43f-712af7b39625');

    $this->lingotekFilterManager->expects($this->any())
      ->method('getSubfilterId')
      ->willReturn('0e79f34d-f27b-4a0c-880e-cd9181a5d265');

    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_LOCKED);
    $response->expects($this->any())
      ->method('getBody')
      ->willReturn(json_encode(
        [
          'next_document_id' => 'my_new_document_id',
          'messages' => [
            'Document my_doc_id has been updated with a new version. Use document my_new_document_id for all future interactions.',
          ],
        ]
      ));
    $this->api->expects($this->at(0))
      ->method('patchDocument')
      ->with('my_doc_id', [
        'content' => '{"content":"My test content","_lingotek_metadata":{"_entity_id":1}}',
        'format' => 'JSON',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
        'content_type' => 'node',
      ])
      ->will($this->returnValue($response));

    $data = [
      'content' => 'My test content',
      '_lingotek_metadata' => [
        '_entity_id' => 1,
        '_intelligence' => ['content_type' => 'node'],
      ],
    ];

    $this->expectException(LingotekDocumentLockedException::class);
    $this->expectExceptionMessage('Document my_doc_id has been updated with a new version. Use document my_new_document_id for all future interactions.');
    $this->lingotek->updateDocument('my_doc_id', $data);
  }

  /**
   * @covers ::uploadDocument
   */
  public function testUploadDocumentPaymentRequired() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->lingotekFilterManager->expects($this->any())
      ->method('getFilterId')
      ->willReturn('4f91482b-5aa1-4a4a-a43f-712af7b39625');

    $this->lingotekFilterManager->expects($this->any())
      ->method('getSubfilterId')
      ->willReturn('0e79f34d-f27b-4a0c-880e-cd9181a5d265');

    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_PAYMENT_REQUIRED);
    $response->expects($this->any())
      ->method('getBody')
      ->willReturn(json_encode(
        [
          'messages' => [
            "Community has been disabled. Please contact support@lingotek.com to re-enable your community.",
          ],
        ]
      ));
    $this->api->expects($this->at(0))
      ->method('addDocument')
      ->with([
        'title' => 'title',
        'content' => '{"content":"My test content","_lingotek_metadata":{"_entity_id":1}}',
        'locale_code' => 'es',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'format' => 'JSON',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
        'content_type' => 'node',
      ])
      ->will($this->returnValue($response));

    $data = [
      'content' => 'My test content',
      '_lingotek_metadata' => [
        '_entity_id' => 1,
        '_intelligence' => ['content_type' => 'node'],
      ],
    ];

    $this->expectException(LingotekPaymentRequiredException::class);
    $this->expectExceptionMessage('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');
    $this->lingotek->uploadDocument('title', $data, 'es', NULL, NULL);
  }

  /**
   * @covers ::downloadDocument
   */
  public function testDownloadDocument() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_OK);
    $response->expects($this->any())
      ->method('getBody')
      ->willReturn(json_encode(
        [
          'title' =>
            [
              'value' => 'Document title',
            ],
        ]
      ));

    $language = $this->createMock(ConfigurableLanguageInterface::class);
    $language->expects($this->any())
      ->method('getId')
      ->will($this->returnValue('es'));

    $this->config->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap([['default.workflow', 'default_workflow']]));
    $this->languageLocaleMapper->expects($this->any())
      ->method('getConfigurableLanguageForLocale')
      ->with('es_ES')
      ->will($this->returnValue($language));

    // Workflow id has the original value.
    $this->api->expects($this->at(0))
      ->method('getTranslation')
      ->with('my_doc_id', 'es_ES')
      ->will($this->returnValue($response));

    $this->assertNotNull($this->lingotek->downloadDocument('my_doc_id', 'es_ES'));
  }

  /**
   * @covers ::downloadDocument
   */
  public function testDownloadDocumentGone() {
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_GONE);
    $response->expects($this->any())
      ->method('getBody')
      ->willReturn(json_encode(
        [
          'messages' => [
            "Document my_doc_id has been archived.",
          ],
        ]
      ));

    $language = $this->createMock(ConfigurableLanguageInterface::class);
    $language->expects($this->any())
      ->method('getId')
      ->will($this->returnValue('es'));

    $this->config->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap([['default.workflow', 'default_workflow']]));
    $this->languageLocaleMapper->expects($this->any())
      ->method('getConfigurableLanguageForLocale')
      ->with('es_ES')
      ->will($this->returnValue($language));

    // Workflow id has the original value.
    $this->api->expects($this->at(0))
      ->method('getTranslation')
      ->with('my_doc_id', 'es_ES')
      ->will($this->returnValue($response));

    $this->assertFalse($this->lingotek->downloadDocument('my_doc_id', 'es_ES'));
  }

}
