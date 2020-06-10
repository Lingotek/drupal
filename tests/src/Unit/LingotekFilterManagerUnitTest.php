<?php

namespace Drupal\Tests\lingotek\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\lingotek\Entity\LingotekProfile;
use Drupal\lingotek\LingotekFilterManager;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\lingotek\LingotekFilterManager
 * @group lingotek
 * @preserveGlobalState disabled
 */
class LingotekFilterManagerUnitTest extends UnitTestCase {

  /**
   * The Lingotek Filter manager.
   *
   * @var \Drupal\lingotek\LingotekFilterManagerInterface
   */
  protected $filterManager;

  /**
   * The config object.
   *
   * @var \Drupal\Core\Config\Config|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->config = $this->getMockBuilder(Config::class)
      ->disableOriginalConstructor()
      ->getMock();
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->expects($this->any())
      ->method('get')
      ->with('lingotek.settings')
      ->will($this->returnValue($this->config));

    $this->filterManager = new LingotekFilterManager($configFactory);
  }

  /**
   * @covers ::getLocallyAvailableFilters
   */
  public function testGetLocallyAvailableFilters() {
    // Test with no local filters.
    $this->config->expects($this->at(0))
      ->method('get')
      ->with('account.resources.filter')
      ->will($this->returnValue([]));

    $filters = $this->filterManager->getLocallyAvailableFilters();
    $this->assertNotEmpty($filters);
    $this->assertArrayEquals($filters, ['project_default' => 'Project Default', 'drupal_default' => 'Drupal Default']);

    // Test with some filters.
    $this->config->expects($this->at(0))
      ->method('get')
      ->with('account.resources.filter')
      ->will($this->returnValue(['aaa' => 'Test filter']));

    $filters = $this->filterManager->getLocallyAvailableFilters();
    $this->assertNotEmpty($filters);
    $this->assertEquals(['project_default' => 'Project Default', 'drupal_default' => 'Drupal Default', 'aaa' => 'Test filter'], $filters);
  }

  public function getDefaultFilterProvider() {
    return [
      ['bbb', ['aaa' => 'Test label', 'bbb' => 'Another label'], 'bbb'],
      ['aaa', ['aaa' => 'Test label', 'bbb' => 'Another label'], 'aaa'],
      ['xxx', ['aaa' => 'Test label', 'bbb' => 'Another label'], NULL],
      ['xxx', [], NULL],
    ];
  }

  /**
   * @covers ::getDefaultSubfilter
   * @dataProvider getDefaultFilterProvider
   */
  public function testGetDefaultFilter($id, $filters, $expectedFilter) {
    $this->config->expects($this->at(0))
      ->method('get')
      ->with('default.filter')
      ->will($this->returnValue($id));
    $this->config->expects($this->at(1))
      ->method('get')
      ->with('account.resources.filter')
      ->will($this->returnValue($filters));

    $filter = $this->filterManager->getDefaultFilter();
    $this->assertEquals($expectedFilter, $filter);
  }

  /**
   * @covers ::getDefaultSubfilter
   * @dataProvider getDefaultFilterProvider
   */
  public function testGetDefaultSubfilter($id, $filters, $expectedFilter) {
    $this->config->expects($this->at(0))
      ->method('get')
      ->with('default.subfilter')
      ->will($this->returnValue($id));
    $this->config->expects($this->at(1))
      ->method('get')
      ->with('account.resources.filter')
      ->will($this->returnValue($filters));

    $filter = $this->filterManager->getDefaultSubfilter();
    $this->assertEquals($expectedFilter, $filter);
  }

  public function getDefaultFilterLabelProvider() {
    return [
      ['bbb', ['aaa' => 'Test label', 'bbb' => 'Another label'], 'Another label'],
      ['aaa', ['aaa' => 'Test label', 'bbb' => 'Another label'], 'Test label'],
      ['xxx', ['aaa' => 'Test label', 'bbb' => 'Another label'], ''],
      ['xxx', [], ''],
    ];
  }

  /**
   * @covers ::getDefaultFilterLabel
   * @dataProvider getDefaultFilterLabelProvider
   */
  public function testGetDefaultFilterLabel($id, $filters, $expectedLabel) {
    $this->config->expects($this->at(0))
      ->method('get')
      ->with('default.filter')
      ->will($this->returnValue($id));
    $this->config->expects($this->at(1))
      ->method('get')
      ->with('account.resources.filter')
      ->will($this->returnValue($filters));
    $this->config->expects($this->at(2))
      ->method('get')
      ->with('account.resources.filter')
      ->will($this->returnValue($filters));

    $label = $this->filterManager->getDefaultFilterLabel();
    $this->assertEquals($expectedLabel, $label);
  }

