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
use Drupal\lingotek\LingotekProfileInterface;
use Drupal\lingotek\Plugin\LingotekFormComponent\Field\Profile;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for the profile field form component.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFormComponent\Field\Profile
 * @group lingotek
 * @preserve GlobalState disabled
 */
class ProfileTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFormComponent\Field\Profile
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
    $this->field = new Profile([], 'profile', ['title' => new TranslatableMarkup('Profile')], $this->entityTypeManager, $this->languageManager, $this->lingotekConfiguration, $this->lingotekContentTranslation, $this->entityTypeBundleInfo);

    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $translation = $this->getStringTranslationStub();
    $this->field->setStringTranslation($translation);

  }

  /**
   * @covers ::getHeader
   */
  public function testGetHeader() {
    $header = $this->field->getHeader();
    $this->assertSame('Profile', $header->getUntranslatedString());
  }

  /**
   * @covers ::isApplicable
   */
  public function testIsApplicableWithNoEntityType() {
    $this->assertTrue($this->field->isApplicable());
  }

  /**
   * @covers ::isApplicable
   */
  public function testIsApplicable() {
    $arguments = ['entity_type_id' => 'my_entity_type_id'];
    $this->assertTrue($this->field->isApplicable($arguments));
  }

  /**
   * @covers ::getData
   * @dataProvider dataProviderGetData
   */
  public function testGetData($expected, $enabled, $profile) {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('my_entity_type_id');
    $entity->expects($this->once())
      ->method('bundle')
      ->willReturn('my_bundle');

    $this->lingotekConfiguration->expects($this->once())
      ->method('isEnabled')
      ->with('my_entity_type_id', 'my_bundle')
      ->willReturn($enabled);
    $this->lingotekConfiguration->expects($enabled ? $this->once() : $this->never())
      ->method('getEntityProfile')
      ->with($entity)
      ->willReturn($profile);

    $this->assertSame($expected, (string) $this->field->getData($entity));
  }

  public function dataProviderGetData() {
    $profile = $this->createMock(LingotekProfileInterface::class);
    $profile->expects($this->any())
      ->method('label')
      ->willReturn('My profile');
    yield "disabled for translation" => ['Not enabled', FALSE, NULL];
    yield "with no profile" => ['', TRUE, NULL];
    yield "with profile" => ['My profile', TRUE, $profile];
  }

}
