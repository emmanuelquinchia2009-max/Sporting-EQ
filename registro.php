<?php
require_once __DIR__ . '/php/conexion.php';
require_once __DIR__ . '/php/funciones.php';
require_once __DIR__ . '/php/sesion.php';

iniciarSesion();
if (idUsuarioActual() > 0) {
    header('Location: mis_reservas.php');
    exit;
}

$mensaje = '';
$nombre = '';
$telefono = '';
$correo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = textoEntrada($_POST, 'nombre');
    $telefono = textoEntrada($_POST, 'telefono');
    $correo = strtolower(textoEntrada($_POST, 'correo'));
    $contrasena = textoEntrada($_POST, 'contrasena');

    if (
        $nombre === ''
        || !preg_match('/^[\p{L}\p{M} ]+$/u', $nombre)
        || mb_strlen($nombre, 'UTF-8') > 100
    ) {
        $mensaje = 'Escribe tu nombre usando solo letras y espacios (máximo 100 caracteres).';
    } elseif (!preg_match('/^3[0-9]{9}$/', $telefono)) {
        $mensaje = 'El celular debe tener 10 dígitos y comenzar por 3.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 150) {
        $mensaje = 'Escribe un correo electrónico válido.';
    } elseif (strlen($contrasena) < 8) {
        $mensaje = 'La contraseña debe tener al menos 8 caracteres.';
    } else {
        try {
            $consulta = $conexion->prepare(
                'SELECT id FROM usuarios WHERE telefono = ? OR correo = ? LIMIT 1'
            );
            $consulta->bind_param('ss', $telefono, $correo);
            $consulta->execute();
            $consulta->store_result();
            $usuarioExiste = $consulta->num_rows > 0;
            $consulta->close();

            if ($usuarioExiste) {
                $mensaje = 'Ya existe una cuenta con ese celular o correo.';
            } else {
                $hash = password_hash($contrasena, PASSWORD_DEFAULT);
                $consulta = $conexion->prepare(
                    "INSERT INTO usuarios (nombre, telefono, correo, contrasena, rol)
                     VALUES (?, ?, ?, ?, 'usuario')"
                );
                $consulta->bind_param('ssss', $nombre, $telefono, $correo, $hash);
                $consulta->execute();
                $idUsuario = $conexion->insert_id;
                $consulta->close();

                session_regenerate_id(true);
                $_SESSION['usuario'] = array(
                    'id' => $idUsuario,
                    'nombre' => $nombre,
                    'rol' => 'usuario'
                );
                header('Location: mis_reservas.php?mensaje=' . urlencode('Tu cuenta se creó correctamente.') . '&tipo=exito');
                exit;
            }
        } catch (mysqli_sql_exception $error) {
            error_log('Error al registrar usuario: ' . $error->getMessage());
            $mensaje = 'No se pudo crear la cuenta. Intenta nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Crea una cuenta para reservar escenarios deportivos.">
    <title>Crear cuenta | Cancha</title>
    <link rel="stylesheet" href="css/estilos.css">
    <script src="js/validaciones.js" defer></script>
</head>
<body>
    <header class="encabezado">
        <div class="contenedor encabezado-interior">
            <a class="marca" href="index.php"><span class="marca-icono" aria-hidden="true">C</span><span>CANCHA<span class="marca-punto">.</span><small>DEPORTE PARA TODOS</small></span></a>
            <nav aria-label="Navegación principal"><ul class="navegacion">
                <li><a href="index.php">Inicio</a></li>
                <li><a href="canchas.php">Canchas</a></li>
                <li><a href="login.php">Iniciar sesión</a></li>
            </ul></nav>
            <a class="boton boton-nav" href="login.php">Iniciar sesión</a>
        </div>
    </header>
    <main class="contenedor pagina-formulario contenido-estrecho">
        <section aria-labelledby="titulo-registro">
            <div class="encabezado-pagina">
                <p class="etiqueta">ÚNETE A LA COMUNIDAD</p>
                <h1 id="titulo-registro">Crea tu cuenta.</h1>
                <p class="texto-secundario">Usaremos tus datos para identificar tus reservas.</p>
            </div>
            <?php if ($mensaje !== ''): ?>
                <div class="mensaje error" role="alert"><?php echo escapar($mensaje); ?></div>
            <?php endif; ?>
            <form class="panel-formulario formulario" action="registro.php" method="post" data-validacion>
                <div id="mensaje-validacion" class="mensaje error" role="alert" aria-live="assertive" hidden></div>
                <div class="campo">
                    <label for="nombre">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre" maxlength="100" autocomplete="name" value="<?php echo escapar($nombre); ?>" required>
                </div>
                <div class="campo">
                    <label for="telefono">Celular colombiano</label>
                    <input type="tel" id="telefono" name="telefono" inputmode="numeric" pattern="3[0-9]{9}" minlength="10" maxlength="10" autocomplete="tel" value="<?php echo escapar($telefono); ?>" required>
                    <small>10 dígitos y comienza por 3.</small>
                </div>
                <div class="campo">
                    <label for="correo">Correo electrónico</label>
                    <input type="email" id="correo" name="correo" maxlength="150" autocomplete="email" value="<?php echo escapar($correo); ?>" required>
                </div>
                <div class="campo">
                    <label for="contrasena">Contraseña</label>
                    <input type="password" id="contrasena" name="contrasena" minlength="8" autocomplete="new-password" required>
                    <small>Mínimo 8 caracteres.</small>
                </div>
                <div class="acciones-formulario">
                    <button class="boton" type="submit">Crear cuenta <span aria-hidden="true">→</span></button>
                    <a class="boton boton-secundario" href="login.php">Ya tengo cuenta</a>
                </div>
            </form>
        </section>
    </main>
    <footer class="pie"><div class="contenedor pie-interior">
        <a class="marca marca-pie" href="index.php"><span class="marca-icono" aria-hidden="true">C</span><span>CANCHA<span class="marca-punto">.</span><small>DEPORTE PARA TODOS</small></span></a>
        <p>Tu comunidad, tu cancha, tu juego.</p>
        <a href="canchas.php">Explorar canchas <span aria-hidden="true">↗</span></a>
    </div></footer>
</body>
</html>