  /**
   * @covers ::getDefaultSubfilterLabel
   * @dataProvider getDefaultFilterLabelProvider
   */
  public function testGetDefaultSubfilterLabel($id, $filters, $expectedLabel) {
    $this->config->expects($this->at(0))
      ->method('get')
      ->with('default.subfilter')
      ->will($this->returnValue($id));
    $this->config->expects($this->at(1))
      ->method('get')
      ->with('account.resources.filter')
      ->will($this->returnValue($filters));
    $this->config->expects($this->at(2))
      ->method('get')
      ->with('account.resources.filter')
      ->will($this->returnValue($filters));

    $label = $this->filterManager->getDefaultSubfilterLabel();
    $this->assertEquals($expectedLabel, $label);
  }

  /**
   * @covers ::getSubfilterId
   */
  public function testGetFilterId() {
    // Filter id has the original value.
    $profile = new LingotekProfile(['id' => 'profile1', 'project' => 'my_test_project', 'vault' => 'my_test_vault', 'filter' => 'my_filter'], 'lingotek_profile');
    $filter = $this->filterManager->getFilterId($profile);
    $this->assertEquals('my_filter', $filter);

    // Filter is replaced with project default.
    $profile = new LingotekProfile(['id' => 'profile1', 'project' => 'my_test_project', 'vault' => 'my_test_vault', 'filter' => 'project_default'], 'lingotek_profile');
    $filter = $this->filterManager->getFilterId($profile);
    $this->assertEquals(NULL, $filter);

    // Filter is replaced with drupal default.
    $profile = new LingotekProfile(['id' => 'profile1', 'project' => 'my_test_project', 'vault' => 'my_test_vault', 'filter' => 'drupal_default'], 'lingotek_profile');
    $filter = $this->filterManager->getFilterId($profile);
    $this->assertEquals('4f91482b-5aa1-4a4a-a43f-712af7b39625', $filter);

    // Filter is replaced with the default.
    $this->config->expects($this->at(0))
      ->method('get')
      ->with('default.filter')
      ->will($this->returnValue('another_different_filter'));
    $this->config->expects($this->at(1))
      ->method('get')
      ->with('account.resources.filter')
      ->will($this->returnValue(['another_different_filter' => 'Another different filter']));

    $profile = new LingotekProfile(['id' => 'profile1', 'project' => 'my_test_project', 'vault' => 'my_test_vault', 'filter' => 'default'], 'lingotek_profile');
    $filter = $this->filterManager->getFilterId($profile);
    $this->assertEquals('another_different_filter', $filter);
  }

  /**
   * @covers ::getSubfilterId
   */
  public function testGetSubfilterId() {
    // Filter id has the original value.
    $profile = new LingotekProfile(['id' => 'profile1', 'project' => 'my_test_project', 'vault' => 'my_test_vault', 'subfilter' => 'my_filter'], 'lingotek_profile');
    $filter = $this->filterManager->getSubfilterId($profile);
    $this->assertEquals('my_filter', $filter);

    // Filter is replaced with project default.
    $profile = new LingotekProfile(['id' => 'profile1', 'project' => 'my_test_project', 'vault' => 'my_test_vault', 'subfilter' => 'project_default'], 'lingotek_profile');
    $filter = $this->filterManager->getSubfilterId($profile);
    $this->assertEquals(NULL, $filter);

    // Filter is replaced with drupal default.
    $profile = new LingotekProfile(['id' => 'profile1', 'project' => 'my_test_project', 'vault' => 'my_test_vault', 'subfilter' => 'drupal_default'], 'lingotek_profile');
    $filter = $this->filterManager->getSubfilterId($profile);
    $this->assertEquals('0e79f34d-f27b-4a0c-880e-cd9181a5d265', $filter);

    // Filter is replaced with the default.
    $this->config->expects($this->at(0))
      ->method('get')
      ->with('default.subfilter')
      ->will($this->returnValue('another_different_filter'));
    $this->config->expects($this->at(1))
      ->method('get')
      ->with('account.resources.filter')
      ->will($this->returnValue(['another_different_filter' => 'Another different filter']));

    $profile = new LingotekProfile(['id' => 'profile1', 'project' => 'my_test_project', 'vault' => 'my_test_vault', 'subfilter' => 'default'], 'lingotek_profile');
    $filter = $this->filterManager->getSubfilterId($profile);
    $this->assertEquals('another_different_filter', $filter);
  }

}
