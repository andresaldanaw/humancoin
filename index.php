<?php
require __DIR__ . '/config.php';

if (usuario_actual($db)) {
    header('Location: panel.php');
    exit;
}

$error   = '';
$pestana = 'entrar';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && token_valido()) {

    $accion = $_POST['accion'] ?? '';

    /* ---------- Crear cuenta ---------- */
    if ($accion === 'registrar') {
        $pestana = 'registrar';
        $nombre  = trim($_POST['nombre'] ?? '');
        $usuario = strtolower(trim($_POST['usuario'] ?? ''));
        $pin     = preg_replace('/\D/', '', $_POST['pin'] ?? '');
        $pin2    = preg_replace('/\D/', '', $_POST['pin2'] ?? '');

        if ($nombre === '' || $usuario === '' || $pin === '') {
            $error = 'Completa todos los campos.';
        } elseif (!preg_match('/^[a-z0-9_]{3,20}$/', $usuario)) {
            $error = 'El usuario debe tener entre 3 y 20 caracteres: letras, números o guion bajo.';
        } elseif (!preg_match('/^\d{4}$/', $pin)) {
            $error = 'El PIN debe ser de 4 números.';
        } elseif ($pin !== $pin2) {
            $error = 'Los dos PIN no coinciden.';
        } else {
            $ip = ip_visitante();

            $yaUso = $db->prepare('SELECT usuario FROM usuarios WHERE ip = ? LIMIT 1');
            $yaUso->execute([$ip]);
            $cuentaPrevia = $yaUso->fetch();

            $q = $db->prepare('SELECT id FROM usuarios WHERE usuario = ?');
            $q->execute([$usuario]);

            if ($cuentaPrevia) {
                $error = 'Desde esta conexión ya se creó la cuenta "' . esc($cuentaPrevia['usuario']) . '". Cada persona participa con una sola cuenta.';
            } elseif ($q->fetch()) {
                $error = 'Ese usuario ya está tomado. Elige otro.';
            } else {
                $db->beginTransaction();

                $ins = $db->prepare(
                    'INSERT INTO usuarios (usuario, nombre, pin, saldo, ip, creado)
                     VALUES (?, ?, ?, ?, ?, NOW())'
                );
                $ins->execute([
                    $usuario,
                    $nombre,
                    password_hash($pin, PASSWORD_DEFAULT),
                    MONEDAS_INICIALES,
                    $ip,
                ]);
                $nuevoId = (int) $db->lastInsertId();

                $mov = $db->prepare(
                    'INSERT INTO transacciones (origen_id, destino_id, monto, nota, fecha)
                     VALUES (NULL, ?, ?, ?, NOW())'
                );
                $mov->execute([$nuevoId, MONEDAS_INICIALES, 'Asignación inicial']);

                $db->commit();

                session_regenerate_id(true);
                $_SESSION['uid'] = $nuevoId;
                header('Location: panel.php');
                exit;
            }
        }
    }

    /* ---------- Entrar ---------- */
    if ($accion === 'entrar') {
        $usuario = strtolower(trim($_POST['usuario'] ?? ''));
        $pin     = preg_replace('/\D/', '', $_POST['pin'] ?? '');

        $q = $db->prepare('SELECT * FROM usuarios WHERE usuario = ?');
        $q->execute([$usuario]);
        $u = $q->fetch();

        if ($u && password_verify($pin, $u['pin'])) {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) $u['id'];
            header('Location: panel.php');
            exit;
        }
        $error = 'Usuario o PIN incorrectos.';
    }
}

$total = (int) $db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();

