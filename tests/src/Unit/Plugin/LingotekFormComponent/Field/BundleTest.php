<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFormComponent\Field;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\Plugin\LingotekFormComponent\Field\Bundle;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for the bundle field form component.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFormComponent\Field\Bundle
 * @group lingotek
 * @preserve GlobalState disabled
 */
class BundleTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFormComponent\Field\Bundle
   */
  protected $field;

  /**
   * The mocked language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * The mocked entity type.
   *
   * @var \Drupal\Core\Entity\ContentEntityTypeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityType;

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The entity_type.bundle.info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeBundleInfo;

  /**
   * The mocked Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $lingotekConfiguration;

  /**
   * The mocked Lingotek content translation service.
   *
   * @var \Drupal\lingotek\LingotekContentTranslationServiceInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $lingotekContentTranslation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityType = $this->createMock(ContentEntityTypeInterface::class);
    $this->entityType->expects($this->any())
      ->method('getBundleLabel')
      ->willReturn(new TranslatableMarkup("My Bundle Label"));
    $this->entityType->expects($this->any())
      ->method('getKey')
      ->with('bundle')
      ->willReturn('bundle_key');

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->with('my_entity_type_id')
      ->willReturn($this->entityType);
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->lingotekConfiguration = $this->createMock(LingotekConfigurationServiceInterface::class);
    $this->lingotekContentTranslation = $this->createMock(LingotekContentTranslationServiceInterface::class);
    $this->entityTypeBundleInfo = $this->createMock(EntityTypeBundleInfoInterface::class);
    $this->field = new Bundle([], 'bundle', [], $this->entityTypeManager, $this->languageManager, $this->lingotekConfiguration, $this->lingotekContentTranslation, $this->entityTypeBundleInfo);

    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $translation = $this->getStringTranslationStub();
    $this->field->setStringTranslation($translation);
  }

  /**
   * @covers ::getHeader
   */
  public function testGetHeaderWithNoEntityType() {
    $header = $this->field->getHeader();
    $this->assertSame('Bundle', $header->getUntranslatedString());
  }

  /**
   * @covers ::getHeader
   */
  public function testGetHeader() {
    $header = $this->field->getHeader('my_entity_type_id');
    $this->assertIsArray($header);
    $this->assertSame('My Bundle Label', $header['data']->getUntranslatedString());
    $this->assertSame('entity_table.bundle_key', $header['field']);
  }

  /**
   * @covers ::isApplicable
   */
  public function testIsApplicableWithNoEntityType() {
    $this->assertTrue($this->field->isApplicable());
  }

  /**
   * @covers ::isApplicable
   * @dataProvider dataProviderIsApplicable
   */
  public function testIsApplicable($bundle, $expected) {
    $arguments = ['entity_type_id' => 'my_entity_type_id'];
    $this->entityType->expects($this->once())
      ->method('get')
      ->with('bundle_entity_type')
      ->willReturn($bundle);
    $this->assertSame($expected, $this->field->isApplicable($arguments));
  }

  /**
   * Data provider for testIsApplicable.
   *
   * @return array
   *   [bundle_entity_type, expected]
   */
  public function dataProviderIsApplicable() {
    yield "has bundle" => ['my_bundle_id', TRUE];
    yield "no bundle" => [NULL, FALSE];
    yield "default bundle" => ['bundle', FALSE];
  }

  /**
   * @covers ::getData
   */
  public function testGetData() {
    $bundleDefinitions = [
      'bundle_entity_type' => [
        'label' => 'My Entity Type ID',
        'translatable' => TRUE,
      ],
    ];

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('my_entity_type_id');
    $entity->expects($this->once())
      ->method('bundle')
      ->willReturn('bundle_entity_type');
    $this->entityTypeBundleInfo->expects($this->once())
      ->method('getBundleInfo')
      ->with('my_entity_type_id')
      ->willReturn($bundleDefinitions);

    $this->assertSame('My Entity Type ID', $this->field->getData($entity));
  }

}
