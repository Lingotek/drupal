<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the module uninstall when having metadata in the entity as in 8.x-1.x.
 * This is not a real update path tests, but we want to use a dump.
 *
 * @group lingotek
 */
class LingotekModuleUninstallWith8x1xDataTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.pre8213.php.gz',
    ];
  }

  /**
   * Tests that the module can be uninstalled.
   */
  public function testUninstallModule() {
    $this->runUpdates();

    $this->drupalLogin($this->rootUser);

    // Navigate to the Extend page.
    $this->drupalGet('/admin/modules');

    // Ensure the module is not enabled yet.
    $this->assertSession()->checkboxChecked('edit-modules-lingotek-enable');

    $this->clickLink('Uninstall');

    $this->assertSession()->fieldDisabled('edit-uninstall-lingotek');

    $this->assertText('The following reason prevents Lingotek Translation from being uninstalled:');
    $this->assertText('There is content for the entity type: Lingotek Content Metadata');
    $this->assertLink('Remove lingotek content metadata entities');

    $this->clickLink('Remove lingotek content metadata entities');
    $this->assertText('Are you sure you want to delete all lingotek content metadata entities?');
    $this->assertText('This will delete 15 lingotek content metadata entities.');
    $this->drupalPostForm(NULL, [], 'Delete all lingotek content metadata entities');

    $this->assertFalse($this->getSession()->getPage()->findField('edit-uninstall-lingotek')->hasAttribute('disabled'));

    // Post the form uninstalling the lingotek module.
    $edit = ['uninstall[lingotek]' => '1'];
    $this->drupalPostForm(NULL, $edit, 'Uninstall');

    // We get an advice and we can confirm.
    $this->assertText('The following modules will be completely uninstalled from your site, and all data from these modules will be lost!');
    $this->assertText('The listed configuration will be deleted.');
    $this->assertText('Lingotek Profile');

    $this->drupalPostForm(NULL, [], 'Uninstall');

    $this->assertText('The selected modules have been uninstalled.');
  }

}
