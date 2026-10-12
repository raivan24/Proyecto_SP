<?php
// gestion_clientes.php

session_start();

// Incluir el archivo de conexión a la base de datos
require_once '../controlador/db_connection.php'; // Ajusta la ruta si es necesario
require_once '../controlador/func_ayuda.php'; // Asegúrar que la ruta es correcta

// Verificar si el usuario no ha iniciado sesión o no tiene permisos (solo admin y asistente)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || ($_SESSION["tipo_usuario"] !== 'admin' && $_SESSION["tipo_usuario"] !== 'asistente')) {
    header("location: ../index.php"); // Ajusta la ruta si es necesario
    exit;
}

registrarEnBitacora($conn, "Gestión de Clientes"); //funcion registro bitacora_ruta

$nombre = $apellido = $telefono = $email = $direccion = $cedula = "";
$nombre_err = $email_err = $cedula_err = ""; // Errores para campos obligatorios o únicos
$success_message = "";
$error_message = "";
$edit_mode = false;
$cliente_id_to_edit = null;

// --- Manejo de la lógica para agregar, editar y eliminar clientes ---

// Procesar el formulario cuando se envía (Agregar/Editar)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Detectar si es una edición
    if (isset($_POST["cliente_id"]) && !empty($_POST["cliente_id"])) {
        $edit_mode = true;
        $cliente_id_to_edit = $_POST["cliente_id"];
    }

    // Validar cédula
    if (empty(trim($_POST["cedula"]))) {
        $cedula_err = "Por favor, ingresa la cédula del cliente.";
    } else {
        $cedula = trim($_POST["cedula"]);
        // Verificar si la cédula ya existe (solo si no es la cédula actual del cliente que se está editando)
        $sql_check_cedula = "SELECT id_cliente FROM clientes WHERE cedula = ?";
        if ($edit_mode) {
            $sql_check_cedula .= " AND id_cliente != ?";
        }
        if ($stmt_check_cedula = $conn->prepare($sql_check_cedula)) {
            if ($edit_mode) {
                $stmt_check_cedula->bind_param("si", $param_cedula, $cliente_id_to_edit);
            } else {
                $stmt_check_cedula->bind_param("s", $param_cedula);
            }
            $param_cedula = $cedula;
            $stmt_check_cedula->execute();
            $stmt_check_cedula->store_result();
            if ($stmt_check_cedula->num_rows > 0) {
                $cedula_err = "Esta cédula ya está registrada para otro cliente.";
            }
            $stmt_check_cedula->close();
        }
    }

    // Validar nombre
    if (empty(trim($_POST["nombre"]))) {
        $nombre_err = "Por favor, ingresa el nombre del cliente.";
    } else {
        $nombre = trim($_POST["nombre"]);
    }

    // Validar email (si se proporciona, debe ser único)
    $email = trim($_POST["email"]);
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_err = "Formato de email inválido.";
    } elseif (!empty($email)) {
        $sql_check_email = "SELECT id_cliente FROM clientes WHERE email = ?";
        if ($edit_mode) {
            $sql_check_email .= " AND id_cliente != ?";
        }
        if ($stmt_check_email = $conn->prepare($sql_check_email)) {
            if ($edit_mode) {
                $stmt_check_email->bind_param("si", $param_email, $cliente_id_to_edit);
            } else {
                $stmt_check_email->bind_param("s", $param_email);
            }
            $param_email = $email;
            $stmt_check_email->execute();
            $stmt_check_email->store_result();
            if ($stmt_check_email->num_rows > 0) {
                $email_err = "Este email ya está registrado para otro cliente.";
            }
            $stmt_check_email->close();
        }
    }

    // Recoger otros campos (no obligatorios)
    $apellido = trim($_POST["apellido"]);
    $telefono = trim($_POST["telefono"]);
    $direccion = trim($_POST["direccion"]);

    // Si no hay errores de entrada, proceder con la operación
    if (empty($nombre_err) && empty($email_err) && empty($cedula_err)) {
        if ($edit_mode) {
            // Actualizar cliente existente
            $sql_update = "UPDATE clientes SET cedula = ?, nombre = ?, apellido = ?, telefono = ?, email = ?, direccion = ? WHERE id_cliente = ?";
            if ($stmt_update = $conn->prepare($sql_update)) {
                $stmt_update->bind_param("ssssssi", $param_cedula, $param_nombre, $param_apellido, $param_telefono, $param_email, $param_direccion, $param_id);
                $param_cedula = $cedula;
                $param_nombre = $nombre;
                $param_apellido = $apellido;
                $param_telefono = $telefono;
                $param_email = !empty($email) ? $email : null; // Guardar null si está vacío
                $param_direccion = $direccion;
                $param_id = $cliente_id_to_edit;

                if ($stmt_update->execute()) {
                    $success_message = "Cliente actualizado exitosamente.";
                } else {
                    $error_message = "Error al actualizar el cliente: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                $error_message = "Error al preparar la consulta de actualización: " . $conn->error;
            }

        } else {
            // Agregar nuevo cliente
            $sql_insert = "INSERT INTO clientes (cedula, nombre, apellido, telefono, email, direccion) VALUES (?, ?, ?, ?, ?, ?)";
            if ($stmt_insert = $conn->prepare($sql_insert)) {
                $stmt_insert->bind_param("ssssss", $param_cedula, $param_nombre, $param_apellido, $param_telefono, $param_email, $param_direccion);

                $param_cedula = $cedula;
                $param_nombre = $nombre;
                $param_apellido = $apellido;
                $param_telefono = $telefono;
                $param_email = !empty($email) ? $email : null;
                $param_direccion = $direccion;

                if ($stmt_insert->execute()) {
                    $success_message = "Cliente agregado exitosamente.";
                    // Limpiar campos después de agregar
                    $nombre = $apellido = $telefono = $email = $direccion = $cedula = "";
                } else {
                    $error_message = "Error al agregar el cliente: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            } else {
                $error_message = "Error al preparar la consulta de inserción: " . $conn->error;
            }
        }
    }
}

// Lógica para eliminar cliente
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_to_delete = intval($_GET['id']);
    // Aquí podrías añadir una validación para evitar eliminar clientes con pedidos asociados si así lo deseas.

    $sql_delete = "DELETE FROM clientes WHERE id_cliente = ?";
    if ($stmt_delete = $conn->prepare($sql_delete)) {
        $stmt_delete->bind_param("i", $id_to_delete);
        if ($stmt_delete->execute()) {
            $success_message = "Cliente eliminado exitosamente.";
        } else {
            $error_message = "Error al eliminar el cliente. Asegúrate de que no tenga pedidos asociados: " . $stmt_delete->error;
        }
        $stmt_delete->close();
    } else {
        $error_message = "Error al preparar la consulta de eliminación: " . $conn->error;
    }
}

