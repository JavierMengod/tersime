FROM python:3.10-slim

# --- Dependencias del sistema necesarias para Prophet / cmdstan ---
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    g++ \
    make \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Crear directorio de app
WORKDIR /app

# Copiar requirements primero (mejor cache)
COPY requirements.txt .

# Instalar dependencias Python
RUN pip install --no-cache-dir -r requirements.txt

# Copiar tu código
COPY . .

# Exponer el puerto
EXPOSE 8888

# Comando por defecto (levantar FastAPI con uvicorn)
CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8888"]
