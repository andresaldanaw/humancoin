<?php
require __DIR__ . '/config.php';
$yo = exigir_sesion($db);

$error = '';
$exito = '';
$destinoPrevio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && token_valido() && ($_POST['accion'] ?? '') === 'enviar') {

    $destino   = strtolower(trim($_POST['destino'] ?? ''));
    $destinoPrevio = $destino;
    $monto     = (int) ($_POST['monto'] ?? 0);
    $categoria = trim($_POST['categoria'] ?? '');
    $nota      = trim($_POST['nota'] ?? '');
    if (mb_strlen($nota) > 140) {
        $nota = mb_substr($nota, 0, 140);
    }

    if ($destino === '' || $monto <= 0) {
        $error = 'Escribe a quién le envías y cuántas monedas.';
    } elseif (!isset($CATEGORIAS[$categoria])) {
        $error = 'Elige para qué son estas monedas.';
    } elseif ($nota === '') {
        $error = 'Cuenta en una línea por qué le envías estas monedas.';
    } elseif ($destino === $yo['usuario']) {
        $error = 'No puedes enviarte monedas a ti mismo.';
    } else {
        $q = $db->prepare('SELECT id, nombre, usuario FROM usuarios WHERE usuario = ?');
        $q->execute([$destino]);
        $otro = $q->fetch();

        if (!$otro) {
            $error = 'No existe ninguna cuenta con el usuario "' . esc($destino) . '".';
        } else {
            $db->beginTransaction();

            /* Vuelve a leer el saldo dentro de la transacción */
            $s = $db->prepare('SELECT saldo FROM usuarios WHERE id = ? FOR UPDATE');
            $s->execute([$yo['id']]);
            $saldoReal = (int) $s->fetchColumn();

            if ($monto > $saldoReal) {
                $db->rollBack();
                $error = 'No tienes suficientes monedas. Tu saldo es ' . $saldoReal . '.';
            } else {
                $db->prepare('UPDATE usuarios SET saldo = saldo - ? WHERE id = ?')
                   ->execute([$monto, $yo['id']]);
                $db->prepare('UPDATE usuarios SET saldo = saldo + ? WHERE id = ?')
                   ->execute([$monto, $otro['id']]);
                $db->prepare(
                    'INSERT INTO transacciones (origen_id, destino_id, monto, categoria, nota, fecha)
                     VALUES (?, ?, ?, ?, ?, NOW())'
                )->execute([$yo['id'], $otro['id'], $monto, $categoria, $nota]);

                $db->commit();

                $destinoPrevio = '';
                $exito = 'Enviaste ' . $monto . ' ' . ($monto === 1 ? 'moneda' : 'monedas') . ' a ' . $otro['nombre'] . '.';
                $yo = usuario_actual($db);
            }
        }
    }
}

