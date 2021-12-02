<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFieldProcessor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekMetatagProcessor;
use Drupal\metatag\Plugin\DataType\Metatag;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for the metatags processor plugin.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekMetatagProcessor
 * @group lingotek
 * @preserve GlobalState disabled
 */
class LingotekMetatagProcessorTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekMetatagProcessor
   */
  protected $processor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->processor = new LingotekMetatagProcessor([], 'metatag', []);
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $processor = new LingotekMetatagProcessor([], 'metatag', []);
    $this->assertNotNull($processor);
  }

  /**
   * @covers ::extract
   */
  public function testExtractWithEmptyMetatag() {
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('get')
      ->with('field_metatag')
      ->willReturn([]);

    $data = [];
    $visited = [];
    $this->assertEmpty($data);
    $this->processor->extract($entity, 'field_metatag', $fieldDefinition, $data, $visited);

    // Nothing was added to data.
    $this->assertCount(0, $data);
    $this->assertArrayNotHasKey('field_metatag', $data);

    // Nothing was added to the visited value.
    $this->assertEquals([], $visited);
  }

  /**
   * @covers ::appliesToField
   * @dataProvider dataProviderAppliesToField
   */
  public function testAppliesToField($expected, $field_type) {
    $entity = $this->createMock(ContentEntityInterface::class);
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);
    $fieldDefinition->expects($this->once())
      ->method('getType')
      ->willReturn($field_type);
    $result = $this->processor->appliesToField($fieldDefinition, $entity);
    $this->assertSame($expected, $result);
  }

  public function dataProviderAppliesToField() {
    yield 'null field' => [FALSE, NULL];
    yield 'string_text field' => [FALSE, 'string_text'];
    yield 'metatag field' => [TRUE, 'metatag'];
  }

  /**
   * @covers ::extract
   */
  public function testExtract() {
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    $metatagsData = $this->createMock(Metatag::class);
    $metatagsData->expects($this->once())
      ->method('getValue')
      ->willReturn(serialize([
        'abstract' => 'This is my abstract.',
        'keywords' => 'This, are, my, keywords',
      ]));

    $fieldItem = $this->createMock(FieldItemInterface::class);
    $fieldItem->expects($this->once())
      ->method('get')
      ->with('value')
      ->willReturn($metatagsData);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('get')
      ->with('field_metatag')
      ->willReturn([$fieldItem]);

    $data = [];
    $visited = [];
    $this->assertEmpty($data);
    $this->processor->extract($entity, 'field_metatag', $fieldDefinition, $data, $visited);
    $this->assertCount(1, $data);
    $this->assertCount(1, $data['field_metatag']);

    $this->assertEquals([
      'abstract' => 'This is my abstract.',
      'keywords' => 'This, are, my, keywords',
    ], $data['field_metatag'][0]);
    // Nothing was added to the visited value.
    $this->assertEquals([], $visited);
  }

  /**
   * @covers ::store
   */
  public function testStore() {
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    $fieldItemList = $this->createMock(FieldItemListInterface::class);
    $fieldItemList->expects($this->once())
      ->method('set')
      ->with(0, serialize([
        'abstract' => 'This is my abstract in ES.',
        'keywords' => 'This ES, are ES, my ES, keywords ES',
      ]));

    $entity = $this->createMock(ContentEntityInterface::class);
    $translation = $this->createMock(ContentEntityInterface::class);
    $translation->expects($this->once())
      ->method('get')
      ->with('field_metatag')
      ->willReturn($fieldItemList);

    $data = [
      [
        'abstract' => 'This is my abstract in ES.',
        'keywords' => 'This ES, are ES, my ES, keywords ES',
      ],
    ];
    $this->processor->store($translation, 'es', $entity, 'field_metatag', $fieldDefinition, $data);

    // No asserts needed if there's no error.
    $this->assertTrue(TRUE);
  }

}
