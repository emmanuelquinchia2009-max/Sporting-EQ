<?php
// Conexión local de XAMPP a MySQL.
date_default_timezone_set('America/Bogota');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$servidor = 'localhost';
$usuario = 'root';
$contrasena = '';
$baseDatos = 'reservas_deportivas';
$puertos = array(3306, 3307);
$conexion = null;
$erroresConexion = array();

foreach ($puertos as $puerto) {
    try {
        $conexion = new mysqli(
            $servidor,
            $usuario,
            $contrasena,
            $baseDatos,
            $puerto
        );
        $conexion->set_charset('utf8mb4');
        $conexion->query("SET time_zone = '-05:00'");
        break;
    } catch (mysqli_sql_exception $error) {
        $erroresConexion[] = 'Puerto ' . $puerto . ': ' . $error->getMessage();
    }
}

if (!$conexion) {
    error_log('No fue posible conectar con MySQL. ' . implode(' | ', $erroresConexion));
    http_response_code(500);
    exit('No fue posible conectar con MySQL en los puertos 3306 ni 3307. Verifica que MySQL esté iniciado, las credenciales en php/conexion.php y que hayas importado database/base_datos.sql.');
}
