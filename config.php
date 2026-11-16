<?php
/* ---------------------------------------------------------
   Human Coins — configuración y conexión
   Edita solo los cuatro valores de abajo.
--------------------------------------------------------- */

/* Las credenciales viven en credenciales.php, que NO va en el repositorio.
   Copia credenciales-ejemplo.php a credenciales.php y edítalo en el servidor. */
$archivoCredenciales = __DIR__ . '/credenciales.php';
if (!file_exists($archivoCredenciales)) {
    die('Falta el archivo credenciales.php. Copia credenciales-ejemplo.php y pon los datos de la base de datos.');
}
require $archivoCredenciales;

/* Monedas que recibe cada persona al registrarse */
define('MONEDAS_INICIALES', 1000);

session_start();

try {
    $db = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('No se pudo conectar a la base de datos.');
}

/* Crea las tablas la primera vez que se abre el sitio */
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

function ip_visitante() {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $clave) {
        if (!empty($_SERVER[$clave])) {
            $valor = explode(',', $_SERVER[$clave])[0];
            $valor = trim($valor);
            if (filter_var($valor, FILTER_VALIDATE_IP)) {
                return $valor;
            }
        }
    }
    return '0.0.0.0';
}

/* Símbolo de la moneda: la H con el travesaño curvo */
function simbolo($clase = 'simbolo') {
    return '<svg class="' . htmlspecialchars($clase, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 32 32" fill="none" aria-hidden="true">'
         . '<path d="M9 5 V27" stroke="currentColor" stroke-width="3.2" stroke-linecap="round"/>'
         . '<path d="M23 5 V27" stroke="currentColor" stroke-width="3.2" stroke-linecap="round"/>'
         . '<path d="M9 16 C 13 10.5, 19 21.5, 23 16" stroke="currentColor" stroke-width="3.2" stroke-linecap="round"/>'
         . '</svg>';
}

function esc($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function usuario_actual($db) {
    if (empty($_SESSION['uid'])) {
        return null;
    }
    $q = $db->prepare('SELECT * FROM usuarios WHERE id = ?');
    $q->execute([$_SESSION['uid']]);
    return $q->fetch() ?: null;
}

function exigir_sesion($db) {
    $u = usuario_actual($db);
    if (!$u) {
        header('Location: index.php');
        exit;
    }
    return $u;
}

/* Token simple contra envíos cruzados */
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(16));
}

function token_valido() {
    return isset($_POST['token']) && hash_equals($_SESSION['token'], $_POST['token'] ?? '');
}

/* Para qué se usaron las monedas. El orden es el que se ve en el formulario. */
$CATEGORIAS = [
    'comida'     => 'Comida o bebida',
    'favor'      => 'Un favor o una ayuda',
    'trabajo'    => 'Un trabajo o servicio',
    'objeto'     => 'Un objeto o préstamo',
    'transporte' => 'Transporte',
    'arte'       => 'Arte, música o contenido',
    'regalo'     => 'Un regalo, sin esperar nada',
    'deuda'      => 'Pagar algo que debía',
    'aporte'     => 'Aporte a algo colectivo',
    'otro'       => 'Otra cosa',
];
