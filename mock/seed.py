#!/usr/bin/env python3
"""
Seed script for InfluxDB mock — PINZAS bucket.

Measurements:
  hourly  → tags: dev_eui, device_name, f_port, name  | field: kwh (float)
  daily   → tags: dev_eui, device_name, f_port, name  | field: kwh_total (float)

90 days of deterministic data for 3 simulated devices.
"""

import math
import time
import urllib.request
import urllib.error
from datetime import datetime, timedelta, timezone

# ─── Config ────────────────────────────────────────────────────────────────────
INFLUX_URL   = "http://localhost:8086"
INFLUX_TOKEN = "tersime-mock-token-2024"
INFLUX_ORG   = "tersime"
INFLUX_BUCKET = "PINZAS"

DAYS   = 400
BATCH  = 500

DEVICES = [
    {"dev_eui": "A8610A3332378C1A", "device_name": "Oficina Principal", "f_port": "1", "name": "oficina",    "scale": 1.0},
    {"dev_eui": "B4691C0046ABF2D3", "device_name": "Sala Servidores",   "f_port": "2", "name": "servidores", "scale": 1.5},
    {"dev_eui": "C2741D1157BCG4E4", "device_name": "Almacen",           "f_port": "3", "name": "almacen",    "scale": 0.6},
]


def hourly_kwh(hour: int, scale: float) -> float:
    """Deterministic consumption curve: low at night, peak midday (~1.25 kWh * scale)."""
    base = 0.15 + 1.1 * max(0.0, math.sin(math.pi * (hour - 6) / 14))
    return round(base * scale, 4)


def wait_for_influx(timeout: int = 60) -> None:
    deadline = time.time() + timeout
    url = f"{INFLUX_URL}/health"
    while time.time() < deadline:
        try:
            with urllib.request.urlopen(url, timeout=3) as r:
                if r.status == 200:
                    print("InfluxDB is ready.")
                    return
        except Exception:
            pass
        print("Waiting for InfluxDB…")
        time.sleep(3)
    raise RuntimeError("InfluxDB did not become ready in time.")


def write_lines(lines: list[str]) -> None:
    payload = "\n".join(lines).encode()
    req = urllib.request.Request(
        f"{INFLUX_URL}/api/v2/write?org={INFLUX_ORG}&bucket={INFLUX_BUCKET}&precision=s",
        data=payload,
        method="POST",
        headers={
            "Authorization": f"Token {INFLUX_TOKEN}",
            "Content-Type":  "text/plain; charset=utf-8",
        },
    )
    try:
        with urllib.request.urlopen(req) as r:
            if r.status not in (200, 204):
                raise RuntimeError(f"Unexpected status: {r.status}")
    except urllib.error.HTTPError as e:
        body = e.read().decode(errors="replace")
        raise RuntimeError(f"HTTP {e.code}: {body}") from e


def escape_tag(v: str) -> str:
    return v.replace(" ", r"\ ").replace(",", r"\,").replace("=", r"\=")


def main() -> None:
    wait_for_influx()

    # Reference end: yesterday 23:59:59 UTC (so all points are in the past)
    now_utc  = datetime.now(timezone.utc).replace(hour=0, minute=0, second=0, microsecond=0)
    end_day  = now_utc - timedelta(days=1)
    start_day = end_day - timedelta(days=DAYS - 1)

    hourly_lines: list[str] = []
    daily_lines:  list[str] = []

    for device in DEVICES:
        dev_eui     = escape_tag(device["dev_eui"])
        device_name = escape_tag(device["device_name"])
        f_port      = escape_tag(device["f_port"])
        name        = escape_tag(device["name"])
        scale       = device["scale"]

        tags = f"dev_eui={dev_eui},device_name={device_name},f_port={f_port},name={name}"

        for day_offset in range(DAYS):
            day_dt    = start_day + timedelta(days=day_offset)
            daily_total = 0.0

            for hour in range(24):
                ts_utc = day_dt + timedelta(hours=hour)
                ts_s   = int(ts_utc.timestamp())
                kwh    = hourly_kwh(hour, scale)
                daily_total += kwh
                hourly_lines.append(f"hourly,{tags} kwh={kwh} {ts_s}")

            # daily point at midnight of that day
            ts_day = int(day_dt.timestamp())
            daily_lines.append(
                f"daily,{tags} kwh_total={round(daily_total, 4)} {ts_day}"
            )

    # Write hourly in batches
    total = len(hourly_lines)
    written = 0
    print(f"Writing {total} hourly points…")
    for i in range(0, total, BATCH):
        write_lines(hourly_lines[i : i + BATCH])
        written += min(BATCH, total - i)
        print(f"  hourly {written}/{total}", end="\r")
    print()

    # Write daily in batches
    total = len(daily_lines)
    written = 0
    print(f"Writing {total} daily points…")
    for i in range(0, total, BATCH):
        write_lines(daily_lines[i : i + BATCH])
        written += min(BATCH, total - i)
        print(f"  daily {written}/{total}", end="\r")
    print()

    print("Done. Mock data seeded successfully.")


if __name__ == "__main__":
    main()
