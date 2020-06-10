<?php

namespace Drupal\Tests\lingotek\Unit\Moderation;

use Drupal\lingotek\Moderation\LingotekModerationConfigurationServiceInterface;
use Drupal\lingotek\Moderation\LingotekModerationFactory;
use Drupal\lingotek\Moderation\LingotekModerationHandlerInterface;
use Drupal\lingotek\Moderation\LingotekModerationSettingsFormInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for the moderation factory.
 *
 * @coversDefaultClass \Drupal\lingotek\Moderation\LingotekModerationFactory
 * @group lingotek
 * @preserveGlobalState disabled
 */
class LingotekModerationFactoryTest extends UnitTestCase {

  /**
   * @var \Drupal\lingotek\Moderation\LingotekModerationFactoryInterface
   */
  protected $factory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->factory = new LingotekModerationFactory();
  }

  /**
   * @covers ::addModerationConfiguration
   * @covers ::getModerationConfigurationService
   */
  public function testAddModerationConfiguration() {
    $configServiceLast = $this->createMock(LingotekModerationConfigurationServiceInterface::class);
    $configServiceLast->expects($this->any())
      ->method('applies')
      ->willReturn(TRUE);
    $configServiceFirst = $this->createMock(LingotekModerationConfigurationServiceInterface::class);
    $configServiceFirst->expects($this->any())
      ->method('applies')
      ->willReturn(TRUE);

    $this->factory->addModerationConfiguration($configServiceLast, 'last', 10);
    $this->factory->addModerationConfiguration($configServiceFirst, 'first', 100);

    $configService = $this->factory->getModerationConfigurationService();
    $this->assertEquals($configService, $configServiceFirst, 'Priority is respected if all services apply.');
  }

  /**
   * @covers ::addModerationConfiguration
   * @covers ::getModerationConfigurationService
   */
  public function testAddModerationConfigurationWithANonApplyingService() {
    $configServiceLast = $this->createMock(LingotekModerationConfigurationServiceInterface::class);
    $configServiceLast->expects($this->any())
      ->method('applies')
      ->willReturn(TRUE);
    $configServiceFirst = $this->createMock(LingotekModerationConfigurationServiceInterface::class);
    $configServiceFirst->expects($this->any())
      ->method('applies')
      ->willReturn(FALSE);

    $this->factory->addModerationConfiguration($configServiceLast, 'last', 10);
    $this->factory->addModerationConfiguration($configServiceFirst, 'first', 100);

    $configService = $this->factory->getModerationConfigurationService();
    $this->assertEquals($configService, $configServiceLast, 'Priority is respected, but we return a services that applies.');
  }

  /**
   * @covers ::addModerationForm
   * @covers ::getModerationSettingsForm
   */
  public function testAddModerationSettingsForm() {
    $configServiceLast = $this->createMock(LingotekModerationSettingsFormInterface::class);
    $configServiceLast->expects($this->any())
      ->method('applies')
      ->willReturn(TRUE);
    $configServiceFirst = $this->createMock(LingotekModerationSettingsFormInterface::class);
    $configServiceFirst->expects($this->any())
      ->method('applies')
      ->willReturn(TRUE);

    $this->factory->addModerationForm($configServiceLast, 'last', 10);
    $this->factory->addModerationForm($configServiceFirst, 'first', 100);

    $configService = $this->factory->getModerationSettingsForm();
    $this->assertEquals($configService, $configServiceFirst, 'Priority is respected if all services apply.');
  }

  /**
   * @covers ::addModerationForm
   * @covers ::getModerationSettingsForm
   */
  public function testAddModerationSettingsFormWithANonApplyingService() {
    $configServiceLast = $this->createMock(LingotekModerationSettingsFormInterface::class);
    $configServiceLast->expects($this->any())
      ->method('applies')
      ->willReturn(TRUE);
    $configServiceFirst = $this->createMock(LingotekModerationSettingsFormInterface::class);
    $configServiceFirst->expects($this->any())
      ->method('applies')
      ->willReturn(FALSE);

    $this->factory->addModerationForm($configServiceLast, 'last', 10);
    $this->factory->addModerationForm($configServiceFirst, 'first', 100);

    $configService = $this->factory->getModerationSettingsForm();
    $this->assertEquals($configService, $configServiceLast, 'Priority is respected, but we return a services that applies.');
  }

  /**
   * @covers ::addModerationHandler
   * @covers ::getModerationHandler
   */
  public function testAddModerationHandler() {
    $configServiceLast = $this->createMock(LingotekModerationHandlerInterface::class);
    $configServiceLast->expects($this->any())
      ->method('applies')
      ->willReturn(TRUE);
    $configServiceFirst = $this->createMock(LingotekModerationHandlerInterface::class);
    $configServiceFirst->expects($this->any())
      ->method('applies')
      ->willReturn(TRUE);

    $this->factory->addModerationHandler($configServiceLast, 'last', 10);
    $this->factory->addModerationHandler($configServiceFirst, 'first', 100);

    $configService = $this->factory->getModerationHandler();
    $this->assertEquals($configService, $configServiceFirst, 'Priority is respected if all services apply.');
  }

  /**
   * @covers ::addModerationHandler
   * @covers ::getModerationHandler
   */
  public function testAddModerationHandlerWithANonApplyingService() {
    $configServiceLast = $this->createMock(LingotekModerationHandlerInterface::class);
    $configServiceLast->expects($this->any())
      ->method('applies')
      ->willReturn(TRUE);
    $configServiceFirst = $this->createMock(LingotekModerationHandlerInterface::class);
    $configServiceFirst->expects($this->any())
      ->method('applies')
      ->willReturn(FALSE);

    $this->factory->addModerationHandler($configServiceLast, 'last', 10);
    $this->factory->addModerationHandler($configServiceFirst, 'first', 100);

    $configService = $this->factory->getModerationHandler();
    $this->assertEquals($configService, $configServiceLast, 'Priority is respected, but we return a services that applies.');
  }

}
