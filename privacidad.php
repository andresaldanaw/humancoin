<?php
require __DIR__ . '/config.php';
$yo = usuario_actual($db);
$actualizado = '16 de noviembre de 2026';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Privacidad · Human Coins</title>
<meta name="theme-color" content="#EFE9DC">
<link rel="icon" href="iconos/icono-192.jpg" type="image/jpeg">
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
      <?php else: ?>
        <a class="switch-link" href="index.php">entrar</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="intro">
    <h1 class="serif">Política de tratamiento de datos personales.</h1>
    <p>Human Coins es un experimento social sin ánimo de lucro. Esta política explica qué datos guardamos, para qué, y qué puedes exigir sobre ellos. Está redactada conforme a la Ley 1581 de 2012 y al Decreto 1074 de 2015 de Colombia.</p>
    <p class="hint">Última actualización: <?= esc($actualizado) ?></p>
  </div>

  <section class="block">
    <h2>Quién responde por tus datos</h2>
    <p>El responsable del tratamiento es la <strong>Fundación Corre La Voz</strong>, con domicilio en Bogotá D.C., Colombia. Para cualquier asunto relacionado con tus datos puedes escribir a <strong>info@correlavoz.org</strong>.</p>
    <p>Los servidores donde funciona este sitio están ubicados en Estados Unidos. Al crear una cuenta autorizas esa transferencia internacional de datos, en los términos del artículo 26 de la Ley 1581 de 2012.</p>
  </section>

  <section class="block">
    <h2>Qué guardamos</h2>
    <ul class="pairs">
      <li><span>El nombre que escribas</span><strong>visible</strong></li>
      <li><span>Tu nombre de usuario</span><strong>visible</strong></li>
      <li><span>Tu PIN de 4 números</span><strong>cifrado</strong></li>
      <li><span>Tu saldo y tus movimientos</span><strong>privado</strong></li>
      <li><span>La dirección IP del registro</span><strong>privado</strong></li>
      <li><span>Fecha de creación de la cuenta</span><strong>privado</strong></li>
    </ul>
    <p class="hint">No pedimos correo, teléfono, documento, dirección ni fecha de nacimiento. No usamos cookies de publicidad ni servicios de analítica de terceros. La única cookie que existe es la de sesión, que mantiene tu cartera abierta mientras navegas.</p>
  </section>

  <section class="block">
    <h2>El nombre que elijas es público</h2>
    <p>El nombre y el usuario que escribas aparecen ante las demás personas que participan, porque sin eso nadie podría enviarte monedas. Si no quieres que te identifiquen, usa un apodo: nada en este sitio te obliga a poner tu nombre real, y no comprobamos que lo sea.</p>
    <p>Dentro de tu cartera, y solo en la tuya, ves de quién recibiste y a quién enviaste. Esa información es visible únicamente para las dos personas involucradas en cada movimiento.</p>
  </section>

  <section class="block">
    <h2>Para qué usamos la dirección IP</h2>
    <p>Guardamos la dirección IP desde la que se crea cada cuenta con un único fin: impedir que una misma persona abra varias carteras y se dé monedas a sí misma, lo que arruinaría el experimento. No la usamos para perfilar, ubicar ni rastrear a nadie, no se muestra en ninguna parte del sitio y no se comparte con terceros.</p>
    <p class="hint">Conviene que sepas que este método es imperfecto en los dos sentidos: dos personas que comparten la misma conexión no podrán registrarse ambas, y alguien que cambie de red puede sortearlo. Lo asumimos a propósito, porque la alternativa sería pedirte documento o biometría, y eso nos parece peor.</p>
  </section>

  <section class="block">
    <h2>Qué se publica en el tablero</h2>
    <p>El tablero de estadísticas es público y no requiere cuenta. Allí solo aparecen cifras agregadas: cuánto se mueve, en qué categorías, cómo se reparten los saldos y los motivos escritos en los envíos.</p>
    <p><strong>Nunca se publica quién le envió a quién.</strong> Además, el tablero permanece cerrado hasta que haya al menos <?= MINIMO_PERSONAS ?> participantes, porque con un grupo pequeño hasta una cifra agregada puede señalar a una persona concreta.</p>
    <p class="hint">Lo que sí es público, y conviene tenerlo claro antes de escribirlo, es el texto del motivo que pones en cada envío. Aparece en el tablero sin tu nombre, pero aparece tal cual lo escribiste. No pongas ahí nada que no quieras que se lea.</p>
  </section>

  <section class="block">
    <h2>Tus derechos</h2>
    <p>La Ley 1581 de 2012 te reconoce, entre otros, el derecho a conocer, actualizar y rectificar tus datos; a solicitar prueba de la autorización que diste; a ser informado del uso que se les ha dado; a presentar quejas ante la Superintendencia de Industria y Comercio; y a revocar la autorización o solicitar la supresión de tus datos cuando no exista un deber legal de conservarlos.</p>
    <p>Para ejercer cualquiera de ellos escribe a <strong>info@correlavoz.org</strong> indicando tu nombre de usuario y qué solicitas. Responderemos en los plazos de ley: quince días hábiles para consultas y quince días hábiles para reclamos, prorrogables conforme a la norma.</p>
    <p class="hint">Si pides que borremos tu cuenta, eliminamos tu nombre, tu usuario, tu PIN y tu IP. Los movimientos en los que participaste se conservan sin ningún dato que te identifique, porque forman parte del registro contable del juego y borrarlos descuadraría los saldos de otras personas.</p>
  </section>

  <section class="block">
    <h2>Cuánto tiempo conservamos los datos</h2>
    <p>Mientras el experimento siga en pie y no pidas la supresión. Cuando Human Coins termine, publicaremos los datos agregados y anónimos con fines de divulgación y análisis, y eliminaremos los nombres, usuarios, PIN e IP.</p>
  </section>

  <section class="block">
    <h2>Seguridad, con honestidad</h2>
    <p>Los PIN se guardan cifrados con un algoritmo de un solo sentido: ni nosotros podemos leerlos. El sitio funciona sobre conexión cifrada y cada formulario está protegido contra envíos falsificados desde otros sitios.</p>
    <p class="hint">Dicho eso, no queremos venderte más seguridad de la que hay: un PIN de cuatro cifras tiene diez mil combinaciones y alguien empeñado podría adivinar el de otra persona. Lo elegimos así para que entrar sea fácil, y porque aquí no hay dinero real en juego. No uses este PIN en ningún otro sitio, y no trates esta cartera como si guardara algo de valor económico.</p>
  </section>

  <section class="block">
    <h2>Autorización</h2>
    <p>Al crear una cuenta manifiestas que leíste esta política y que autorizas de forma libre, previa, expresa e informada el tratamiento de tus datos en los términos aquí descritos.</p>
  </section>

  <p class="foot-note">
    <a href="index.php">Ir a Human Coins</a> &middot; <a href="estadisticas.php">Tablero</a><br>
    Las monedas no tienen valor monetario.
  </p>

</div>

</body>
</html>
