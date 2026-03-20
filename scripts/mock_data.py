#!/usr/bin/env python3
"""
Send mock water level sensor data to a specific field on a ThingSpeak channel.

Usage:
    python3 mock_data.py --api-key YOUR_WRITE_API_KEY --field 1
    python3 mock_data.py --api-key YOUR_WRITE_API_KEY --field 2 --count 20 --interval 16
    python3 mock_data.py --api-key YOUR_WRITE_API_KEY --field 3 --base-level 60
"""

import argparse
import math
import random
import time
import urllib.request
import urllib.parse
from datetime import datetime

THINGSPEAK_UPDATE_URL = "https://api.thingspeak.com/update"

# ThingSpeak free tier: minimum 15 seconds between updates.
MIN_INTERVAL = 16


def generate_water_level(step: int, base_level: float = 45.0) -> float:
    """Simulate a water level with slow drift, a daily sine pattern, and noise."""
    drift = step * 0.02
    wave = 8.0 * math.sin(2 * math.pi * step / 50)
    noise = random.uniform(-1.5, 1.5)
    return round(base_level + drift + wave + noise, 2)


def send_reading(api_key: str, field: int, value: float) -> bool:
    params = urllib.parse.urlencode({
        "api_key": api_key,
        f"field{field}": value,
    })
    url = f"{THINGSPEAK_UPDATE_URL}?{params}"

    try:
        with urllib.request.urlopen(url, timeout=10) as resp:
            body = resp.read().decode().strip()
            # ThingSpeak returns the entry ID on success, "0" on rate-limit/failure.
            if body == "0":
                print(f"  -> rate-limited or rejected (response: 0)")
                return False
            print(f"  -> entry {body} created")
            return True
    except Exception as e:
        print(f"  -> error: {e}")
        return False


def main():
    parser = argparse.ArgumentParser(description="Push mock water level data to ThingSpeak")
    parser.add_argument("--api-key", required=True, help="ThingSpeak Write API key")
    parser.add_argument("--field", type=int, required=True, help="ThingSpeak field number (1-8) to write to")
    parser.add_argument("--count", type=int, default=10, help="Number of data points to send (default: 10)")
    parser.add_argument("--interval", type=int, default=MIN_INTERVAL, help=f"Seconds between sends (min {MIN_INTERVAL})")
    parser.add_argument("--base-level", type=float, default=45.0, help="Baseline water level in cm (default: 45.0)")
    args = parser.parse_args()

    if args.interval < MIN_INTERVAL:
        print(f"Warning: interval raised to {MIN_INTERVAL}s (ThingSpeak free-tier minimum)")
        args.interval = MIN_INTERVAL

    print(f"Sending {args.count} mock readings to ThingSpeak field{args.field}")
    print(f"Interval: {args.interval}s | Base level: {args.base_level} cm\n")

    for i in range(args.count):
        level = generate_water_level(i, args.base_level)
        ts = datetime.now().strftime("%H:%M:%S")
        print(f"[{ts}] #{i+1}/{args.count}  water_level = {level} cm")
        send_reading(args.api_key, args.field, level)

        if i < args.count - 1:
            time.sleep(args.interval)

    print("\nDone.")


if __name__ == "__main__":
    main()
