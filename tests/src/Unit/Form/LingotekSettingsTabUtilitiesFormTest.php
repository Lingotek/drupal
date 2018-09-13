<?php

namespace Drupal\Tests\lingotek\Unit\Form {

  use Drupal\lingotek\Form\LingotekSettingsTabUtilitiesForm;
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

    protected function setUp() {
      parent::setUp();

      $this->lingotek = $this->getMock('Drupal\lingotek\LingotekInterface');
      $this->configFactory = $this->getMock('Drupal\Core\Config\ConfigFactoryInterface');
      $this->state = $this->getMock('Drupal\Core\State\StateInterface');
      $this->routeBuilder = $this->getMock('Drupal\Core\Routing\RouteBuilderInterface');

      $this->form = new LingotekSettingsTabUtilitiesForm(
      $this->lingotek,
      $this->configFactory,
      $this->state,
      $this->routeBuilder
      );
      $this->form->setStringTranslation($this->getStringTranslationStub());
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
      $this->state->expects($this->any())
        ->method('get')
        ->with('lingotek.enable_debug_utilities')
        ->will($this->returnValue(FALSE));
      $build = [];
      $form_state = $this->getMock('Drupal\Core\Form\FormStateInterface');
      $build = $this->form->buildForm($build, $form_state);
      $this->assertSame($build['utilities']['lingotek_table']['enable_debug_utilities']['actions']['submit']['#value']->getUntranslatedString(), 'Enable debug operations');
    }

    /**
   * @covers ::buildForm
   */
    public function testFormDebugUtilityWithDebugEnabled() {
      $this->state->expects($this->any())
        ->method('get')
        ->with('lingotek.enable_debug_utilities')
        ->will($this->returnValue(TRUE));
      $build = [];
      $form_state = $this->getMock('Drupal\Core\Form\FormStateInterface');
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

  }
}

namespace {

  // @todo Delete after https://drupal.org/node/1858196 is in.
  if (!function_exists('drupal_set_message')) {

    function drupal_set_message() {
    }

  }
}
