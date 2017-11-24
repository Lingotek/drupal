<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\Core\Url;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Tests the upgrade path after changing route names.
 *
 * @group lingotek
 */
class LingotekRouteNamesRequestTranslation8003Test extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.pre8003.php.gz',
    ];
  }

  /**
   * Tests that the entity definition is loaded correctly.
   */
  public function testRouteName() {
    $document_id = 'my_document_id';
    $locale = 'es_ES';
    $url = Url::fromRoute('lingotek.entity.request_translation',
      [
        'doc_id' => $document_id,
        'locale' => $locale,
      ]);

    try {
      $url->getInternalPath();
      $this->fail('The route did not exist');
    }
    catch (RouteNotFoundException $exception) {
      // We are good.
      $this->assertTrue(TRUE, 'The route was not found before the update.');
    }

    $this->runUpdates();

    $url = Url::fromRoute('lingotek.entity.request_translation',
      [
        'doc_id' => $document_id,
        'locale' => $locale,
      ]);

    try {
      $url->getInternalPath();
      // We are good.
      $this->assertTrue(TRUE, 'The route was found after the update.');
    }
    catch (RouteNotFoundException $exception) {
      $this->fail('The route did not exist');
    }
  }

}
