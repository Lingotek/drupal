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
use Drupal\lingotek\Plugin\LingotekFormComponent\Field\Source;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for the source field form component.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFormComponent\Field\Source
 * @group lingotek
 * @preserve GlobalState disabled
 */
class SourceTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFormComponent\Field\Source
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
    $this->field = new Source([], 'source', ['title' => new TranslatableMarkup('Source')], $this->entityTypeManager, $this->languageManager, $this->lingotekConfiguration, $this->lingotekContentTranslation, $this->entityTypeBundleInfo);

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
    $this->assertSame('Source', $header->getUntranslatedString());
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
  public function testGetData($expectedStatus) {
    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->once())
      ->method('getId')
      ->willReturn('es');

    $this->languageManager->expects($this->once())
      ->method('getLanguages')
      ->willReturn(['es' => $language]);

    $this->languageManager->expects($this->once())
      ->method('getLanguage')
      ->with('es')
      ->willReturn($language);

    $entity = $this->createMock(ContentEntityInterface::class);

    $this->lingotekContentTranslation->expects($this->once())
      ->method('getSourceLocale')
      ->with($entity)
      ->willReturn('es');
    $this->lingotekContentTranslation->expects($this->once())
      ->method('getSourceStatus')
      ->with($entity)
      ->willReturn($expectedStatus);

    $build = $this->field->getData($entity);
    // $this->assertSame([], $build);
    $this->assertSame('lingotek_source_status', $build['data']['#type']);
    $this->assertSame($entity, $build['data']['#entity']);
    $this->assertSame($language, $build['data']['#language']);
    $this->assertSame($expectedStatus, $build['data']['#status']);
  }

  public function dataProviderGetData() {
    yield "current source status" => [Lingotek::STATUS_CURRENT];
    yield "pending source status" => [Lingotek::STATUS_PENDING];
  }

}
