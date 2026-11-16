<?php
require __DIR__ . '/config.php';
$yo = usuario_actual($db);

/* Solo transferencias entre personas: la asignación inicial no cuenta */
$donde = 'WHERE origen_id IS NOT NULL';

$personas      = (int) $db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
$enCirculacion = (int) $db->query('SELECT COALESCE(SUM(saldo), 0) FROM usuarios')->fetchColumn();
$numEnvios     = (int) $db->query("SELECT COUNT(*) FROM transacciones $donde")->fetchColumn();
$movido        = (int) $db->query("SELECT COALESCE(SUM(monto), 0) FROM transacciones $donde")->fetchColumn();
$promedio      = $numEnvios > 0 ? round($movido / $numEnvios) : 0;

/* Cuánta gente ha enviado al menos una vez */
$activos = (int) $db->query("SELECT COUNT(DISTINCT origen_id) FROM transacciones $donde")->fetchColumn();

/* Reparto actual: quién quedó por encima y por debajo de lo que arrancó */
$arriba = (int) $db->query('SELECT COUNT(*) FROM usuarios WHERE saldo > ' . MONEDAS_INICIALES)->fetchColumn();
$abajo  = (int) $db->query('SELECT COUNT(*) FROM usuarios WHERE saldo < ' . MONEDAS_INICIALES)->fetchColumn();
$igual  = $personas - $arriba - $abajo;

$saldoMax = (int) $db->query('SELECT COALESCE(MAX(saldo), 0) FROM usuarios')->fetchColumn();
$saldoMin = (int) $db->query('SELECT COALESCE(MIN(saldo), 0) FROM usuarios')->fetchColumn();

/* Para qué se usan las monedas */
$porCategoria = $db->query(
    "SELECT categoria, COUNT(*) AS envios, SUM(monto) AS total
       FROM transacciones
      $donde AND categoria IS NOT NULL
      GROUP BY categoria
      ORDER BY total DESC"
)->fetchAll();

$totalCategorias = 0;
foreach ($porCategoria as $c) {
    $totalCategorias += (int) $c['total'];
}

/* Últimos motivos, sin decir quién */
$motivos = $db->query(
    "SELECT monto, categoria, nota, fecha
       FROM transacciones
      $donde AND nota IS NOT NULL AND nota <> ''
      ORDER BY id DESC
      LIMIT 25"
)->fetchAll();

/* Actividad de los últimos 14 días */
$dias = $db->query(
    "SELECT DATE(fecha) AS dia, COUNT(*) AS envios, SUM(monto) AS total
       FROM transacciones
      $donde AND fecha >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
      GROUP BY DATE(fecha)
      ORDER BY dia ASC"
)->fetchAll();

$porDia = [];
foreach ($dias as $d) {
    $porDia[$d['dia']] = (int) $d['total'];
}
$serie = [];
for ($i = 13; $i >= 0; $i--) {
    $f = date('Y-m-d', strtotime("-$i day"));
    $serie[] = ['dia' => $f, 'total' => $porDia[$f] ?? 0];
}
$topDia = max(array_column($serie, 'total'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tablero · Human Coins</title>
<meta name="theme-color" content="#EFE9DC">
<link rel="manifest" href="manifest.json">
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
    <h1 class="serif">Qué está pasando con las monedas.</h1>
    <p>Todo lo de esta página es anónimo. No se ve quién le envió a quién, solo cuánto se mueve y para qué.</p>
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
    <h2>En qué se usan</h2>
    <?php if (!$porCategoria): ?>
      <div class="ledger-empty">Todavía no hay envíos.</div>
    <?php else: ?>
      <ul class="bars">
        <?php foreach ($porCategoria as $c):
          $pct = $totalCategorias > 0 ? round((int) $c['total'] * 100 / $totalCategorias) : 0;
        ?>
          <li>
            <div class="bar-head">
              <span><?= esc($CATEGORIAS[$c['categoria']] ?? $c['categoria']) ?></span>
              <span class="bar-pct"><?= $pct ?>%</span>
            </div>
            <div class="bar-track"><div class="bar-fill" style="width: <?= $pct ?>%"></div></div>
            <p class="bar-note"><?= number_format((int) $c['total'], 0, ',', '.') ?> monedas en <?= (int) $c['envios'] ?> <?= (int) $c['envios'] === 1 ? 'envío' : 'envíos' ?></p>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="block">
    <h2>Cómo quedó el reparto</h2>
    <ul class="pairs">
      <li><span>Arrancaron todos con</span><strong><?= number_format(MONEDAS_INICIALES, 0, ',', '.') ?></strong></li>
      <li><span>Tienen más que al principio</span><strong><?= $arriba ?></strong></li>
      <li><span>Tienen menos</span><strong><?= $abajo ?></strong></li>
      <li><span>Siguen igual</span><strong><?= $igual ?></strong></li>
      <li><span>Saldo más alto</span><strong><?= number_format($saldoMax, 0, ',', '.') ?></strong></li>
      <li><span>Saldo más bajo</span><strong><?= number_format($saldoMin, 0, ',', '.') ?></strong></li>
    </ul>
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
    <?php if (!$motivos): ?>
      <div class="ledger-empty">Todavía no hay motivos registrados.</div>
    <?php else: ?>
      <ul class="why-list">
        <?php foreach ($motivos as $m): ?>
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

  <p class="hint">
    Las monedas no tienen valor monetario. Nadie puede crear monedas nuevas: solo entran al mundo cuando entra una persona más.
  </p>

</div>

<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('sw.js').catch(function () {});
  });
}
</script>

</body>
</html>
