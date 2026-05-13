<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Informe Bajo Demanda</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; margin: 0; }
    header { background-color: #0d47a1; color: white; padding: 16px; text-align: center; }
    header img { height: 50px; margin-bottom: 8px; }
    h1 { font-size: 20px; margin: 0; }
    h2 { font-size: 16px; margin-top: 24px; color: #0d47a1; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
    .meta { font-size: 11px; margin-top: 8px; color: #f0f0f0; }
    .content { padding: 24px; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { border: 1px solid #ccc; padding: 6px; text-align: left; font-size: 11px; }
    th { background-color: #e3f2fd; }
    .small { font-size: 10px; color: #555; }
    .grafica { margin: 20px 0; text-align: center; }
    .grafica img { max-width: 100%; height: auto; border: 1px solid #ccc; }
    footer { position: fixed; bottom: 10px; left: 0; right: 0; text-align: center; font-size: 10px; color: #666; }
  </style>
</head>
<body>
  <!-- Encabezado -->
  <header>
    <img src="{{ public_path('assets/img/TERSIME.png') }}" alt="Logo TERSIME">
    <h1>Informe Bajo Demanda</h1>
    <div class="meta">
      Usuario: {{ $user->name ?? 'N/A' }} — Periodo: {{ $fromDate }} a {{ $toDate }}
      @if($email) — Correo destino: {{ $email }} @endif
    </div>
  </header>

  <div class="content">
    <!-- Sección dispositivos -->
    <h2>Dispositivos seleccionados</h2>
    @if($dispositivos->isEmpty())
      <p class="small">Ninguno</p>
    @else
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Otros datos</th>
          </tr>
        </thead>
        <tbody>
          @foreach($dispositivos as $d)
            <tr>
              <td>{{ $d->id }}</td>
              <td>{{ $d->nombre }}</td>
              <td class="small">Métricas o resúmenes por dispositivo</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif

    <!-- Sección notificaciones -->
    <h2>Notificaciones configuradas</h2>
    <p class="small">
      @if(!empty($notificaciones))
        {{ implode(', ', $notificaciones) }}
      @else
        Ninguna
      @endif
    </p>

    <!-- Sección gráficas -->
    <h2>Gráficas</h2>

    @if(!empty($graficas))
      @foreach($graficas as $nombre => $ruta)
        @php
          // Normalizar ruta a formato file:// válido
          $src = $ruta;
          if ($src && strpos($src, 'file://') !== 0) {
              $src = 'file://' . $src;
          }

          // Determinar título legible (compatible PHP 7)
          switch ($nombre) {
              case 'consumo_alto':
                  $titulo = 'Consumo anormalmente alto';
                  break;
              case 'consumo_bajo':
                  $titulo = 'Consumo anormalmente bajo';
                  break;
              case 'top5':
                  $titulo = 'Top 5 dispositivos';
                  break;
              case 'tiempo_real':
                  $titulo = 'Monitorización en tiempo real';
                  break;
              default:
                  $titulo = ucfirst(str_replace('_', ' ', $nombre));
          }
        @endphp

        @if(file_exists($ruta))
          <div class="grafica">
            <p><strong>{{ $titulo }}</strong></p>
            <img src="{{ $src }}" alt="{{ $titulo }}">
          </div>
        @else
          <p class="small"><em>No se encontró la gráfica: {{ $nombre }}</em></p>
        @endif
      @endforeach
    @else
      <p class="small">No se han generado gráficas para este informe.</p>
    @endif
  </div>

  <!-- Pie de página -->
  <footer>
    Generado el {{ now()->format('Y-m-d H:i:s') }}
  </footer>
</body>
</html>
