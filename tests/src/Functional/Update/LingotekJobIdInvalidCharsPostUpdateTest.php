<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\field\Entity\FieldConfig;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests the upgrade path for updating the Lingotek job ids.
 *
 * @group lingotek
 */
class LingotekJobIdInvalidCharsPostUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.job-ids-slashes.php.gz',
    ];
  }

  /**
   * Tests that the Lingotek metadata dependencies are updated correctly.
   */
  public function testJobIdsInvalidCharsPostUpdates() {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $contentTranslationService */
    $contentTranslationService = \Drupal::service('lingotek.content_translation');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $configTranslationService */
    $configTranslationService = \Drupal::service('lingotek.config_translation');
    $mappers = \Drupal::service('plugin.manager.config_translation.mapper')->getMappers();

    $node1 = Node::load(1);
    $node2 = Node::load(2);
    $node3 = Node::load(3);
    $node4 = Node::load(4);
    $this->assertEquals('my-job-id-1', $contentTranslationService->getJobId($node1));
    $this->assertEquals('my-job-id/2', $contentTranslationService->getJobId($node2));
    $this->assertEquals('my-job-id\3', $contentTranslationService->getJobId($node3));
    $this->assertEquals('my/job/id\4', $contentTranslationService->getJobId($node4));

    $config1 = FieldConfig::load('node.article.body');
    $config2 = FieldConfig::load('node.page.body');
    $config3 = NodeType::load('article');
    $config4 = NodeType::load('page');
    $this->assertEquals('my-job-id/2', $configTranslationService->getJobId($config1));
    $this->assertEquals('my-job-id-1', $configTranslationService->getJobId($config2));
    $this->assertEquals('my/job/id\4', $configTranslationService->getJobId($config3));
    $this->assertEquals('my-job-id\3', $configTranslationService->getJobId($config4));
    $this->assertEquals('my/job/id\4', $configTranslationService->getConfigJobId($mappers['system.site_maintenance_mode']));

    $this->runUpdates();

    $node1 = Node::load(1);
    $node2 = Node::load(2);
    $node3 = Node::load(3);
    $node4 = Node::load(4);
    $this->assertEquals('my-job-id-1', $contentTranslationService->getJobId($node1));
    $this->assertEquals('my-job-id-2', $contentTranslationService->getJobId($node2));
    $this->assertEquals('my-job-id-3', $contentTranslationService->getJobId($node3));
    $this->assertEquals('my-job-id-4', $contentTranslationService->getJobId($node4));

    $config1 = FieldConfig::load('node.article.body');
    $config2 = FieldConfig::load('node.page.body');
    $config3 = NodeType::load('article');
    $config4 = NodeType::load('page');
    $this->assertEquals('my-job-id-2', $configTranslationService->getJobId($config1));
    $this->assertEquals('my-job-id-1', $configTranslationService->getJobId($config2));
    $this->assertEquals('my-job-id-4', $configTranslationService->getJobId($config3));
    $this->assertEquals('my-job-id-3', $configTranslationService->getJobId($config4));
    $this->assertEquals('my-job-id-4', $configTranslationService->getConfigJobId($mappers['system.site_maintenance_mode']));

    $this->drupalLogin($this->rootUser);
    $this->drupalGet('admin/lingotek/jobs');
    $this->assertText('Translation Jobs');
    $this->assertText('my-job-id-1');
    $this->assertText('7 content items, 1 config items');
    $this->assertText('my-job-id-2');
    $this->assertText('3 content items, 1 config items');
    $this->assertText('my-job-id-3');
    $this->assertText('3 content items, 1 config items');
    $this->assertText('my-job-id-4');
    $this->assertText('2 content items, 2 config items');
  }

}
