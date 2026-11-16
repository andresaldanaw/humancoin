<?php
/* ---------------------------------------------------------
   Human Coins — instalador
   Súbelo a la raíz del sitio y ábrelo una sola vez en el navegador.
   Crea la base de datos, el usuario, credenciales.php y las tablas.
   Al terminar, bórralo.
--------------------------------------------------------- */

session_start();

$RUTA_CRED = __DIR__ . '/credenciales.php';

/* Si ya está instalado, no se puede volver a correr */
if (file_exists($RUTA_CRED) && empty($_GET['forzar'])) {
    $yaInstalado = true;
}

function esc($t) {
    return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
}

/* ---------- cPanel LiveAPI (para crear la base de datos solo) ---------- */

function cpanel_disponible() {
    return file_exists('/usr/local/cpanel/php/cpanel.php');
}

function cpanel_crear($nombreCorto, $usuarioCorto, $clave) {
    require_once '/usr/local/cpanel/php/cpanel.php';
    $cp = new CPANEL();

    $prefijo = get_current_user() . '_';
    $baseCompleta    = $prefijo . $nombreCorto;
    $usuarioCompleto = $prefijo . $usuarioCorto;

    $pasos = [];

    $r = $cp->uapi('Mysql', 'create_database', ['name' => $baseCompleta]);
    $pasos[] = ['Crear la base de datos ' . $baseCompleta, empty($r['cpanelresult']['errors'])];

    $r = $cp->uapi('Mysql', 'create_user', ['name' => $usuarioCompleto, 'password' => $clave]);
    $pasos[] = ['Crear el usuario ' . $usuarioCompleto, empty($r['cpanelresult']['errors'])];

    $r = $cp->uapi('Mysql', 'set_privileges_on_database', [
        'user'       => $usuarioCompleto,
        'database'   => $baseCompleta,
        'privileges' => 'ALL PRIVILEGES',
    ]);
    $pasos[] = ['Dar permisos al usuario sobre la base', empty($r['cpanelresult']['errors'])];

    return [$baseCompleta, $usuarioCompleto, $pasos];
}

/* ---------- Crear las tablas ---------- */

