<?php

namespace Drupal\Tests\lingotek\Unit\Form {

  use Drupal\Core\Config\Config;
  use Drupal\Core\Config\ConfigFactoryInterface;
  use Drupal\Core\Form\FormStateInterface;
  use Drupal\Core\Messenger\MessengerInterface;
  use Drupal\Core\Routing\RouteBuilderInterface;
  use Drupal\Core\Routing\UrlGeneratorInterface;
  use Drupal\Core\State\StateInterface;
  use Drupal\Core\Utility\LinkGeneratorInterface;
  use Drupal\lingotek\Form\LingotekSettingsTabUtilitiesForm;
  use Drupal\lingotek\LingotekInterface;
  use Drupal\Tests\UnitTestCase;

  /**
   * @coversDefaultClass \Drupal\lingotek\Form\LingotekSettingsTabUtilitiesForm
   * @group lingotek
   * @preserveGlobalState disabled
   */
  class LingotekSettingsTabUtilitiesFormTest extends UnitTestCase {

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
     * @var \Drupal\Core\State\StateInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $state;

    /**
     * @var \Drupal\Core\Routing\RouteBuilderInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $routeBuilder;

    /**
     * @var LingotekSettingsTabUtilitiesForm
     */
    protected $form;

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

    protected function setUp(): void {
      parent::setUp();

      $this->lingotek = $this->createMock(LingotekInterface::class);
      $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
      $this->state = $this->createMock(StateInterface::class);
      $this->routeBuilder = $this->createMock(RouteBuilderInterface::class);
      $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
      $this->linkGenerator = $this->createMock(LinkGeneratorInterface::class);
      $messenger = $this->createMock(MessengerInterface::class);

      $this->form = new LingotekSettingsTabUtilitiesForm(
        $this->lingotek,
        $this->configFactory,
        $this->state,
        $this->routeBuilder,
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
      $this->assertSame('lingotek.settings_tab_utilities_form', $form_id);
    }

    /**
     * @covers ::buildForm
     */
    public function testFormDebugUtilityWithDebugDisabled() {
      $config = $this->createMock(Config::class);
      $config->expects($this->once())
        ->method('get')
        ->with('account.callback_url')
        ->willReturn('http://example.com/lingotek/notify');
      $this->configFactory->expects($this->once())
        ->method('get')
        ->with('lingotek.settings')
        ->willReturn($config);
      $this->state->expects($this->any())
        ->method('get')
        ->with('lingotek.enable_debug_utilities')
        ->will($this->returnValue(FALSE));
      $build = [];
      $form_state = $this->createMock(FormStateInterface::class);
      $build = $this->form->buildForm($build, $form_state);
      $this->assertSame($build['utilities']['lingotek_table']['enable_debug_utilities']['actions']['submit']['#value']->getUntranslatedString(), 'Enable debug operations');
    }

    /**
     * @covers ::buildForm
     */
    public function testFormDebugUtilityWithDebugEnabled() {
      $config = $this->createMock(Config::class);
      $config->expects($this->once())
        ->method('get')
        ->with('account.callback_url')
        ->willReturn('http://example.com/lingotek/notify');
      $this->configFactory->expects($this->once())
        ->method('get')
        ->with('lingotek.settings')
        ->willReturn($config);
      $this->state->expects($this->any())
        ->method('get')
        ->with('lingotek.enable_debug_utilities')
        ->will($this->returnValue(TRUE));
      $build = [];
      $form_state = $this->createMock(FormStateInterface::class);
      $build = $this->form->buildForm($build, $form_state);
      $this->assertSame($build['utilities']['lingotek_table']['enable_debug_utilities']['actions']['submit']['#value']->getUntranslatedString(), 'Disable debug operations');
    }

    /**
     * @covers ::switchDebugUtilities
     */
    public function testSwitchDebugWithDebugEnabled() {
      $this->state->expects($this->any())
        ->method('get')
        ->with('lingotek.enable_debug_utilities')
        ->will($this->returnValue(TRUE));
      $this->state->expects($this->once())
        ->method('set')
        ->with('lingotek.enable_debug_utilities', FALSE);
      $this->routeBuilder->expects($this->once())
        ->method('rebuild');

      $this->form->switchDebugUtilities();
    }

    /**
     * @covers ::switchDebugUtilities
     */
    public function testSwitchDebugWithDebugDisabled() {
      $this->state->expects($this->any())
        ->method('get')
        ->with('lingotek.enable_debug_utilities')
        ->will($this->returnValue(FALSE));
      $this->state->expects($this->once())
        ->method('set')
        ->with('lingotek.enable_debug_utilities', TRUE);
      $this->routeBuilder->expects($this->once())
        ->method('rebuild');

      $this->form->switchDebugUtilities();
    }

    /**
     * @covers ::refreshResources
     */
    public function testRefreshResources() {
      $this->lingotek->expects($this->once())
        ->method('getResources')
        ->with(TRUE);
      $this->configFactory->expects($this->never())
        ->method('getEditable');
      $this->form->refreshResources();
    }

  }
}
