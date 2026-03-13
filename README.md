# Farm Water Level Sensor

A [farmOS](https://farmOS.org) module that provides a **Water Level** log type for recording and tracking water level sensor readings.

## Features

- **Water Level log type** — dedicated log type for recording water level observations
- **Quantity tracking** — attach standard quantity measurements (depth, volume, etc.) to each reading
- **Asset association** — link readings to specific sensor assets or water sources
- **Geometry/location** — record the geographic location of sensor installations
- **Sensor depth field** — store the installation depth of the sensor itself

## Requirements

- [farmOS](https://farmOS.org) 3.x
- Drupal 10 or 11

## Installation

### Via Composer (recommended)

```bash
composer require drupal/farm_water_level
```

Then enable the module:

```bash
drush en farm_water_level
```

### Manual installation

1. Clone or download this repository into your farmOS modules directory:
   - Docker: `/opt/drupal/web/modules/`
   - Or the `modules` directory of your server's document root
2. Enable the module via the Drupal admin UI at **Extend** or with Drush:
   ```bash
   drush en farm_water_level
   ```

## Usage

1. Navigate to **Logs > Add log > Water level** to create a new water level reading.
2. Associate the reading with an asset (e.g., a well, pond, or sensor device).
3. Add quantity measurements for the water level (depth in meters, feet, etc.).
4. Optionally set the geometry for the sensor location and add notes.

## Module structure

```
farm_water_level/
├── farm_water_level.info.yml          # Module definition
├── farm_water_level.module            # Hook implementations
├── farm_water_level.install           # Install/uninstall hooks
├── farm_water_level.permissions.yml   # Custom permissions
├── farm_water_level.links.action.yml  # Action links
├── composer.json                      # Composer metadata
├── config/
│   └── install/                       # Config entities installed with the module
│       ├── log.type.water_level.yml
│       ├── farm_entity.bundle_field.log.water_level.asset.yml
│       ├── farm_entity.bundle_field.log.water_level.quantity.yml
│       ├── farm_entity.bundle_field.log.water_level.geometry.yml
│       ├── farm_entity.bundle_field.log.water_level.notes.yml
│       └── system.action.log_water_level.yml
└── src/
    └── Plugin/Log/LogType/
        └── WaterLevel.php             # Log type plugin class
```

## Development

For local development, refer to the [farmOS development environment](https://farmOS.org/development/environment/) documentation to set up a Docker-based dev instance.

## License

This module is licensed under the [GNU General Public License, version 2 or later](https://www.gnu.org/licenses/gpl-2.0.html).
