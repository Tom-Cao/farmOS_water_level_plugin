# farmOS Water Level Plugin - Comprehensive Research & Development Guide

## 1. DRUPAL MODULE FILE STRUCTURE

### Standard Required Files

A farmOS (Drupal) module requires the following core files:

#### **[module_name].info.yml** (REQUIRED)
Contains module metadata and dependencies. This is the ONLY required file.

```yaml
name: farmOS Water Level ThingSpeak
description: Integrates ThingSpeak API with farmOS for water level sensor data
type: module
package: farmOS Contrib
core_version_requirement: ^11
dependencies:
  - farm:farm_entity
  - farm:farm_log          # If creating water level logs
  - data_stream:data_stream # For sensor data streams
```

**Key Fields:**
- `name`: Human-readable name
- `description`: Purpose of module
- `type`: Always "module" for Drupal modules
- `package`: Category (farmOS Contrib recommended)
- `core_version_requirement`: Drupal version compatibility (^11 for current farmOS 4.x)
- `dependencies`: List of required modules with format "namespace:module_name"

#### **[module_name].module** (OPTIONAL)
PHP file for hook implementations. Common hooks include:
- `hook_cron()`: Execute periodic tasks
- `hook_entity_base_field_info()`: Add fields to all bundles of an entity type
- `hook_form_alter()`: Modify forms

#### **[module_name].services.yml** (RECOMMENDED)
Registers services available for dependency injection:

```yaml
services:
  thingspeak.service:
    class: Drupal\farm_water_level_thingspeak\Service\ThingSpeakService
    arguments:
      - '@http_client'
      - '@logger.factory'
```

#### **[module_name].routing.yml** (IF NEEDED)
Defines custom URL routes for admin pages or APIs.

#### **config/install/*.yml** (FOR CONFIG ENTITIES)
Configuration entities installed with the module:
- Custom entity types
- Custom bundles (data stream types, log types, etc.)

#### **src/** (STANDARD)
PHP classes organized by PSR-4 autoloading:
- `src/Plugin/` - Plugin classes
- `src/Service/` - Service classes  
- `src/Form/` - Form classes
- `src/Hook/` - Hook implementations (Drupal 11 attribute-based hooks)

#### **tests/** (RECOMMENDED)
Automated test files for quality assurance.

---

## 2. farmOS LOGGING & QUANTITY SYSTEM FOR SENSOR DATA

### Key Entities

**Logs** (`log` entity type)
- Records of activities, observations, or data collection
- Has bundles (types) like: observation, input, output, activity
- Fields: timestamp, name, notes, status, flags
- References: assets (what it applies to), areas, etc.
- Perfect for storing sensor readings as "observation" logs

**Assets** (`asset` entity type) 
- Represents physical items or entities being tracked
- Has bundles: animal, plant, land, equipment, structure, group, sensor
- **Sensor assets**: Specifically for IoT devices and data collection devices
- Can have location, inventory, flags

**Data Streams** (`data_stream` entity type)
- Time-series data containers specifically designed for sensor data
- Each data stream = single stream of data from a sensor
- Identified by UUID with private_key and public access control
- Provides custom types via plugins
- Better than logs for continuous sensor data (e.g., water level readings)

**Quantities** (not a separate entity - stored in logs)
- Represent measured values on logs
- Each log can have multiple quantities
- Linked via `quantity` field on logs
- Include measure (e.g., "water level"), value, units
- Can be referenced from inventory or other sources

### Recommended Approach for Water Level Data

**Option 1: Using Data Streams (RECOMMENDED)**
- Create a custom "thingspeak" data stream type
- Each water level sensor = one data stream entity
- Data streams store sensor readings with timestamps
- Integrates with farmOS's sensor monitoring system
- Supports private keys for data access control

**Option 2: Using Observation Logs**
- Create "water_level_observation" logs
- Each log contains timestamp, location, water level measurement
- References a "sensor" asset
- Less ideal for continuous sensor data (more for discrete observations)

### farmOS Dependencies for Sensor Module

Essential modules to declare:
```yaml
dependencies:
  - entity:entity              # Core entity system
  - farm:farm_entity          # farmOS entity extensions  
  - data_stream:data_stream   # For data stream functionality
  - farm:farm_log             # If creating observation logs
  - farm:farm_asset           # If creating sensor assets
  - drupal:core               # Drupal core
```

---

## 3. THINGSPEAK REST API ENDPOINTS

### Base URLs
- **API Base**: `https://api.thingspeak.com`
- **Web UI**: `https://thingspeak.com`

### Endpoints for Fetching Water Level Data

#### **Get Channel Feeds (Latest Data)**
```
GET https://api.thingspeak.com/channels/{channel_id}/feeds.json
```

