<?php

namespace Drupal\Tests\farm_water_level_thingspeak\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\farm_water_level_thingspeak\Service\ThingSpeakService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;

/**
 * Unit tests for the ThingSpeakService.
 *
 * @group farm_water_level_thingspeak
 * @coversDefaultClass \Drupal\farm_water_level_thingspeak\Service\ThingSpeakService
 */
class ThingSpeakServiceTest extends UnitTestCase {

  /**
   * The mocked HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $httpClient;

  /**
   * The mocked config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $configFactory;

  /**
   * The mocked logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $loggerFactory;

  /**
   * The mocked logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $logger;

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $entityTypeManager;

  /**
   * The mocked state service.
   *
   * @var \Drupal\Core\State\StateInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $state;

  /**
   * The service under test.
   *
   * @var \Drupal\farm_water_level_thingspeak\Service\ThingSpeakService
   */
  protected $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->httpClient = $this->prophesize(ClientInterface::class);
    $this->configFactory = $this->prophesize(ConfigFactoryInterface::class);
    $this->logger = $this->prophesize(LoggerChannelInterface::class);
    $this->loggerFactory = $this->prophesize(LoggerChannelFactoryInterface::class);
    $this->loggerFactory->get('farm_water_level_thingspeak')->willReturn($this->logger->reveal());
    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->state = $this->prophesize(StateInterface::class);

    $this->service = new ThingSpeakService(
      $this->httpClient->reveal(),
      $this->configFactory->reveal(),
      $this->loggerFactory->reveal(),
      $this->entityTypeManager->reveal(),
      $this->state->reveal(),
    );
  }

  /**
   * @covers ::fetchFeeds
   */
  public function testFetchFeedsReturnsEmptyArrayOnEmptyChannelId(): void {
    $this->logger->warning(Argument::containingString('channel ID'))->shouldBeCalled();
    $result = $this->service->fetchFeeds('', '');
    $this->assertSame([], $result);
  }

  /**
   * @covers ::fetchFeeds
   */
  public function testFetchFeedsReturnsFeeds(): void {
    $feeds = [
      ['entry_id' => 1, 'created_at' => '2024-01-01T12:00:00Z', 'field1' => '1.23'],
      ['entry_id' => 2, 'created_at' => '2024-01-01T12:05:00Z', 'field1' => '1.25'],
    ];
    $responseBody = json_encode(['channel' => ['id' => 123], 'feeds' => $feeds]);
    $response = new Response(200, [], $responseBody);

    $this->httpClient->request('GET', Argument::containingString('/channels/123/feeds.json'), Argument::type('array'))
      ->willReturn($response);

    $result = $this->service->fetchFeeds('123', 'test_api_key');
    $this->assertCount(2, $result);
    $this->assertSame('1.23', $result[0]['field1']);
  }

  /**
   * @covers ::fetchFeeds
   */
  public function testFetchFeedsHandlesRequestException(): void {
    $this->httpClient->request('GET', Argument::any(), Argument::any())
      ->willThrow(new RequestException('Connection error', new Request('GET', 'http://example.com')));

    $this->logger->error(Argument::containingString('Error fetching'), Argument::type('array'))->shouldBeCalled();

    $result = $this->service->fetchFeeds('123', 'key');
    $this->assertSame([], $result);
  }

  /**
   * @covers ::fetchFeeds
   */
  public function testFetchFeedsHandlesInvalidJson(): void {
    $response = new Response(200, [], 'not valid json{');

    $this->httpClient->request('GET', Argument::any(), Argument::any())
      ->willReturn($response);

    $this->logger->error(Argument::containingString('Invalid JSON'), Argument::type('array'))->shouldBeCalled();

    $result = $this->service->fetchFeeds('123', 'key');
    $this->assertSame([], $result);
  }

  /**
   * @covers ::fetchFeeds
   */
  public function testFetchFeedsHandlesMissingFeedsKey(): void {
    $response = new Response(200, [], json_encode(['channel' => []]));

    $this->httpClient->request('GET', Argument::any(), Argument::any())
      ->willReturn($response);

    $this->logger->warning(Argument::containingString('missing "feeds" key'))->shouldBeCalled();

    $result = $this->service->fetchFeeds('123', 'key');
    $this->assertSame([], $result);
  }

  /**
   * @covers ::syncData
   */
  public function testSyncDataSkipsWhenChannelIdNotConfigured(): void {
    $config = $this->prophesize(ImmutableConfig::class);
    $config->get('channel_id')->willReturn('');
    $config->get('api_key')->willReturn('');
    $config->get('water_level_field')->willReturn('field1');
    $config->get('units')->willReturn('m');
    $config->get('log_name_prefix')->willReturn('Water Level');
    $config->get('sync_interval')->willReturn(900);
    $this->configFactory->get('farm_water_level_thingspeak.settings')->willReturn($config->reveal());

    $this->logger->notice(Argument::containingString('channel ID not configured'))->shouldBeCalled();

    $result = $this->service->syncData();
    $this->assertSame(0, $result);
  }

  /**
   * @covers ::syncData
   */
  public function testSyncDataSkipsWhenSyncIntervalNotElapsed(): void {
    $config = $this->prophesize(ImmutableConfig::class);
    $config->get('channel_id')->willReturn('123');
    $config->get('api_key')->willReturn('');
    $config->get('water_level_field')->willReturn('field1');
    $config->get('units')->willReturn('m');
    $config->get('log_name_prefix')->willReturn('Water Level');
    $config->get('sync_interval')->willReturn(900);
    $this->configFactory->get('farm_water_level_thingspeak.settings')->willReturn($config->reveal());

    // Last sync was only 60 seconds ago.
    $this->state->get('farm_water_level_thingspeak.last_sync', 0)->willReturn(time() - 60);

    $result = $this->service->syncData();
    $this->assertSame(0, $result);
  }

  /**
   * @covers ::getLastSyncTime
   */
  public function testGetLastSyncTimeReturnsZeroByDefault(): void {
    $this->state->get('farm_water_level_thingspeak.last_sync', 0)->willReturn(0);
    $this->assertSame(0, $this->service->getLastSyncTime());
  }

  /**
   * @covers ::getLastEntryId
   */
  public function testGetLastEntryIdReturnsNullByDefault(): void {
    $this->state->get(ThingSpeakService::LAST_ENTRY_STATE_KEY, NULL)->willReturn(NULL);
    $this->assertNull($this->service->getLastEntryId());
  }

  /**
   * @covers ::clearSyncThrottle
   */
  public function testClearSyncThrottleOnlyDeletesLastSyncKey(): void {
    $this->state->delete('farm_water_level_thingspeak.last_sync')->shouldBeCalled();
    $this->state->delete(ThingSpeakService::LAST_ENTRY_STATE_KEY)->shouldNotBeCalled();
    $this->service->clearSyncThrottle();
  }

  /**
   * @covers ::resetSyncState
   */
  public function testResetSyncStateDeletesStateKeys(): void {
    $this->state->delete(ThingSpeakService::LAST_ENTRY_STATE_KEY)->shouldBeCalled();
    $this->state->delete('farm_water_level_thingspeak.last_sync')->shouldBeCalled();
    $this->service->resetSyncState();
  }

}