function crear_tablas($host, $base, $usuario, $clave) {
    $db = new PDO(
        "mysql:host=$host;dbname=$base;charset=utf8mb4",
        $usuario,
        $clave,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $db->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario VARCHAR(20) NOT NULL UNIQUE,
            nombre VARCHAR(80) NOT NULL,
            pin VARCHAR(255) NOT NULL,
            saldo INT NOT NULL DEFAULT 0,
            ip VARCHAR(45) NULL,
            creado DATETIME NOT NULL,
            INDEX (ip)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS transacciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            origen_id INT NULL,
            destino_id INT NOT NULL,
            monto INT NOT NULL,
            categoria VARCHAR(40) NULL,
            nota VARCHAR(140) NULL,
            fecha DATETIME NOT NULL,
            INDEX (origen_id),
            INDEX (destino_id),
            INDEX (categoria)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    return $db;
}

/* ---------- Escribir credenciales.php ---------- */

function escribir_credenciales($ruta, $host, $base, $usuario, $clave) {
    $p = function ($v) { return var_export((string) $v, true); };

    $contenido = "<?php\n"
        . "/* Generado por el instalador de Human Coins. */\n\n"
        . '$DB_HOST = ' . $p($host) . ";\n"
        . '$DB_NAME = ' . $p($base) . ";\n"
        . '$DB_USER = ' . $p($usuario) . ";\n"
        . '$DB_PASS = ' . $p($clave) . ";\n";

    if (file_put_contents($ruta, $contenido) === false) {
        return false;
    }
    @chmod($ruta, 0640);
    return true;
}

/* ---------- Proceso ---------- */

$error   = '';
$listo   = false;
$pasos   = [];
$resumen = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($yaInstalado)) {

    $modo    = $_POST['modo'] ?? 'manual';
    $host    = trim($_POST['host'] ?? 'localhost');
    $base    = trim($_POST['base'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $clave   = $_POST['clave'] ?? '';

    try {
        if ($base === '' || $usuario === '' || $clave === '') {
            throw new Exception('Completa todos los campos.');
        }

        if ($modo === 'auto') {
            if (!cpanel_disponible()) {
                throw new Exception('Este servidor no expone la API de cPanel desde PHP. Usa el modo manual: crea la base en cPanel y vuelve aquí.');
            }
            list($base, $usuario, $pasos) = cpanel_crear($base, $usuario, $clave);
            foreach ($pasos as $p) {
                if (!$p[1]) {
                    throw new Exception('Falló este paso en cPanel: ' . $p[0] . '. Puede que ese nombre ya exista. Revisa en cPanel y usa el modo manual.');
                }
            }
        }

        crear_tablas($host, $base, $usuario, $clave);

        if (!escribir_credenciales($RUTA_CRED, $host, $base, $usuario, $clave)) {
            throw new Exception('No pude escribir credenciales.php. Dale permiso de escritura a la carpeta y vuelve a intentar.');
        }

        $listo = true;
        $resumen = [
            'Servidor'          => $host,
            'Base de datos'     => $base,
            'Usuario'           => $usuario,
            'Tablas'            => 'usuarios, transacciones',
            'Monedas de inicio' => '1.000 por persona',
        ];

    } catch (PDOException $e) {
        $error = 'No pude conectarme a la base de datos. Revisa el nombre, el usuario y la contraseña, y que el usuario tenga todos los permisos sobre esa base.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instalar Human Coins</title>
<meta name="robots" content="noindex, nofollow">
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

<?php if (!empty($yaInstalado)): ?>

  <div class="intro">
    <h1 class="serif">Ya está instalado.</h1>
    <p>El archivo credenciales.php existe, así que el sitio ya quedó configurado. Por seguridad no vuelvo a correr el instalador.</p>
    <p><strong>Borra instalar.php del servidor</strong> y entra por la puerta principal.</p>
  </div>

  <a class="primary-btn" href="index.php" style="display:block;text-align:center;text-decoration:none">Ir al sitio</a>

<?php elseif ($listo): ?>

  <div class="intro">
    <h1 class="serif">Listo, quedó instalado.</h1>
    <p>La base de datos responde, las tablas están creadas y credenciales.php quedó escrito.</p>
  </div>

  <?php if ($pasos): ?>
    <section class="block">
      <h2>Lo que hice en cPanel</h2>
      <ul class="pairs">
        <?php foreach ($pasos as $p): ?>
          <li><span><?= esc($p[0]) ?></span><strong>hecho</strong></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <section class="block">
    <h2>Configuración guardada</h2>
    <ul class="pairs">
      <?php foreach ($resumen as $k => $v): ?>
        <li><span><?= esc($k) ?></span><strong><?= esc($v) ?></strong></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="block">
    <h2>Falta un paso tuyo</h2>
    <p><strong>Borra instalar.php del servidor ahora.</strong> Mientras siga ahí, cualquiera que encuentre la dirección puede ver esta pantalla.</p>
  </section>

  <a class="primary-btn" href="index.php" style="display:block;text-align:center;text-decoration:none;margin-top:20px">Abrir Human Coins</a>

<?php else: ?>

  <div class="intro">
    <h1 class="serif">Instalar Human Coins</h1>
    <p>Esto crea las tablas y deja escrito el archivo de credenciales. Se corre una sola vez.</p>
  </div>

  <?php if ($error): ?>
    <div class="error-text" style="margin-bottom:18px"><?= esc($error) ?></div>
  <?php endif; ?>

  <div class="entry-card">
    <form method="post">

      <?php if (cpanel_disponible()): ?>
        <label class="field-label" for="modo">Cómo quieres hacerlo</label>
        <select class="text-input" id="modo" name="modo">
          <option value="auto">Crear la base de datos por mí (API de cPanel)</option>
          <option value="manual">Ya creé la base en cPanel</option>
        </select>
        <p class="hint" style="margin-top:10px">En modo automático escribe los nombres <em>cortos</em>, sin el prefijo de tu cuenta: cPanel se lo agrega solo.</p>
      <?php else: ?>
        <input type="hidden" name="modo" value="manual">
        <p class="hint" style="margin:0 0 18px">Este servidor no permite crear la base desde PHP, así que créala en cPanel y escribe aquí sus datos.</p>
      <?php endif; ?>

      <label class="field-label" for="host">Servidor</label>
      <input class="text-input" id="host" name="host" type="text" value="localhost" required>

      <label class="field-label" for="base">Base de datos</label>
      <input class="text-input" id="base" name="base" type="text" placeholder="humancoins" required>

      <label class="field-label" for="usuario">Usuario</label>
      <input class="text-input" id="usuario" name="usuario" type="text" placeholder="humancoins" required>

      <label class="field-label" for="clave">Contraseña</label>
      <input class="text-input" id="clave" name="clave" type="text" required>

      <button class="primary-btn" type="submit">Instalar</button>
    </form>
  </div>

  <p class="hint">Cuando termine, borra instalar.php del servidor.</p>

<?php endif; ?>

</div>

</body>
</html>
