<?php
// login.php

// Iniciar sesión PHP para manejar variables de sesión
session_start();

// Incluir el archivo de conexión a la base de datos
include'controlador/db_connection.php';

// Definir variables e inicializarlas con valores vacíos
$email = $password = "";
$email_err = $password_err = $login_err = "";

// Procesar el formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Verificar si el email está vacío
    if (empty(trim($_POST["email"]))) {
        $email_err = "Por favor, ingresa tu email.";
    } else {
        $email = trim($_POST["email"]);
    }

    // Verificar si la contraseña está vacía
    if (empty(trim($_POST["password"]))) {
        $password_err = "Por favor, ingresa tu contraseña.";
    } else {
        $password = trim($_POST["password"]);

        $hashed_password1 = password_hash($password, PASSWORD_DEFAULT); //aqui estoy transformando a hash mi password
    }

    // Validar credenciales
    if (empty($email_err) && empty($password_err)) {
        // Preparar una declaración SELECT
        $sql = "SELECT id_usuario, nombre, email, password, tipo_usuario FROM usuarios WHERE email = ?";

        if ($stmt = $conn->prepare($sql)) {
            // Vincular variables a la declaración preparada como parámetros
            $stmt->bind_param("s", $param_email);

            // Establecer parámetros
            $param_email = $email;

            // Intentar ejecutar la declaración preparada
            if ($stmt->execute()) {
                // Almacenar el resultado
                $stmt->store_result();

                // Verificar si el email existe, si es así, verificar la contraseña
                if ($stmt->num_rows == 1) {
                    // Vincular variables de resultado
                    $stmt->bind_result($id_usuario, $nombre, $email, $hashed_password, $tipo_usuario);
                    if ($stmt->fetch()) {
                        if ($hashed_password !== null && password_verify($password, $hashed_password)) {
                            // Contraseña correcta, iniciar una nueva sesión
                            // session_start ya fue llamado al inicio

                            // Almacenar datos en variables de sesión
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id_usuario"] = $id_usuario;
                            $_SESSION["email"] = $email;
                            $_SESSION["nombre"] = $nombre;
                            $_SESSION["tipo_usuario"] = $tipo_usuario;

                            // Redirigir al usuario a la página de bienvenida según su tipo
                            switch ($tipo_usuario) {
                                case 'admin':
                                    header("location: vista/admin_dashboard.php");
                                    break;
                                case 'asistente':
                                    header("location: vista/asistente_dashboard.php");
                                    break;
                                case 'ayudante':
                                    header("location: vista/ayudante_dashboard.php");
                                    break;
                                default:
                                    header("location: welcome.php"); // Página genérica si el tipo no coincide
                                    break;
                            }
                            exit;
                        } else {
                            // Contraseña no válida
                            $login_err = "Email -- o contraseña inválidos."; //muestro los pass solo prueba, luego debo borrar las variables
                        }
                    }
                } else {
                    // Email no existe
                    $login_err = "Email No Registrado o contraseña inválidos.";
                }
            } else {
                echo "¡Ups! Algo salió mal. Por favor, inténtalo de nuevo más tarde.";
            }

            // Cerrar declaración
            $stmt->close();
        }
    }

    // Cerrar conexión
    $conn->close();
}
?>
