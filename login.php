<?php
require_once __DIR__ . '/php/conexion.php';
require_once __DIR__ . '/php/funciones.php';
require_once __DIR__ . '/php/sesion.php';

iniciarSesion();
if (idUsuarioActual() > 0) {
    header('Location: mis_reservas.php');
    exit;
}

$volver = destinoDespuesLogin(textoEntrada($_GET, 'volver'));
$mensaje = '';
$correo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = strtolower(textoEntrada($_POST, 'correo'));
    $contrasena = textoEntrada($_POST, 'contrasena');
    $volver = destinoDespuesLogin(textoEntrada($_POST, 'volver'));

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || $contrasena === '') {
        $mensaje = 'Escribe tu correo y contraseña.';
    } else {
        try {
            $consulta = $conexion->prepare(
                'SELECT id, nombre, contrasena, rol FROM usuarios WHERE correo = ? LIMIT 1'
            );
            $consulta->bind_param('s', $correo);
            $consulta->execute();
            $resultado = $consulta->get_result();
            $usuario = $resultado->fetch_assoc();
            $consulta->close();

            if (!$usuario || !password_verify($contrasena, $usuario['contrasena'])) {
                $mensaje = 'El correo o la contraseña no son correctos.';
            } else {
                session_regenerate_id(true);
                $_SESSION['usuario'] = array(
                    'id' => (int) $usuario['id'],
                    'nombre' => $usuario['nombre'],
                    'rol' => $usuario['rol']
                );
                header('Location: ' . $volver);
                exit;
            }
        } catch (mysqli_sql_exception $error) {
            error_log('Error al iniciar sesión: ' . $error->getMessage());
            $mensaje = 'No se pudo iniciar sesión. Intenta nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Inicia sesión para administrar tus reservas deportivas.">
    <title>Iniciar sesión | Cancha</title>
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
                <li><a href="registro.php">Registrarme</a></li>
            </ul></nav>
            <a class="boton boton-nav" href="registro.php">Crear cuenta</a>
        </div>
    </header>
    <main class="contenedor pagina-formulario contenido-estrecho">
        <section aria-labelledby="titulo-login">
            <div class="encabezado-pagina">
                <p class="etiqueta">QUÉ BUENO TENERTE DE VUELTA</p>
                <h1 id="titulo-login">Inicia sesión.</h1>
            </div>
            <?php if ($mensaje !== ''): ?>
                <div class="mensaje error" role="alert"><?php echo escapar($mensaje); ?></div>
            <?php endif; ?>
            <form class="panel-formulario formulario" action="login.php" method="post" data-validacion>
                <div id="mensaje-validacion" class="mensaje error" role="alert" aria-live="assertive" hidden></div>
                <input type="hidden" name="volver" value="<?php echo escapar($volver); ?>">
                <div class="campo">
                    <label for="correo">Correo electrónico</label>
                    <input type="email" id="correo" name="correo" autocomplete="email" value="<?php echo escapar($correo); ?>" required>
                </div>
                <div class="campo">
                    <label for="contrasena">Contraseña</label>
                    <input type="password" id="contrasena" name="contrasena" autocomplete="current-password" required>
                </div>
                <div class="acciones-formulario">
                    <button class="boton" type="submit">Iniciar sesión <span aria-hidden="true">→</span></button>
                    <a class="boton boton-secundario" href="registro.php">Crear cuenta</a>
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
