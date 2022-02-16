<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFormComponent\Field;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\Plugin\LingotekFormComponent\Field\Title;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for the title field form component.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFormComponent\Field\Title
 * @group lingotek
 * @preserve GlobalState disabled
 */
class TitleTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFormComponent\Field\Title
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
      ->method('hasKey')
      ->willReturn(TRUE);
    $this->entityType->expects($this->any())
      ->method('getKey')
      ->with('label')
      ->willReturn('label_key');
    $this->entityType->expects($this->any())
      ->method('get')
      ->with('bundle_entity_type')
      ->willReturn('my_bundle');

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->with('my_entity_type_id')
      ->willReturn($this->entityType);
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->lingotekConfiguration = $this->createMock(LingotekConfigurationServiceInterface::class);
    $this->lingotekContentTranslation = $this->createMock(LingotekContentTranslationServiceInterface::class);
    $this->entityTypeBundleInfo = $this->createMock(EntityTypeBundleInfoInterface::class);
    $this->field = new Title([], 'title', ['title' => new TranslatableMarkup('Label')], $this->entityTypeManager, $this->languageManager, $this->lingotekConfiguration, $this->lingotekContentTranslation, $this->entityTypeBundleInfo);

    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $translation = $this->getStringTranslationStub();
    $this->field->setStringTranslation($translation);

    $titleField = $this->createMock(BaseFieldDefinition::class);
    $titleField->expects($this->any())
      ->method('getLabel')
      ->willReturn(new TranslatableMarkup('My Bundle Label'));
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->entityFieldManager->expects($this->any())
      ->method('getBaseFieldDefinitions')
      ->with('my_entity_type_id')
      ->willReturn(['label_key' => $titleField]);
    $linkGenerator = $this->createMock(LinkGeneratorInterface::class);
    $linkGenerator->expects($this->any())
      ->method('generateFromLink')
      ->willReturn(new Link('This is the content label', $this->getUrl()));
    $container = new ContainerBuilder();
    $container->set('entity_field.manager', $this->entityFieldManager);
    $container->set('language_manager', $this->languageManager);
    $container->set('link_generator', $linkGenerator);
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::getHeader
   */
  public function testGetHeaderWithoutEntityId() {
    $header = $this->field->getHeader();
    $this->assertSame('Label', $header->getUntranslatedString());
  }

  /**
   * @covers ::getHeader
   */
  public function testGetHeader() {
    $header = $this->field->getHeader('my_entity_type_id');
    $this->assertIsArray($header);
    $this->assertSame('My Bundle Label', $header['data']->getUntranslatedString());
    $this->assertSame('entity_table.label_key', $header['field']);
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
   */
  public function testGetDataWithNoCanonicalUrl() {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('hasLinkTemplate')
      ->with('canonical')
      ->willReturn(FALSE);
    $entity->expects($this->once())
      ->method('label')
      ->willReturn('This is the content label');
    $entity->expects($this->any())
      ->method('toUrl')
      ->willReturn($this->getUrl());

    $title = $this->field->getData($entity);
    $this->assertSame('This is the content label', $title);
  }

  /**
   * @covers ::getData
   */
  public function testGetDataWithCanonicalUrl() {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('hasLinkTemplate')
      ->with('canonical')
      ->willReturn(TRUE);
    $entity->expects($this->once())
      ->method('label')
      ->willReturn('This is the content label');
    $entity->expects($this->any())
      ->method('toUrl')
      ->willReturn($this->getUrl());

    $title = $this->field->getData($entity);
    $this->assertSame('This is the content label', $title->getText());
    $this->assertSame($this->getUrl()->toString(), $title->getUrl()->toString());
  }

  protected function getUrl() {
    $url = $this->createMock(Url::class);
    $url->expects($this->any())
      ->method('toString')
      ->willReturn('https://example.com/this-is-the-url');
    return $url;
  }

}
