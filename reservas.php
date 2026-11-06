<?php
require_once __DIR__ . '/php/conexion.php';
require_once __DIR__ . '/php/funciones.php';
require_once __DIR__ . '/php/sesion.php';

exigirAdmin();

$estadoSeleccionado = textoEntrada($_GET, 'estado');
$escenarioSeleccionado = filter_input(INPUT_GET, 'escenario', FILTER_VALIDATE_INT);
$escenarioSeleccionado = $escenarioSeleccionado ? (int) $escenarioSeleccionado : 0;
$fechaSeleccionada = textoEntrada($_GET, 'fecha');
$telefonoTexto = textoEntrada($_GET, 'telefono');
$telefonoDigitos = preg_replace('/[^0-9]/', '', $telefonoTexto);
$telefonoPatron = $telefonoTexto === ''
    ? ''
    : ($telefonoDigitos !== '' ? '%' . $telefonoDigitos . '%' : '000000000000');
$estadosPermitidos = array('Activa', 'Modificada', 'Cancelada', 'Finalizada');
$errorFiltro = '';

if (!in_array($estadoSeleccionado, $estadosPermitidos, true)) {
    $estadoSeleccionado = '';
}
if ($fechaSeleccionada !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaSeleccionada)) {
    $errorFiltro = 'La fecha del filtro no tiene un formato válido.';
    $fechaSeleccionada = '';
}

$consultaEscenarios = $conexion->prepare(
    'SELECT id, nombre, unidad_deportiva FROM escenarios ORDER BY unidad_deportiva, nombre'
);
$consultaEscenarios->execute();
$escenarios = $consultaEscenarios->get_result();

$consulta = $conexion->prepare(
    "SELECT r.id, u.nombre AS nombre_solicitante, u.telefono,
            r.fecha, r.hora_inicio, r.hora_fin,
            CASE
              WHEN r.estado IN ('Activa', 'Modificada')
                AND TIMESTAMP(r.fecha, r.hora_fin) <= NOW()
              THEN 'Finalizada'
              ELSE r.estado
            END AS estado,
            e.nombre AS escenario, e.unidad_deportiva
     FROM reservas r
     INNER JOIN escenarios e ON e.id = r.id_escenario
     INNER JOIN usuarios u ON u.id = r.id_usuario
     WHERE (? = '' OR
            (CASE
              WHEN r.estado IN ('Activa', 'Modificada')
                AND TIMESTAMP(r.fecha, r.hora_fin) <= NOW()
              THEN 'Finalizada'
              ELSE r.estado
            END) = ?)
       AND (? = 0 OR r.id_escenario = ?)
       AND (? = '' OR r.fecha = ?)
       AND (? = '' OR u.telefono LIKE ?)
     ORDER BY
       CASE WHEN r.estado IN ('Cancelada', 'Finalizada') THEN 1 ELSE 0 END,
       CASE WHEN TIMESTAMP(r.fecha, r.hora_inicio) >= NOW() THEN 0 ELSE 1 END,
       r.fecha,
       r.hora_inicio"
);
$consulta->bind_param(
    'ssiissss',
    $estadoSeleccionado,
    $estadoSeleccionado,
    $escenarioSeleccionado,
    $escenarioSeleccionado,
    $fechaSeleccionada,
    $fechaSeleccionada,
    $telefonoTexto,
    $telefonoPatron
);
$consulta->execute();
$reservas = $consulta->get_result();

