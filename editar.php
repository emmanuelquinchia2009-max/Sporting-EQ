<?php
require_once __DIR__ . '/php/conexion.php';
require_once __DIR__ . '/php/funciones.php';
require_once __DIR__ . '/php/sesion.php';

exigirLogin('mis_reservas.php');
$idUsuario = idUsuarioActual();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    header('Location: mis_reservas.php?mensaje=' . urlencode('La reserva indicada no es válida.') . '&tipo=error');
    exit;
}

$consulta = $conexion->prepare(
    'SELECT id, id_escenario, fecha, hora_inicio, hora_fin, estado
     FROM reservas WHERE id = ? AND id_usuario = ?'
);
$consulta->bind_param('ii', $id, $idUsuario);
$consulta->execute();
$resultado = $consulta->get_result();
$reserva = $resultado->fetch_assoc();
$consulta->close();

if (!$reserva) {
    header('Location: mis_reservas.php?mensaje=' . urlencode('No tienes permiso para modificar esta reserva.') . '&tipo=error');
    exit;
}

if (!in_array(
    estadoVisibleReserva($reserva['estado'], $reserva['fecha'], $reserva['hora_fin']),
    array('Activa', 'Modificada'),
    true
)) {
    header('Location: mis_reservas.php?mensaje=' . urlencode('Solo se pueden editar reservas futuras activas.') . '&tipo=error');
    exit;
}

if (!puedeModificarReserva($reserva['fecha'], $reserva['hora_inicio'])) {
    $mensajeAnticipacion = 'Solo puedes cancelar hasta '
        . HORAS_ANTICIPACION_CANCELACION
        . ' horas antes de la reserva.';
    header('Location: mis_reservas.php?mensaje=' . urlencode($mensajeAnticipacion) . '&tipo=error');
    exit;
}

$consultaEscenarios = $conexion->prepare(
    'SELECT id, nombre, unidad_deportiva FROM escenarios WHERE activo = 1 ORDER BY unidad_deportiva, nombre'
);
$consultaEscenarios->execute();
$escenarios = $consultaEscenarios->get_result();
$franjas = obtenerFranjasDisponibles(
    $conexion,
    (int) $reserva['id_escenario'],
    $reserva['fecha'],
    (int) $reserva['id']
);
$hoy = date('Y-m-d');
$fechaMaxima = date('Y-m-d', strtotime('+30 days'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Modificar una reserva deportiva futura.">
    <meta name="theme-color" content="#101b17">
    <title>Editar reserva | Cancha</title>
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
                    <li><a href="reservar.php">Reservar</a></li>
                    <li><a aria-current="page" href="mis_reservas.php">Mis reservas</a></li>
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
        <section aria-labelledby="titulo-edicion">
            <div class="encabezado-pagina">
                <p class="etiqueta">ACTUALIZA TU PLAN</p>
                <h1 id="titulo-edicion">Edita tu reserva.</h1>
                <p class="texto-secundario">Al guardar los cambios, el estado cambiará a “Modificada”. Solo se muestran franjas futuras disponibles.</p>
            </div>

            <div class="formulario-layout">
                <form class="panel-formulario formulario" action="php/actualizar.php" method="post" data-validacion data-horarios-url="php/horarios.php" data-reserva-id="<?php echo (int) $reserva['id']; ?>">
                    <div id="mensaje-validacion" class="mensaje error" role="alert" aria-live="assertive" hidden></div>
                    <input type="hidden" name="id" value="<?php echo (int) $reserva['id']; ?>">
                    <div class="campo">
                        <label for="escenario">Escenario deportivo</label>
                        <select id="escenario" name="escenario" required>
                            <option value="">Selecciona un escenario</option>
                            <?php while ($escenario = $escenarios->fetch_assoc()): ?>
                                <option value="<?php echo (int) $escenario['id']; ?>"<?php echo (int) $reserva['id_escenario'] === (int) $escenario['id'] ? ' selected' : ''; ?>>
                                    <?php echo escapar($escenario['unidad_deportiva'] . ' · ' . $escenario['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="rejilla campos-horario">
                        <div class="campo">
                            <label for="fecha">Fecha</label>
                            <input type="date" id="fecha" name="fecha" min="<?php echo escapar($hoy); ?>" max="<?php echo escapar($fechaMaxima); ?>" value="<?php echo escapar($reserva['fecha']); ?>" required>
                        </div>
                        <div class="campo">
                            <label for="hora_inicio">Franja de una hora</label>
                            <select id="hora_inicio" name="hora_inicio" data-hora-actual="<?php echo escapar(substr($reserva['hora_inicio'], 0, 5)); ?>" required>
                                <option value="">Selecciona una franja</option>
                                <?php foreach ($franjas as $franja): ?>
                                    <option value="<?php echo escapar($franja['inicio']); ?>"<?php echo substr($reserva['hora_inicio'], 0, 5) === $franja['inicio'] ? ' selected' : ''; ?>>
                                        <?php echo escapar($franja['etiqueta']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="acciones-formulario">
                        <button class="boton" type="submit">Guardar cambios <span aria-hidden="true">→</span></button>
                        <a class="boton boton-secundario" href="mis_reservas.php">Cancelar edición</a>
                    </div>
                </form>
                <aside class="panel-foto" aria-label="Deporte para todos">
                    <img src="img/baloncesto.jpg" alt="" loading="lazy">
                    <div class="panel-foto-texto">
                        <strong>El juego continúa.</strong>
                        <span>Ajusta los detalles y vuelve a la cancha.</span>
                    </div>
                </aside>
            </div>
        </section>
    </main>

    <footer class="pie">
        <div class="contenedor pie-interior">
            <a class="marca marca-pie" href="index.php"><span class="marca-icono" aria-hidden="true">C</span><span>CANCHA<span class="marca-punto">.</span><small>DEPORTE PARA TODOS</small></span></a>
            <p>Tu comunidad, tu cancha, tu juego.</p>
            <a href="mis_reservas.php">Mis reservas <span aria-hidden="true">↗</span></a>
        </div>
    </footer>
</body>
</html>
<?php
$consultaEscenarios->close();
?>
