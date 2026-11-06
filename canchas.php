<?php
require_once __DIR__ . '/php/conexion.php';
require_once __DIR__ . '/php/funciones.php';
require_once __DIR__ . '/php/sesion.php';
iniciarSesion();

$busqueda = textoEntrada($_GET, 'buscar');
$tipoSeleccionado = textoEntrada($_GET, 'tipo');
$comunaSeleccionada = textoEntrada($_GET, 'comuna');
$patronBusqueda = '%' . $busqueda . '%';

$consultaTipos = $conexion->prepare(
    'SELECT DISTINCT tipo FROM escenarios WHERE activo = 1 ORDER BY tipo'
);
$consultaTipos->execute();
$tipos = $consultaTipos->get_result();

$consultaComunas = $conexion->prepare(
    "SELECT DISTINCT comuna
     FROM escenarios
     WHERE activo = 1 AND comuna IS NOT NULL AND comuna <> ''
     ORDER BY comuna"
);
$consultaComunas->execute();
$comunas = $consultaComunas->get_result();

$consulta = $conexion->prepare(
    "SELECT id, nombre, unidad_deportiva, tipo, superficie, barrio, comuna,
            direccion, capacidad, hora_apertura, hora_cierre, costo_hora, imagen
     FROM escenarios
     WHERE activo = 1
       AND (? = '' OR tipo = ?)
       AND (? = '' OR comuna = ?)
       AND (? = '' OR nombre LIKE ? OR barrio LIKE ? OR unidad_deportiva LIKE ?)
     ORDER BY unidad_deportiva, nombre"
);
$consulta->bind_param(
    'ssssssss',
    $tipoSeleccionado,
    $tipoSeleccionado,
    $comunaSeleccionada,
    $comunaSeleccionada,
    $busqueda,
    $patronBusqueda,
    $patronBusqueda,
    $patronBusqueda
);
$consulta->execute();
$escenarios = $consulta->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Explora escenarios deportivos en unidades deportivas de Medellín y encuentra dónde jugar.">
    <meta name="theme-color" content="#101b17">
    <title>Canchas en Medellín | Cancha</title>
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
                    <li><a href="index.php">Inicio</a></li>
                    <li><a aria-current="page" href="canchas.php">Canchas</a></li>
                    <?php if (idUsuarioActual() > 0): ?>
                        <li><a href="reservar.php">Reservar</a></li>
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
                <a class="boton boton-nav" href="login.php">Iniciar sesión</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="contenedor pagina-catalogo">
        <section aria-labelledby="titulo-canchas">
            <div class="encabezado-pagina">
                <p class="etiqueta">ESCENARIOS EN MEDELLÍN</p>
                <h1 id="titulo-canchas">Encuentra tu cancha.</h1>
                <p class="texto-secundario">Explora por disciplina, comuna o nombre. Los horarios que aparecen son ejemplos tentativos para este proyecto académico; no son horarios oficiales.</p>
            </div>

            <form class="filtros-canchas panel-formulario" action="canchas.php" method="get">
                <div class="campo">
                    <label for="buscar">Buscar escenario</label>
                    <input type="search" id="buscar" name="buscar" value="<?php echo escapar($busqueda); ?>" placeholder="Nombre, barrio o unidad deportiva">
                </div>
                <div class="campo">
                    <label for="tipo">Deporte</label>
                    <select id="tipo" name="tipo">
                        <option value="">Todos los deportes</option>
                        <?php while ($tipo = $tipos->fetch_assoc()): ?>
                            <option value="<?php echo escapar($tipo['tipo']); ?>"<?php echo $tipoSeleccionado === $tipo['tipo'] ? ' selected' : ''; ?>>
                                <?php echo escapar($tipo['tipo']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="campo">
                    <label for="comuna">Comuna</label>
                    <select id="comuna" name="comuna">
                        <option value="">Todas las comunas</option>
                        <?php while ($comuna = $comunas->fetch_assoc()): ?>
                            <option value="<?php echo escapar($comuna['comuna']); ?>"<?php echo $comunaSeleccionada === $comuna['comuna'] ? ' selected' : ''; ?>>
                                <?php echo escapar($comuna['comuna']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="acciones-filtros">
                    <button class="boton" type="submit">Buscar <span aria-hidden="true">→</span></button>
                    <a class="boton boton-secundario" href="canchas.php">Limpiar</a>
                </div>
            </form>

            <p class="resultado-catalogo">
                <?php echo (int) $escenarios->num_rows; ?>
                <?php echo $escenarios->num_rows === 1 ? 'escenario encontrado' : 'escenarios encontrados'; ?>
            </p>

            <?php if ($escenarios->num_rows === 0): ?>
                <div class="estado-vacio">
                    <h2>No encontramos escenarios</h2>
                    <p>Prueba con otro nombre, deporte o comuna.</p>
                    <a class="boton boton-secundario" href="canchas.php">Ver todos los escenarios</a>
                </div>
            <?php else: ?>
                <div class="rejilla catalogo-grid">
                    <?php while ($escenario = $escenarios->fetch_assoc()): ?>
                        <?php
                        $imagen = $escenario['imagen'] ? $escenario['imagen'] : 'img/futbol.jpg';
                        $ubicacion = array_filter(array($escenario['barrio'], $escenario['comuna'] ? 'Comuna ' . $escenario['comuna'] : ''));
                        $horario = $escenario['hora_apertura'] && $escenario['hora_cierre']
                            ? substr($escenario['hora_apertura'], 0, 5) . ' - ' . substr($escenario['hora_cierre'], 0, 5)
                            : 'Horario por verificar';
                        ?>
                        <article class="cancha-card">
                            <a class="cancha-foto" href="reservar.php?escenario=<?php echo (int) $escenario['id']; ?>">
                                <img src="<?php echo escapar($imagen); ?>" alt="<?php echo escapar($escenario['nombre']); ?>" loading="lazy">
                                <span class="deporte-etiqueta"><?php echo escapar($escenario['tipo']); ?></span>
                            </a>
                            <div class="cancha-detalle">
                                <p class="cancha-unidad"><?php echo escapar($escenario['unidad_deportiva']); ?></p>
                                <h2><?php echo escapar($escenario['nombre']); ?></h2>
                                <dl class="cancha-datos">
                                    <div><dt>Ubicación</dt><dd><?php echo escapar($ubicacion ? implode(' · ', $ubicacion) : 'Por verificar'); ?></dd></div>
                                    <div><dt>Superficie</dt><dd><?php echo escapar($escenario['superficie'] ? $escenario['superficie'] : 'Por verificar'); ?></dd></div>
                                    <div><dt>Horario</dt><dd><?php echo escapar($horario); ?></dd></div>
                                </dl>
                                <?php if (!$escenario['hora_apertura'] || !$escenario['hora_cierre']): ?>
                                    <p class="aviso-verificacion">El horario oficial está pendiente de confirmación.</p>
                                <?php endif; ?>
                                <a class="boton cancha-reservar" href="reservar.php?escenario=<?php echo (int) $escenario['id']; ?>">
                                    Reservar <span aria-hidden="true">↗</span>
                                </a>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
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
$consulta->close();
$consultaTipos->close();
$consultaComunas->close();
?>
