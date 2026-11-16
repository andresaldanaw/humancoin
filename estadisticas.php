<?php
require __DIR__ . '/config.php';
$yo = usuario_actual($db);

$donde = 'WHERE origen_id IS NOT NULL';

$personas      = (int) $db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
$enCirculacion = (int) $db->query('SELECT COALESCE(SUM(saldo), 0) FROM usuarios')->fetchColumn();
$numEnvios     = (int) $db->query("SELECT COUNT(*) FROM transacciones $donde")->fetchColumn();
$movido        = (int) $db->query("SELECT COALESCE(SUM(monto), 0) FROM transacciones $donde")->fetchColumn();
$promedio      = $numEnvios > 0 ? round($movido / $numEnvios) : 0;
$activos       = (int) $db->query("SELECT COUNT(DISTINCT origen_id) FROM transacciones $donde")->fetchColumn();

$abierto = ($personas >= MINIMO_PERSONAS);

/* ---------- Todo lo de abajo solo se calcula si hay suficiente gente ---------- */

$gini = null; $giniSerie = []; $top10 = 0; $intactos = 0;
$velocidad = 0; $diasPrimerEnvio = null; $medianaReaccion = null;
$razones = []; $reciprocidad = 0; $paresUnicos = 0;
$porCategoria = []; $totalCategorias = 0; $montoPorCategoria = [];
$palabras = []; $largoMotivo = 0;
$serie = []; $topDia = 0; $altasSerie = [];
$saldoMax = 0; $saldoMin = 0; $arriba = 0; $abajo = 0; $igual = 0;

/* Gini sobre una lista de valores */
function gini(array $v) {
    $n = count($v);
    if ($n < 2) { return null; }
    sort($v);
    $suma = array_sum($v);
    if ($suma <= 0) { return null; }
    $acum = 0;
    foreach ($v as $i => $x) { $acum += ($i + 1) * $x; }
    return round(((2 * $acum) / ($n * $suma)) - (($n + 1) / $n), 3);
}

