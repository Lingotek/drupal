<?php

namespace Drupal\Tests\lingotek\Unit\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\lingotek\Form\LingotekSettingsConnectForm;
use Drupal\lingotek\LingotekInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\lingotek\Form\LingotekSettingsConnectForm
 * @group lingotek
 * @preserveGlobalState disabled
 */
class LingotekSettingsConnectFormTest extends UnitTestCase {

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
    $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $this->linkGenerator = $this->createMock(LinkGeneratorInterface::class);
    $messenger = $this->createMock(MessengerInterface::class);

    $this->form = new LingotekSettingsConnectForm(
      $this->lingotek,
      $this->configFactory,
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
    $this->assertSame('lingotek.connect_form', $form_id);
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
        ['account.default_client_id', 'my-client-id'],
        ['account.host', 'http://example.com'],
      ]);
    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('lingotek.settings')
      ->willReturn($config);

    $this->urlGenerator->expects($this->once())
      ->method('generateFromRoute')
      ->with('lingotek.setup_account_handshake')
      ->willReturn('http://example.com/setup-account-handshake');

    $build = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $build = $this->form->buildForm($build, $form_state);
    $this->assertSame('http://example.com/?client_id=my-client-id&response_type=token&redirect_uri=http%3A%2F%2Fexample.com%2Fsetup-account-handshake',
      $build['account_types']['existing_account']['cta']['#url']->getUri());
  }

}
