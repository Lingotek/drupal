<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\field\Entity\FieldConfig;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Tests the upgrade path moving Lingotek profile from settings to config metadata.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekConfigEntitiesProfiles8217Test extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.pre8217.php.gz',
    ];
  }

  /**
   * Tests that the Lingotek metadata dependencies are updated correctly.
   */
  public function testLingotekMetadataConfigProfilePostUpdate() {
    // The values we want to remove.
    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('lingotek.settings');

    $node_type_default_profile = $config->get('translate.config.node_type.profile');
    $node_fields_default_profile = $config->get('translate.config.node_fields.profile');
    $article_profile = $config->get('translate.config.node_type.article.profile');
    $article_body_profile = $config->get('translate.config.node_fields.node.article.body.profile');
    $page_profile = $config->get('translate.config.node_type.page.profile');
    $page_body_profile = $config->get('translate.config.node_fields.node.page.body.profile');
    $maintenance_profile = $config->get('translate.config.system.site_maintenance_mode.profile');

    $this->assertEquals('manual', $node_type_default_profile);
    $this->assertEquals('customized', $node_fields_default_profile);
    $this->assertEquals('automatic', $article_profile);
    $this->assertEquals('customized', $article_body_profile);
    $this->assertEquals('manual', $page_profile);
    $this->assertEquals('customized', $page_body_profile);
    $this->assertEquals('manual', $maintenance_profile);

    $this->runUpdates();

    // The values were removed as expected when expected (not defaults but
    // concrete config settings).
    $config_factory = \Drupal::configFactory();
    $config_factory->clearStaticCache();
    $config = $config_factory->getEditable('lingotek.settings');
    $node_type_default_profile = $config->get('translate.config.node_type.profile');
    $node_fields_default_profile = $config->get('translate.config.node_fields.profile');
    $article_profile = $config->get('translate.config.node_type.article.profile');
    $article_body_profile = $config->get('translate.config.node_fields.node.article.body.profile');
    $page_profile = $config->get('translate.config.node_type.page.profile');
    $page_body_profile = $config->get('translate.config.node_fields.node.page.body.profile');
    $maintenance_profile = $config->get('translate.config.system.site_maintenance_mode.profile');

    $this->assertEquals('manual', $node_type_default_profile);
    $this->assertEquals('customized', $node_fields_default_profile);
    $this->assertNull($article_profile);
    $this->assertNull($article_body_profile);
    $this->assertNull($page_profile);
    $this->assertNull($page_body_profile);
    $this->assertNull($maintenance_profile);

    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotekConfig */
    $lingotekConfig = \Drupal::service('lingotek.configuration');
    $node_type_default_profile = $lingotekConfig->getConfigEntityDefaultProfileId('node_type');
    $node_fields_default_profile = $lingotekConfig->getConfigEntityDefaultProfileId('node_fields');
    $article = NodeType::load('article');
    $article_profile = $lingotekConfig->getConfigEntityProfile($article, FALSE);
    $article_body = FieldConfig::load('node.article.body');
    $article_body_profile = $lingotekConfig->getConfigEntityProfile($article_body, FALSE);
    $page = NodeType::load('page');
    $page_profile = $lingotekConfig->getConfigEntityProfile($page, FALSE);
    $page_body = FieldConfig::load('node.page.body');
    $page_body_profile = $lingotekConfig->getConfigEntityProfile($page_body, FALSE);
    $maintenance_profile = $lingotekConfig->getConfigProfile('system.site_maintenance_mode', FALSE);

    $this->assertEquals('manual', $node_type_default_profile);
    $this->assertEquals('customized', $node_fields_default_profile);
    $this->assertEquals('automatic', $article_profile->id());
    $this->assertEquals('customized', $article_body_profile->id());
    $this->assertEquals('manual', $page_profile->id());
    $this->assertEquals('customized', $page_body_profile->id());
    $this->assertEquals('manual', $maintenance_profile->id());
  }

}
