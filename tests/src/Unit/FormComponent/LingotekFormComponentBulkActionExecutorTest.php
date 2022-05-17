<?php

namespace Drupal\Tests\lingotek\Unit\FormComponent;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\lingotek\Exception\LingotekDocumentArchivedException;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionExecutor;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionInterface;
use Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\DownloadTranslations;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionExecutor
 * @group lingotek
 * @preserveGlobalState disabled
 */
class LingotekFormComponentBulkActionExecutorTest extends UnitTestCase {

  /**
   * The bulk action executor under test.
   *
   * @var \Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionExecutor
   */
  protected $executor;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->executor = new LingotekFormComponentBulkActionExecutor();
  }

  /**
   * @covers ::execute
   * @dataProvider dataProviderExecute
   */
  public function testExecute($batched, $batchBuilder, $expected) {
    $entities = [];
    $options = [];
    $action = $this->createMock(DownloadTranslations::class);
    $fallbackAction = $this->createMock(LingotekFormComponentBulkActionInterface::class);
    $action->expects($this->any())
      ->method('isBatched')
      ->willReturn($batched);
    $action->expects($batched ? $this->once() : $this->never())
      ->method('hasBatchBuilder')
      ->willReturn($batchBuilder);
    if ($batchBuilder) {
      $action->expects($this->once())
        ->method('createBatch')
        ->with($this->executor, $entities, [], $fallbackAction)
        ->willReturn($expected);
    }
    $action->expects(!$batched ? $this->once() : $this->never())
      ->method('execute')
      ->with($entities, [])
      ->willReturn($expected);
    try {
      $result = $this->executor->execute($action, $entities, $options, $fallbackAction);
    }
    catch (\Error $e) {
      // As there is no batch_set available, this will fail when batched with no function.
      $result = $expected;
    }

    $this->assertSame($expected, $result);
  }

  public function dataProviderExecute() {
    yield 'no batch' => [FALSE, FALSE, ['executed']];
    yield 'batch, no function' => [TRUE, FALSE, ['batch set']];
    yield 'batch, function' => [TRUE, TRUE, ['actionBatch']];
  }

  /**
   * @covers ::doExecuteSingle
   */
  public function testDoExecuteSingle() {
    $entity = $this->createMock(ContentEntityInterface::class);
    $options = [];
    $context = [];
    $expected = ['expected result'];
    $action = $this->createMock(LingotekFormComponentBulkActionInterface::class);
    $action->expects($this->once())
      ->method('executeSingle')
      ->willReturn($expected);
    $fallbackAction = $this->createMock(LingotekFormComponentBulkActionInterface::class);
    $result = $this->executor->doExecuteSingle($action, $entity, $options, $fallbackAction, $context);
    $this->assertSame($expected, $result);
  }

  /**
   * @covers ::doExecuteSingle
   */
  public function testDoExecuteSingleWithExceptionAndFallback() {
    $entity = $this->createMock(ContentEntityInterface::class);
    $options = [];
    $context = [];
    $expected = ['expected result'];
    $action = $this->createMock(LingotekFormComponentBulkActionInterface::class);
    $action->expects($this->once())
      ->method('executeSingle')
      ->willThrowException(new LingotekDocumentArchivedException('error'));
    $fallbackAction = $this->createMock(LingotekFormComponentBulkActionInterface::class);
    $fallbackAction->expects($this->once())
      ->method('executeSingle')
      ->willReturn($expected);
    $result = $this->executor->doExecuteSingle($action, $entity, $options, $fallbackAction, $context);
    $this->assertSame($expected, $result);
  }

  /**
   * @covers ::doExecuteSingle
   */
  public function testDoExecuteSingleWithExceptionAndNoFallback() {
    $entity = $this->createMock(ContentEntityInterface::class);
    $options = [];
    $context = [];
    $action = $this->createMock(LingotekFormComponentBulkActionInterface::class);
    $action->expects($this->once())
      ->method('executeSingle')
      ->willThrowException(new LingotekDocumentArchivedException('error'));

    $this->expectException(LingotekDocumentArchivedException::class);

    $this->executor->doExecuteSingle($action, $entity, $options, NULL, $context);
  }

  /**
   * @covers ::doExecuteSingle
   */
  public function testDoExecute() {
    $entities = [];
    $options = [];
    $context = [];
    $expected = ['expected result'];
    $action = $this->createMock(LingotekFormComponentBulkActionInterface::class);
    $action->expects($this->once())
      ->method('execute')
      ->willReturn($expected);
    $fallbackAction = $this->createMock(LingotekFormComponentBulkActionInterface::class);
    $result = $this->executor->doExecute($action, $entities, $options, $fallbackAction, $context);
    $this->assertSame($expected, $result);
  }

  /**
   * @covers ::doExecuteSingle
   */
  public function testDoExecuteWithExceptionAndFallback() {
    $entities = [];
    $options = [];
    $context = [];
    $expected = ['expected result'];
    $action = $this->createMock(LingotekFormComponentBulkActionInterface::class);
    $action->expects($this->once())
      ->method('execute')
      ->willThrowException(new LingotekDocumentArchivedException('error'));
    $fallbackAction = $this->createMock(LingotekFormComponentBulkActionInterface::class);
    $fallbackAction->expects($this->once())
      ->method('execute')
      ->willReturn($expected);
    $result = $this->executor->doExecute($action, $entities, $options, $fallbackAction, $context);
    $this->assertSame($expected, $result);
  }

  /**
   * @covers ::doExecuteSingle
   */
  public function testDoExecuteWithExceptionAndNoFallback() {
    $entities = [];
    $options = [];
    $context = [];
    $action = $this->createMock(LingotekFormComponentBulkActionInterface::class);
    $action->expects($this->once())
      ->method('execute')
      ->willThrowException(new LingotekDocumentArchivedException('error'));

    $this->expectException(LingotekDocumentArchivedException::class);

    $this->executor->doExecute($action, $entities, $options, NULL, $context);
  }

}