**Parameters:**
- `api_key`: Channel API key (required for private channels, optional for public)
- `results`: Number of records (1-8000, default 8000)
- `days`: Last N days of data
- `start`: Start ISO 8601 timestamp
- `end`: End ISO 8601 timestamp  
- `timezone`: TZ database name (e.g., "America/New_York")
- `round`: Decimal places (0-8)

**Response Example:**
```json
{
  "channel": {
    "id": 123456,
    "name": "Farm Water Tank",
    "latitude": "40.123",
    "longitude": "-74.456",
    "field1": "Water Level (cm)",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-12-31T23:59:59Z"
  },
  "feeds": [
    {
      "id": 1,
      "created_at": "2024-12-31T23:50:00Z",
      "entry_id": 1,
      "field1": "245.5"
    },
    {
      "id": 2,
      "created_at": "2024-12-31T23:55:00Z",
      "field1": "246.2"
    }
  ]
}
```

#### **Get Single Field Data**
```
GET https://api.thingspeak.com/channels/{channel_id}/fields/{field_number}.json
```

Field numbers: 1-8 (water level would typically be field1)

#### **Get Latest Entry**
```
GET https://api.thingspeak.com/channels/{channel_id}/feeds/last.json?api_key={api_key}
```

#### **Get Field Statistics**
```
GET https://api.thingspeak.com/channels/{channel_id}/fields/{field_number}.json?status=true&results=10
```

### Authentication
- **Read Access**: 
  - Public channels: No key needed
  - Private channels: Requires channel API key
- **Write Access**: Requires channel write API key
- **API Keys**: Available in ThingSpeak channel settings

### Rate Limits
- Free tier: 3.6 million API hits per day (~42 requests/second)
- Commercial: Higher limits available
- No response caching; new requests always fetch fresh data

---

## 4. EXTERNAL API INTEGRATION IN farmOS MODULES

### HTTP Client Integration (Drupal Standard)

**Service Registration** (services.yml):
```yaml
services:
  http_client:
    class: GuzzleHttp\Client  # Built into Drupal
    factory: 'drupal_http_client_factory:create'
```

Drupal provides `@http_client` service using GuzzleHttp/Guzzle.

**Service Class Pattern**:
```php
<?php
namespace Drupal\farm_water_level_thingspeak\Service;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

class ThingSpeakService {
  
  public function __construct(
    private ClientInterface $httpClient,
    private LoggerChannelFactoryInterface $loggerFactory,
  ) {}
  
  public function fetchChannelData($channelId, $apiKey, $params = []) {
    try {
      $response = $this->httpClient->request('GET', 
        "https://api.thingspeak.com/channels/{$channelId}/feeds.json",
        [
          'query' => array_merge(['api_key' => $apiKey], $params),
          'timeout' => 10,
          'connect_timeout' => 5,
        ]
      );
      
      return json_decode($response->getBody()->getContents(), TRUE);
    } catch (\Exception $e) {
      $this->loggerFactory->get('farm_water_level_thingspeak')
        ->error('API Error: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }
}
```

### Cron Task Implementation

**Hook-based Approach** (Modern Drupal 11):
```php
<?php
// src/Hook/CronHooks.php
namespace Drupal\farm_water_level_thingspeak\Hook;

use Drupal\Core\Hook\Attribute\Hook;

class CronHooks {
  
  #[Hook('cron')]
  public function cron() {
    // Fetch latest data from all configured ThingSpeak channels
    // Create or update data stream entries
  }
}
```

**Configuration Storage**:
- Use Drupal's state API for runtime data: `\Drupal::state()->get('farm_water_level_thingspeak.last_sync')`
- Use configuration entities for channel mappings
- Store in database for persistence across requests

**Scheduling**:
- Drupal cron runs via: 
  - Automatic on page views (can be disabled)
  - Drush: `drush cron`
  - System cron job: `curl https://example.com/cron.php`
  - Advanced Cron module for more control

### Configuration Management

**Channel Configuration** (config entity or simple storage):
```yaml
# config/install/farm_water_level_thingspeak.settings.yml
thingspeak_channels:
  - channel_id: '123456'
    api_key: 'XXXXX'
    field_number: 1  # Which field = water level
    update_frequency: 3600  # seconds
    target_data_stream: 'uuid-of-data-stream'
```

**Admin Form** (`src/Form/ThingSpeakSettingsForm.php`):
- Allow admin to configure channel ID, API key
- Test connection button
- Sync frequency settings
- Logging options

### Error Handling & Logging

```php
// Log successful sync
$this->loggerFactory->get('farm_water_level_thingspeak')
  ->info('Synced @count readings from channel @id', 
    ['@count' => count($data), '@id' => $channelId]
  );

// Log errors with context
$this->loggerFactory->get('farm_water_level_thingspeak')
  ->error('Failed to fetch data: @error', 
    ['@error' => $e->getMessage()]
  );
```

