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
$idEscenario = filter_input(INPUT_POST, 'escenario', FILTER_VALIDATE_INT);
$fecha = textoEntrada($_POST, 'fecha');
$horaInicio = textoEntrada($_POST, 'hora_inicio');

if (!$id || $id < 1) {
    volverAMisReservas('La reserva indicada no es válida.');
}

$transaccionActiva = false;
try {
    $conexion->begin_transaction();
    $transaccionActiva = true;

    // Bloquea la cuenta antes de validar su límite de reservas semanales.
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
        'SELECT fecha, hora_inicio, estado
         FROM reservas
         WHERE id = ? AND id_usuario = ?
         FOR UPDATE'
    );
    $consulta->bind_param('ii', $id, $idUsuario);
    $consulta->execute();
    $resultado = $consulta->get_result();
    $reservaActual = $resultado->fetch_assoc();
    $consulta->close();

    if (!$reservaActual) {
        $conexion->rollback();
        $transaccionActiva = false;
        volverAMisReservas('No tienes permiso para modificar esta reserva.');
    }

    if (!in_array($reservaActual['estado'], array('Activa', 'Modificada'), true)) {
        $conexion->rollback();
        $transaccionActiva = false;
        volverAMisReservas('Solo se pueden editar reservas activas.');
    }

    if (!puedeModificarReserva($reservaActual['fecha'], $reservaActual['hora_inicio'])) {
        $conexion->rollback();
        $transaccionActiva = false;
        volverAMisReservas(
            'Solo puedes cancelar hasta ' . HORAS_ANTICIPACION_CANCELACION . ' horas antes de la reserva.'
        );
    }

    $consulta = $conexion->prepare(
        'SELECT id FROM escenarios WHERE id = ? AND activo = 1 FOR UPDATE'
    );
    $consulta->bind_param('i', $idEscenario);
    $consulta->execute();
    $consulta->store_result();
    $escenarioActivo = $consulta->num_rows > 0;
    $consulta->close();

    if (!$escenarioActivo) {
        $conexion->rollback();
        $transaccionActiva = false;
        volverAMisReservas('El escenario seleccionado no está activo.');
    }

    $errorValidacion = validarDatosReserva(
        $conexion,
        $idEscenario,
        $fecha,
        $horaInicio,
        (int) $id
    );
    if ($errorValidacion !== '') {
        $conexion->rollback();
        $transaccionActiva = false;
        volverAMisReservas($errorValidacion);
    }

    $errorLimite = validarLimitesUsuario(
        $conexion,
        $idUsuario,
        $fecha,
        $horaInicio,
        (int) $id
    );
    if ($errorLimite !== '') {
        $conexion->rollback();
        $transaccionActiva = false;
        volverAMisReservas($errorLimite);
    }

    $horaFin = date('H:i:s', strtotime($horaInicio . ' +1 hour'));
    if (existeSolapamiento($conexion, $idEscenario, $fecha, $horaInicio, $horaFin, (int) $id)) {
        $conexion->rollback();
        $transaccionActiva = false;
        volverAMisReservas('Ese horario se cruza con otra reserva activa.');
    }

    $consulta = $conexion->prepare(
        "UPDATE reservas
         SET id_escenario = ?, fecha = ?, hora_inicio = ?, hora_fin = ?, estado = 'Modificada'
         WHERE id = ? AND id_usuario = ?"
    );
    $consulta->bind_param(
        'isssii',
        $idEscenario,
        $fecha,
        $horaInicio,
        $horaFin,
        $id,
        $idUsuario
    );
    $consulta->execute();
    $consulta->close();

    $conexion->commit();
    $transaccionActiva = false;
    volverAMisReservas('La reserva se modificó correctamente.', 'exito');
} catch (mysqli_sql_exception $error) {
    if ($transaccionActiva) {
        $conexion->rollback();
    }
    error_log('Error al modificar reserva: ' . $error->getMessage());
    volverAMisReservas('No se pudo modificar la reserva. Intenta nuevamente.');
}
