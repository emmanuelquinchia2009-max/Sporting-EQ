<?php
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/sesion.php';

exigirLogin('mis_reservas.php');
$idUsuario = idUsuarioActual();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    volverAMisReservas('Acceso no permitido.');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    volverAMisReservas('La reserva indicada no es válida.');
}

$transaccionActiva = false;
try {
    $conexion->begin_transaction();
    $transaccionActiva = true;

    $consulta = $conexion->prepare('SELECT id FROM usuarios WHERE id = ? FOR UPDATE');
    $consulta->bind_param('i', $idUsuario);
    $consulta->execute();
    $consulta->store_result();
    $usuarioExiste = $consulta->num_rows > 0;
    $consulta->close();

    if (!$usuarioExiste) {
        $conexion->rollback();
        $transaccionActiva = false;
        volverAMisReservas('La sesión no corresponde a una cuenta válida. Inicia sesión nuevamente.');
    }

    $consulta = $conexion->prepare(
        'SELECT id_escenario, fecha, hora_inicio, estado
         FROM reservas
         WHERE id = ? AND id_usuario = ?
         FOR UPDATE'
    );
    $consulta->bind_param('ii', $id, $idUsuario);
    $consulta->execute();
    $resultado = $consulta->get_result();
    $reserva = $resultado->fetch_assoc();
    $consulta->close();

    if (!$reserva) {
        $conexion->rollback();
        $transaccionActiva = false;
        volverAMisReservas('No tienes permiso para modificar esta reserva.');
    }

    if (!in_array($reserva['estado'], array('Activa', 'Modificada'), true)) {
        $conexion->rollback();
        $transaccionActiva = false;
        volverAMisReservas('La reserva no está activa y no se puede cancelar.');
    }

    if (!puedeModificarReserva($reserva['fecha'], $reserva['hora_inicio'])) {
        $conexion->rollback();
        $transaccionActiva = false;
        volverAMisReservas(
            'Solo puedes cancelar hasta ' . HORAS_ANTICIPACION_CANCELACION . ' horas antes de la reserva.'
        );
    }

    $consulta = $conexion->prepare(
        'SELECT id FROM escenarios WHERE id = ? FOR UPDATE'
    );
    $consulta->bind_param('i', $reserva['id_escenario']);
    $consulta->execute();
    $consulta->store_result();
    $consulta->close();

    $consulta = $conexion->prepare(
        "UPDATE reservas
         SET estado = 'Cancelada'
         WHERE id = ?
           AND id_usuario = ?
           AND estado IN ('Activa', 'Modificada')
           AND TIMESTAMP(fecha, hora_inicio) >= DATE_ADD(NOW(), INTERVAL ? HOUR)"
    );
    $horasAnticipacion = HORAS_ANTICIPACION_CANCELACION;
    $consulta->bind_param('iii', $id, $idUsuario, $horasAnticipacion);
    $consulta->execute();
    $filasActualizadas = $consulta->affected_rows;
    $consulta->close();

    if ($filasActualizadas === 0) {
        $conexion->rollback();
        $transaccionActiva = false;
        volverAMisReservas(
            'Solo puedes cancelar hasta ' . HORAS_ANTICIPACION_CANCELACION . ' horas antes de la reserva.'
        );
    }

    $conexion->commit();
    $transaccionActiva = false;
    volverAMisReservas('La reserva se canceló y su horario quedó disponible para otra solicitud.', 'exito');
} catch (mysqli_sql_exception $error) {
    if ($transaccionActiva) {
        $conexion->rollback();
    }
    error_log('Error al cancelar reserva: ' . $error->getMessage());
    volverAMisReservas('No se pudo cancelar la reserva. Intenta nuevamente.');
}
