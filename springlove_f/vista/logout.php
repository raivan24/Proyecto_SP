<?php

// ----------------------------------------------------
// 1. CONFIGURACIÓN DEL RESPALDO
// ----------------------------------------------------
$db_host = 'localhost';
$db_user = 'root'; // Reemplaza con tu usuario de MySQL
$db_pass = ''; // Reemplaza con tu contraseña de MySQL
$db_name = 'florerias_db'; // Reemplaza con el nombre de tu BD

// Directorio donde se guardará el respaldo (¡Debe tener permisos de escritura!)
$backup_dir = '../respaldo/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}
$filename = $db_name . '_' . date('Ymd_His') . '.sql';
$filepath = $backup_dir . $filename;

// Comando mysqldump: exporta la base de datos a un archivo SQL
// Si la contraseña está vacía, usamos --password= para evitar que mysqldump pregunte interactivamente.
$password_option = $db_pass !== '' ? "-p{$db_pass}" : "--password=";

$mysqldump = 'mysqldump';
if (stripos(PHP_OS, 'WIN') === 0) {
    $xampPath = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
    if (file_exists($xampPath)) {
        $mysqldump = $xampPath;
    }
}

$command = "\"{$mysqldump}\" --opt -h {$db_host} -u {$db_user} {$password_option} {$db_name} > \"{$filepath}\"";

// ----------------------------------------------------
// 2. EJECUTAR EL RESPALDO
// ----------------------------------------------------
// Nota: 'exec()' es una función que ejecuta comandos del sistema operativo.
// Asegúrate de que tu servidor web (Apache/Nginx) tenga los permisos necesarios.
exec($command, $output, $return_var);

// Verificar si mysqldump se ejecutó correctamente (0 indica éxito)
if ($return_var === 0) {
    // Éxito en el respaldo
    $mensaje = "Respaldo creado con éxito: " . $filename;
    echo $mensaje;
} else {
    // Error en el respaldo
    $mensaje = "Error al ejecutar el respaldo. Código de error: " . $return_var;
    // Opcionalmente, registrar $output en un log de errores
}
//--------------------------------------------- Fin Respaldo----

// logout.php

// Iniciar sesión PHP
session_start();

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la sesión
session_destroy();

/*
// Evitar que el navegador almacene en caché la página después de cerrar sesión
header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); */


//Destruir la cookie de sesión en el navegador
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}


// Redirigir a la página de inicio de sesión
header("location: ../index.php"); // Asumiendo que index.php 
exit;
?>
