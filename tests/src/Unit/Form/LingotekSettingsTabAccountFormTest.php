<?php

namespace Drupal\Tests\lingotek\Unit\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\lingotek\Form\LingotekSettingsTabAccountForm;
use Drupal\lingotek\LingotekFilterManagerInterface;
use Drupal\lingotek\LingotekInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\lingotek\Form\LingotekSettingsTabAccountForm
 * @group lingotek
 * @preserveGlobalState disabled
 */
class LingotekSettingsTabAccountFormTest extends UnitTestCase {

  /**
   * The Lingotek service
   *
   * @var \Drupal\lingotek\LingotekInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $lingotek;

  /**
   * The config factory.
   *
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
   * The url generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $urlGenerator;

  /**
   * The link generator.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $linkGenerator;

  /**
   * @var \Drupal\lingotek\Form\LingotekSettingsTabAccountForm
   */
  protected $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->lingotek = $this->createMock(LingotekInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->lingotekFilterManager = $this->createMock(LingotekFilterManagerInterface::class);
    $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $this->linkGenerator = $this->createMock(LinkGeneratorInterface::class);
    $messenger = $this->createMock(MessengerInterface::class);

    $this->form = new LingotekSettingsTabAccountForm(
      $this->lingotek,
      $this->configFactory,
      $this->lingotekFilterManager,
      $this->urlGenerator,
      $this->linkGenerator
    );
    $this->form->setStringTranslation($this->getStringTranslationStub());
    $this->form->setMessenger($messenger);
  }

  /**
   * @covers ::getFormId
   */
  public function testGetFormId() {
    $form_id = $this->form->getFormID();
    $this->assertSame('lingotek.settings_tab_account_form', $form_id);
  }

  /**
   * @covers ::buildForm
   */
  public function testBuildForm() {
    $config = $this->createMock(Config::class);
    $config->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['account.login_id', 'test@example.com'],
        ['account.access_token', 'ef4b4d69-5be2-4513-b4f1-7e0f6f9511a0'],
      ]);
    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('lingotek.settings')
      ->willReturn($config);

    $this->lingotekFilterManager->expects($this->once())
      ->method('getLocallyAvailableFilters')
      ->willReturn([
        'filter 1' => 'filter-uuid-1',
      ]);

    $build = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $build = $this->form->buildForm($build, $form_state);
    $this->assertSame('ef4b4d69-5be2-4513-b4f1-7e0f6f9511a0', $build['account']['account_table']['token_row'][1]['#markup']);
  }

  /**
   * @covers ::disconnect
   */
  public function testDisconnect() {
    $build = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('setRedirect')
      ->with('lingotek.account_disconnect');
    $build = $this->form->disconnect($build, $form_state);
  }

}
