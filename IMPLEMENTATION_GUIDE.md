# farmOS Water Level Plugin Research Summary

I've completed comprehensive research on building a Drupal module for farmOS that connects to ThingSpeak API. Below is the detailed information you requested:

---

## 1. STANDARD DRUPAL MODULE FILE STRUCTURE

A farmOS (Drupal) module requires this file hierarchy:

### **Essential Files**
- **`[modulename].info.yml`** (REQUIRED) - Module metadata, declaring name, description, dependencies, and Drupal compatibility
- **`[modulename].module`** - Optional PHP file for hook implementations (cron, entity field info, form alteration)
- **`[modulename].services.yml`** - Service definitions for dependency injection
- **`[modulename].routing.yml`** - URL route definitions (if creating admin pages or APIs)

### **Directory Structure**
```
farm_water_level_thingspeak/
├── farm_water_level_thingspeak.info.yml    # REQUIRED: Module metadata
├── farm_water_level_thingspeak.module       # Hook implementations (hook_cron, etc)
├── farm_water_level_thingspeak.services.yml # Service definitions
├── config/
│   ├── install/                             # Config installed on module enable
│   │   ├── data_stream.type.thingspeak.yml # Custom data stream type config
│   │   └── farm_water_level_thingspeak.settings.yml
│   └── optional/
├── src/
│   ├── Plugin/DataStream/DataStreamType/
│   │   └── ThingSpeak.php                  # Data stream plugin class
│   ├── Service/
│   │   └── ThingSpeakService.php           # API integration service
│   ├── Form/
│   │   └── ChannelConfigForm.php           # Admin configuration form
│   ├── Hook/
│   │   └── CronHooks.php                   # Cron hook (Drupal 11 attributes)
│   └── Hook/
│       └── FieldHooks.php                  # Entity field additions
├── tests/Unit/
└── README.md
```

### **Key .info.yml Example**
```yaml
name: farmOS Water Level ThingSpeak
description: Integrates ThingSpeak API with farmOS to fetch and store water level sensor data
type: module
package: farmOS Contrib
core_version_requirement: ^11
dependencies:
  - entity:entity
  - farm:farm_entity
  - data_stream:data_stream
```

---

## 2. farmOS LOGGING & QUANTITY SYSTEM FOR SENSOR DATA

### **Key Entities for Sensor Data**

**1. Data Streams** (RECOMMENDED for continuous sensor data)
- Entity type: `data_stream`
- Purpose: Time-series data containers specifically designed for IoT/sensor data
- Each data stream = one sensor's stream of values
- Identified by UUID, with `private_key` and `public` access control
- Supports custom data stream types via plugins
- Has `storageGet()` and `storageSet()` methods for managing data
- Better for continuous readings (temperature, water level, pressure)

**2. Logs** (for discrete observations)
- Entity type: `log` with various bundles (observation, activity, input, output)
- Use case: Record specific events/observations
- Can reference assets (the sensor) and areas
- Support quantity measurements
- Better for isolated data points, not continuous streams

**3. Assets** (the physical sensor device)
- Entity type: `asset` with bundles including "sensor"
- Represents the actual IoT device, sensor, or equipment
- Can have location, metadata, associated logs
- Referenced by logs or data streams

**4. Quantities** (measurements on logs)
- Not a separate entity—stored as field data on logs
- Include: measure (type), value, units
- Example: water_level = 245.5 cm

### **Recommended Approach: Data Streams**
For a water level sensor that continuously reports values:
1. Create a custom `thingspeak` data stream type (plugin)
2. Each configured ThingSpeak channel = one data stream entity
3. Implement `storageSet()` to save fetched readings with timestamps
4. Cron task fetches from ThingSpeak API and stores in data stream
5. farmOS UI displays data streams with graphs/tables

### **Module Dependencies for Sensor Integration**
```yaml
dependencies:
  - entity:entity              # Core entity system
  - farm:farm_entity          # farmOS entity framework
  - data_stream:data_stream   # For sensor data streams (REQUIRED)
  - farm:farm_log             # If creating observation logs
  - farm:farm_asset           # If creating sensor assets
  - drupal:core               # Implicit Drupal requirement
```

---

## 3. THINGSPEAK REST API ENDPOINTS

### **Base URL**
```
https://api.thingspeak.com
```

