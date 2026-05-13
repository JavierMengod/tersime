#!/usr/bin/env python3
"""
Real-time simulator for PINZAS InfluxDB mock.
Writes one hourly point per device every hour and updates the daily total.
"""

import math
import time
import urllib.request
import urllib.error
from datetime import datetime, timezone

INFLUX_URL    = "http://influxdb-mock:8086"
INFLUX_TOKEN  = "tersime-mock-token-2024"
INFLUX_ORG    = "tersime"
INFLUX_BUCKET = "PINZAS"

DEVICES = [
    {"dev_eui": "A8610A3332378C1A", "device_name": "Oficina Principal", "f_port": "1", "name": "oficina",    "scale": 1.0},
    {"dev_eui": "B4691C0046ABF2D3", "device_name": "Sala Servidores",   "f_port": "2", "name": "servidores", "scale": 1.5},
    {"dev_eui": "C2741D1157BCG4E4", "device_name": "Almacen",           "f_port": "3", "name": "almacen",    "scale": 0.6},
]


def hourly_kwh(hour: int, scale: float) -> float:
    base = 0.15 + 1.1 * max(0.0, math.sin(math.pi * (hour - 6) / 14))
    return round(base * scale, 4)


def escape_tag(v: str) -> str:
    return v.replace(" ", r"\ ").replace(",", r"\,").replace("=", r"\=")


def wait_for_influx(timeout: int = 120) -> None:
    deadline = time.time() + timeout
    url = f"{INFLUX_URL}/health"
    while time.time() < deadline:
        try:
            with urllib.request.urlopen(url, timeout=3) as r:
                if r.status == 200:
                    print("InfluxDB ready.", flush=True)
                    return
        except Exception:
            pass
        print("Waiting for InfluxDB…", flush=True)
        time.sleep(5)
    raise RuntimeError("InfluxDB did not become ready in time.")


def write_lines(lines: list) -> None:
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


def write_current_hour() -> None:
    now = datetime.now(timezone.utc)
    hour_ts = now.replace(minute=0, second=0, microsecond=0)
    day_ts  = now.replace(hour=0, minute=0, second=0, microsecond=0)
    hour    = hour_ts.hour

    lines = []
    for device in DEVICES:
        tags = (
            f"dev_eui={escape_tag(device['dev_eui'])},"
            f"device_name={escape_tag(device['device_name'])},"
            f"f_port={escape_tag(device['f_port'])},"
            f"name={escape_tag(device['name'])}"
        )
        scale = device["scale"]
        kwh   = hourly_kwh(hour, scale)

        # Accumulate daily total from hour 0 up to current hour
        daily_total = sum(hourly_kwh(h, scale) for h in range(hour + 1))

        ts_h = int(hour_ts.timestamp())
        ts_d = int(day_ts.timestamp())

        lines.append(f"hourly,{tags} kwh={kwh} {ts_h}")
        lines.append(f"daily,{tags} kwh_total={round(daily_total, 4)} {ts_d}")

    write_lines(lines)
    print(f"[{now.strftime('%Y-%m-%d %H:%M:%S')} UTC] Written hour={hour} for {len(DEVICES)} devices.", flush=True)


def seconds_until_next_hour() -> float:
    now = datetime.now(timezone.utc)
    return 3600 - (now.minute * 60 + now.second + now.microsecond / 1e6)


def main() -> None:
    wait_for_influx()

    # Write immediately on startup
    write_current_hour()

    while True:
        sleep_s = seconds_until_next_hour()
        print(f"Next write in {sleep_s:.0f}s.", flush=True)
        time.sleep(sleep_s)
        write_current_hour()


if __name__ == "__main__":
    main()