---

## 5. FARMOS VERSION & DEPENDENCIES

### farmOS Version Information
**Current Version**: 4.x (Latest: 4.0.0-beta4, released 2026-03-09)

**Key Details**:
- **Drupal Core**: 11.3.5
- **PHP**: >= 8.4 (requires PHP 8.4+)
- **Database**:
  - PostgreSQL 16+
  - MariaDB 10.6+
  - MySQL 8.0+
  - SQLite 3.45+

### Typical farmOS Module Dependencies

```yaml
# Minimal sensor module dependencies
dependencies:
  - entity:entity                    # Core entity system
  - farm:farm_entity                # farmOS entity base
  - data_stream:data_stream         # For sensor data streams
  - drupal:core                     # Drupal core (implicit)

# Extended sensor module dependencies (if creating custom logs)
dependencies:
  - entity:entity
  - farm:farm_entity
  - farm:farm_log                   # For observation logs
  - farm:farm_asset                 # For sensor assets
  - data_stream:data_stream
  - drupal:dynamic_entity_reference # Flexible entity refs
```

### Core farmOS Dependencies (from composer.json)
- `drupal/core: ^11.3.5`
- `drupal/entity: 1.6`
- `drupal/entity_reference_revisions: 1.14`
- `drupal/fraction: ^3.0` (for decimal measurements)
- `drupal/log: ^3.1` (activity logs)
- `drupal/state_machine: ^1.12` (workflow states)
- `drupal/migrate_plus: ^6.0.8` (data import)

### PHP Version Requirements
- **farmOS 4.x**: PHP 8.4+
- **farmOS 3.x**: PHP 8.2+
- Latest versions strongly recommended

---

## 6. REPOSITORY ANALYSIS

### Current Repository Status
Location: `/home/runner/work/farmOS_water_level_plugin/farmOS_water_level_plugin`

**Contents**:
- `.git/` - Git repository (empty/setup)
- `README.md` - Minimal documentation (27 bytes)
- **No existing code structure** - Ready for implementation

**Recommendation**: This is a fresh repository ready for module development. Follow the structure outlined above.

---

## 7. RECOMMENDED MODULE STRUCTURE FOR WATER LEVEL PLUGIN

```
farm_water_level_thingspeak/
├── farm_water_level_thingspeak.info.yml
├── farm_water_level_thingspeak.module
├── farm_water_level_thingspeak.services.yml
├── config/
│   └── install/
│       ├── data_stream.type.thingspeak.yml
│       └── farm_water_level_thingspeak.settings.yml
├── src/
│   ├── Plugin/
│   │   └── DataStream/
│   │       └── DataStreamType/
│   │           ├── ThingSpeak.php
│   │           └── ThingSpeakForm.php
│   ├── Service/
│   │   └── ThingSpeakService.php
│   ├── Form/
│   │   └── ChannelConfigForm.php
│   └── Hook/
│       ├── CronHooks.php
│       └── FieldHooks.php
├── tests/
│   └── Unit/
│       └── Service/
│           └── ThingSpeakServiceTest.php
└── README.md
```

---

## 8. KEY DEVELOPMENT PATTERNS

### Pattern: Dependency Injection in Services
```php
public function __construct(
  protected ClientInterface $httpClient,
  protected EntityTypeManagerInterface $entityTypeManager,
  protected LoggerChannelFactoryInterface $loggerFactory,
) {}
```

### Pattern: Entity Loading and Data Creation
```php
// Load or create a data stream
$data_stream = $this->entityTypeManager
  ->getStorage('data_stream')
  ->load($uuid);

// Create new entry on data stream
$plugin = $data_stream->getPlugin();
$plugin->storageSet($value, $timestamp);
```

### Pattern: Configuration Access
```php
$config = \Drupal::config('farm_water_level_thingspeak.settings');
$api_key = $config->get('thingspeak_api_key');
```

### Pattern: State Storage (Runtime)
```php
// Store last sync time
\Drupal::state()->set('farm_water_level_thingspeak.last_sync', time());

// Retrieve last sync time
$last_sync = \Drupal::state()->get('farm_water_level_thingspeak.last_sync', 0);
```

---

## 9. TESTING & DEPLOYMENT CHECKLIST

- [ ] Module can be installed via Drupal UI or Composer
- [ ] No fatal PHP errors during install
- [ ] Configuration entities properly installed
- [ ] ThingSpeak API connection tested
- [ ] Cron task executes without errors
- [ ] Data streams created and updated successfully
- [ ] Logging shows proper error/success messages
- [ ] Admin form allows configuration
- [ ] Uninstall removes all data cleanly
- [ ] Code passes PHPStan static analysis
- [ ] README.md documents setup and configuration

