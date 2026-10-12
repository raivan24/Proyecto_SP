<?php
// db_connection.php

// Configuración de la base de datos
define('DB_SERVER', 'localhost'); // Usualmente 'localhost' si está en el mismo servidor
define('DB_USERNAME', 'root');    // nombre de usuario de MySQL
define('DB_PASSWORD', '');        // contraseña de MySQL
define('DB_NAME', 'florerias_db'); // El nombre de la base de datos que creamos

// Intentar conectar a la base de datos MySQL
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar la conexión
if ($conn->connect_error) {
    $message = "Error de conexión a la base de datos: " . $conn->connect_error;
    if (function_exists('send_json_response')) {
        send_json_response([
            'success' => false,
            'message' => 'Error de conexión a la base de datos.',
            'error_detail' => $message
        ], 500);
    }
    throw new Exception($message);
}


?>