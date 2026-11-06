<?php
require_once __DIR__ . '/config.php';

// Escapa texto antes de insertarlo en una página HTML.
function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

// Evita errores si una solicitud envía un arreglo donde se espera texto.
function textoEntrada($datos, $nombre)
{
    return isset($datos[$nombre]) && is_string($datos[$nombre])
        ? trim($datos[$nombre])
        : '';
}

// Redirige al listado con un mensaje sencillo.
function volverAReservas($mensaje, $tipo = 'error')
{
    $parametros = http_build_query(array('mensaje' => $mensaje, 'tipo' => $tipo));
    header('Location: ../reservas.php?' . $parametros);
    exit;
}

function volverAMisReservas($mensaje, $tipo = 'error')
{
    $parametros = http_build_query(array('mensaje' => $mensaje, 'tipo' => $tipo));
    header('Location: ../mis_reservas.php?' . $parametros);
    exit;
}

// Calcula el estado finalizado para mostrarlo sin guardarlo en la base de datos.
function estadoVisibleReserva($estado, $fecha, $horaFin)
{
    if (
        in_array($estado, array('Activa', 'Modificada'), true)
        && strtotime($fecha . ' ' . $horaFin) <= time()
    ) {
        return 'Finalizada';
    }
    return $estado;
}

// Una reserva solo se modifica o cancela hasta el límite configurado.
function puedeModificarReserva($fecha, $horaInicio)
{
    $inicio = strtotime($fecha . ' ' . $horaInicio);
    return $inicio !== false
        && $inicio >= time() + (HORAS_ANTICIPACION_CANCELACION * 3600);
}

// Obtiene el lunes y el lunes siguiente para la semana de la fecha elegida.
function rangoSemanaReserva($fecha)
{
    $inicio = DateTime::createFromFormat('!Y-m-d', $fecha);
    if (!$inicio || $inicio->format('Y-m-d') !== $fecha) {
        return false;
    }

    $inicio->modify('monday this week');
    $fin = clone $inicio;
    $fin->modify('+7 days');

    return array($inicio->format('Y-m-d'), $fin->format('Y-m-d'));
}

// Revisa el máximo semanal y si el usuario ya tiene esa hora en otra cancha.
function validarLimitesUsuario($conexion, $idUsuario, $fecha, $horaInicio, $idExcluir = 0)
{
    $rangoSemana = rangoSemanaReserva($fecha);
    if (!$rangoSemana) {
        return 'La fecha de la reserva no es válida.';
    }

    $consulta = $conexion->prepare(
        "SELECT COUNT(*)
         FROM reservas
         WHERE id_usuario = ?
           AND fecha >= ?
           AND fecha < ?
           AND estado IN ('Activa', 'Modificada')
           AND TIMESTAMP(fecha, hora_fin) > NOW()
           AND (? = 0 OR id <> ?)"
    );
    $consulta->bind_param(
        'issii',
        $idUsuario,
        $rangoSemana[0],
        $rangoSemana[1],
        $idExcluir,
        $idExcluir
    );
    $consulta->execute();
    $consulta->bind_result($cantidadSemana);
    $consulta->fetch();
    $consulta->close();

    if ($cantidadSemana >= MAX_RESERVAS_ACTIVAS_SEMANA) {
        return 'Solo puedes tener ' . MAX_RESERVAS_ACTIVAS_SEMANA . ' reservas activas por semana (de lunes a domingo).';
    }

    $consulta = $conexion->prepare(
        "SELECT id
         FROM reservas
         WHERE id_usuario = ?
           AND fecha = ?
           AND hora_inicio = ?
           AND estado IN ('Activa', 'Modificada')
           AND (? = 0 OR id <> ?)
         LIMIT 1"
    );
    $consulta->bind_param(
        'issii',
        $idUsuario,
        $fecha,
        $horaInicio,
        $idExcluir,
        $idExcluir
    );
    $consulta->execute();
    $consulta->store_result();
    $horaYaReservada = $consulta->num_rows > 0;
    $consulta->close();

    if ($horaYaReservada) {
        return 'Ya tienes otra reserva activa en esa fecha y hora.';
    }

    return '';
}

// Busca un escenario activo por su identificador.
function obtenerEscenario($conexion, $id)
{
    $consulta = $conexion->prepare(
        'SELECT id, nombre, unidad_deportiva, tipo, hora_apertura, hora_cierre
         FROM escenarios WHERE id = ? AND activo = 1'
    );
    $consulta->bind_param('i', $id);
    $consulta->execute();
    $resultado = $consulta->get_result();
    $escenario = $resultado->fetch_assoc();
    $consulta->close();

    return $escenario;
}

// Comprueba que la fecha sea hoy o esté dentro de los próximos 30 días.
function fechaEnRangoDeReserva($fecha)
{
    $fechaValida = DateTime::createFromFormat('!Y-m-d', $fecha);
    if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
        return false;
    }

    $hoy = new DateTime('today');
    $limite = (clone $hoy)->modify('+30 days');
    return $fechaValida >= $hoy && $fechaValida <= $limite;
}