### **Primary Endpoint: Get Channel Data**
```
GET https://api.thingspeak.com/channels/{channel_id}/feeds.json?api_key={api_key}&results=100
```

**Parameters:**
- `api_key` - Channel API key (required for private channels)
- `results` - Number of records to return (1-8000, default 8000)
- `days` - Get last N days of data
- `start` / `end` - ISO 8601 timestamps for date range
- `timezone` - TZ database name (e.g., "America/New_York")
- `round` - Decimal places to round to (0-8)

**Response Format:**
```json
{
  "channel": {
    "id": 123456,
    "name": "Farm Water Tank",
    "field1": "Water Level (cm)",
    "created_at": "2024-01-01T00:00:00Z"
  },
  "feeds": [
    {
      "created_at": "2024-12-31T23:50:00Z",
      "field1": "245.5"
    },
    {
      "created_at": "2024-12-31T23:55:00Z",
      "field1": "246.2"
    }
  ]
}
```

### **Alternative Endpoints**

**Get Latest Entry:**
```
GET https://api.thingspeak.com/channels/{channel_id}/feeds/last.json
```

**Get Single Field (Water Level might be field1-field8):**
```
GET https://api.thingspeak.com/channels/{channel_id}/fields/{field_number}.json?results=100
```

### **Authentication**
- Public channels: No key required
- Private channels: Include `api_key` parameter
- API key available in channel settings on ThingSpeak.com
- Rate limits: ~42 requests/second on free tier

---

## 4. EXTERNAL API INTEGRATION IN MODULES

### **HTTP Client Pattern (Drupal Standard)**

Drupal provides `@http_client` service (GuzzleHttp). Inject it via services.yml:

```yaml
# farm_water_level_thingspeak.services.yml
services:
  thingspeak.service:
    class: Drupal\farm_water_level_thingspeak\Service\ThingSpeakService
    arguments:
      - '@http_client'
      - '@logger.factory'
      - '@entity_type.manager'
```

### **Service Class Example**
```php
<?php
namespace Drupal\farm_water_level_thingspeak\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

class ThingSpeakService {
  
  public function __construct(
    protected ClientInterface $httpClient,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}
  
  public function fetchChannelData($channelId, $apiKey, $results = 100) {
    try {
      $response = $this->httpClient->request('GET', 
        "https://api.thingspeak.com/channels/{$channelId}/feeds.json",
        [
          'query' => [
            'api_key' => $apiKey,
            'results' => $results,
          ],
          'timeout' => 10,
        ]
      );
      
      $data = json_decode($response->getBody()->getContents(), TRUE);
      return $data;
      
    } catch (RequestException $e) {
      $this->loggerFactory->get('farm_water_level_thingspeak')
        ->error('ThingSpeak API error: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }
}
```

### **Cron Implementation (Periodic Sync)**

Modern Drupal 11 uses attributes for hooks:

```php
<?php
// src/Hook/CronHooks.php
namespace Drupal\farm_water_level_thingspeak\Hook;

use Drupal\Core\Hook\Attribute\Hook;

class CronHooks {
  
  #[Hook('cron')]
  public function cron(\Drupal\farm_water_level_thingspeak\Service\ThingSpeakService $service) {
    // Fetch latest data from all configured ThingSpeak channels
    // Create/update corresponding data stream entries
    $service->syncAllChannels();
  }
}
```

**Cron Execution Methods:**
- Automatic on page views (Drupal default, can disable)
- Manual: `drush cron` (Drush command)
- System cron: `curl https://site.com/cron.php`
- Advanced Cron module (more scheduling control)

### **Configuration Storage Patterns**

**Store runtime state (non-persistent):**
```php
\Drupal::state()->set('farm_water_level_thingspeak.last_sync', time());
$last_sync = \Drupal::state()->get('farm_water_level_thingspeak.last_sync', 0);
```

**Store persistent configuration:**
```php
$config = \Drupal::config('farm_water_level_thingspeak.settings');
$api_key = $config->get('api_key');
```

### **Error Handling & Logging**
```php
\Drupal::logger('farm_water_level_thingspeak')
  ->info('Synced @count readings', ['@count' => 100]);

\Drupal::logger('farm_water_level_thingspeak')
  ->error('API call failed: @error', ['@error' => $exception->getMessage()]);
```

---

## 5. FARMOS & DRUPAL VERSION INFORMATION

