import logging

from fastapi import FastAPI
from pydantic import BaseModel
from typing import List, Optional
import pandas as pd
import numpy as np
from prophet import Prophet

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI()

MAX_FFILL_HOURS = 6   # huecos más largos quedan como NaN → Prophet los ignora
MIN_POINTS      = 48  # mínimo de puntos reales para entrenar el modelo


class PredictionRequest(BaseModel):
    timestamps: List[str]
    values:     List[float]
    predic_hours: Optional[int] = None


# ---------------------------------------------------------------------------
#  Endpoint principal
# ---------------------------------------------------------------------------

@app.post("/predict")
def predict(req: PredictionRequest):
    df = pd.DataFrame({
        "ds": pd.to_datetime(req.timestamps, utc=True),
        "y":  req.values,
    }).sort_values("ds").reset_index(drop=True)

    if df["ds"].dt.tz is not None:
        df["ds"] = df["ds"].dt.tz_convert("UTC").dt.tz_localize(None)

    pred_hours = int(req.predic_hours) if req.predic_hours else 24

    # --- Resamplear a frecuencia horaria con límite de relleno ---
    df = df.set_index("ds").asfreq("H")
    df["y"] = df["y"].ffill(limit=MAX_FFILL_HOURS)
    df = df.reset_index()
    df["y"] = df["y"].clip(lower=0)

    # --- Filtro de outliers: z-score rodante 24 h ---
    rolling_median = df["y"].rolling(window=24, center=True, min_periods=1).median()
    rolling_std    = df["y"].rolling(window=24, center=True, min_periods=1).std().fillna(0)
    upper_bound    = rolling_median + 3 * rolling_std.clip(lower=rolling_median * 0.1)
    df["y"]        = df["y"].clip(upper=upper_bound)

    if df["y"].notna().sum() < MIN_POINTS:
        logger.warning("Datos insuficientes para Prophet (%d puntos válidos)", df["y"].notna().sum())
        return _flat_fallback(df, pred_hours)

    # --- Construir y entrenar modelo ---
    n_days = (df["ds"].max() - df["ds"].min()).days

    model = Prophet(
        daily_seasonality=True,
        weekly_seasonality=True,
        yearly_seasonality=False,       # se añade manualmente si hay datos suficientes
        changepoint_prior_scale=0.15,
        seasonality_mode="additive",    # kWh horario: swing diario fijo → additive más estable
        interval_width=0.80,
        uncertainty_samples=300,        # default 1000 es lento; 300 es preciso y rápido
    )

    # Estacionalidad anual solo con ≥18 meses para evitar overfitting con 1 ciclo
    if n_days >= 540:
        model.add_seasonality(name="yearly", period=365.25, fourier_order=3)
        logger.info("Estacionalidad anual activada (%d días de datos)", n_days)

    df_fit = df[["ds", "y"]].dropna()

    try:
        model.fit(df_fit)
    except Exception as e:
        logger.error("Prophet fit fallido: %s", e)
        return _flat_fallback(df, pred_hours)

    # --- Predicción ---
    last_ts   = df["ds"].max()
    future_df = pd.DataFrame({
        "ds": pd.date_range(last_ts + pd.Timedelta(hours=1), periods=pred_hours, freq="H")
    })

    forecast = model.predict(future_df)

    for col in ("yhat", "yhat_lower", "yhat_upper"):
        forecast[col] = (
            forecast[col]
            .replace([np.inf, -np.inf], np.nan)
            .ffill()
            .fillna(0)
        )
        forecast[col] = np.maximum(forecast[col], 0)

    # --- Formatear salida ---
    df["ds"]       = df["ds"].dt.strftime("%Y-%m-%dT%H:%M:%SZ")
    future_df["ds"] = future_df["ds"].dt.strftime("%Y-%m-%dT%H:%M:%SZ")

    reales = [
        [t, float(v)]
        for t, v in zip(df["ds"], df["y"])
        if pd.notna(v)
    ]

    predichos = [
        [t, float(y), float(yl), float(yu)]
        for t, y, yl, yu in zip(
            future_df["ds"],
            forecast["yhat"],
            forecast["yhat_lower"],
            forecast["yhat_upper"],
        )
    ]

    logger.info(
        "Predicción OK — %d reales, %d predichos (n_days=%d)",
        len(reales), len(predichos), n_days,
    )
    return {"reales": reales, "predichos": predichos}


# ---------------------------------------------------------------------------
#  Helpers
# ---------------------------------------------------------------------------

def _flat_fallback(df: pd.DataFrame, pred_hours: int) -> dict:
    """Devuelve la última observación repetida cuando no hay datos suficientes."""
    last_ts  = df["ds"].max() if not df.empty else pd.Timestamp.utcnow()
    last_val = float(df["y"].dropna().iloc[-1]) if df["y"].notna().any() else 0.0

    future_dates = pd.date_range(
        start=last_ts + pd.Timedelta(hours=1),
        periods=pred_hours,
        freq="H",
    )

    reales = [
        [t.strftime("%Y-%m-%dT%H:%M:%SZ") if hasattr(t, "strftime") else t, float(v)]
        for t, v in zip(df["ds"], df["y"])
        if pd.notna(v)
    ]
    predichos = [
        [t.strftime("%Y-%m-%dT%H:%M:%SZ"), last_val, last_val, last_val]
        for t in future_dates
    ]
    return {"reales": reales, "predichos": predichos}


# ---------------------------------------------------------------------------
#  Health
# ---------------------------------------------------------------------------

@app.get("/")
def root():
    return {"message": "Servicio de predicción operativo ✅"}