// Genera franjas disponibles de una hora respetando el horario del escenario.
function obtenerFranjasDisponibles($conexion, $idEscenario, $fecha, $idExcluir = 0)
{
    $escenario = obtenerEscenario($conexion, $idEscenario);
    if (
        !$escenario
        || !$escenario['hora_apertura']
        || !$escenario['hora_cierre']
        || !fechaEnRangoDeReserva($fecha)
    ) {
        return array();
    }

    $apertura = DateTime::createFromFormat('!H:i:s', $escenario['hora_apertura']);
    $cierre = DateTime::createFromFormat('!H:i:s', $escenario['hora_cierre']);
    if (!$apertura || !$cierre || $apertura >= $cierre) {
        return array();
    }

    $consulta = $conexion->prepare(
        "SELECT hora_inicio, hora_fin
         FROM reservas
         WHERE id_escenario = ?
           AND fecha = ?
           AND estado IN ('Activa', 'Modificada')
           AND (? = 0 OR id <> ?)"
    );
    $consulta->bind_param('isii', $idEscenario, $fecha, $idExcluir, $idExcluir);
    $consulta->execute();
    $resultado = $consulta->get_result();
    $ocupadas = $resultado->fetch_all(MYSQLI_ASSOC);
    $consulta->close();

    $ahora = new DateTime();
    $hoy = date('Y-m-d');
    $franjas = array();
    $inicio = clone $apertura;

    while (true) {
        $fin = (clone $inicio)->modify('+1 hour');
        if ($fin > $cierre) {
            break;
        }

        $horaInicio = $inicio->format('H:i:s');
        $horaFin = $fin->format('H:i:s');
        $inicioCompleto = DateTime::createFromFormat('Y-m-d H:i:s', $fecha . ' ' . $horaInicio);
        $yaPaso = $fecha === $hoy && $inicioCompleto <= $ahora;
        $seCruza = false;

        foreach ($ocupadas as $reserva) {
            if ($horaInicio < $reserva['hora_fin'] && $horaFin > $reserva['hora_inicio']) {
                $seCruza = true;
                break;
            }
        }

        if (!$yaPaso && !$seCruza) {
            $franjas[] = array(
                'inicio' => substr($horaInicio, 0, 5),
                'fin' => substr($horaFin, 0, 5),
                'etiqueta' => substr($horaInicio, 0, 5) . ' - ' . substr($horaFin, 0, 5)
            );
        }

        $inicio = $fin;
    }

    return $franjas;
}

// Comprueba en SQL si la franja se cruza con otra reserva vigente.
function existeSolapamiento($conexion, $idEscenario, $fecha, $horaInicio, $horaFin, $idExcluir = 0)
{
    $consulta = $conexion->prepare(
        "SELECT id
         FROM reservas
         WHERE id_escenario = ?
           AND fecha = ?
           AND estado IN ('Activa', 'Modificada')
           AND hora_inicio < ?
           AND hora_fin > ?
           AND (? = 0 OR id <> ?)
         LIMIT 1"
    );
    $consulta->bind_param(
        'isssii',
        $idEscenario,
        $fecha,
        $horaFin,
        $horaInicio,
        $idExcluir,
        $idExcluir
    );
    $consulta->execute();
    $consulta->store_result();
    $haySolapamiento = $consulta->num_rows > 0;
    $consulta->close();

    return $haySolapamiento;
}

// Valida escenario, fecha y franja tanto al crear como al editar.
function validarDatosReserva($conexion, $idEscenario, $fecha, $hora, $idExcluir = 0)
{
    if (!filter_var($idEscenario, FILTER_VALIDATE_INT) || (int) $idEscenario < 1) {
        return 'Selecciona un escenario deportivo válido.';
    }

    if (!fechaEnRangoDeReserva($fecha)) {
        return 'La fecha debe estar entre hoy y los próximos 30 días.';
    }

    if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $hora)) {
        return 'Selecciona una franja horaria válida.';
    }

    $franjas = obtenerFranjasDisponibles($conexion, (int) $idEscenario, $fecha, (int) $idExcluir);
    foreach ($franjas as $franja) {
        if ($franja['inicio'] === $hora) {
            return '';
        }
    }

    $escenario = obtenerEscenario($conexion, (int) $idEscenario);
    if (!$escenario) {
        return 'El escenario seleccionado no está activo.';
    }
    if (!$escenario['hora_apertura'] || !$escenario['hora_cierre']) {
        return 'El horario de este escenario está pendiente de verificación.';
    }

    return 'La franja ya pasó, está ocupada o no está dentro del horario del escenario.';
}

// Oculta parte del número de contacto en el listado.
function enmascararTelefono($telefono)
{
    return substr($telefono, 0, 3) . '***' . substr($telefono, -4);
}