if ($abierto) {

    $saldos = array_map('intval', $db->query('SELECT saldo FROM usuarios')->fetchAll(PDO::FETCH_COLUMN));
    $gini = gini($saldos);

    rsort($saldos);
    $cuantos10 = max(1, (int) round(count($saldos) * 0.10));
    $top10 = $enCirculacion > 0
        ? round(array_sum(array_slice($saldos, 0, $cuantos10)) * 100 / $enCirculacion)
        : 0;

    $intactos = (int) $db->query('SELECT COUNT(*) FROM usuarios WHERE saldo = ' . MONEDAS_INICIALES)->fetchColumn();
    $arriba   = (int) $db->query('SELECT COUNT(*) FROM usuarios WHERE saldo > ' . MONEDAS_INICIALES)->fetchColumn();
    $abajo    = (int) $db->query('SELECT COUNT(*) FROM usuarios WHERE saldo < ' . MONEDAS_INICIALES)->fetchColumn();
    $igual    = $personas - $arriba - $abajo;
    $saldoMax = (int) $db->query('SELECT COALESCE(MAX(saldo), 0) FROM usuarios')->fetchColumn();
    $saldoMin = (int) $db->query('SELECT COALESCE(MIN(saldo), 0) FROM usuarios')->fetchColumn();

    /* Velocidad: cuántas veces ha cambiado de manos cada moneda */
    $velocidad = $enCirculacion > 0 ? round($movido / $enCirculacion, 2) : 0;

    /* Gini en el tiempo: reconstruido por dia a partir del libro de movimientos */
    $filas = $db->query(
        'SELECT origen_id, destino_id, monto, DATE(fecha) AS dia
           FROM transacciones ORDER BY id ASC'
    )->fetchAll();
    $saldoVivo = [];
    $porDiaGini = [];
    foreach ($filas as $f) {
        if ($f['origen_id'] === null) {
            $saldoVivo[(int) $f['destino_id']] = (int) $f['monto'];
        } else {
            $saldoVivo[(int) $f['origen_id']]  = ($saldoVivo[(int) $f['origen_id']]  ?? 0) - (int) $f['monto'];
            $saldoVivo[(int) $f['destino_id']] = ($saldoVivo[(int) $f['destino_id']] ?? 0) + (int) $f['monto'];
        }
        if (count($saldoVivo) >= MINIMO_PERSONAS) {
            $g = gini(array_values($saldoVivo));
            if ($g !== null) { $porDiaGini[$f['dia']] = $g; }
        }
    }
    foreach ($porDiaGini as $d => $g) { $giniSerie[] = ['dia' => $d, 'valor' => $g]; }
    $giniSerie = array_slice($giniSerie, -30);

    /* Cuánto tarda la gente en hacer su primer envío */
    $primeros = $db->query(
        'SELECT u.id, u.creado, MIN(t.fecha) AS primera
           FROM usuarios u
           JOIN transacciones t ON t.origen_id = u.id
          GROUP BY u.id, u.creado'
    )->fetchAll();
    if ($primeros) {
        $horas = [];
        foreach ($primeros as $p) {
            $horas[] = (strtotime($p['primera']) - strtotime($p['creado'])) / 3600;
        }
        sort($horas);
        $m = count($horas);
        $mediana = ($m % 2) ? $horas[intdiv($m, 2)] : ($horas[$m/2 - 1] + $horas[$m/2]) / 2;
        $diasPrimerEnvio = $mediana < 48 ? round($mediana) . ' h' : round($mediana / 24) . ' días';
    }

    /* Acumuladores contra regaladores */
    $flujo = $db->query(
        'SELECT u.id,
                COALESCE((SELECT SUM(monto) FROM transacciones WHERE origen_id = u.id), 0) AS enviado,
                COALESCE((SELECT SUM(monto) FROM transacciones WHERE destino_id = u.id AND origen_id IS NOT NULL), 0) AS recibido
           FROM usuarios u'
    )->fetchAll();
    $gruposFlujo = ['Solo han recibido' => 0, 'Reciben más de lo que dan' => 0, 'Dan y reciben parejo' => 0, 'Dan más de lo que reciben' => 0, 'Solo han dado' => 0, 'No se han movido' => 0];
    foreach ($flujo as $f) {
        $e = (int) $f['enviado']; $r = (int) $f['recibido'];
        if ($e === 0 && $r === 0)      { $gruposFlujo['No se han movido']++; }
        elseif ($e === 0)              { $gruposFlujo['Solo han recibido']++; }
        elseif ($r === 0)              { $gruposFlujo['Solo han dado']++; }
        elseif ($e > $r * 1.25)        { $gruposFlujo['Dan más de lo que reciben']++; }
        elseif ($r > $e * 1.25)        { $gruposFlujo['Reciben más de lo que dan']++; }
        else                           { $gruposFlujo['Dan y reciben parejo']++; }
    }

    /* Reciprocidad: de los pares que intercambiaron, cuántos lo hicieron en ambos sentidos */
    $pares = $db->query(
        "SELECT DISTINCT origen_id, destino_id FROM transacciones $donde"
    )->fetchAll();
    $set = []; foreach ($pares as $p) { $set[$p['origen_id'] . '-' . $p['destino_id']] = true; }
    $vistos = []; $mutuos = 0;
    foreach ($pares as $p) {
        $a = (int) $p['origen_id']; $b = (int) $p['destino_id'];
        $llave = min($a, $b) . '-' . max($a, $b);
        if (isset($vistos[$llave])) { continue; }
        $vistos[$llave] = true;
        if (isset($set[$b . '-' . $a])) { $mutuos++; }
    }
    $paresUnicos  = count($vistos);
    $reciprocidad = $paresUnicos > 0 ? round($mutuos * 100 / $paresUnicos) : 0;

    /* Categorías */
    $porCategoria = $db->query(
        "SELECT categoria, COUNT(*) AS envios, SUM(monto) AS total, ROUND(AVG(monto)) AS promedio
           FROM transacciones $donde AND categoria IS NOT NULL
          GROUP BY categoria ORDER BY total DESC"
    )->fetchAll();
    foreach ($porCategoria as $c) { $totalCategorias += (int) $c['total']; }

    /* Palabras de los motivos */
    $notas = $db->query(
        "SELECT nota FROM transacciones $donde AND nota IS NOT NULL AND nota <> ''"
    )->fetchAll(PDO::FETCH_COLUMN);
    $vacias = ['para','por','que','con','una','uno','los','las','del','mas','más','me','le','la','el','de','en','un','y','a','su','al','se','es','lo','mi','te','ya','muy','ser','fue','son','como','esta','este','esto','porque','cuando','donde','pero','todo','toda','hace','hizo','dar','dio','les','nos','sus','ha','he','si','no','o','u','e','the','of'];
    $cuenta = [];
    $largos = [];
    foreach ($notas as $n) {
        $largos[] = mb_strlen($n);
        $limpio = mb_strtolower(strtr($n, 'áéíóúüñÁÉÍÓÚÜÑ', 'aeiouunAEIOUUN'));
        foreach (preg_split('/[^a-z0-9]+/u', $limpio, -1, PREG_SPLIT_NO_EMPTY) as $w) {
            if (mb_strlen($w) < 3 || in_array($w, $vacias, true)) { continue; }
            $cuenta[$w] = ($cuenta[$w] ?? 0) + 1;
        }
    }
    arsort($cuenta);
    $palabras = array_slice($cuenta, 0, 18, true);
    $largoMotivo = $largos ? round(array_sum($largos) / count($largos)) : 0;

    /* Últimos motivos */
    $razones = $db->query(
        "SELECT monto, categoria, nota, fecha FROM transacciones
          $donde AND nota IS NOT NULL AND nota <> ''
          ORDER BY id DESC LIMIT 25"
    )->fetchAll();

    /* Movimiento y altas de los últimos 14 días */
    $dias = $db->query(
        "SELECT DATE(fecha) AS dia, SUM(monto) AS total FROM transacciones
          $donde AND fecha >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
          GROUP BY DATE(fecha)"
    )->fetchAll();
    $mapa = []; foreach ($dias as $d) { $mapa[$d['dia']] = (int) $d['total']; }

    $altas = $db->query(
        "SELECT DATE(creado) AS dia, COUNT(*) AS n FROM usuarios
          WHERE creado >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
          GROUP BY DATE(creado)"
    )->fetchAll();
    $mapaAltas = []; foreach ($altas as $a) { $mapaAltas[$a['dia']] = (int) $a['n']; }

    for ($i = 13; $i >= 0; $i--) {
        $f = date('Y-m-d', strtotime("-$i day"));
        $serie[] = ['dia' => $f, 'total' => $mapa[$f] ?? 0];
        $altasSerie[] = ['dia' => $f, 'n' => $mapaAltas[$f] ?? 0];
    }
    $topDia = max(array_column($serie, 'total'));
}