/* Movimientos */
$mov = $db->prepare(
    'SELECT t.*, o.nombre AS origen_nombre, o.usuario AS origen_usuario,
                 d.nombre AS destino_nombre, d.usuario AS destino_usuario
       FROM transacciones t
       LEFT JOIN usuarios o ON o.id = t.origen_id
       LEFT JOIN usuarios d ON d.id = t.destino_id
      WHERE t.origen_id = :origen OR t.destino_id = :destino
      ORDER BY t.id DESC
      LIMIT 40'
);
$mov->execute([':origen' => $yo['id'], ':destino' => $yo['id']]);
$movimientos = $mov->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi cartera · Human Coins</title>
<meta name="theme-color" content="#EFE9DC">
<link rel="manifest" href="manifest.json">
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="icon" href="iconos/icono-192.png" type="image/png">
<link rel="apple-touch-icon" href="iconos/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Human Coins">
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
      <a class="switch-link" href="estadisticas.php">tablero</a>
      <a class="switch-link" href="salir.php">salir</a>
    </div>
  </div>

  <p class="greeting">Hola, <?= esc($yo['nombre']) ?> &middot; @<?= esc($yo['usuario']) ?></p>

  <div class="balance-block">
    <div class="balance-number">
      <img class="balance-coin" src="moneda.webp" alt="">
      <?= number_format((int) $yo['saldo'], 0, ',', '.') ?>
      <span class="unit"><?= (int) $yo['saldo'] === 1 ? 'human coin' : 'human coins' ?></span>
    </div>
  </div>

  <section class="block">
    <h2>Enviar monedas</h2>

    <?php if ($error): ?>
      <div class="send-feedback err"><?= $error ?></div>
    <?php endif; ?>
    <?php if ($exito): ?>
      <div class="send-feedback ok"><?= esc($exito) ?></div>
    <?php endif; ?>

    <form method="post" class="send-form">
      <input type="hidden" name="token" value="<?= esc($_SESSION['token']) ?>">
      <input type="hidden" name="accion" value="enviar">

      <div>
        <label class="field-label" for="destino">Para quién <span class="hint-inline">escribe su usuario</span></label>
        <input class="text-input" id="destino" name="destino" type="text" maxlength="20"
               autocomplete="off" autocapitalize="none" spellcheck="false"
               placeholder="usuario" value="<?= esc($destinoPrevio) ?>" required>
      </div>

      <div>
        <label class="field-label" for="monto">Cuántas</label>
        <div class="step-row">
          <button type="button" class="step-btn" data-step="-50" aria-label="Quitar 50">&minus;50</button>
          <input class="text-input" id="monto" name="monto" type="number" min="1" step="1" max="<?= (int) $yo['saldo'] ?>" placeholder="Cantidad" required>
          <button type="button" class="step-btn" data-step="50" aria-label="Agregar 50">+50</button>
        </div>
      </div>

      <div>
        <label class="field-label" for="categoria">Para qué</label>
        <select class="text-input" id="categoria" name="categoria" required>
          <option value="">Elige una opción</option>
          <?php foreach ($CATEGORIAS as $clave => $etiqueta): ?>
            <option value="<?= esc($clave) ?>"><?= esc($etiqueta) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="field-label" for="nota">En una línea, por qué</label>
        <input class="text-input" id="nota" name="nota" type="text" maxlength="140" placeholder="Me cocinó el almuerzo del domingo" required>
      </div>

      <button class="primary-btn" type="submit" <?= (int) $yo['saldo'] === 0 ? 'disabled' : '' ?>>Enviar</button>
      <?php if ((int) $yo['saldo'] === 0): ?>
        <div class="send-feedback">Te quedaste sin monedas. Ahora solo puedes recibir.</div>
      <?php endif; ?>
    </form>
  </section>

  <section class="block">
    <h2>Movimientos</h2>
    <?php if (!$movimientos): ?>
      <div class="ledger-empty">Todavía no tienes movimientos.</div>
    <?php else: ?>
      <?php foreach ($movimientos as $m):
        $entra = ((int) $m['destino_id'] === (int) $yo['id']);
        $emision = ($m['origen_id'] === null);
      ?>
        <div class="ledger-row">
          <div class="ledger-desc">
            <?php if ($emision): ?>
              Asignación inicial
            <?php elseif ($entra): ?>
              De <?= esc($m['origen_nombre']) ?>
            <?php else: ?>
              Para <?= esc($m['destino_nombre']) ?>
            <?php endif; ?>
            <?php if (!empty($m['categoria']) && isset($CATEGORIAS[$m['categoria']])): ?>
              <br><span class="ledger-tag"><?= esc($CATEGORIAS[$m['categoria']]) ?></span>
            <?php endif; ?>
            <?php if (!$emision && !empty($m['nota'])): ?>
              <span class="ledger-why"><?= esc($m['nota']) ?></span>
            <?php endif; ?>
            <span class="ledger-when"><?= date('d/m/Y · H:i', strtotime($m['fecha'])) ?></span>
          </div>
          <div class="ledger-amount <?= $entra ? 'plus' : 'minus' ?>"><?= $entra ? '+' : '−' ?><?= number_format((int) $m['monto'], 0, ',', '.') ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <p class="foot-note">
    <a href="estadisticas.php">Tablero</a> &middot; <a href="privacidad.php">Privacidad</a>
  </p>

</div>

<script>
(function () {
  var monto = document.getElementById('monto');
  if (monto) {
    var tope = parseInt(monto.getAttribute('max'), 10) || 0;
    document.querySelectorAll('.step-btn').forEach(function (b) {
      b.addEventListener('click', function () {
        var a = parseInt(monto.value, 10);
        if (isNaN(a)) { a = 0; }
        var v = a + parseInt(b.dataset.step, 10);
        if (v < 0) { v = 0; }
        if (tope && v > tope) { v = tope; }
        monto.value = v > 0 ? v : '';
      });
    });
  }
})();
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('sw.js').catch(function () {});
  });
}
</script>

</body>
</html>
