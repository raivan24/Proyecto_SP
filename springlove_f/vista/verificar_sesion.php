<?php
session_start();
$inactividad = 300; // 5 minutos en segundos

if (isset($_SESSION['tiempo'])) {
    $vida_sesion = time() - $_SESSION['tiempo'];
    if ($vida_sesion > $inactividad) {
        session_unset();
        session_destroy();
        echo json_encode(["status" => "expired"]);
        exit();
    }
}
$_SESSION['tiempo'] = time(); 
echo json_encode(["status" => "active"]);
?>
