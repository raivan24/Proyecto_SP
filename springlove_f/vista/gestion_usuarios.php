<?php
// gestion_usuarios.php

session_start();

// Incluir el archivo de conexión a la base de datos
require_once '../controlador/db_connection.php';
require_once '../controlador/func_ayuda.php'; // Asegúrar que la ruta es correcta
registrarEnBitacora($conn, "Gestión de Usuarios"); //funcion registro bitacora_ruta

// Verificar si el usuario no ha iniciado sesión o no es administrador
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["tipo_usuario"] !== 'admin') {
    header("location: ../index.php");
    exit;
}

$nombre = $email = $password = $tipo_usuario = "";
$nombre_err = $email_err = $password_err = $tipo_usuario_err = "";
$success_message = "";
$error_message = "";
$edit_mode = false;
$user_id_to_edit = null;

// --- Manejo de la lógica para agregar, editar y eliminar usuarios ---

// Procesar el formulario cuando se envía (Agregar/Editar)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Detectar si es una edición
    if (isset($_POST["user_id"]) && !empty($_POST["user_id"])) {
        $edit_mode = true;
        $user_id_to_edit = $_POST["user_id"];
    }

    // Validar nombre
    if (empty(trim($_POST["nombre"]))) {
        $nombre_err = "Por favor, ingresa el nombre.";
    } else {
        $nombre = trim($_POST["nombre"]);
    }

    // Validar email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Por favor, ingresa el email.";
    } else {
        $email = trim($_POST["email"]);
        // Verificar si el email ya existe (solo si no es el email actual del usuario que se está editando)
        $sql_check_email = "SELECT id_usuario FROM usuarios WHERE email = ?";
        if ($edit_mode) {
            $sql_check_email .= " AND id_usuario != ?";
        }
        if ($stmt_check_email = $conn->prepare($sql_check_email)) {
            if ($edit_mode) {
                $stmt_check_email->bind_param("si", $param_email, $user_id_to_edit);
            } else {
                $stmt_check_email->bind_param("s", $param_email);
            }
            $param_email = $email;
            $stmt_check_email->execute();
            $stmt_check_email->store_result();
            if ($stmt_check_email->num_rows > 0) {
                $email_err = "Este email ya está en uso.";
            }
            $stmt_check_email->close();
        }
    }

    // Validar tipo de usuario
    $allowed_types = ['admin', 'asistente', 'ayudante'];
    if (empty(trim($_POST["tipo_usuario"])) || !in_array($_POST["tipo_usuario"], $allowed_types)) {
        $tipo_usuario_err = "Por favor, selecciona un tipo de usuario válido.";
    } else {
        $tipo_usuario = trim($_POST["tipo_usuario"]);
    }

    // Validar contraseña (requerida al agregar, opcional al editar)
    $input_password = trim($_POST["password"]);
    if (!$edit_mode || (!empty($input_password))) {
        if (empty($input_password)) {
            $password_err = "Por favor, ingresa una contraseña.";
        } elseif (strlen($input_password) < 6 || strlen($input_password) > 12) {
            $password_err = "La contraseña debe tener entre 6 y 12 caracteres.";
        } elseif (!preg_match("/[0-9]/", $input_password)) {
            $password_err = "La contraseña debe contener al menos un número.";
        } elseif (!preg_match("/[A-Z]/", $input_password)) {
            $password_err = "La contraseña debe contener al menos una letra mayúscula.";
        } elseif (!preg_match("/[a-z]/", $input_password)) {
            $password_err = "La contraseña debe contener al menos una letra minúscula.";
        } elseif (!preg_match("/[!@#$%^&*()\-_+=\[\]{}|;:,.<>?]/", $input_password)) {
            $password_err = "La contraseña debe contener al menos un carácter especial.";
        } else {
            $password = password_hash($input_password, PASSWORD_DEFAULT); // Hash de la contraseña
        }
    }


    // Si no hay errores de entrada, proceder con la operación
    if (empty($nombre_err) && empty($email_err) && empty($password_err) && empty($tipo_usuario_err)) {
        if ($edit_mode) {
            // Actualizar usuario existente
            $sql_update = "UPDATE usuarios SET nombre = ?, email = ?, tipo_usuario = ?";
            if (!empty($password)) { // Solo actualizar la contraseña si se proporcionó una nueva y pasó la validación
                $sql_update .= ", password = ?";
            }
            $sql_update .= " WHERE id_usuario = ?";

            if ($stmt_update = $conn->prepare($sql_update)) {
                if (!empty($password)) {
                    $stmt_update->bind_param("ssssi", $param_nombre, $param_email, $param_tipo, $param_password, $param_id);
                    $param_password = $password;
                } else {
                    $stmt_update->bind_param("sssi", $param_nombre, $param_email, $param_tipo, $param_id);
                }
                $param_nombre = $nombre;
                $param_email = $email;
                $param_tipo = $tipo_usuario;
                $param_id = $user_id_to_edit;

                if ($stmt_update->execute()) {
                    $success_message = "Usuario actualizado exitosamente.";
                } else {
                    $error_message = "Error al actualizar el usuario. Por favor, inténtalo de nuevo: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                $error_message = "Error al preparar la consulta de actualización: " . $conn->error;
            }

        } else {
            // Agregar nuevo usuario
            $sql_insert = "INSERT INTO usuarios (nombre, email, password, tipo_usuario) VALUES (?, ?, ?, ?)";
            if ($stmt_insert = $conn->prepare($sql_insert)) {
                $stmt_insert->bind_param("ssss", $param_nombre, $param_email, $param_password, $param_tipo);

                $param_nombre = $nombre;
                $param_email = $email;
                $param_password = $password; // Ya está hasheada
                $param_tipo = $tipo_usuario;

                if ($stmt_insert->execute()) {
                    $success_message = "Usuario agregado exitosamente.";
                    // Limpiar campos después de agregar
                    $nombre = $email = $password = $tipo_usuario = "";
                } else {
                    $error_message = "Error al agregar el usuario. Por favor, inténtalo de nuevo: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            } else {
                $error_message = "Error al preparar la consulta de inserción: " . $conn->error;
            }
        }
    }
}

// Lógica para eliminar usuario
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_to_delete = intval($_GET['id']);
    // Prevenir que el administrador elimine su propia cuenta accidentalmente (opcional)
    if ($id_to_delete == $_SESSION['id_usuario']) {
        $error_message = "No puedes eliminar tu propia cuenta de administrador.";
    } else {
        $sql_delete = "DELETE FROM usuarios WHERE id_usuario = ?";
        if ($stmt_delete = $conn->prepare($sql_delete)) {
            $stmt_delete->bind_param("i", $id_to_delete);
            if ($stmt_delete->execute()) {
                $success_message = "Usuario eliminado exitosamente.";
            } else {
                $error_message = "Error al eliminar el usuario. Asegúrate de que no tenga ventas asociadas: " . $stmt_delete->error;
            }
            $stmt_delete->close();
        } else {
            $error_message = "Error al preparar la consulta de eliminación: " . $conn->error;
        }
    }
}

