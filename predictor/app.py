from fastapi import FastAPI
from pydantic import BaseModel
from typing import List, Optional
import pandas as pd
import numpy as np
from prophet import Prophet

app = FastAPI()

class PredictionRequest(BaseModel):
    timestamps: List[str]
    values: List[float]
    predic_hours: Optional[int] = None


@app.post("/predict")
def predict(req: PredictionRequest):
    # --- Cargar datos ---
    df = pd.DataFrame({
        "ds": pd.to_datetime(req.timestamps, utc=True),
        "y": req.values
    }).sort_values("ds").reset_index(drop=True)

    # Convertir a tz-naive
    if df["ds"].dt.tz is not None:
        df["ds"] = df["ds"].dt.tz_convert("UTC").dt.tz_localize(None)

    pred_hours = int(req.predic_hours) if req.predic_hours else 24

    # --- Detectar serie acumulada ---
    if len(df) >= 2:
        diffs = df["y"].diff().fillna(0)
        if (diffs >= 0).all():
            if df["y"].iloc[-1] - df["y"].iloc[0] > max(df["y"].median(), 1) * 2:
                df["y"] = df["y"].diff().fillna(0)

    # --- Preprocesado suave (no suavizar demasiado) ---
    df = df.set_index("ds").asfreq("H")
    df["y"] = df["y"].fillna(method="ffill").fillna(method="bfill")
    df = df.reset_index()
    df["y"] = df["y"].clip(lower=0)

    # Fallback en caso de pocos datos
    if len(df) < 24:
        last_ts = df["ds"].iloc[-1]
        freq = pd.Timedelta(hours=1)
        future_dates = pd.date_range(start=last_ts + freq, periods=pred_hours, freq=freq)
        last_val = float(df["y"].iloc[-1])
        reales = [[t.strftime("%Y-%m-%dT%H:%M:%SZ"), float(v)] for t, v in zip(df["ds"], df["y"])]
        predichos = [[t.strftime("%Y-%m-%dT%H:%M:%SZ"), last_val] for t in future_dates]
        return {"reales": reales, "predichos": predichos}

    # --- Prophet (solo tendencia y estacionalidad, sin regresores) ---
    model = Prophet(
        daily_seasonality=True,
        weekly_seasonality=True,
        changepoint_prior_scale=0.8,  # más brusco
        seasonality_mode="multiplicative"
    )

    try:
        model.fit(df[["ds", "y"]])
    except:
        # fallback
        last_ts = df["ds"].iloc[-1]
        future_dates = pd.date_range(start=last_ts + pd.Timedelta(hours=1), periods=pred_hours, freq="H")
        last_val = float(df["y"].iloc[-1])
        reales = [[t.strftime("%Y-%m-%dT%H:%M:%SZ"), float(v)] for t, v in zip(df["ds"], df["y"])]
        predichos = [[t.strftime("%Y-%m-%dT%H:%M:%SZ"), last_val] for t in future_dates]
        return {"reales": reales, "predichos": predichos}

    # --- Generar futuro ---
    last_ts = df["ds"].iloc[-1]
    future_dates = pd.date_range(last_ts + pd.Timedelta(hours=1), periods=pred_hours, freq="H")
    future_df = pd.DataFrame({"ds": future_dates})

    forecast = model.predict(future_df)

    # --- Limpieza de yhat ---
    yhat = forecast["yhat"].replace([np.inf, -np.inf], np.nan).fillna(method="ffill").fillna(0)
    yhat = np.maximum(yhat, 0)

    # --- CÁLCULO DE VARIABILIDAD REAL ---
    df["hour"] = df["ds"].dt.hour
    hourly_std = df.groupby("hour")["y"].std().fillna(0)

    # si no hay variabilidad, usar 5% de media
    global_std = max(df["y"].std(), 0.05 * df["y"].mean())

    # --- Generar ruido proporcional ---
    forecast_hours = future_df["ds"].dt.hour
    noise_base = forecast_hours.map(hourly_std).fillna(global_std)

    # ruido controlado (más brusco)
    rnd = np.random.normal(loc=0, scale=1, size=pred_hours)
    noise = rnd * noise_base.values * 0.8   # factor ajustable

    # --- Mezclar y crear picos bruscos ---
    y_final = yhat + noise
    y_final = np.maximum(y_final, 0)

    # --- Limpiar fechas ---
    df["ds"] = df["ds"].dt.strftime("%Y-%m-%dT%H:%M:%SZ")
    future_df["ds"] = future_df["ds"].dt.strftime("%Y-%m-%dT%H:%M:%SZ")

    reales = [[t, float(v)] for t, v in zip(df["ds"], df["y"])]
    predichos = [[t, float(v)] for t, v in zip(future_df["ds"], y_final)]

    return {"reales": reales, "predichos": predichos}


@app.get("/")
def root():
    return {"message": "Servicio de predicción operativo ✅"}
