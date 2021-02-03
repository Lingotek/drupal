<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\webform\Traits\WebformBrowserTestTrait;

/**
 * Tests translating a config entity using the bulk management form.
 *
 * @group lingotek
 */
class LingotekWebformBulkTranslationTest extends LingotekTestBase {

  use WebformBrowserTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'frozenintime', 'webform'];

  /**
   * A webform.
   *
   * @var \Drupal\webform\WebformInterface
   */
  protected $webform;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $values = [
      'id' => 'test',
      'title' => 'Test',
    ];
    $elements = [
      'first_name' => [
        '#type' => 'textfield',
        '#title' => 'First name',
      ],
      'last_name' => [
        '#type' => 'textfield',
        '#title' => 'Last name',
      ],
      'sex' => [
        '#type' => 'webform_select_other',
        '#options' => 'sex',
        '#title' => 'Sex',
      ],
      'martial_status' => [
        '#type' => 'webform_select_other',
        '#options' => 'marital_status',
        '#title' => 'Martial status',
      ],
      'employment_status' => [
        '#type' => 'webform_select_other',
        '#options' => 'employment_status',
        '#title' => 'Employment status',
      ],
      'age' => [
        '#type' => 'number',
        '#title' => 'Age',
      ],
    ];
    $this->webform = $this->createWebform($values, $elements);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    $this->saveLingotekConfigTranslationSettings([
      'webform' => 'automatic',
    ]);

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'webform');
  }

  /**
   * Tests that a webform can be translated using the links on the management page.
   */
  public function testWebformTranslationUsingLinks() {
    $assert_session = $this->assertSession();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('webform');

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/webform/test?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/webform/test/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN', 1);
    $this->assertText(t('Test uploaded successfully'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertEqual('Test', $data['title']);
    $this->assertEqual(6, count($data['elements']));
    $this->assertEqual('First name', $data['elements']['first_name']['#title']);
    $this->assertEqual('Last name', $data['elements']['last_name']['#title']);
    $this->assertEqual('Sex', $data['elements']['sex']['#title']);
    $this->assertEqual('Martial status', $data['elements']['martial_status']['#title']);
    $this->assertEqual('Employment status', $data['elements']['employment_status']['#title']);
    $this->assertEqual('Age', $data['elements']['age']['#title']);

    // There is a link for checking status.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/webform/test?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we can already request a translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/webform/test/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN', 1);
    $this->assertText('Test status checked successfully');

    // Request the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/webform/test/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText("Translation to es_MX requested successfully");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_download/webform/test/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to es_MX status checked successfully");

    // Download the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/download/webform/test/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX downloaded successfully');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id', 'ES');

    $this->drupalGet('es/webform/test');
    $assert_session->pageTextContains('Nombre');
    $assert_session->pageTextContains('Apellidos');
    $assert_session->pageTextContains('Sexo');
    $assert_session->pageTextContains('Estado civil');
    $assert_session->pageTextContains('Situación laboral');
    $assert_session->pageTextContains('Edad');
  }

}
