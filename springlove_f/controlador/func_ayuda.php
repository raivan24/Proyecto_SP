<?php
//func_ayuda.php
//funcion Registrar bitacora de rutas (seccion)
/**
 * Registra la visita de un usuario en la bitácora de rutas.
 * * @param mysqli $conn Conexión activa a la base de datos.
 * @param string $seccion Nombre legible de la sección (ej: "Gestión de Pedidos").
 */
function registrarEnBitacora($conn, $seccion) {
    // Solo registrar si el usuario ha iniciado sesión
    if (isset($_SESSION['id_usuario'])) {
        $id_usuario = $_SESSION['id_usuario'];
        $ruta_archivo = basename($_SERVER['PHP_SELF']); // Obtiene el nombre del archivo actual (ej: gestion_pedidos.php)
        $metodo = $_SERVER['REQUEST_METHOD']; // GET, POST, etc.
        $ip = $_SERVER['REMOTE_ADDR']; // Dirección IP del cliente
        $agente = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido'; // Navegador/SO

        $sql = "INSERT INTO bitacora_rutas (id_usuario, seccion, ruta_archivo, metodo_peticion, direccion_ip, navegador_dispositivo) VALUES (?, ?, ?, ?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("isssss", $id_usuario, $seccion, $ruta_archivo, $metodo, $ip, $agente);
            $stmt->execute();
            $stmt->close();
        }
    }
}











?>