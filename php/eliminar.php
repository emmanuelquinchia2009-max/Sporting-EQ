<?php
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/sesion.php';

// Pueden eliminar el administrador (cualquier reserva) y el usuario (solo las suyas).
exigirLogin('mis_reservas.php');
$idUsuario = idUsuarioActual();
$esAdmin = esAdmin();

// Define a qué página se vuelve después de eliminar.
$origen = textoEntrada($_POST, 'origen');
$volverAMisReservas = !$esAdmin || $origen === 'mis_reservas';

function responderEliminar($mensaje, $tipo, $volverAMisReservas)
{
    if ($volverAMisReservas) {
        volverAMisReservas($mensaje, $tipo);
    }
    volverAReservas($mensaje, $tipo);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderEliminar('Acceso no permitido.', 'error', $volverAMisReservas);
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    responderEliminar('La reserva indicada no es válida.', 'error', $volverAMisReservas);
}

try {
    // Solo se eliminan reservas canceladas o que aún no han empezado.
    // El admin puede borrar cualquiera; el usuario únicamente las suyas.
    $consulta = $conexion->prepare(
        "DELETE FROM reservas
         WHERE id = ?
           AND (? = 1 OR id_usuario = ?)
           AND (estado = 'Cancelada'
                OR (estado IN ('Activa', 'Modificada') AND TIMESTAMP(fecha, hora_inicio) > NOW()))"
    );
    $admin = $esAdmin ? 1 : 0;
    $consulta->bind_param('iii', $id, $admin, $idUsuario);
    $consulta->execute();
    $filasEliminadas = $consulta->affected_rows;
    $consulta->close();

    if ($filasEliminadas === 0) {
        responderEliminar(
            'No se pudo eliminar: solo se eliminan tus reservas canceladas o futuras.',
            'error',
            $volverAMisReservas
        );
    }
    responderEliminar(
        'La reserva se eliminó definitivamente y el horario quedó disponible.',
        'exito',
        $volverAMisReservas
    );
} catch (mysqli_sql_exception $error) {
    error_log('Error al eliminar reserva: ' . $error->getMessage());
    responderEliminar('No se pudo eliminar la reserva. Intenta nuevamente.', 'error', $volverAMisReservas);
}