$mensaje = textoEntrada($_GET, 'mensaje');
$tipoMensaje = isset($_GET['tipo']) && $_GET['tipo'] === 'exito' ? 'exito' : 'error';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Panel administrativo de reservas deportivas en Medellín.">
    <meta name="theme-color" content="#101b17">
    <title>Administración de reservas | Cancha</title>
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
                    <li><a href="mis_reservas.php">Mis reservas</a></li>
                    <li><a aria-current="page" href="reservas.php">Administración</a></li>
                </ul>
            </nav>
            <div class="acciones">
                <span><?php echo escapar(usuarioActual()['nombre']); ?></span>
                <form action="logout.php" method="post"><button class="boton boton-nav" type="submit">Cerrar sesión</button></form>
            </div>
        </div>
    </header>

    <main class="contenedor pagina-listado">
        <section aria-labelledby="titulo-reservas">
            <div class="barra-tabla">
                <div>
                    <p class="etiqueta">PANEL DE ADMINISTRACIÓN</p>
                        <h1 id="titulo-reservas">Todas las reservas.</h1>
                </div>
                <a class="boton" href="canchas.php">Buscar escenario <span aria-hidden="true">→</span></a>
            </div>

            <?php if ($mensaje !== ''): ?>
                <div class="mensaje <?php echo escapar($tipoMensaje); ?>" role="status" aria-live="polite">
                    <?php echo escapar($mensaje); ?>
                </div>
            <?php endif; ?>
            <?php if ($errorFiltro !== ''): ?>
                <div class="mensaje error" role="alert"><?php echo escapar($errorFiltro); ?></div>
            <?php endif; ?>

            <form class="filtros-reservas panel-formulario" action="reservas.php" method="get">
                <div class="campo">
                    <label for="filtro-telefono">Celular</label>
                    <input type="search" id="filtro-telefono" name="telefono" inputmode="numeric" value="<?php echo escapar($telefonoTexto); ?>" placeholder="Buscar número">
                </div>
                <div class="campo">
                    <label for="filtro-escenario">Escenario</label>
                    <select id="filtro-escenario" name="escenario">
                        <option value="0">Todos los escenarios</option>
                        <?php while ($escenario = $escenarios->fetch_assoc()): ?>
                            <option value="<?php echo (int) $escenario['id']; ?>"<?php echo $escenarioSeleccionado === (int) $escenario['id'] ? ' selected' : ''; ?>>
                                <?php echo escapar($escenario['unidad_deportiva'] . ' · ' . $escenario['nombre']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="campo">
                    <label for="filtro-estado">Estado</label>
                    <select id="filtro-estado" name="estado">
                        <option value="">Todos los estados</option>
                        <?php foreach ($estadosPermitidos as $estado): ?>
                            <option value="<?php echo escapar($estado); ?>"<?php echo $estadoSeleccionado === $estado ? ' selected' : ''; ?>>
                                <?php echo escapar($estado); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo">
                    <label for="filtro-fecha">Fecha</label>
                    <input type="date" id="filtro-fecha" name="fecha" value="<?php echo escapar($fechaSeleccionada); ?>">
                </div>
                <div class="acciones-filtros">
                    <button class="boton" type="submit">Filtrar <span aria-hidden="true">→</span></button>
                    <a class="boton boton-secundario" href="reservas.php">Limpiar</a>
                </div>
            </form>

            <div class="tabla-contenedor">
                <table>
                    <caption>El teléfono se muestra parcialmente oculto. Las reservas próximas aparecen primero; las canceladas y finalizadas, al final.</caption>
                    <thead>
                        <tr>
                            <th scope="col">Solicitante</th>
                            <th scope="col">Celular</th>
                            <th scope="col">Escenario / unidad</th>
                            <th scope="col">Fecha</th>
                            <th scope="col">Franja</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($reservas->num_rows === 0): ?>
                            <tr><td colspan="7">No hay reservas que coincidan con los filtros.</td></tr>
                        <?php else: ?>
                            <?php while ($reserva = $reservas->fetch_assoc()): ?>
                                <?php
                                $claseEstado = '';
                                if ($reserva['estado'] === 'Modificada') {
                                    $claseEstado = ' estado-modificada';
                                } elseif ($reserva['estado'] === 'Cancelada') {
                                    $claseEstado = ' estado-cancelada';
                                } elseif ($reserva['estado'] === 'Finalizada') {
                                    $claseEstado = ' estado-finalizada';
                                }
                                $inicioFuturo = strtotime($reserva['fecha'] . ' ' . $reserva['hora_inicio']) > time();
                                $eliminable = $reserva['estado'] === 'Cancelada'
                                    || (in_array($reserva['estado'], array('Activa', 'Modificada'), true) && $inicioFuturo);
                                ?>
                                <tr>
                                    <td><?php echo escapar($reserva['nombre_solicitante']); ?></td>
                                    <td><?php echo escapar(enmascararTelefono($reserva['telefono'])); ?></td>
                                    <td>
                                        <strong><?php echo escapar($reserva['escenario']); ?></strong>
                                        <small class="unidad-tabla"><?php echo escapar($reserva['unidad_deportiva']); ?></small>
                                    </td>
                                    <td><?php echo escapar($reserva['fecha']); ?></td>
                                    <td><?php echo escapar(substr($reserva['hora_inicio'], 0, 5) . ' - ' . substr($reserva['hora_fin'], 0, 5)); ?></td>
                                    <td><span class="estado<?php echo $claseEstado; ?>"><?php echo escapar($reserva['estado']); ?></span></td>
                                    <td class="celda-acciones">
                                        <?php if ($eliminable): ?>
                                            <form action="php/eliminar.php" method="post" data-validacion data-confirmacion="¿Eliminar definitivamente esta reserva?">
                                                <input type="hidden" name="id" value="<?php echo (int) $reserva['id']; ?>">
                                                <button class="boton boton-pequeno boton-peligro" type="submit">Eliminar</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="texto-no-editable"><?php echo escapar($reserva['estado']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
$consulta->close();
$consultaEscenarios->close();
?>
