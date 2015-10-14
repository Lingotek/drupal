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
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->api = $this->getMock('\Drupal\lingotek\Remote\LingotekApiInterface');
    $config = $this->getMockBuilder('\Drupal\Core\Config\Config')
      ->disableOriginalConstructor()
      ->getMock();
    $config->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap([['default.project', 'project1'],['default.workflow', 'wf1']]));

    $config_factory = $this->getMock('\Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('getEditable')
      ->will($this->returnValue($config));

    $this->lingotek = new Lingotek($this->api, $config_factory);
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
