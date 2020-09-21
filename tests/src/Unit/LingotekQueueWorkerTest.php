<?php

namespace Drupal\Tests\lingotek\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\lingotek\Plugin\QueueWorker\LingotekDownloaderQueueWorker;
use Drupal\node\NodeStorage;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass Drupal\lingotek\Plugin\QueueWorker\LingotekDownloaderQueueWorker
 * @group lingotek
 * @preserveGlobalState disabled
 */
class LingotekQueueWorkerTest extends UnitTestCase {

  /**
   * LingotekDownloaderQueueWorker.
   *
   * @var Drupal\lingotek\Plugin\QueueWorker\LingotekDownloaderQueueWorker
   */
  private $lingotekDownloaderQueueWorker;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->lingotekDownloaderQueueWorker = new LingotekDownloaderQueueWorker([], 'lingotek_downloader_queue_worker', []);
    $entity_type_manager = $this->getMockBuilder(EntityTypeManager::class)->disableOriginalConstructor()
      ->getMock();
    $node_storage = $this->getMockBuilder(NodeStorage::class)->disableOriginalConstructor()
      ->getMock();
    $node_storage->expects($this->any())
      ->method('load')
      ->with('random')
      ->willReturn(new \stdClass());
    $entity_type_manager->expects($this->any())
      ->method('getStorage')
      ->with('random')
      ->willReturn($node_storage);
    $logger_factory = $this->getMockBuilder(LoggerChannelFactory::class)->disableOriginalConstructor()
      ->getMock();
    $logger_factory->expects($this->any())
      ->method('get')
      ->willReturn($this->getMockBuilder(LoggerInterface::class)->disableOriginalConstructor()->getMock());
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    $container->set('logger.factory', $logger_factory);
    \Drupal::setContainer($container);
  }

  /**
   * Test if exception when unsupported Entity Type.
   */
  public function testDownload() {
    $data = [
      'locale' => 'random',
      'entity_type_id' => 'random',
      'entity_id' => 'random',
      'document_id' => 'random',
    ];
    $this->expectExceptionMessage('Can not download - entity (object) is not supported instance of a class');
    $this->lingotekDownloaderQueueWorker->processItem($data);
  }

}
