<?php

namespace Drupal\Tests\lingotek\Unit;

use Drupal\lingotek\Entity\LingotekProfile;
use Drupal\lingotek\Lingotek;
use Drupal\Tests\UnitTestCase;
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
   * @var \Drupal\lingotek\LanguageLocaleMapperInterface
   */
  protected $languageLocaleMapper;

  /**
   * @var \Drupal\lingotek\Remote\LingotekHttpInterface
   */
  protected $api;

  /**
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The Lingotek Filter manager.
   *
   * @var \Drupal\lingotek\LingotekFilterManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $lingotekFilterManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->api = $this->getMock('\Drupal\lingotek\Remote\LingotekApiInterface');
    $this->languageLocaleMapper = $this->getMock('Drupal\lingotek\LanguageLocaleMapperInterface');
    $this->config = $this->getMockBuilder('\Drupal\Core\Config\Config')
      ->disableOriginalConstructor()
      ->getMock();
    $this->lingotekFilterManager = $this->getMock('\Drupal\lingotek\LingotekFilterManagerInterface');
    $config_factory = $this->getMock('\Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('getEditable')
      ->with('lingotek.settings')
      ->will($this->returnValue($this->config));

    $this->lingotek = new Lingotek($this->api, $this->languageLocaleMapper, $config_factory, $this->lingotekFilterManager);
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
    $this->lingotek->getVaults(FALSE);
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

    // Ensure the call will be made.
    $this->api->expects($this->once())
      ->method('getVaults')
      ->will($this->returnValue(['a_vault' => 'A vault']));

    // And the results will be stored.
    $this->config->expects($this->at(2))
      ->method('set')
      ->with('account.resources.vault', ['a_vault' => 'A vault'])
      ->will($this->returnSelf());

    $this->config->expects($this->at(3))
      ->method('save');

    $this->config->expects($this->at(4))
      ->method('get')
      ->with('default.vault')
      ->will($this->returnValue(NULL));

    $this->config->expects($this->at(5))
      ->method('set')
      ->with('default.vault', 'a_vault')
      ->will($this->returnSelf());

    $this->config->expects($this->at(6))
      ->method('save');

    $this->lingotek->getVaults(FALSE);
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

    // Ensure the call will be made.
    $this->api->expects($this->once())
      ->method('getVaults')
      ->will($this->returnValue(['a_vault' => 'A vault']));

    // And the results will be stored.
    $this->config->expects($this->at(2))
      ->method('set')
      ->with('account.resources.vault', ['a_vault' => 'A vault'])
      ->will($this->returnSelf());

    $this->config->expects($this->at(3))
      ->method('save');

    $this->config->expects($this->at(4))
      ->method('get')
      ->with('default.vault')
      ->will($this->returnValue(NULL));

    $this->config->expects($this->at(5))
      ->method('set')
      ->with('default.vault', 'a_vault')
      ->will($this->returnSelf());

    $this->config->expects($this->at(6))
      ->method('save');

    $this->lingotek->getVaults(TRUE);
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
    // A call is performed when getting vaults and there are none locally.
    $this->config->expects($this->at(0))
      ->method('get')
      ->with('account.resources.filter')
      ->will($this->returnValue([]));
    $this->config->expects($this->at(1))
      ->method('get')
      ->with('default.community')
      ->will($this->returnValue(['my_community']));

    // Ensure the call will be made.
    $this->api->expects($this->once())
      ->method('getFilters')
      ->will($this->returnValue(['a_filter' => 'A filter']));

    // And the results will be stored.
    $this->config->expects($this->at(2))
      ->method('set')
      ->with('account.resources.filter', ['a_filter' => 'A filter'])
      ->will($this->returnSelf());

    $this->config->expects($this->at(3))
      ->method('save');

    $this->config->expects($this->at(4))
      ->method('get')
      ->with('default.filter')
      ->will($this->returnValue(NULL));

    $this->config->expects($this->at(5))
      ->method('set')
      ->with('default.filter', 'a_filter')
      ->will($this->returnSelf());

    $this->config->expects($this->at(6))
      ->method('save');

    $this->lingotek->getFilters(FALSE);
  }

  /**
   * @covers ::getFilters
   */
  public function testGetFiltersWithDataButForcing() {
    // A call is performed when forced even if there are vaults locally.
    $this->config->expects($this->at(0))
      ->method('get')
      ->with('account.resources.filter')
      ->will($this->returnValue(['a_filter' => 'A filter']));

    $this->config->expects($this->at(1))
      ->method('get')
      ->with('default.community')
      ->will($this->returnValue(['my_community']));

    // Ensure the call will be made.
    $this->api->expects($this->once())
      ->method('getFilters')
      ->will($this->returnValue(['a_filter' => 'A filter']));

    // And the results will be stored.
    $this->config->expects($this->at(2))
      ->method('set')
      ->with('account.resources.filter', ['a_filter' => 'A filter'])
      ->will($this->returnSelf());

    $this->config->expects($this->at(3))
      ->method('save');

    $this->config->expects($this->at(4))
      ->method('get')
      ->with('default.filter')
      ->will($this->returnValue(NULL));

    $this->config->expects($this->at(5))
      ->method('set')
      ->with('default.filter', 'a_filter')
      ->will($this->returnSelf());

    $this->config->expects($this->at(6))
      ->method('save');

    $this->lingotek->getFilters(TRUE);
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
    $this->lingotek->getProjects(FALSE);
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

    // Ensure the call will be made.
    $this->api->expects($this->once())
      ->method('getProjects')
      ->will($this->returnValue(['a_project' => 'A project']));

    // And the results will be stored.
    $this->config->expects($this->at(2))
      ->method('set')
      ->with('account.resources.project', ['a_project' => 'A project'])
      ->will($this->returnSelf());

    $this->config->expects($this->at(3))
      ->method('save');

    $this->config->expects($this->at(4))
      ->method('get')
      ->with('default.project')
      ->will($this->returnValue(NULL));

    $this->config->expects($this->at(5))
      ->method('set')
      ->with('default.project', 'a_project')
      ->will($this->returnSelf());

    $this->config->expects($this->at(6))
      ->method('save');

    $this->lingotek->getProjects(FALSE);
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

    // Ensure the call will be made.
    $this->api->expects($this->once())
      ->method('getProjects')
      ->will($this->returnValue(['a_project' => 'A project']));

    // And the results will be stored.
    $this->config->expects($this->at(2))
      ->method('set')
      ->with('account.resources.project', ['a_project' => 'A project'])
      ->will($this->returnSelf());

    $this->config->expects($this->at(3))
      ->method('save');

    $this->config->expects($this->at(4))
      ->method('get')
      ->with('default.project')
      ->will($this->returnValue(NULL));

    $this->config->expects($this->at(5))
      ->method('set')
      ->with('default.project', 'a_project')
      ->will($this->returnSelf());

    $this->config->expects($this->at(6))
      ->method('save');

    $this->lingotek->getProjects(TRUE);
  }

  /**
   * @covers ::uploadDocument
   */
  public function testUploadDocument() {
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
        'content' => '"content"',
        'locale_code' => 'es',
        'format' => 'JSON',
        'project_id' => 'my_test_project',
        'fprm_subfilter_id' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265',
        'fprm_id' => '4f91482b-5aa1-4a4a-a43f-712af7b39625',
        'vault_id' => 'my_test_vault',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
      ]);

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
      ]);

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
      ]);

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
       ]);

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
      ]);

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
      ]);

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
      ]);

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
      ]);

    // We upload with a profile that has a vault and a project.
    $profile = new LingotekProfile(['id' => 'profile1', 'project' => 'my_test_project', 'vault' => 'my_test_vault'], 'lingotek_profile');
    $this->lingotek->uploadDocument('title', 'content', 'es', NULL, $profile);

    // We upload with a profile that has another vault and another project.
    $profile = new LingotekProfile(['id' => 'profile2', 'project' => 'another_test_project', 'vault' => 'another_test_vault'], 'lingotek_profile');
    $this->lingotek->uploadDocument('title', 'content', 'es', NULL, $profile);

    // We upload with a profile that has marked to use the default vault and project,
    // so must be replaced.
    $profile = new LingotekProfile(['id' => 'profile2', 'project' => 'default', 'vault' => 'default', 'filter' => 'drupal_default'], 'lingotek_profile');
    $this->lingotek->uploadDocument('title', 'content', 'es', NULL, $profile);

    // We upload without a profile.
    $this->lingotek->uploadDocument('title', 'content', 'es', NULL, NULL);

    // We upload without a profile, but with url.
    $this->lingotek->uploadDocument('title', 'content', 'es', 'http://example.com/node/1', NULL);

    // We upload with a profile that has marked to use the project default
    // workflow template vault, so must be omitted.
    $profile = new LingotekProfile(['id' => 'profile2', 'project' => 'default', 'vault' => 'project_workflow_vault', 'filter' => '0e79f34d-f27b-4a0c-880e-cd9181a5d265'], 'lingotek_profile');
    $this->lingotek->uploadDocument('title', 'content', 'es', NULL, $profile);

    // We upload with content as an array.
    $profile = new LingotekProfile(['id' => 'profile0', 'project' => 'test_project', 'vault' => 'test_vault'], 'lingotek_profile');
    $this->lingotek->uploadDocument('title', ['content' => 'wedgiePlatypus'], 'es', NULL, $profile);

    // We upload with a job ID.
    $this->lingotek->uploadDocument('title', ['content' => 'wedgiePlatypus'], 'es', NULL, $profile, 'my_job_id');
  }

  /**
   * @covers ::updateDocument
   */
  public function testUpdateDocument() {
    $response = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')
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
      ])
      ->will($this->returnValue($response));

    $this->api->expects($this->at(6))
      ->method('patchDocument')
      ->with('my_doc_id', [
        'format' => 'JSON',
        'job_id' => 'my_job_id',
        'external_application_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
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
  }

  /**
   * @covers ::addTarget
   */
  public function testAddTarget() {
    $response = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $response->expects($this->any())
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_CREATED);
    $language = $this->getMock('\Drupal\language\ConfigurableLanguageInterface');
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
      ->with('my_doc_id', 'es_ES', 'overridden_workflow')
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
    $profile = new LingotekProfile(['id' => 'profile2', 'workflow' => 'default', 'language_overrides' => ['es' => ['overrides' => 'custom', 'custom' => ['workflow' => 'overridden_workflow']]]], 'lingotek_profile');
    $this->assertTrue($this->lingotek->addTarget('my_doc_id', 'es_ES', $profile));

    // We upload with a profile that has another vault and another project, but
    // overridden with a default, so must be replaced.
    $profile = new LingotekProfile(['id' => 'profile2', 'workflow' => 'a_different_test_workflow', 'language_overrides' => ['es' => ['overrides' => 'custom', 'custom' => ['workflow' => 'default']]]], 'lingotek_profile');
    $this->assertTrue($this->lingotek->addTarget('my_doc_id', 'es_ES', $profile));

    // We upload without a profile
    $this->assertTrue($this->lingotek->addTarget('my_doc_id', 'es_ES', NULL));
  }

  /**
   * @covers ::deleteDocument
   */
  public function testDeleteDocument() {
    $response = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')
      ->disableOriginalConstructor()
      ->getMock();
    // Both HTTP_ACCEPTED (202) AND HTTP_NO_CONTENT (204) are success statuses.
    $response->expects($this->at(0))
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_ACCEPTED);
    $response->expects($this->at(1))
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_NO_CONTENT);

    // Test returning an error.
    $response->expects($this->at(2))
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_INTERNAL_SERVER_ERROR);

    $response->expects($this->at(3))
      ->method('getStatusCode')
      ->willReturn(Response::HTTP_NOT_FOUND);

    $this->api->expects($this->any())
      ->method('deleteDocument')
      ->with('my_doc_id')
      ->will($this->returnValue($response));

    $this->assertTrue($this->lingotek->deleteDocument('my_doc_id'));
    $this->assertTrue($this->lingotek->deleteDocument('my_doc_id'));
    $this->assertFalse($this->lingotek->deleteDocument('my_doc_id'));
    $this->assertFalse($this->lingotek->deleteDocument('my_doc_id'));
  }

  /**
   * @covers ::getDocumentTranslationStatus
   */
  public function testGetDocumentTranslationStatus() {
    $response = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')
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
                ],
            ],
            [
              'properties' =>
                [
                  'locale_code' => 'de-DE',
                  'percent_complete' => 50,
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
   * @covers ::uploadDocument
   */
  public function testUploadWithNoMetadataLeaked() {
    $response = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')
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
    $response = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')
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

    $data = ['content' => 'My test content', '_lingotek_metadata' => ['_entity_id' => 1, '_intelligence' => ['content_type' => 'node']]];
    $this->lingotek->updateDocument('my_doc_id', $data);
  }

}
