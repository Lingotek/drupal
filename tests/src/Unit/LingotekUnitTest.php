<?php

namespace Drupal\Tests\lingotek\Unit;

use Drupal\lingotek\Entity\LingotekProfile;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\Remote\LingotekHttpInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\lingotek\Lingotek
 * @group lingotek
 * @preserveGlobalState disabled
 */

class LingotekUnitTest extends UnitTestCase {

  /**
   * @var Lingotek
   */
  protected $lingotek;

  /**
   * @var LingotekHttpInterface
   */
  protected $api;

  /**
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->api = $this->getMock('\Drupal\lingotek\Remote\LingotekApiInterface');
    $this->config = $this->getMockBuilder('\Drupal\Core\Config\Config')
      ->disableOriginalConstructor()
      ->getMock();
    $config_factory = $this->getMock('\Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('getEditable')
      ->with('lingotek.settings')
      ->will($this->returnValue($this->config));

    $this->lingotek = new Lingotek($this->api, $config_factory);
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
   * @covers ::uploadDocument
   */
  public function testUploadDocument() {
    $this->config->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap([['default.project', 'project1'],['default.workflow', 'wf1'],['default.vault', 'default_vault']]));

    // Vault id has the original value.
    $this->api->expects($this->at(0))
      ->method('addDocument')
      ->with(['title' => 'title', 'content' => 'content', 'locale_code' => 'es',
              'format' => 'JSON', 'project_id' => 'project1', 'workflow_id' => 'wf1',
              'vault_id' => 'my_test_vault']);

    // Vault id has changed.
    $this->api->expects($this->at(1))
      ->method('addDocument')
      ->with(['title' => 'title', 'content' => 'content', 'locale_code' => 'es',
              'format' => 'JSON', 'project_id' => 'project1', 'workflow_id' => 'wf1',
              'vault_id' => 'another_test_vault']);

    // If there is a profile with default vault, it must be replaced.
    $this->api->expects($this->at(2))
      ->method('addDocument')
      ->with(['title' => 'title', 'content' => 'content', 'locale_code' => 'es',
              'format' => 'JSON', 'project_id' => 'project1', 'workflow_id' => 'wf1',
              'vault_id' => 'default_vault']);

    // If there is no profile, vault should not be included.
    $this->api->expects($this->at(3))
      ->method('addDocument')
      ->with(['title' => 'title', 'content' => 'content', 'locale_code' => 'es',
              'format' => 'JSON', 'project_id' => 'project1', 'workflow_id' => 'wf1',
             ]);

    // We upload with a profile that has a vault.
    $profile = new LingotekProfile(['id' => 'profile1', 'vault' => 'my_test_vault'], 'lingotek_profile');
    $this->lingotek->uploadDocument('title', 'content', 'es', $profile);

    // We upload with a profile that has another vault.
    $profile = new LingotekProfile(['id' => 'profile2', 'vault' => 'another_test_vault'], 'lingotek_profile');
    $this->lingotek->uploadDocument('title', 'content', 'es', $profile);

    // We upload with a profile that has marked to use the default vault,
    // so must be replaced.
    $profile = new LingotekProfile(['id' => 'profile2', 'vault' => 'default'], 'lingotek_profile');
    $this->lingotek->uploadDocument('title', 'content', 'es', $profile);

    // We upload without a profile
    $this->lingotek->uploadDocument('title', 'content', 'es', NULL);
  }

}
