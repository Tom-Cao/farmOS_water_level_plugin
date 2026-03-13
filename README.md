# Farm Water Level Sensor

A [farmOS](https://farmOS.org) module that integrates with [ThingSpeak](https://thingspeak.com/) to pull water level sensor data and display it within farmOS.

## How it works

1. A physical water level sensor sends readings to a ThingSpeak channel.
2. This module reads that data via the ThingSpeak Read API.
3. Readings are displayed on a dashboard at `/farm/water-level`.
4. On cron, new readings are automatically synced and stored as farmOS **Water Level** logs with quantity measurements.

## Features

- **ThingSpeak integration** — connects to any ThingSpeak channel using a Read API key
- **Live dashboard** — view the latest reading and a table of recent data at `/farm/water-level`
- **Automatic sync** — cron pulls new readings and creates Water Level log entities with quantities
- **Configurable** — set channel ID, API key, field number, sync interval, and batch size from the admin UI

## Requirements

- [farmOS](https://farmOS.org) 3.x
- Drupal 10 or 11
- A ThingSpeak channel with water level sensor data

## Installation

### Via Composer (recommended)

```bash
composer require drupal/farm_water_level
drush en farm_water_level
```

### Manual

1. Place this module in your farmOS modules directory (`/opt/drupal/web/modules/` in Docker).
2. Enable: `drush en farm_water_level`

## Configuration

1. Go to **Administration > Configuration > System > Water Level Sensor** (`/admin/config/farm/water-level`).
2. Enter your **ThingSpeak Channel ID**.
3. Enter your **ThingSpeak Read API Key** (required for private channels).
4. Adjust the **Sync Interval** and **Results per Request** as needed.
5. Save.

## Usage

- Visit `/farm/water-level` to see the current reading and recent history.
- Water level logs are created automatically during cron and appear under **Logs** in farmOS.
- Each log includes a quantity measurement (in cm) for the water level value.

## Module structure

```
farm_water_level/
├── farm_water_level.info.yml              # Module definition
├── farm_water_level.module                # Hooks (help, cron, bundle fields)
├── farm_water_level.install               # Install/uninstall hooks
├── farm_water_level.routing.yml           # Routes (settings form, dashboard)
├── farm_water_level.services.yml          # Service definitions
├── farm_water_level.permissions.yml       # Permissions
├── farm_water_level.links.menu.yml        # Menu links
├── farm_water_level.links.action.yml      # Action links
├── composer.json                          # Composer metadata
├── config/
│   ├── install/
│   │   ├── farm_water_level.settings.yml  # Default config values
│   │   ├── log.type.water_level.yml       # Water level log type
│   │   └── farm_entity.bundle_field.*.yml # Field definitions
│   └── schema/
│       └── farm_water_level.schema.yml    # Config schema
└── src/
    ├── ThingSpeakClient.php               # ThingSpeak Read API client service
    ├── Controller/
    │   └── WaterLevelController.php       # Dashboard controller
    ├── Form/
    │   └── ThingSpeakSettingsForm.php      # Admin settings form
    └── Plugin/Log/LogType/
        └── WaterLevel.php                 # Log type plugin
```

## License

GNU General Public License, version 2 or later.