$esquema  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$urlSitio = $esquema . '://' . ($_SERVER['HTTP_HOST'] ?? 'coin.correlavoz.org') . '/estadisticas.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tablero · Human Coins</title>
<meta name="theme-color" content="#EFE9DC">
<meta name="description" content="Qué hace la gente cuando todos empiezan con la misma cantidad de monedas. Datos anónimos y abiertos.">
<link rel="manifest" href="manifest.json">
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="icon" href="iconos/icono-192.png" type="image/png">
<link rel="apple-touch-icon" href="iconos/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="estilo.css">
</head>
<body>

<div class="page">

  <div class="brand">
    <div class="brand-name"><img class="coin-icon" src="moneda.webp" alt=""> Human Coins</div>
    <div class="nav-row">
      <?php if ($yo): ?>
        <a class="switch-link" href="panel.php">mi cartera</a>
        <a class="switch-link" href="salir.php">salir</a>
      <?php else: ?>
        <a class="switch-link" href="index.php">entrar</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="intro">
    <h1 class="serif">Qué hacemos con la misma riqueza inicial.</h1>
    <p>Esta página es pública y anónima. Nunca muestra quién le envió a quién: solo cuánto se mueve, para qué, y cómo se reparte.</p>
  </div>

  <div class="stat-grid">
    <div class="stat">
      <p class="stat-num"><?= number_format($personas, 0, ',', '.') ?></p>
      <p class="stat-label"><?= $personas === 1 ? 'persona' : 'personas' ?></p>
    </div>
    <div class="stat">
      <p class="stat-num"><?= number_format($enCirculacion, 0, ',', '.') ?></p>
      <p class="stat-label">monedas en circulación</p>
    </div>
    <div class="stat">
      <p class="stat-num"><?= number_format($numEnvios, 0, ',', '.') ?></p>
      <p class="stat-label"><?= $numEnvios === 1 ? 'envío' : 'envíos' ?></p>
    </div>
  </div>