$esquema  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$urlSitio = $esquema . '://' . ($_SERVER['HTTP_HOST'] ?? 'coin.correlavoz.org') . '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Human Coins</title>
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
  </div>

  <div class="intro">
    <img class="hero-coin" src="moneda.webp" alt="Human Coin">
    <h1 class="serif">Cada persona recibe <?= number_format(MONEDAS_INICIALES, 0, ',', '.') ?> monedas.</h1>

    <button type="button" class="explica-toggle" id="explicaToggle" hidden aria-expanded="false" aria-controls="explica">¿Qué es esto?</button>

    <div id="explica" class="explica">
      <p>Human Coins es un juego. Cada persona recibe <?= number_format(MONEDAS_INICIALES, 0, ',', '.') ?> monedas, exactamente las mismas que todos los demás, y no se pueden crear más allá de los participantes. La idea es intercambiarlas por lo que quieran, en el mundo real o digital. Lo que queremos ver es qué hacemos con una riqueza convencional (como lo es todo el dinero a fin de cuentas).</p>
      <p>¿Quiénes acumulan? ¿Quiénes regalan todo el primer día? ¿Favores, deudas, mercados? Eso es lo que queremos ver. Pueden invitar a quien quieran.</p>
    </div>

    <button type="button" class="primary-btn" id="compartir" data-url="<?= esc($urlSitio) ?>">Invitar a alguien</button>
    <div class="send-feedback" id="compartirAviso"></div>

    <p class="privacidad explica-extra">Los movimientos se muestran de forma anónima en el tablero: se ve cuánto se mueve y para qué, nunca quién le envió a quién. Lo único que guardamos aparte es la dirección IP desde la que se crea cada cuenta, y solo para que una misma persona no abra muchas carteras.</p>
  </div>

  <div class="entry-card">

    <div class="tabs">
      <button type="button" class="tab <?= $pestana === 'entrar' ? 'on' : '' ?>" data-pane="pane-entrar">Entrar</button>
      <button type="button" class="tab <?= $pestana === 'registrar' ? 'on' : '' ?>" data-pane="pane-crear">Crear cuenta</button>
    </div>

    <?php if ($error): ?>
      <div class="error-text"><?= esc($error) ?></div>
    <?php endif; ?>

    <form method="post" class="pane <?= $pestana === 'entrar' ? 'on' : '' ?>" id="pane-entrar">
      <input type="hidden" name="token" value="<?= esc($_SESSION['token']) ?>">
      <input type="hidden" name="accion" value="entrar">

      <label class="field-label" for="e-usuario">Tu usuario</label>
      <input class="text-input" id="e-usuario" name="usuario" type="text" autocomplete="username" maxlength="20" required>

      <label class="field-label" for="e-pin">Tu PIN</label>
      <input class="text-input pin-input" id="e-pin" name="pin" type="password" inputmode="numeric" pattern="\d{4}" maxlength="4" autocomplete="current-password" required>

      <button class="primary-btn" type="submit">Entrar</button>
    </form>

    <form method="post" class="pane <?= $pestana === 'registrar' ? 'on' : '' ?>" id="pane-crear">
      <input type="hidden" name="token" value="<?= esc($_SESSION['token']) ?>">
      <input type="hidden" name="accion" value="registrar">

      <label class="field-label" for="r-nombre">Tu nombre</label>
      <input class="text-input" id="r-nombre" name="nombre" type="text" placeholder="Ej: Andrés" maxlength="80" autocomplete="name" required>

      <label class="field-label" for="r-usuario">Tu usuario <span class="hint-inline">así te encuentran para enviarte monedas</span></label>
      <input class="text-input" id="r-usuario" name="usuario" type="text" maxlength="20" pattern="[A-Za-z0-9_]{3,20}" autocomplete="username" required>

      <label class="field-label" for="r-pin">Un PIN de 4 números</label>
      <input class="text-input pin-input" id="r-pin" name="pin" type="password" inputmode="numeric" pattern="\d{4}" maxlength="4" autocomplete="new-password" required>

      <label class="field-label" for="r-pin2">Repite el PIN</label>
      <input class="text-input pin-input" id="r-pin2" name="pin2" type="password" inputmode="numeric" pattern="\d{4}" maxlength="4" autocomplete="new-password" required>

      <button class="primary-btn" type="submit">Crear cuenta y recibir <?= number_format(MONEDAS_INICIALES, 0, ',', '.') ?></button>
    </form>

  </div>

  <p class="foot-note">
    <a href="estadisticas.php">Ver el tablero del experimento</a> &middot; <a href="privacidad.php">Privacidad</a>
  </p>

</div>

<script>
document.querySelectorAll('.tab').forEach(function (b) {
  b.addEventListener('click', function () {
    document.querySelectorAll('.tab').forEach(function (x) { x.classList.remove('on'); });
    document.querySelectorAll('.pane').forEach(function (x) { x.classList.remove('on'); });
    b.classList.add('on');
    document.getElementById(b.dataset.pane).classList.add('on');
  });
});
(function () {
  var caja = document.getElementById('explica');
  var btn = document.getElementById('explicaToggle');
  var extra = document.querySelector('.explica-extra');
  if (!caja || !btn) { return; }

  var visto = false;
  try { visto = localStorage.getItem('hc_intro_visto') === '1'; } catch (e) {}

  function plegar(cerrado) {
    caja.classList.toggle('cerrada', cerrado);
    if (extra) { extra.classList.toggle('cerrada', cerrado); }
    btn.setAttribute('aria-expanded', cerrado ? 'false' : 'true');
    btn.textContent = cerrado ? '¿Qué es esto?' : 'Ocultar';
  }

  btn.hidden = false;
  plegar(visto);

  btn.addEventListener('click', function () {
    plegar(!caja.classList.contains('cerrada'));
  });

  try { localStorage.setItem('hc_intro_visto', '1'); } catch (e) {}
})();

(function () {
  var b = document.getElementById('compartir');
  if (!b) { return; }
  var aviso = document.getElementById('compartirAviso');
  var url = b.dataset.url;
  var texto = 'Todos empezamos con las mismas monedas. Entra a Human Coins y mira qué hacemos con ellas.';

  b.addEventListener('click', function () {
    if (navigator.share) {
      navigator.share({ title: 'Human Coins', text: texto, url: url }).catch(function () {});
      return;
    }
    if (navigator.clipboard) {
      navigator.clipboard.writeText(texto + ' ' + url).then(function () {
        aviso.textContent = 'Enlace copiado. Pégalo donde quieras.';
        aviso.className = 'send-feedback ok';
      }).catch(function () {
        aviso.textContent = url;
        aviso.className = 'send-feedback';
      });
      return;
    }
    aviso.textContent = url;
    aviso.className = 'send-feedback';
  });
})();

document.querySelectorAll('.pin-input').forEach(function (c) {
  c.addEventListener('input', function () {
    c.value = c.value.replace(/\D/g, '').slice(0, 4);
  });
});
</script>

<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('sw.js').catch(function () {});
  });
}
</script>

</body>
</html>