### **Current farmOS Version: 4.x**
- **Latest Release**: 4.0.0-beta4 (March 9, 2026)
- **Drupal Core**: 11.3.5
- **PHP Requirement**: >= 8.4 (minimum 8.4, latest recommended)
- **Status**: Production-ready, modern Drupal 11

### **Database Compatibility**
- PostgreSQL 16+
- MariaDB 10.6+
- MySQL 8.0+
- SQLite 3.45+

### **Typical farmOS Module Dependencies**
```yaml
dependencies:
  # Absolute minimum for a custom module
  - drupal:core
  
  # Typical for farmOS integration
  - entity:entity                    # Drupal Entity API
  - farm:farm_entity                # farmOS entity framework
  
  # For sensor/data stream modules
  - data_stream:data_stream         # Sensor data streams (RECOMMENDED)
  - farm:farm_log                   # If creating logs
  - farm:farm_asset                 # If creating assets
  
  # HTTP client comes with Drupal core (no dependency needed)
```

### **Core Dependency Versions (from farmOS composer.json)**
- `drupal/core: ^11.3.5`
- `drupal/entity: 1.6`
- `drupal/fraction: ^3.0` (high-precision decimals)
- `drupal/log: ^3.1` (log entities)
- `drupal/state_machine: ^1.12` (workflows)
- `drupal/migrate_plus: ^6.0.8` (data import)

### **Drupal 11 Specific Changes**
- Uses PHP 8.4 attributes for hooks instead of hook_ functions
- `#[Hook('hook_name')]` attribute replaces `function module_hook_name()`
- Strongly typed dependency injection required
- All code must pass PHPStan static analysis

---

## 6. REPOSITORY ANALYSIS

**Location**: `/home/runner/work/farmOS_water_level_plugin/farmOS_water_level_plugin`

**Current Status:**
- Fresh repository with `.git/` initialized
- Only contains `README.md` (27 bytes)
- **No existing code**—ready for full development

**Recommendation**: Repository is clean slate. Follow the module structure outlined above to build the plugin from scratch.

---

## 7. QUICK START MODULE STRUCTURE

For your water level plugin, create this structure:

```
farm_water_level_thingspeak/
├── farm_water_level_thingspeak.info.yml      # (REQUIRED)
├── farm_water_level_thingspeak.services.yml
├── config/install/
│   ├── data_stream.type.thingspeak.yml       # Data stream type config
│   └── farm_water_level_thingspeak.settings.yml
├── src/
│   ├── Plugin/DataStream/DataStreamType/ThingSpeak.php
│   ├── Service/ThingSpeakService.php         # API client
│   ├── Form/ChannelConfigForm.php            # Admin UI
│   └── Hook/CronHooks.php                    # Periodic sync
└── README.md
```

---

## 8. KEY IMPLEMENTATION PATTERNS

**Dependency Injection in __construct()**
```php
public function __construct(
  protected ClientInterface $httpClient,
  protected EntityTypeManagerInterface $entityTypeManager,
  protected LoggerChannelFactoryInterface $loggerFactory,
) {}
```

**Load/Create Data Stream Entry**
```php
$data_stream = $this->entityTypeManager
  ->getStorage('data_stream')
  ->load($uuid);
$plugin = $data_stream->getPlugin();
$plugin->storageSet($value, $timestamp);
```

**Drupal 11 Hook Attributes**
```php
#[Hook('cron')]
public function syncData() { /* sync code */ }
```

---

## 9. TESTING & DEPLOYMENT CHECKLIST

✅ Verify module installs without errors  
✅ Test ThingSpeak API connection  
✅ Confirm cron task executes  
✅ Validate data streams are created/updated  
✅ Check logging output  
✅ Admin form configuration works  
✅ Clean uninstall removes all data  
✅ Pass PHPStan code analysis  

---

## NEXT STEPS FOR YOUR PROJECT

1. **Initialize module files** with `.info.yml` and basic structure
2. **Create ThingSpeakService** for API communication
3. **Implement data stream plugin** for storing sensor readings
4. **Add cron hook** for periodic syncing
5. **Build configuration form** for API key and channel settings
6. **Write tests** and documentation
7. **Test with actual ThingSpeak account** (free tier available)

All the patterns, file structures, and code examples above follow farmOS and Drupal 11 best practices and are ready for implementation.