<?php if (!$abierto): ?>

  <section class="block">
    <h2>Todavía no hay datos suficientes</h2>
    <p>Vuelve pronto: cuando haya más movimiento, aquí aparece qué estamos haciendo con las monedas.</p>
    <button type="button" class="primary-btn" id="compartir" data-url="<?= esc($urlSitio) ?>">Invitar a más gente</button>
    <div class="send-feedback" id="compartirAviso"></div>
  </section>

<?php else: ?>

  <div class="stat-grid" style="margin-top:16px">
    <div class="stat">
      <p class="stat-num"><?= $gini !== null ? number_format($gini, 3, ',', '.') : '—' ?></p>
      <p class="stat-label">desigualdad (Gini)</p>
    </div>
    <div class="stat">
      <p class="stat-num"><?= number_format($velocidad, 2, ',', '.') ?></p>
      <p class="stat-label">veces que cambió de manos cada moneda</p>
    </div>
    <div class="stat">
      <p class="stat-num"><?= $top10 ?>%</p>
      <p class="stat-label">lo tiene el 10% con más</p>
    </div>
    <div class="stat">
      <p class="stat-num"><?= number_format($movido, 0, ',', '.') ?></p>
      <p class="stat-label">monedas movidas</p>
    </div>
    <div class="stat">
      <p class="stat-num"><?= number_format($promedio, 0, ',', '.') ?></p>
      <p class="stat-label">promedio por envío</p>
    </div>
    <div class="stat">
      <p class="stat-num"><?= $personas > 0 ? round($activos * 100 / $personas) : 0 ?>%</p>
      <p class="stat-label">ha enviado alguna vez</p>
    </div>
  </div>

  <section class="block">
    <h2>Desigualdad</h2>
    <p class="hint" style="margin-top:0">El Gini va de 0 a 1. Todos arrancamos en 0, con exactamente lo mismo. Colombia como país está alrededor de 0,55.</p>
    <?php if (count($giniSerie) >= 3): ?>
      <div class="spark" style="margin-top:16px">
        <?php foreach ($giniSerie as $g): ?>
          <div class="spark-col" title="<?= date('d/m', strtotime($g['dia'])) ?>: <?= number_format($g['valor'], 3, ',', '.') ?>">
            <div class="spark-bar" style="height: <?= max(2, round($g['valor'] * 100)) ?>%"></div>
            <span class="spark-day"><?= date('d/m', strtotime($g['dia'])) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <ul class="pairs" style="margin-top:18px">
      <li><span>Siguen con sus <?= number_format(MONEDAS_INICIALES, 0, ',', '.') ?> intactas</span><strong><?= $intactos ?></strong></li>
      <li><span>Tienen más que al principio</span><strong><?= $arriba ?></strong></li>
      <li><span>Tienen menos</span><strong><?= $abajo ?></strong></li>
      <li><span>Saldo más alto</span><strong><?= number_format($saldoMax, 0, ',', '.') ?></strong></li>
      <li><span>Saldo más bajo</span><strong><?= number_format($saldoMin, 0, ',', '.') ?></strong></li>
    </ul>
  </section>

  <section class="block">
    <h2>Quién da y quién guarda</h2>
    <ul class="bars">
      <?php foreach ($gruposFlujo as $nombre => $n):
        $pct = $personas > 0 ? round($n * 100 / $personas) : 0; ?>
        <li>
          <div class="bar-head"><span><?= esc($nombre) ?></span><span class="bar-pct"><?= $n ?></span></div>
          <div class="bar-track"><div class="bar-fill" style="width: <?= $pct ?>%"></div></div>
        </li>
      <?php endforeach; ?>
    </ul>
    <ul class="pairs" style="margin-top:18px">
      <li><span>Parejas que intercambiaron en los dos sentidos</span><strong><?= $reciprocidad ?>%</strong></li>
      <li><span>Parejas distintas que se han enviado algo</span><strong><?= $paresUnicos ?></strong></li>
      <?php if ($diasPrimerEnvio): ?>
        <li><span>Tardan en hacer su primer envío</span><strong><?= esc($diasPrimerEnvio) ?></strong></li>
      <?php endif; ?>
    </ul>
  </section>

  <section class="block">
    <h2>En qué se usan</h2>
    <?php if (!$porCategoria): ?>
      <div class="ledger-empty">Todavía no hay envíos con categoría.</div>
    <?php else: ?>
      <ul class="bars">
        <?php foreach ($porCategoria as $c):
          $pct = $totalCategorias > 0 ? round((int) $c['total'] * 100 / $totalCategorias) : 0; ?>
          <li>
            <div class="bar-head">
              <span><?= esc($CATEGORIAS[$c['categoria']] ?? $c['categoria']) ?></span>
              <span class="bar-pct"><?= $pct ?>%</span>
            </div>
            <div class="bar-track"><div class="bar-fill" style="width: <?= $pct ?>%"></div></div>
            <p class="bar-note"><?= number_format((int) $c['total'], 0, ',', '.') ?> monedas en <?= (int) $c['envios'] ?> <?= (int) $c['envios'] === 1 ? 'envío' : 'envíos' ?> · típico <?= number_format((int) $c['promedio'], 0, ',', '.') ?></p>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="block">
    <h2>Las palabras que más se repiten</h2>
    <?php if (!$palabras): ?>
      <div class="ledger-empty">Todavía no hay motivos escritos.</div>
    <?php else: ?>
      <div class="nube">
        <?php $maxP = max($palabras); foreach ($palabras as $w => $n): ?>
          <span class="nube-palabra" style="font-size: <?= round(0.8 + ($n / $maxP) * 1.1, 2) ?>rem" title="<?= $n ?> veces"><?= esc($w) ?></span>
        <?php endforeach; ?>
      </div>
      <p class="bar-note">Un motivo mide en promedio <?= $largoMotivo ?> caracteres.</p>
    <?php endif; ?>
  </section>

  <section class="block">
    <h2>Movimiento de los últimos 14 días</h2>
    <?php if ($topDia === 0): ?>
      <div class="ledger-empty">Todavía no hay movimiento.</div>
    <?php else: ?>
      <div class="spark">
        <?php foreach ($serie as $d): ?>
          <div class="spark-col" title="<?= date('d/m', strtotime($d['dia'])) ?>: <?= $d['total'] ?>">
            <div class="spark-bar" style="height: <?= max(2, round($d['total'] * 100 / $topDia)) ?>%"></div>
            <span class="spark-day"><?= date('d/m', strtotime($d['dia'])) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="block">
    <h2>Por qué se mueven</h2>
    <?php if (!$razones): ?>
      <div class="ledger-empty">Todavía no hay motivos registrados.</div>
    <?php else: ?>
      <ul class="why-list">
        <?php foreach ($razones as $m): ?>
          <li>
            <p class="why-text"><?= esc($m['nota']) ?></p>
            <p class="why-meta">
              <?= number_format((int) $m['monto'], 0, ',', '.') ?> monedas<?php if (!empty($m['categoria']) && isset($CATEGORIAS[$m['categoria']])): ?> &middot; <?= esc($CATEGORIAS[$m['categoria']]) ?><?php endif; ?> &middot; <?= date('d/m/Y', strtotime($m['fecha'])) ?>
            </p>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <button type="button" class="primary-btn" id="compartir" data-url="<?= esc($urlSitio) ?>">Compartir el tablero</button>
  <div class="send-feedback" id="compartirAviso"></div>

<?php endif; ?>

  <p class="foot-note">
    <a href="index.php">Ir a Human Coins</a> &middot; <a href="privacidad.php">Privacidad</a>
  </p>

</div>

<script>
(function () {
  var b = document.getElementById('compartir');
  if (!b) { return; }
  var aviso = document.getElementById('compartirAviso');
  var url = b.dataset.url;
  var texto = 'Todos empezamos con las mismas monedas. Mira qué estamos haciendo con ellas.';
  b.addEventListener('click', function () {
    if (navigator.share) {
      navigator.share({ title: 'Human Coins', text: texto, url: url }).catch(function () {});
      return;
    }
    if (navigator.clipboard) {
      navigator.clipboard.writeText(texto + ' ' + url).then(function () {
        aviso.textContent = 'Enlace copiado. Pégalo donde quieras.';
        aviso.className = 'send-feedback ok';
      }).catch(function () { aviso.textContent = url; });
      return;
    }
    aviso.textContent = url;
  });
})();
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('sw.js').catch(function () {});
  });
}
</script>

</body>
</html>
