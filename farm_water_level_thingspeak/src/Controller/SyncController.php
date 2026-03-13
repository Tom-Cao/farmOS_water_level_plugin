<?php

namespace Drupal\farm_water_level_thingspeak\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\farm_water_level_thingspeak\Service\ThingSpeakService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for manually triggering a ThingSpeak data sync.
 */
class SyncController extends ControllerBase {

  /**
   * The ThingSpeak service.
   *
   * @var \Drupal\farm_water_level_thingspeak\Service\ThingSpeakService
   */
  protected ThingSpeakService $thingSpeakService;

  /**
   * Constructs a new SyncController.
   *
   * @param \Drupal\farm_water_level_thingspeak\Service\ThingSpeakService $thingspeak_service
   *   The ThingSpeak service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(ThingSpeakService $thingspeak_service, MessengerInterface $messenger) {
    $this->thingSpeakService = $thingspeak_service;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('farm_water_level_thingspeak.thingspeak_service'),
      $container->get('messenger'),
    );
  }

  /**
   * Triggers a manual sync and redirects back to the settings page.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the settings page.
   */
  public function sync(): RedirectResponse {
    // Bypass the sync throttle so new data is fetched immediately,
    // but keep the last entry ID to avoid importing duplicates.
    $this->thingSpeakService->clearSyncThrottle();

    $count = $this->thingSpeakService->syncData();

    if ($count > 0) {
      $this->messenger()->addStatus($this->t('Sync complete: @count new water level reading(s) imported.', [
        '@count' => $count,
      ]));
    }
    else {
      $this->messenger()->addWarning($this->t('Sync complete: no new water level readings were found.'));
    }

    return new RedirectResponse(Url::fromRoute('farm_water_level_thingspeak.settings')->toString());
  }

}
