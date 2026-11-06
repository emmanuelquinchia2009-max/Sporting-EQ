<?php
require_once __DIR__ . '/php/sesion.php';
require_once __DIR__ . '/php/funciones.php';
iniciarSesion();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reserva canchas y espacios deportivos de forma sencilla. Encuentra tu escenario y asegura tu próximo partido.">
    <meta name="theme-color" content="#101b17">
    <title>Cancha | Vive el deporte</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
    <header class="encabezado">
        <div class="contenedor encabezado-interior">
            <a class="marca" href="index.php" aria-label="Cancha, inicio">
                <span class="marca-icono" aria-hidden="true">C</span>
                <span>CANCHA<span class="marca-punto">.</span><small>DEPORTE PARA TODOS</small></span>
            </a>
            <nav aria-label="Navegación principal">
                <ul class="navegacion">
                    <li><a aria-current="page" href="index.php">Inicio</a></li>
                    <li><a href="canchas.php">Canchas</a></li>
                    <?php if (idUsuarioActual() > 0): ?>
                        <li><a href="mis_reservas.php">Mis reservas</a></li>
                        <?php if (esAdmin()): ?><li><a href="reservas.php">Administración</a></li><?php endif; ?>
                    <?php else: ?>
                        <li><a href="login.php">Iniciar sesión</a></li>
                        <li><a href="registro.php">Registrarme</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php if (idUsuarioActual() > 0): ?>
                <div class="acciones">
                    <span><?php echo escapar(usuarioActual()['nombre']); ?></span>
                    <form action="logout.php" method="post"><button class="boton boton-nav" type="submit">Cerrar sesión</button></form>
                </div>
            <?php else: ?>
                <a class="boton boton-nav" href="reservar.php">Reservar cancha <span aria-hidden="true">↗</span></a>
            <?php endif; ?>
        </div>
    </header>

    <main>
        <section class="home-hero">
            <div class="hero-fondo" role="img" aria-label="Cancha de fútbol iluminada al atardecer"></div>
            <div class="contenedor hero-contenido">
                <p class="etiqueta etiqueta-clara"><span class="punto-vivo"></span> TU PRÓXIMO PARTIDO EMPIEZA AQUÍ</p>
                <h1>El juego se vive<br>en <span>la cancha.</span></h1>
                <p class="hero-descripcion">Encuentra tu espacio, reúne a tu equipo y convierte cualquier día en un gran partido.</p>
                <div class="acciones">
                    <a class="boton boton-acento" href="reservar.php">Encuentra tu cancha <span aria-hidden="true">→</span></a>
                    <a class="enlace-play" href="#experiencia"><span class="play-icono" aria-hidden="true">↘</span> Descubre la experiencia</a>
                </div>
            </div>
            <div class="hero-indice" aria-hidden="true"><span>01</span><i></i> 04</div>
            <div class="hero-sello" aria-hidden="true"><span>JUEGA</span><strong>+</strong><span>VIVE</span></div>
        </section>

        <section class="franja-datos" aria-label="Ventajas de reservar">
            <div class="contenedor datos-interior">
                <p><strong>Tu deporte,</strong> tu espacio, tu momento.</p>
                <div><span class="dato-icono">01</span><span>Reserva en minutos</span></div>
                <div><span class="dato-icono">02</span><span>Espacios para todos</span></div>
                <div><span class="dato-icono">03</span><span>Tu equipo te espera</span></div>
            </div>
        </section>

        <section class="contenedor seccion escenarios" id="escenarios" aria-labelledby="titulo-escenarios">
            <div class="encabezado-seccion">
                <div>
                    <p class="etiqueta">ENCUENTRA TU JUEGO</p>
                    <h2 id="titulo-escenarios">Un espacio para<br><span>cada pasión.</span></h2>
                </div>
                <p class="texto-seccion">Desde el primer saque hasta el último gol, tenemos el lugar perfecto para que tu equipo brille.</p>
            </div>

            <div class="rejilla escenarios-grid">
                <a class="escenario-card escenario-futbol" href="canchas.php?tipo=F%C3%BAtbol">
                    <img src="img/futbol.jpg" alt="Cancha de fútbol" loading="lazy">
                    <span class="escenario-numero">01 / FÚTBOL</span>
                    <span class="escenario-info"><span><strong>Cancha de fútbol</strong><small>Para jugar en equipo</small></span><span class="flecha-card" aria-hidden="true">↗</span></span>
                </a>
                <a class="escenario-card" href="canchas.php?tipo=Baloncesto">
                    <img src="img/baloncesto.jpg" alt="Cancha de baloncesto" loading="lazy">
                    <span class="escenario-numero">02 / BALONCESTO</span>
                    <span class="escenario-info"><span><strong>Cancha de baloncesto</strong><small>El próximo punto es tuyo</small></span><span class="flecha-card" aria-hidden="true">↗</span></span>
                </a>
                <a class="escenario-card" href="canchas.php?tipo=Voleibol">
                    <img src="img/voleibol.jpg" alt="Cancha de voleibol" loading="lazy">
                    <span class="escenario-numero">03 / VOLEIBOL</span>
                    <span class="escenario-info"><span><strong>Cancha de voleibol</strong><small>Arma tu equipo</small></span><span class="flecha-card" aria-hidden="true">↗</span></span>
                </a>
                <a class="escenario-card" href="canchas.php?tipo=Piscina">
                    <img src="img/piscina.jpg" alt="Piscina deportiva" loading="lazy">
                    <span class="escenario-numero">04 / NATACIÓN</span>
                    <span class="escenario-info"><span><strong>Piscina</strong><small>Encuentra tu ritmo</small></span><span class="flecha-card" aria-hidden="true">↗</span></span>
                </a>
            </div>
        </section>

        <section class="experiencia" id="experiencia" aria-labelledby="titulo-experiencia">
            <div class="contenedor experiencia-grid">
                <div class="experiencia-foto">
                    <img src="img/voleibol.jpg" alt="Red y balón listos para un partido de voleibol" loading="lazy">
                    <span class="foto-etiqueta">EL DEPORTE NOS MUEVE</span>
                </div>
                <div class="experiencia-texto">
                    <p class="etiqueta etiqueta-clara">MÁS QUE UN PARTIDO</p>
                    <h2 id="titulo-experiencia">El mejor plan<br>es <span>jugar.</span></h2>
                    <p>Un buen partido empieza mucho antes del silbato. Encuentra el escenario, invita a tu equipo y deja que el deporte haga el resto.</p>
                    <a class="boton boton-acento" href="reservar.php">Haz tu reserva <span aria-hidden="true">→</span></a>
                </div>
            </div>
        </section>

        <section class="contenedor cierre-cta">
            <div>
                <p class="etiqueta">EL PRÓXIMO TURNO ES TUYO</p>
                <h2>¿Listo para salir a jugar?</h2>
            </div>
            <a class="boton boton-oscuro" href="reservar.php">Reserva tu espacio <span aria-hidden="true">↗</span></a>
        </section>
    </main>

    <footer class="pie">
        <div class="contenedor pie-interior">
            <a class="marca marca-pie" href="index.php"><span class="marca-icono" aria-hidden="true">C</span><span>CANCHA<span class="marca-punto">.</span><small>DEPORTE PARA TODOS</small></span></a>
            <p>Tu comunidad, tu cancha, tu juego.</p>
            <a href="canchas.php">Explorar canchas <span aria-hidden="true">↗</span></a>
        </div>
    </footer>
</body>
</html>
