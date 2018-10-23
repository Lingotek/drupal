<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\node\Entity\Node;

/**
 * Tests the upgrade path for deleting the lingotek old metadata fields.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekOldMetadataFieldUninstalledUpdate8215Test extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.pre8213.php.gz',
    ];
  }

  /**
   * Tests that the upgrade sets the correct field formatter id in a view.
   */
  public function testUpgrade() {
    $node = Node::load(1);
    $this->assertTrue($node->hasField('lingotek_document_id'));
    $this->assertTrue($node->hasField('lingotek_hash'));
    $this->assertTrue($node->hasField('lingotek_profile'));
    $this->assertTrue($node->hasField('lingotek_translation_source'));
    $this->assertTrue($node->hasField('lingotek_translation_status'));
    $this->assertFalse($node->hasField('lingotek_translation_created'));
    $this->assertFalse($node->hasField('lingotek_translation_changed'));
    $this->assertTrue($node->hasField('lingotek_metadata'));

    $this->runUpdates();

    $node = Node::load(1);
    $this->assertFalse($node->hasField('lingotek_document_id'));
    $this->assertFalse($node->hasField('lingotek_hash'));
    $this->assertFalse($node->hasField('lingotek_profile'));
    $this->assertFalse($node->hasField('lingotek_translation_source'));
    $this->assertFalse($node->hasField('lingotek_translation_status'));
    $this->assertFalse($node->hasField('lingotek_translation_created'));
    $this->assertFalse($node->hasField('lingotek_translation_changed'));
    $this->assertTrue($node->hasField('lingotek_metadata'));

  }

}
