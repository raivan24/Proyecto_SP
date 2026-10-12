<?php
// restaurar_basedatos.php

session_start();

// Asegurar respuesta JSON limpia para el fetch del dashboard
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/restaurar_basedatos_error.log');
error_reporting(E_ALL);
ob_start();

function clear_output_buffers() {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

function send_json_response($payload, $code = 200) {
    clear_output_buffers();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    if ($json === false) {
        $json = '{"success":false,"message":"Error interno al generar la respuesta JSON."}';
    }
    echo $json;
    exit;
}

set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($exception) {
    clear_output_buffers();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('restaurar_basedatos exception: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ' on line ' . $exception->getLine());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno durante la restauración.',
        'error_detail' => $exception->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        clear_output_buffers();
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        $message = isset($error['message']) ? $error['message'] : 'Error fatal desconocido.';
        error_log('restaurar_basedatos fatal error: ' . $message . ' in ' . ($error['file'] ?? 'desconocido') . ' on line ' . ($error['line'] ?? 'desconocido'));
        echo json_encode([
            'success' => false,
            'message' => 'Error fatal durante la restauración.',
            'error_detail' => $message
        ], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
});

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["tipo_usuario"] !== 'admin') {
    send_json_response([
        'success' => false,
        'message' => 'Acceso denegado. Solo administradores pueden realizar esta operación.'
    ]);
}

if (!isset($_FILES['backupFile']) || $_FILES['backupFile']['error'] !== UPLOAD_ERR_OK) {
    $error_message = 'Error al cargar el archivo.';
    if (isset($_FILES['backupFile']['error'])) {
        switch ($_FILES['backupFile']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_message = 'El archivo excede el tamaño máximo permitido.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message = 'El archivo se subió parcialmente.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_message = 'No se seleccionó ningún archivo.';
                break;
        }
    }
    send_json_response(['success' => false, 'message' => $error_message]);
}

$file_extension = strtolower(pathinfo($_FILES['backupFile']['name'], PATHINFO_EXTENSION));
if ($file_extension !== 'sql') {
    send_json_response([
        'success' => false,
        'message' => 'Tipo de archivo no válido. Solo se permiten archivos .sql'
    ]);
}

$max_size = 50 * 1024 * 1024;
if ($_FILES['backupFile']['size'] > $max_size) {
    send_json_response([
        'success' => false,
        'message' => 'El archivo es demasiado grande. Tamaño máximo: 50MB'
    ]);
}

try {
    require_once __DIR__ . '/db_connection.php';
} catch (Throwable $e) {
    send_json_response([
        'success' => false,
        'message' => 'Error de conexión a la base de datos.',
        'error_detail' => $e->getMessage()
    ], 500);
}

// Preservar los registros actuales de la bitácora antes de restaurar.
$bitacora_preserve = [];
if ($result_preserve = $conn->query("SHOW TABLES LIKE 'bitacora_rutas'")) {
    if ($result_preserve->num_rows > 0) {
        $result_preserve->free();
        $result_backup = $conn->query("SELECT id_usuario, seccion, ruta_archivo, metodo_peticion, direccion_ip, navegador_dispositivo, fecha_hora FROM bitacora_rutas");
        if ($result_backup) {
            while ($row = $result_backup->fetch_assoc()) {
                $bitacora_preserve[] = $row;
            }
            $result_backup->free();
        }
    } else {
        $result_preserve->free();
    }
}

$temp_dir = __DIR__ . '/temp_backups/';
if (!file_exists($temp_dir)) {
    mkdir($temp_dir, 0755, true);
}

$temp_file = $temp_dir . 'restore_' . time() . '_' . uniqid() . '.sql';
if (!move_uploaded_file($_FILES['backupFile']['tmp_name'], $temp_file)) {
    send_json_response([
        'success' => false,
        'message' => 'Error al guardar el archivo temporal.'
    ]);
}

$sql_content = file_get_contents($temp_file);
if ($sql_content === false) {
    unlink($temp_file);
    send_json_response([
        'success' => false,
        'message' => 'No se pudo leer el archivo SQL'
    ]);
}

set_time_limit(0);
ini_set('memory_limit', '512M');

/**
 * Divide el SQL en consultas individuales, respetando cambios de delimitador.
 * Soporta: DELIMITER, comentarios con --, # y bloques /* */

set_time_limit(0);
ini_set('memory_limit', '512M');

function get_mysql_executable() {
    $paths = [
        'C:\\xampp\\mysql\\bin\\mysql.exe',
        'C:\\xampp\\bin\\mysql.exe'
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    return 'mysql';
}

function execute_sql_file_with_mysql($file, $db_host, $db_user, $db_pass, $db_name, &$output) {
    $mysql_exe = get_mysql_executable();
    $password_arg = $db_pass === '' ? '--password=' : '--password=' . escapeshellarg($db_pass);
    $command = escapeshellarg($mysql_exe)
        . ' --host=' . escapeshellarg($db_host)
        . ' --user=' . escapeshellarg($db_user)
        . ' ' . $password_arg
        . ' --default-character-set=utf8mb4 '
        . escapeshellarg($db_name);

    $descriptor_spec = [
        0 => ['file', $file, 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];

    $process = proc_open($command, $descriptor_spec, $pipes);
    if (!is_resource($process)) {
        $output = 'No se pudo iniciar el proceso mysql.exe.';
        return false;
    }

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $return_code = proc_close($process);
    $output = trim($stdout . "\n" . $stderr);
    return $return_code === 0;
}

$errors = [];
$success_count = 0;
$mysql_import_output = '';

if (!execute_sql_file_with_mysql($temp_file, DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, $mysql_import_output)) {
    $errors[] = 'Error importando el archivo SQL con mysql.exe: ' . preg_replace('/\s+/', ' ', trim($mysql_import_output));
    error_log('restaurar_basedatos mysql import failed: ' . $mysql_import_output);
} else {
    $success_count = 1;
}

$conn->query('SET foreign_key_checks = 1');

// Restaurar registros previos de bitácora si existían antes de la restauración.
if (!empty($bitacora_preserve) && $conn->query("SHOW TABLES LIKE 'bitacora_rutas'")) {
    $stmt = $conn->prepare("INSERT IGNORE INTO bitacora_rutas (id_usuario, seccion, ruta_archivo, metodo_peticion, direccion_ip, navegador_dispositivo, fecha_hora) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        foreach ($bitacora_preserve as $row) {
            $stmt->bind_param(
                'issssss',
                $row['id_usuario'],
                $row['seccion'],
                $row['ruta_archivo'],
                $row['metodo_peticion'],
                $row['direccion_ip'],
                $row['navegador_dispositivo'],
                $row['fecha_hora']
            );
            $stmt->execute();
        }
        $stmt->close();
    }
}

unlink($temp_file);

$files = glob($temp_dir . 'restore_*.sql');
foreach ($files as $file) {
    if (time() - filemtime($file) > 3600) {
        unlink($file);
    }
}

if (count($errors) === 0) {
    send_json_response([
        'success' => true,
        'message' => "Base de datos restaurada exitosamente. Se ejecutaron {$success_count} consultas."
    ]);
} else {
    send_json_response([
        'success' => false,
        'message' => 'Error durante la restauración.',
        'error_detail' => implode('; ', array_slice($errors, 0, 3))
    ]);
}

$conn->close();
?>