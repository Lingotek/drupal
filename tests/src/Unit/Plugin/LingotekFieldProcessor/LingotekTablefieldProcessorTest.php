<?php

namespace Drupal\Tests\lingotek\Unit\Plugin\LingotekFieldProcessor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekTablefieldProcessor;
use Drupal\Tests\UnitTestCase;

/**
 * Unit test for the tablefield processor plugin.
 *
 * @coversDefaultClass \Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekTablefieldProcessor
 * @group lingotek
 * @preserve GlobalState disabled
 */
class LingotekTablefieldProcessorTest extends UnitTestCase {

  /**
   * The class instance under test.
   *
   * @var \Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekTablefieldProcessor
   */
  protected $processor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->processor = new LingotekTablefieldProcessor([], 'tablefield', []);
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $processor = new LingotekTablefieldProcessor([], 'tablefield', []);
    $this->assertNotNull($processor);
  }

  /**
   * @covers ::extract
   */
  public function testExtractWithEmptyTablefield() {
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('get')
      ->with('field_tablefield')
      ->willReturn([]);

    $data = [];
    $visited = [];
    $this->assertEmpty($data);
    $this->processor->extract($entity, 'field_tablefield', $fieldDefinition, $data, $visited);

    // Nothing was added to data.
    $this->assertCount(0, $data);
    $this->assertArrayNotHasKey('field_tablefield', $data);

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
    yield 'tablefield field' => [TRUE, 'tablefield'];
  }

  /**
   * @covers ::extract
   */
  public function testExtract() {
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    $tableFieldItem = new \stdClass();
    $tableFieldItem->value = [
      'caption' => 'test caption',
      0 => [
        'cell 1,1',
        'cell 1,2',
      ],
      1 => [
        'cell 2,1',
        'cell 2,2',
      ],
    ];
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('get')
      ->with('field_tablefield')
      ->willReturn([$tableFieldItem]);

    $data = [];
    $visited = [];
    $this->assertEmpty($data);
    $this->processor->extract($entity, 'field_tablefield', $fieldDefinition, $data, $visited);
    $this->assertCount(1, $data);
    $this->assertCount(1, $data['field_tablefield']);

    $this->assertEquals([
      'caption' => 'test caption',
      'row:0' => [
        'col:0' => 'cell 1,1',
        'col:1' => 'cell 1,2',
      ],
      'row:1' => [
        'col:0' => 'cell 2,1',
        'col:1' => 'cell 2,2',
      ],
    ], $data['field_tablefield'][0]);
    // Nothing was added to the visited value.
    $this->assertEquals([], $visited);
  }

  /**
   * @covers ::store
   */
  public function testStore() {
    $fieldDefinition = $this->createMock(BaseFieldDefinition::class);

    $tableFieldItemList = $this->createMock(FieldItemListInterface::class);
    $tableFieldItemList->expects($this->once())
      ->method('set')
      ->with(0, [
        'caption' => 'test caption ES',
        'value' => [
          0 => [
            'cell 1,1 ES',
            'cell 1,2 ES',
          ],
          1 => [
            'cell 2,1 ES',
            'cell 2,2 ES',
          ],
        ],
      ]);

    $entity = $this->createMock(ContentEntityInterface::class);
    $translation = $this->createMock(ContentEntityInterface::class);
    $translation->expects($this->once())
      ->method('get')
      ->with('field_tablefield')
      ->willReturn($tableFieldItemList);

    $data = [
      [
        'caption' => 'test caption ES',
        'row:0' => [
          'col:0' => 'cell 1,1 ES',
          'col:1' => 'cell 1,2 ES',
        ],
        'row:1' => [
          'col:0' => 'cell 2,1 ES',
          'col:1' => 'cell 2,2 ES',
        ],
      ],
    ];
    $this->processor->store($translation, 'es', $entity, 'field_tablefield', $fieldDefinition, $data);

    // No asserts needed if there's no error.
    $this->assertTrue(TRUE);
  }

}
