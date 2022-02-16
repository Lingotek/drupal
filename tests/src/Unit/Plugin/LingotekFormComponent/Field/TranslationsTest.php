<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFormComponent\Field;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\Plugin\LingotekFormComponent\Field\Translations;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for the translations field form component.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFormComponent\Field\Translations
 * @group lingotek
 * @preserve GlobalState disabled
 */
class TranslationsTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFormComponent\Field\Translations
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
    $this->field = new Translations([], 'translations', ['title' => new TranslatableMarkup('Translations')], $this->entityTypeManager, $this->languageManager, $this->lingotekConfiguration, $this->lingotekContentTranslation, $this->entityTypeBundleInfo);

    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $translation = $this->getStringTranslationStub();
    $this->field->setStringTranslation($translation);

    $container = new ContainerBuilder();
    $container->set('language_manager', $this->languageManager);
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::getHeader
   */
  public function testGetHeader() {
    $header = $this->field->getHeader();
    $this->assertSame('Translations', $header->getUntranslatedString());
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
  public function testGetData($translationStatuses) {
    $es = $this->createMock(LanguageInterface::class);
    $es->expects($this->once())
      ->method('getId')
      ->willReturn('es');
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('language')
      ->willReturn($es);

    $this->lingotekContentTranslation->expects($this->once())
      ->method('getTargetStatuses')
      ->with($entity)
      ->willReturn($translationStatuses);

    $this->lingotekConfiguration->expects($this->once())
      ->method('getEnabledLanguages')
      ->willReturn(array_keys($translationStatuses));

    $build = $this->field->getData($entity);
    // $this->assertSame([], $build);
    $this->assertSame('lingotek_target_statuses', $build['data']['#type']);
    $this->assertSame($entity, $build['data']['#entity']);
    $this->assertSame('es', $build['data']['#source_langcode']);
    $this->assertSame($translationStatuses, $build['data']['#statuses']);
  }

  public function dataProviderGetData() {
    $languages = [
      'es' => $this->createMock(LanguageInterface::class)->expects($this->once())->method('getId')->willReturn('es'),
      'it' => $this->createMock(LanguageInterface::class)->expects($this->once())->method('getId')->willReturn('it'),
      'de' => $this->createMock(LanguageInterface::class)->expects($this->once())->method('getId')->willReturn('de'),
    ];
    yield "first set of translations statuses" => [['es' => Lingotek::STATUS_CURRENT, 'de' => Lingotek::STATUS_PENDING, 'it' => Lingotek::STATUS_READY]];
    yield "second set of translations statuses" => [['es' => Lingotek::STATUS_PENDING, 'de' => Lingotek::STATUS_CURRENT, 'it' => Lingotek::STATUS_INTERMEDIATE]];
  }

}
