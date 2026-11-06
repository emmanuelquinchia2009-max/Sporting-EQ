<?php
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/funciones.php';

header('Content-Type: application/json; charset=utf-8');

$idEscenario = filter_input(INPUT_GET, 'escenario', FILTER_VALIDATE_INT);
$fecha = textoEntrada($_GET, 'fecha');
$idExcluir = filter_input(INPUT_GET, 'reserva', FILTER_VALIDATE_INT);
$idExcluir = $idExcluir ? (int) $idExcluir : 0;

if (!$idEscenario || !fechaEnRangoDeReserva($fecha)) {
    http_response_code(400);
    echo json_encode(array(
        'franjas' => array(),
        'mensaje' => 'Selecciona un escenario y una fecha entre hoy y los próximos 30 días.'
    ));
    exit;
}

try {
    $escenario = obtenerEscenario($conexion, (int) $idEscenario);
    if (!$escenario) {
        http_response_code(404);
        echo json_encode(array('franjas' => array(), 'mensaje' => 'El escenario no está disponible.'));
        exit;
    }

    $franjas = obtenerFranjasDisponibles($conexion, (int) $idEscenario, $fecha, $idExcluir);
    $mensaje = '';
    if (!$escenario['hora_apertura'] || !$escenario['hora_cierre']) {
        $mensaje = 'El horario oficial del escenario está pendiente de verificación.';
    } elseif (!$franjas) {
        $mensaje = 'No hay franjas disponibles para esta fecha.';
    }

    echo json_encode(
        array('franjas' => $franjas, 'mensaje' => $mensaje),
        JSON_UNESCAPED_UNICODE
    );
} catch (mysqli_sql_exception $error) {
    error_log('Error al consultar franjas: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(array('franjas' => array(), 'mensaje' => 'No fue posible consultar los horarios.'));
}
