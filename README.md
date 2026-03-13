# farmOS Water Level ThingSpeak Plugin

A [farmOS](https://farmos.org/) module that connects to [ThingSpeak](https://thingspeak.com/) to automatically import water level sensor data as farmOS observation logs.

## Features

- Fetches water level readings from a ThingSpeak channel via the ThingSpeak REST API
- Creates farmOS **Observation logs** with a standard quantity for each water level reading
- Tracks the last imported entry ID to avoid duplicate imports
- Configurable sync interval (cron-driven)
- Manual sync trigger from the admin UI
- Supports both public and private ThingSpeak channels
- Configurable units (m, ft, cm, in, etc.)

## Requirements

- farmOS 2.x or 3.x (Drupal 9.4+ / 10 / 11)
- PHP 8.1+
- `farm_log_observation` module (included with farmOS)
- `farm_quantity_standard` module (included with farmOS)

## Installation

1. Copy (or symlink) the `farm_water_level_thingspeak/` directory into your farmOS
   `web/modules/custom/` directory.
2. Enable the module via Drush or the Drupal admin UI:
   ```
   drush en farm_water_level_thingspeak
   ```
3. Navigate to **Farm > Settings > Water Level Settings** and configure
   your ThingSpeak channel.

## Configuration

Go to **Farm > Settings > Water Level Settings** (path: `/farm/water-level/settings`) and fill in:

| Setting | Description |
|---------|-------------|
| **Channel ID** | The numeric ThingSpeak channel ID |
| **Read API Key** | ThingSpeak Read API key (leave empty for public channels) |
| **Water Level Field** | Which ThingSpeak field (field1–field8) holds the water level value |
| **Sync Interval** | How often to pull new data (5 min – 1 day) |
| **Units** | Unit of measurement appended to the quantity (e.g. `m`, `ft`, `cm`) |
| **Log Name Prefix** | Prefix for the auto-generated observation log names |

## How It Works

1. **Cron** calls `hook_cron()` → `ThingSpeakService::syncData()` at the
   configured interval.
2. The service fetches up to 100 feeds newer than the last imported entry from
   `https://api.thingspeak.com/channels/{channel_id}/feeds.json`.
3. For each feed entry with a non-empty water level value, an **Observation log**
   is created in farmOS with:
   - A **standard quantity** (measure: `water_content`) set to the sensor value
   - The ThingSpeak entry ID recorded in the log notes for traceability
4. The last imported entry ID is stored in Drupal State so only new readings
   are imported on subsequent syncs.

You can also trigger a manual sync by visiting `/farm/water-level/sync`
(requires the *Administer farmOS Water Level ThingSpeak* permission).

## ThingSpeak Data Format

The module expects the water level reading to be a numeric value in one of
ThingSpeak's eight data fields (`field1` through `field8`). Configure which
field to use in the module settings.

Example ThingSpeak feed entry:
```json
{
  "created_at": "2024-06-01T10:00:00Z",
  "entry_id": 1001,
  "field1": "1.42"
}
```

## Development & Testing

Unit tests are located in `tests/src/Unit/`. Run them with PHPUnit inside a
full Drupal/farmOS test environment:

```bash
phpunit web/modules/custom/farm_water_level_thingspeak
```

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).