// Lógica para cargar datos del usuario en el formulario de edición
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $user_id_to_edit = intval($_GET['id']);
    $sql_fetch_user = "SELECT nombre, email, tipo_usuario FROM usuarios WHERE id_usuario = ?";
    if ($stmt_fetch_user = $conn->prepare($sql_fetch_user)) {
        $stmt_fetch_user->bind_param("i", $user_id_to_edit);
        $stmt_fetch_user->execute();
        $stmt_fetch_user->store_result();
        if ($stmt_fetch_user->num_rows == 1) {
            $stmt_fetch_user->bind_result($nombre, $email, $tipo_usuario);
            $stmt_fetch_user->fetch();
            $edit_mode = true; // Activar modo edición
        } else {
            $error_message = "Usuario no encontrado para edición.";
        }
        $stmt_fetch_user->close();
    } else {
        $error_message = "Error al preparar la consulta de obtención de usuario.";
    }
}


// Obtener todos los usuarios para mostrarlos en la tabla
$usuarios_db = [];
$sql_select_users = "SELECT id_usuario, nombre, email, tipo_usuario FROM usuarios ORDER BY tipo_usuario, nombre";
if ($result_users = $conn->query($sql_select_users)) {
    $usuarios_db = $result_users->fetch_all(MYSQLI_ASSOC);
} else {
    $error_message = "Error al cargar los usuarios: " . $conn->error;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <!-- Bootstrap CSS -->
    <link href="../css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0fdf4;
        }
        .navbar, .dropdown-menu {
            background-color: #065f46;
        }
        .navbar-brand, .nav-link {
            color: white !important;
        }
        .nav-link:hover {
            background-color: #10b981;
            border-radius: 0.5rem;
        }
        .card {
            background-color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            padding: 2rem;
        }
        .btn-primary {
            background-color: #10b981;
            border-color: #10b981;
            color: white;
            font-weight: 600;
            transition: background-color 0.2s ease-in-out, border-color 0.2s ease-in-out;
        }
        .btn-primary:hover {
            background-color: #059669;
            border-color: #059669;
        }
        .accordion-button:not(.collapsed) {
            background-color: #d1fae5; /* Color claro para acordeones abiertos */
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            transition: background-color 0.2s ease-in-out, border-color 0.2s ease-in-out;
        }
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        .status-badge {
            font-size: 1.1em;
            padding: 0.5em 0.8em;
            border-radius: 0.5rem;
        }
    </style>
