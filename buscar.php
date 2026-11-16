<?php
/* Predictor de usuarios para el campo "Para quién".
   Exige sesión abierta y al menos 2 caracteres, y devuelve pocos
   resultados, para que nadie pueda sacar la lista completa de participantes. */

require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$yo = usuario_actual($db);
if (!$yo) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$q = strtolower(trim($_GET['q'] ?? ''));
$q = preg_replace('/[^a-z0-9_]/', '', $q);

if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

/* El guion bajo es comodin en LIKE y es valido en los usuarios: hay que escaparlo */
$patron = str_replace('_', '!_', $q);

$busca = $db->prepare(
    'SELECT usuario, nombre
       FROM usuarios
      WHERE usuario LIKE :patron ESCAPE "!" AND id <> :yo
      ORDER BY
        CASE WHEN usuario = :exacto THEN 0
             WHEN usuario LIKE :empieza ESCAPE "!" THEN 1
             ELSE 2 END,
        usuario ASC
      LIMIT 6'
);
$busca->execute([
    ':patron'  => '%' . $patron . '%',
    ':yo'      => $yo['id'],
    ':exacto'  => $q,
    ':empieza' => $patron . '%',
]);

$salida = [];
foreach ($busca->fetchAll() as $f) {
    $salida[] = ['usuario' => $f['usuario'], 'nombre' => $f['nombre']];
}

echo json_encode($salida, JSON_UNESCAPED_UNICODE);
