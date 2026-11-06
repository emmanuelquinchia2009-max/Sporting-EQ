<?php
require_once __DIR__ . '/config.php';

// Inicia una sesión segura una sola vez por solicitud.
function iniciarSesion()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(array(
            'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax'
        ));
        session_start();
    }
}

function usuarioActual()
{
    iniciarSesion();
    return isset($_SESSION['usuario']) && is_array($_SESSION['usuario'])
        ? $_SESSION['usuario']
        : null;
}

function idUsuarioActual()
{
    $usuario = usuarioActual();
    return isset($usuario['id']) && filter_var($usuario['id'], FILTER_VALIDATE_INT)
        ? (int) $usuario['id']
        : 0;
}

function esAdmin()
{
    $usuario = usuarioActual();
    return $usuario && isset($usuario['rol']) && $usuario['rol'] === 'admin';
}

// Solo permite volver a páginas locales conocidas después de iniciar sesión.
function destinoDespuesLogin($destino)
{
    $ruta = basename((string) parse_url($destino, PHP_URL_PATH));
    if (in_array($ruta, array('mis_reservas.php', 'reservas.php'), true)) {
        return $ruta;
    }

    if ($ruta === 'reservar.php') {
        $parametros = array();
        $consulta = parse_url($destino, PHP_URL_QUERY);
        if ($consulta) {
            parse_str($consulta, $recibidos);
            if (
                isset($recibidos['escenario'])
                && is_scalar($recibidos['escenario'])
                && filter_var($recibidos['escenario'], FILTER_VALIDATE_INT)
                && (int) $recibidos['escenario'] > 0
            ) {
                $parametros['escenario'] = (int) $recibidos['escenario'];
            }
            if (
                isset($recibidos['fecha'])
                && is_string($recibidos['fecha'])
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $recibidos['fecha'])
            ) {
                $parametros['fecha'] = $recibidos['fecha'];
            }
            if (
                isset($recibidos['hora'])
                && is_string($recibidos['hora'])
                && preg_match('/^(?:[01]\d|2[0-3]):00$/', $recibidos['hora'])
            ) {
                $parametros['hora'] = $recibidos['hora'];
            }
        }
        return 'reservar.php' . ($parametros ? '?' . http_build_query($parametros) : '');
    }

    return 'mis_reservas.php';
}

function exigirLogin($paginaDestino = 'mis_reservas.php')
{
    if (idUsuarioActual() > 0) {
        return;
    }

    $destino = destinoDespuesLogin($paginaDestino);
    $prefijo = strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME']), '/php/') !== false
        ? '../'
        : '';
    header('Location: ' . $prefijo . 'login.php?volver=' . urlencode($destino));
    exit;
}

function exigirAdmin()
{
    if (idUsuarioActual() < 1) {
        exigirLogin('reservas.php');
    }
    if (!esAdmin()) {
        $prefijo = strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME']), '/php/') !== false
            ? '../'
            : '';
        header('Location: ' . $prefijo . 'mis_reservas.php?mensaje=' . urlencode('No tienes permiso para acceder a esta página.'));
        exit;
    }
}