</head>
<body class="min-vh-100 d-flex flex-column">
    <!-- NavBar-->
     <?php
    include 'menu.php';
    ?>

    <main class="flex-grow-1 container my-5">
        <h1 class="mb-4 text-center text-success">Gestión de Usuarios</h1>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-5">
            <h2 class="card-header bg-success text-white"><?php echo $edit_mode ? 'Editar Usuario' : 'Agregar Nuevo Usuario'; ?></h2>
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id_to_edit); ?>">
                    <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label">Nombre:</label>
                            <input type="text" id="nombre" name="nombre" class="form-control <?php echo (!empty($nombre_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($nombre); ?>">
                            <div class="invalid-feedback"><?php echo $nombre_err; ?></div>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email:</label>
                            <input type="email" id="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>">
                            <div class="invalid-feedback"><?php echo $email_err; ?></div>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Contraseña: <?php echo $edit_mode ? '(Dejar en blanco para no cambiar)' : ''; ?></label>
                            <input type="password" id="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="" maxlength="12" minlength="8" placeholder="Debe tener entre 6 y 12 caracteres">
                            <div class="invalid-feedback"><?php echo $password_err; ?></div>
                        </div>
                        <div class="col-md-6">
                            <label for="tipo_usuario" class="form-label">Tipo de Usuario:</label>
                            <select id="tipo_usuario" name="tipo_usuario" class="form-select <?php echo (!empty($tipo_usuario_err)) ? 'is-invalid' : ''; ?>">
                                <option value="">Selecciona un tipo</option>
                                <option value="admin" <?php echo ($tipo_usuario == 'admin') ? 'selected' : ''; ?>>Administrador</option>
                                <option value="asistente" <?php echo ($tipo_usuario == 'asistente') ? 'selected' : ''; ?>>Asistente</option>
                                <option value="ayudante" <?php echo ($tipo_usuario == 'ayudante') ? 'selected' : ''; ?>>Ayudante</option>
                            </select>
                            <div class="invalid-feedback"><?php echo $tipo_usuario_err; ?></div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? 'Actualizar Usuario' : 'Agregar Usuario'; ?></button>
                            <?php if ($edit_mode): ?>
                                <a href="gestion_usuarios.php" class="btn btn-secondary ms-2">Cancelar Edición</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <h2 class="card-header bg-success text-white">Listado de Usuarios</h2>
            <div class="card-body">
                <?php if (empty($usuarios_db)): ?>
                    <div class="alert alert-info" role="alert">
                        No hay usuarios registrados.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Tipo de Usuario</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios_db as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['id_usuario']); ?></td>
                                        <td><?php echo htmlspecialchars($user['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($user['tipo_usuario'])); ?></td>
                                        <td>
                                            <a href="gestion_usuarios.php?action=edit&id=<?php echo $user['id_usuario']; ?>" class="btn btn-sm btn-info">Editar</a>
                                            <a href="gestion_usuarios.php?action=delete&id=<?php echo $user['id_usuario']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar a este usuario?');">Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Bootstrap Bundle with Popper -->
    <script src="../js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
