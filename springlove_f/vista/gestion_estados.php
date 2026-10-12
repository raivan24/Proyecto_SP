<?php
// gestion_estados.php

session_start();

// Incluir el archivo de conexión a la base de datos
require_once '../controlador/db_connection.php';
require_once '../controlador/func_ayuda.php'; // Asegúrar que la ruta es correcta
registrarEnBitacora($conn, "Gestión de Estados"); //funcion registro bitacora_ruta


// Verificar si el usuario no ha iniciado sesión o no es administrador
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["tipo_usuario"] === 'ayudante') {
    header("location: ../index.php");
    exit;
}

$nombre_estado = "";
$nombre_estado_err = "";
$success_message = "";
$error_message = "";
$edit_mode = false;
$estado_id_to_edit = null;

// --- Manejo de la lógica para agregar, editar y eliminar estados ---

// Procesar el formulario cuando se envía (Agregar/Editar)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Detectar si es una edición
    if (isset($_POST["estado_id"]) && !empty($_POST["estado_id"])) {
        $edit_mode = true;
        $estado_id_to_edit = $_POST["estado_id"];
    }

    // Validar nombre del estado
    if (empty(trim($_POST["nombre_estado"]))) {
        $nombre_estado_err = "Por favor, ingresa el nombre del estado.";
    } else {
        $nombre_estado = trim($_POST["nombre_estado"]);
        // Verificar si el nombre del estado ya existe (solo si no es el nombre actual del estado que se está editando)
        $sql_check_estado = "SELECT id_estado FROM estados WHERE nombre_estado = ?";
        if ($edit_mode) {
            $sql_check_estado .= " AND id_estado != ?";
        }
        if ($stmt_check_estado = $conn->prepare($sql_check_estado)) {
            if ($edit_mode) {
                $stmt_check_estado->bind_param("si", $param_nombre, $estado_id_to_edit);
            } else {
                $stmt_check_estado->bind_param("s", $param_nombre);
            }
            $param_nombre = $nombre_estado;
            $stmt_check_estado->execute();
            $stmt_check_estado->store_result();
            if ($stmt_check_estado->num_rows > 0) {
                $nombre_estado_err = "Este estado ya existe.";
            }
            $stmt_check_estado->close();
        }
    }

    // Si no hay errores de entrada, proceder con la operación
    if (empty($nombre_estado_err)) {
        if ($edit_mode) {
            // Actualizar estado existente
            $sql_update = "UPDATE estados SET nombre_estado = ? WHERE id_estado = ?";
            if ($stmt_update = $conn->prepare($sql_update)) {
                $stmt_update->bind_param("si", $param_nombre, $param_id);
                $param_nombre = $nombre_estado;
                $param_id = $estado_id_to_edit;

                if ($stmt_update->execute()) {
                    $success_message = "Estado actualizado exitosamente.";
                } else {
                    $error_message = "Error al actualizar el estado. Por favor, inténtalo de nuevo: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                $error_message = "Error al preparar la consulta de actualización: " . $conn->error;
            }

        } else {
            // Agregar nuevo estado
            $sql_insert = "INSERT INTO estados (nombre_estado) VALUES (?)";
            if ($stmt_insert = $conn->prepare($sql_insert)) {
                $stmt_insert->bind_param("s", $param_nombre);
                $param_nombre = $nombre_estado;

                if ($stmt_insert->execute()) {
                    $success_message = "Estado agregado exitosamente.";
                    // Limpiar campo después de agregar
                    $nombre_estado = "";
                } else {
                    $error_message = "Error al agregar el estado. Por favor, inténtalo de nuevo: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            } else {
                $error_message = "Error al preparar la consulta de inserción: " . $conn->error;
            }
        }
    }
}

// Lógica para eliminar estado
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_to_delete = intval($_GET['id']);
    // Aquí puedes añadir una validación para evitar eliminar estados predeterminados o si están en uso en ventas.
    // Por simplicidad, no se incluye una verificación de uso en ventas aquí, pero sería crucial en un sistema real.

    $sql_delete = "DELETE FROM estados WHERE id_estado = ?";
    if ($stmt_delete = $conn->prepare($sql_delete)) {
        $stmt_delete->bind_param("i", $id_to_delete);
        if ($stmt_delete->execute()) {
            $success_message = "Estado eliminado exitosamente.";
        } else {
            $error_message = "Error al eliminar el estado. Asegúrate de que no esté en uso en ninguna venta: " . $stmt_delete->error;
        }
        $stmt_delete->close();
    } else {
        $error_message = "Error al preparar la consulta de eliminación: " . $conn->error;
    }
}

// Lógica para cargar datos del estado en el formulario de edición
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $estado_id_to_edit = intval($_GET['id']);
    $sql_fetch_estado = "SELECT nombre_estado FROM estados WHERE id_estado = ?";
    if ($stmt_fetch_estado = $conn->prepare($sql_fetch_estado)) {
        $stmt_fetch_estado->bind_param("i", $estado_id_to_edit);
        $stmt_fetch_estado->execute();
        $stmt_fetch_estado->store_result();
        if ($stmt_fetch_estado->num_rows == 1) {
            $stmt_fetch_estado->bind_result($nombre_estado);
            $stmt_fetch_estado->fetch();
            $edit_mode = true; // Activar modo edición
        } else {
            $error_message = "Estado no encontrado para edición.";
        }
        $stmt_fetch_estado->close();
    } else {
        $error_message = "Error al preparar la consulta de obtención de estado.";
    }
}


// Obtener todos los estados para mostrarlos en la tabla
$estados_db = [];
$sql_select_estados = "SELECT id_estado, nombre_estado FROM estados ORDER BY nombre_estado";
if ($result_estados = $conn->query($sql_select_estados)) {
    $estados_db = $result_estados->fetch_all(MYSQLI_ASSOC);
} else {
    $error_message = "Error al cargar los estados: " . $conn->error;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Estados</title>
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
        <h1 class="mb-4 text-center text-success">Gestión de Estados</h1>

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
            <h2 class="card-header bg-success text-white"><?php echo $edit_mode ? 'Editar Estado' : 'Agregar Nuevo Estado'; ?></h2>
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="estado_id" value="<?php echo htmlspecialchars($estado_id_to_edit); ?>">
                    <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="nombre_estado" class="form-label">Nombre del Estado:</label>
                            <input type="text" id="nombre_estado" name="nombre_estado" class="form-control <?php echo (!empty($nombre_estado_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($nombre_estado); ?>">
                            <div class="invalid-feedback"><?php echo $nombre_estado_err; ?></div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100 me-2"><?php echo $edit_mode ? 'Actualizar Estado' : 'Agregar Estado'; ?></button>
                            <?php if ($edit_mode): ?>
                                <a href="gestion_estados.php" class="btn btn-secondary w-100">Cancelar Edición</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <h2 class="card-header bg-success text-white">Listado de Estados</h2>
            <div class="card-body">
                <?php if (empty($estados_db)): ?>
                    <div class="alert alert-info" role="alert">
                        No hay estados registrados. ¡Agrega algunos!
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre del Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estados_db as $estado): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($estado['id_estado']); ?></td>
                                        <td><?php echo htmlspecialchars($estado['nombre_estado']); ?></td>
                                        <td>
                                            <a href="gestion_estados.php?action=edit&id=<?php echo $estado['id_estado']; ?>" class="btn btn-sm btn-info">Editar</a>
                                            <a href="gestion_estados.php?action=delete&id=<?php echo $estado['id_estado']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar este estado?');">Eliminar</a>
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