// Lógica para cargar datos del cliente en el formulario de edición
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $cliente_id_to_edit = intval($_GET['id']);
    $sql_fetch_cliente = "SELECT cedula, nombre, apellido, telefono, email, direccion FROM clientes WHERE id_cliente = ?";
    if ($stmt_fetch_cliente = $conn->prepare($sql_fetch_cliente)) {
        $stmt_fetch_cliente->bind_param("i", $cliente_id_to_edit);
        $stmt_fetch_cliente->execute();
        $stmt_fetch_cliente->store_result();
        if ($stmt_fetch_cliente->num_rows == 1) {
            $stmt_fetch_cliente->bind_result($cedula, $nombre, $apellido, $telefono, $email, $direccion);
            $stmt_fetch_cliente->fetch();
            $edit_mode = true; // Activar modo edición
        } else {
            $error_message = "Cliente no encontrado para edición.";
        }
        $stmt_fetch_cliente->close();
    } else {
        $error_message = "Error al preparar la consulta de obtención de cliente.";
    }
}


// Obtener todos los clientes para mostrarlos en la tabla
$clientes_db = [];
$sql_select_clientes = "SELECT id_cliente, cedula, nombre, apellido, telefono, email, direccion, fecha_registro FROM clientes ORDER BY nombre, apellido";
if ($result_clientes = $conn->query($sql_select_clientes)) {
    $clientes_db = $result_clientes->fetch_all(MYSQLI_ASSOC);
} else {
    $error_message = "Error al cargar los clientes: " . $conn->error;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes</title>
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
        <h1 class="mb-4 text-center text-success">Gestión de Clientes</h1>

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
            <h2 class="card-header bg-success text-white"><?php echo $edit_mode ? 'Editar Cliente' : 'Agregar Nuevo Cliente'; ?></h2>
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="cliente_id" value="<?php echo htmlspecialchars($cliente_id_to_edit); ?>">
                    <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="cedula" class="form-label">Cédula:</label>
                            <input type="text" id="cedula" name="cedula" class="form-control <?php echo (!empty($cedula_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($cedula); ?>" required>
                            <div class="invalid-feedback"><?php echo $cedula_err; ?></div>
                        </div>
                        <div class="col-md-6">
                            <label for="nombre" class="form-label">Nombre:</label>
                            <input type="text" id="nombre" name="nombre" class="form-control <?php echo (!empty($nombre_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($nombre); ?>" required>
                            <div class="invalid-feedback"><?php echo $nombre_err; ?></div>
                        </div>
                        <div class="col-md-6">
                            <label for="apellido" class="form-label">Apellido:</label>
                            <input type="text" id="apellido" name="apellido" class="form-control" value="<?php echo htmlspecialchars($apellido); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="telefono" class="form-label">Teléfono:</label>
                            <input type="text" id="telefono" name="telefono" class="form-control" value="<?php echo htmlspecialchars($telefono); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email (Opcional, debe ser único):</label>
                            <input type="email" id="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>">
                            <div class="invalid-feedback"><?php echo $email_err; ?></div>
                        </div>
                        <div class="col-12">
                            <label for="direccion" class="form-label">Dirección:</label>
                            <input type="text" id="direccion" name="direccion" class="form-control" value="<?php echo htmlspecialchars($direccion); ?>">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? 'Actualizar Cliente' : 'Agregar Cliente'; ?></button>
                            <?php if ($edit_mode): ?>
                                <a href="gestion_clientes.php" class="btn btn-secondary ms-2">Cancelar Edición</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <h2 class="card-header bg-success text-white">Listado de Clientes</h2>
            <div class="card-body">
                <?php if (empty($clientes_db)): ?>
                    <div class="alert alert-info" role="alert">
                        No hay clientes registrados. ¡Agrega algunos!
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cédula</th>
                                    <th>Nombre</th>
                                    <th>Apellido</th>
                                    <th>Teléfono</th>
                                    <th>Email</th>
                                    <th>Dirección</th>
                                    <th>Fecha Registro</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientes_db as $cliente): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cliente['id_cliente']); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['cedula']); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['apellido']); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['telefono']); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['email']); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['direccion']); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['fecha_registro']); ?></td>
                                        <td>
                                            <a href="gestion_clientes.php?action=edit&id=<?php echo $cliente['id_cliente']; ?>" class="btn btn-sm btn-info">Editar</a>
                                            <a href="gestion_clientes.php?action=delete&id=<?php echo $cliente['id_cliente']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar a este cliente?');">Eliminar</a>
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
