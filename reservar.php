<?php
require_once __DIR__ . '/php/conexion.php';
require_once __DIR__ . '/php/funciones.php';
require_once __DIR__ . '/php/sesion.php';

$parametrosReserva = array_intersect_key(
    $_GET,
    array('escenario' => true, 'fecha' => true, 'hora' => true)
);
$destinoReserva = 'reservar.php';
if ($parametrosReserva) {
    $destinoReserva .= '?' . http_build_query($parametrosReserva);
}
exigirLogin($destinoReserva);
$escenarioSeleccionado = filter_input(INPUT_GET, 'escenario', FILTER_VALIDATE_INT);
$fechaSeleccionada = textoEntrada($_GET, 'fecha');
$horaSeleccionada = textoEntrada($_GET, 'hora');
$consultaEscenarios = $conexion->prepare(
    'SELECT id, nombre, unidad_deportiva, tipo, hora_apertura, hora_cierre
     FROM escenarios WHERE activo = 1 ORDER BY unidad_deportiva, nombre'
);
$consultaEscenarios->execute();
$escenarios = $consultaEscenarios->get_result();

$escenarioInicial = $escenarioSeleccionado
    ? obtenerEscenario($conexion, (int) $escenarioSeleccionado)
    : null;
$franjas = array();
if ($escenarioInicial && fechaEnRangoDeReserva($fechaSeleccionada)) {
    $franjas = obtenerFranjasDisponibles(
        $conexion,
        (int) $escenarioSeleccionado,
        $fechaSeleccionada
    );
}
$hoy = date('Y-m-d');
$fechaMaxima = date('Y-m-d', strtotime('+30 days'));
$mensaje = textoEntrada($_GET, 'mensaje');
$tipoMensaje = isset($_GET['tipo']) && $_GET['tipo'] === 'exito' ? 'exito' : 'error';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reserva un escenario deportivo en Medellín.">
    <meta name="theme-color" content="#101b17">
    <title>Reservar espacio | Cancha</title>
    <link rel="stylesheet" href="css/estilos.css">
    <script src="js/validaciones.js" defer></script>
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
                    <li><a href="index.php">Inicio</a></li>
                    <li><a href="canchas.php">Canchas</a></li>
                    <li><a aria-current="page" href="reservar.php">Reservar</a></li>
                    <li><a href="mis_reservas.php">Mis reservas</a></li>
                    <?php if (esAdmin()): ?><li><a href="reservas.php">Administración</a></li><?php endif; ?>
                </ul>
            </nav>
            <div class="acciones">
                <span><?php echo escapar(usuarioActual()['nombre']); ?></span>
                <form action="logout.php" method="post"><button class="boton boton-nav" type="submit">Cerrar sesión</button></form>
            </div>
        </div>
    </header>

    <main class="contenedor pagina-formulario">
        <section aria-labelledby="titulo-reserva">
            <div class="encabezado-pagina">
                <p class="etiqueta">TU MOMENTO DE JUGAR</p>
                <h1 id="titulo-reserva">Reserva tu espacio.</h1>
                <p class="texto-secundario">Elige un escenario activo, una fecha dentro de los próximos 30 días y una franja disponible de una hora.</p>
            </div>

            <?php if ($mensaje !== ''): ?>
                <div class="mensaje <?php echo escapar($tipoMensaje); ?>" role="status" aria-live="polite">
                    <?php echo escapar($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="formulario-layout">
                <form class="panel-formulario formulario" action="php/guardar.php" method="post" data-validacion data-horarios-url="php/horarios.php">
                    <div id="mensaje-validacion" class="mensaje error" role="alert" aria-live="assertive" hidden></div>
                    <div class="campo">
                        <label for="escenario">Escenario deportivo</label>
                        <select id="escenario" name="escenario" required>
                            <option value="">Selecciona un escenario</option>
                            <?php while ($escenario = $escenarios->fetch_assoc()): ?>
                                <option value="<?php echo (int) $escenario['id']; ?>"<?php echo $escenarioSeleccionado && (int) $escenarioSeleccionado === (int) $escenario['id'] ? ' selected' : ''; ?>>
                                    <?php echo escapar($escenario['unidad_deportiva'] . ' · ' . $escenario['nombre'] . ' (' . $escenario['tipo'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="rejilla campos-horario">
                        <div class="campo">
                            <label for="fecha">Fecha</label>
                            <input type="date" id="fecha" name="fecha" min="<?php echo escapar($hoy); ?>" max="<?php echo escapar($fechaMaxima); ?>" value="<?php echo escapar($fechaSeleccionada); ?>" required>
                        </div>
                        <div class="campo">
                            <label for="hora_inicio">Franja de una hora</label>
                            <select id="hora_inicio" name="hora_inicio" required>
                                <option value=""><?php echo $franjas ? 'Selecciona una franja' : 'Selecciona escenario y fecha'; ?></option>
                                <?php foreach ($franjas as $franja): ?>
                                    <option value="<?php echo escapar($franja['inicio']); ?>"<?php echo $horaSeleccionada === $franja['inicio'] ? ' selected' : ''; ?>>
                                        <?php echo escapar($franja['etiqueta']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <p class="texto-ayuda">Cada reserva dura una hora. Los horarios configurados son ejemplos académicos tentativos y deben confirmarse con la unidad deportiva.</p>
                    <div class="acciones-formulario">
                        <button class="boton" type="submit">Confirmar reserva <span aria-hidden="true">→</span></button>
                        <a class="boton boton-secundario" href="mis_reservas.php">Ver mis reservas</a>
                    </div>
                </form>
                <?php
                $imagenReserva = $escenarioInicial && $escenarioInicial['tipo'] === 'Piscina'
                    ? 'img/piscina.jpg'
                    : 'img/futbol.jpg';
                ?>
                <aside class="panel-foto" aria-label="Información para reservar">
                    <img src="<?php echo escapar($imagenReserva); ?>" alt="" loading="lazy">
                    <div class="panel-foto-texto">
                        <strong>Tu equipo.<br>Tu cancha. Tu momento.</strong>
                        <span>Escoge una franja disponible y prepárate para jugar.</span>
                    </div>
                </aside>
            </div>
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
<?php
$consultaEscenarios->close();
?>
