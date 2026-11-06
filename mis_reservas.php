<?php
require_once __DIR__ . '/php/conexion.php';
require_once __DIR__ . '/php/funciones.php';
require_once __DIR__ . '/php/sesion.php';

exigirLogin('mis_reservas.php');
$idUsuario = idUsuarioActual();

$consulta = $conexion->prepare(
    "SELECT r.id, r.fecha, r.hora_inicio, r.hora_fin, r.estado,
            e.nombre AS escenario, e.unidad_deportiva
     FROM reservas r
     INNER JOIN escenarios e ON e.id = r.id_escenario
     WHERE r.id_usuario = ?
     ORDER BY
       CASE
         WHEN r.estado = 'Cancelada'
           OR (r.estado IN ('Activa', 'Modificada') AND TIMESTAMP(r.fecha, r.hora_fin) <= NOW())
         THEN 1 ELSE 0
       END,
       r.fecha,
       r.hora_inicio"
);
$consulta->bind_param('i', $idUsuario);
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
    <meta name="description" content="Consulta y administra tus reservas deportivas.">
    <meta name="theme-color" content="#101b17">
    <title>Mis reservas | Cancha</title>
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
    <main class="contenedor pagina-listado">
        <section aria-labelledby="titulo-reservas">
            <div class="barra-tabla">
                <div>
                    <p class="etiqueta">TU ACTIVIDAD DEPORTIVA</p>
                    <h1 id="titulo-reservas">Mis reservas.</h1>
                </div>
                <a class="boton" href="canchas.php">Buscar cancha <span aria-hidden="true">→</span></a>
            </div>
            <?php if ($mensaje !== ''): ?>
                <div class="mensaje <?php echo escapar($tipoMensaje); ?>" role="status" aria-live="polite">
                    <?php echo escapar($mensaje); ?>
                </div>
            <?php endif; ?>
            <div class="tabla-contenedor">
                <table>
                    <caption>Las próximas reservas aparecen primero. Las canceladas y finalizadas quedan al final.</caption>
                    <thead>
                        <tr>
                            <th scope="col">Escenario / unidad</th>
                            <th scope="col">Fecha</th>
                            <th scope="col">Franja</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($reservas->num_rows === 0): ?>
                            <tr><td colspan="5">Todavía no tienes reservas.</td></tr>
                        <?php else: ?>
                            <?php while ($reserva = $reservas->fetch_assoc()): ?>
                                <?php
                                $estado = estadoVisibleReserva(
                                    $reserva['estado'],
                                    $reserva['fecha'],
                                    $reserva['hora_fin']
                                );
                                $editable = in_array($estado, array('Activa', 'Modificada'), true)
                                    && puedeModificarReserva($reserva['fecha'], $reserva['hora_inicio']);
                                $claseEstado = '';
                                if ($estado === 'Modificada') {
                                    $claseEstado = ' estado-modificada';
                                } elseif ($estado === 'Cancelada') {
                                    $claseEstado = ' estado-cancelada';
                                } elseif ($estado === 'Finalizada') {
                                    $claseEstado = ' estado-finalizada';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo escapar($reserva['escenario']); ?></strong>
                                        <small class="unidad-tabla"><?php echo escapar($reserva['unidad_deportiva']); ?></small>
                                    </td>
                                    <td><?php echo escapar($reserva['fecha']); ?></td>
                                    <td><?php echo escapar(substr($reserva['hora_inicio'], 0, 5) . ' - ' . substr($reserva['hora_fin'], 0, 5)); ?></td>
                                    <td><span class="estado<?php echo $claseEstado; ?>"><?php echo escapar($estado); ?></span></td>
                                    <td class="celda-acciones">
                                        <?php if ($editable): ?>
                                            <a class="boton boton-pequeno boton-secundario" href="editar.php?id=<?php echo (int) $reserva['id']; ?>">Editar</a>
                                            <form action="php/cancelar.php" method="post" data-validacion data-confirmacion="¿Cancelar esta reserva? El horario quedará disponible.">
                                                <input type="hidden" name="id" value="<?php echo (int) $reserva['id']; ?>">
                                                <button class="boton boton-pequeno" type="submit">Cancelar</button>
                                            </form>
                                            <form action="php/eliminar.php" method="post" data-validacion data-confirmacion="¿Eliminar definitivamente esta reserva?">
                                                <input type="hidden" name="id" value="<?php echo (int) $reserva['id']; ?>">
                                                <input type="hidden" name="origen" value="mis_reservas">
                                                <button class="boton boton-pequeno boton-peligro" type="submit">Eliminar</button>
                                            </form>
                                        <?php elseif (in_array($estado, array('Activa', 'Modificada'), true)): ?>
                                            <span class="texto-no-editable">Ya no se puede modificar</span>
                                        <?php elseif ($estado === 'Cancelada'): ?>
                                            <form action="php/eliminar.php" method="post" data-validacion data-confirmacion="¿Eliminar definitivamente esta reserva?">
                                                <input type="hidden" name="id" value="<?php echo (int) $reserva['id']; ?>">
                                                <input type="hidden" name="origen" value="mis_reservas">
                                                <button class="boton boton-pequeno boton-peligro" type="submit">Eliminar</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="texto-no-editable"><?php echo escapar($estado); ?></span>
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
<?php $consulta->close(); ?>
