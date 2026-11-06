<?php
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/sesion.php';

exigirLogin('reservar.php');
$idUsuario = idUsuarioActual();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    volverAMisReservas('Acceso no permitido.');
}

$idEscenario = filter_input(INPUT_POST, 'escenario', FILTER_VALIDATE_INT);
$fecha = textoEntrada($_POST, 'fecha');
$horaInicio = textoEntrada($_POST, 'hora_inicio');

$errorValidacion = validarDatosReserva(
    $conexion,
    $idEscenario,
    $fecha,
    $horaInicio
);
if ($errorValidacion !== '') {
    volverAMisReservas($errorValidacion);
}

$transaccionActiva = false;
try {
    $conexion->begin_transaction();
    $transaccionActiva = true;

    // Serializa las reservas de la misma cuenta para respetar el límite semanal.
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

    // Bloquea el escenario mientras se revisa su disponibilidad.
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
        $horaInicio
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
        $horaInicio
    );
    if ($errorLimite !== '') {
        $conexion->rollback();
        $transaccionActiva = false;
        volverAMisReservas($errorLimite);
    }

    $horaFin = date('H:i:s', strtotime($horaInicio . ' +1 hour'));
    if (existeSolapamiento($conexion, $idEscenario, $fecha, $horaInicio, $horaFin)) {
        $conexion->rollback();
        $transaccionActiva = false;
        volverAMisReservas('Ese horario se cruza con otra reserva activa.');
    }

    $consulta = $conexion->prepare(
        "INSERT INTO reservas (id_escenario, id_usuario, fecha, hora_inicio, hora_fin, estado)
         VALUES (?, ?, ?, ?, ?, 'Activa')"
    );
    $consulta->bind_param(
        'iisss',
        $idEscenario,
        $idUsuario,
        $fecha,
        $horaInicio,
        $horaFin
    );
    $consulta->execute();
    $consulta->close();

    $conexion->commit();
    $transaccionActiva = false;
    volverAMisReservas('La reserva se creó correctamente.', 'exito');
} catch (mysqli_sql_exception $error) {
    if ($transaccionActiva) {
        $conexion->rollback();
    }
    error_log('Error al crear reserva: ' . $error->getMessage());
    volverAMisReservas('No se pudo guardar la reserva. Intenta nuevamente.');
}
