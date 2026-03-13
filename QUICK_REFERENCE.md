# farmOS Water Level Plugin - Quick Reference

## File Checklist

Must-have files for your module:
- [ ] `farm_water_level_thingspeak.info.yml` - Module metadata (REQUIRED)
- [ ] `farm_water_level_thingspeak.services.yml` - Service definitions
- [ ] `farm_water_level_thingspeak.module` - Optional: Hook implementations
- [ ] `config/install/data_stream.type.thingspeak.yml` - Data stream config
- [ ] `src/Service/ThingSpeakService.php` - API integration
- [ ] `src/Hook/CronHooks.php` - Periodic sync task
- [ ] `README.md` - Documentation

## .info.yml Template

```yaml
name: farmOS Water Level ThingSpeak
description: Fetches water level data from ThingSpeak API and stores in farmOS data streams
type: module
package: farmOS Contrib
core_version_requirement: ^11
dependencies:
  - entity:entity
  - farm:farm_entity
  - data_stream:data_stream
```

## services.yml Template

```yaml
services:
  thingspeak.service:
    class: Drupal\farm_water_level_thingspeak\Service\ThingSpeakService
    arguments:
      - '@http_client'
      - '@logger.factory'
      - '@entity_type.manager'
```

## Cron Hook Template (Drupal 11)

```php
<?php
namespace Drupal\farm_water_level_thingspeak\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\farm_water_level_thingspeak\Service\ThingSpeakService;

class CronHooks {
  
  public function __construct(protected ThingSpeakService $thingSpeakService) {}
  
  #[Hook('cron')]
  public function cron() {
    // Fetch and sync data from all configured ThingSpeak channels
    $this->thingSpeakService->syncAllChannels();
  }
}
```

## ThingSpeak API Call Template

```php
$response = $this->httpClient->request('GET', 
  "https://api.thingspeak.com/channels/{$channelId}/feeds.json",
  [
    'query' => [
      'api_key' => $apiKey,
      'results' => 100,
      'timezone' => 'UTC',
    ],
    'timeout' => 10,
  ]
);

$data = json_decode($response->getBody()->getContents(), TRUE);
```

## Data Stream Entity Example

Load and update a data stream:
```php
$data_stream = $this->entityTypeManager
  ->getStorage('data_stream')
  ->load($uuid);

$plugin = $data_stream->getPlugin();
$plugin->storageSet(245.5, time());  // value, timestamp
```

## Config Entity Template (data_stream.type.thingspeak.yml)

```yaml
langcode: en
status: true
dependencies:
  enforced:
    module:
      - farm_water_level_thingspeak
id: thingspeak
label: ThingSpeak
description: Data stream type for ThingSpeak API integration
```

## Logging Examples

```php
// Success log
\Drupal::logger('farm_water_level_thingspeak')
  ->info('Synced @count readings from channel @id', 
    ['@count' => 100, '@id' => '123456']
  );

// Error log
\Drupal::logger('farm_water_level_thingspeak')
  ->error('API error: @message', 
    ['@message' => $e->getMessage()]
  );
```

## State/Config Storage

```php
// Runtime state (not persistent across requests)
\Drupal::state()->set('farm_water_level_thingspeak.last_sync', time());
$time = \Drupal::state()->get('farm_water_level_thingspeak.last_sync');

// Persistent configuration
$config = \Drupal::config('farm_water_level_thingspeak.settings');
$api_key = $config->get('api_key');
```

## ThingSpeak API Endpoints (Water Level Use Cases)

| Endpoint | Purpose |
|----------|---------|
| `/channels/{id}/feeds.json` | Get historical data with pagination |
| `/channels/{id}/feeds/last.json` | Get most recent reading |
| `/channels/{id}/fields/{field_num}.json` | Get specific field data |
| `?api_key={key}&results=100` | Common query parameters |
| `&start=2024-12-31T00:00:00Z` | ISO 8601 start timestamp |
| `&days=7` | Get last 7 days of data |

## Drupal/farmOS Versions Required

| Component | Version |
|-----------|---------|
| farmOS | 4.x (latest 4.0.0-beta4) |
| Drupal | 11.3.5+ |
| PHP | 8.4+ |
| Database | PostgreSQL 16+, MySQL 8.0+, MariaDB 10.6+ |

## Key Classes to Extend/Implement

- `DataStreamTypeBase` - Base class for custom data stream types
- `DataStreamStorageInterface` - Interface for data storage operations
- `DataStreamApiInterface` - Interface for API operations
- `FormBase` - For custom configuration forms
- `ClientInterface` - Injected HTTP client (GuzzleHttp)

## Drupal 11 Hooks Used (with Attributes)

```php
#[Hook('cron')]                          // Periodic tasks
#[Hook('entity_base_field_info')]        // Add base fields
#[Hook('farm_entity_bundle_field_info')] // Add bundle fields
#[Hook('form_alter')]                    // Modify forms
```

## Testing Checklist

- [ ] Module installs without PHP errors
- [ ] Services are registered and autowired correctly
- [ ] ThingSpeak API connection works with test credentials
- [ ] Cron task executes and logs properly
- [ ] Data stream entries are created/updated
- [ ] Timestamps are in correct timezone
- [ ] Admin configuration form allows channel setup
- [ ] Uninstall cleans up all data
- [ ] Code passes PHPStan analysis

## Common Issues & Solutions

**Issue: "HTTP Client not found"**
- Solution: Make sure `@http_client` is injected in constructor, not accessed via `\Drupal` static methods

**Issue: "Data Stream type not recognized"**
- Solution: Verify plugin class is in `src/Plugin/DataStream/DataStreamType/` and has correct namespace

**Issue: "Cron not executing"**
- Solution: Run `drush cron` manually or check cron logging; may need to enable cron in Drupal settings

**Issue: "ThingSpeak API 403 Forbidden"**
- Solution: Verify API key is correct, channel ID matches, key has read permissions

## Resources

- farmOS Documentation: https://farmos.org/guide/
- farmOS Module Development: https://farmos.org/development/module/
- Drupal 11 Documentation: https://www.drupal.org/docs/drupal-apis
- ThingSpeak API Docs: https://www.thingspeak.com/docs/

---

**Module Name Convention**: `farm_[feature]` (e.g., `farm_water_level_thingspeak`)  
**Namespace**: `Drupal\farm_water_level_thingspeak`  
**Minimum PHP**: 8.4  
**Minimum Drupal**: 11  
**Minimum farmOS**: 4.0  
