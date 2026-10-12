<?php
// historial_pedidos.php

session_start();

// Incluir el archivo de conexión a la base de datos
require_once '../controlador/db_connection.php'; // Asegúrate de que la ruta es correcta
require_once '../controlador/func_ayuda.php'; // Asegúrar que la ruta es correcta
registrarEnBitacora($conn, "Historial de Pedidos"); //funcion registro bitacora_ruta

// Verificar si el usuario no ha iniciado sesión o no tiene permisos (solo admin y asistente)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || ($_SESSION["tipo_usuario"] !== 'admin' && $_SESSION["tipo_usuario"] !== 'asistente')) {
    header("location: ../index.php"); // Ajusta la ruta si es necesario
    exit;
}

// --- NUEVO: Manejo de búsqueda por ID ---
$search_id = null;
$search_error = "";
if (isset($_GET['search_id']) && !empty(trim($_GET['search_id']))) {
    $search_id_input = trim($_GET['search_id']);
    if (is_numeric($search_id_input) && intval($search_id_input) > 0) {
        $search_id = intval($search_id_input);
    } else {
        $search_error = "El ID del pedido debe ser un número entero positivo.";
    }
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20; // Pedidos por página para el historial (ajustado a 20 para mejor rendimiento)
$offset = ($page - 1) * $limit;

// Obtener pedidos para el historial (filtrado por ID si se busca)
$pedidos_historial = [];
$sql_select_pedidos_historial = "
    SELECT
        p.id_pedido,
        p.fecha_creacion,
        p.fecha_entrega_estimada,
        p.id_estado,
        p.estado_pago,
        e.nombre_estado,
        p.nombre_cliente,
        p.telefono_cliente,
        p.direccion_entrega,
        p.notas_cliente,
        p.total_estimado,
        u.nombre AS nombre_usuario_creador,
        c.cedula AS cliente_cedula_db
    FROM
        pedidos p
    JOIN
        estados e ON p.id_estado = e.id_estado
    JOIN
        usuarios u ON p.id_usuario_creador = u.id_usuario
    LEFT JOIN
        clientes c ON p.id_cliente = c.id_cliente
";

$sql_count_pedidos = "
    SELECT COUNT(*) AS total
    FROM pedidos p
    JOIN estados e ON p.id_estado = e.id_estado
    JOIN usuarios u ON p.id_usuario_creador = u.id_usuario
    LEFT JOIN clientes c ON p.id_cliente = c.id_cliente
";

$where_clause = "";
$params = [];
$types = "";

if ($search_id !== null) {
    $where_clause = " WHERE p.id_pedido = ?";
    $params[] = $search_id;
    $types .= "i";
}

$sql_count_pedidos .= $where_clause;
$total_rows = 0;
if ($stmt_count = $conn->prepare($sql_count_pedidos)) {
    if ($search_id !== null) {
        $stmt_count->bind_param("i", $search_id);
    }
    if ($stmt_count->execute()) {
        $result_count = $stmt_count->get_result();
        if ($row_count = $result_count->fetch_assoc()) {
            $total_rows = intval($row_count['total']);
        }
        $result_count->free();
    }
    $stmt_count->close();
}

$total_pages = $total_rows > 0 ? ceil($total_rows / $limit) : 1;
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

$sql_select_pedidos_historial .= $where_clause . " ORDER BY p.fecha_creacion DESC LIMIT ? OFFSET ?";

if ($stmt_pedidos = $conn->prepare($sql_select_pedidos_historial)) {
    $query_params = $params;
    $query_types = $types . "ii";
    $query_params[] = $limit;
    $query_params[] = $offset;
    $stmt_pedidos->bind_param($query_types, ...$query_params);
    if ($stmt_pedidos->execute()) {
        $result_pedidos_historial = $stmt_pedidos->get_result();
        while ($pedido = $result_pedidos_historial->fetch_assoc()) {
            $pedido['productos'] = [];

            // Obtener productos del pedido
            $sql_detalle_pedido_productos = "
                SELECT
                    dp.cantidad,
                    dp.precio_unitario_al_momento_pedido,
                    prod.nombre_producto,
                    prod.categoria
                FROM
                    detalle_pedido dp
                JOIN
                    productos prod ON dp.id_producto = prod.id_producto
                WHERE
                    dp.id_pedido = ?
            ";

            if ($stmt_detalle = $conn->prepare($sql_detalle_pedido_productos)) {
                $stmt_detalle->bind_param("i", $pedido['id_pedido']);
                if ($stmt_detalle->execute()) {
                    $result_detalle = $stmt_detalle->get_result();
                    while ($detalle = $result_detalle->fetch_assoc()) {
                        $pedido['productos'][] = $detalle;
                    }
                } else {
                    error_log("Error al obtener detalle de pedido para ID " . $pedido['id_pedido'] . ": " . $stmt_detalle->error);
                }
                $stmt_detalle->close();
            } else {
                error_log("Error al preparar la consulta de detalle de pedido: " . $conn->error);
            }

            // Obtener historial de estados para este pedido
            $pedido['historial_estados'] = [];
            $sql_historial_estados = "
                SELECT
                    he.fecha_cambio,
                    e_ant.nombre_estado AS estado_anterior,
                    e_nuevo.nombre_estado AS estado_nuevo,
                    u.nombre AS usuario_cambio
                FROM
                    historial_estado_pedido he
                LEFT JOIN
                    estados e_ant ON he.id_estado_anterior = e_ant.id_estado
                JOIN
                    estados e_nuevo ON he.id_estado_nuevo = e_nuevo.id_estado
                JOIN
                    usuarios u ON he.id_usuario_cambio = u.id_usuario
                WHERE
                    he.id_pedido = ?
                ORDER BY he.fecha_cambio ASC
            ";

            if ($stmt_historial = $conn->prepare($sql_historial_estados)) {
                $stmt_historial->bind_param("i", $pedido['id_pedido']);
                if ($stmt_historial->execute()) {
                    $result_historial = $stmt_historial->get_result();
                    while ($hist = $result_historial->fetch_assoc()) {
                        $pedido['historial_estados'][] = $hist;
                    }
                } else {
                    error_log("Error al obtener historial de estados para ID " . $pedido['id_pedido'] . ": " . $stmt_historial->error);
                }
                $stmt_historial->close();
            } else {
                error_log("Error al preparar la consulta de historial de estados: " . $conn->error);
            }

            $pedidos_historial[] = $pedido;
        }
        $result_pedidos_historial->free();
    } else {
        $error_message = "Error al ejecutar la consulta de pedidos: " . $stmt_pedidos->error;
    }
    $stmt_pedidos->close();
} else {
    $error_message = "Error al preparar la consulta de pedidos: " . $conn->error;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Pedidos</title>
    <!-- Bootstrap CSS -->
    <link href="../css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
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
        <h1 class="mb-4 text-center text-success">Historial de Pedidos</h1>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-header bg-success text-white">Listado Completo de Pedidos</h2>
            <div class="card-body">
                <!-- NUEVO: Formulario de búsqueda por ID -->
                <form method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="mb-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label for="search_id" class="form-label">Buscar Pedido por ID:</label>
                            <input type="number" class="form-control" id="search_id" name="search_id" min="1" placeholder="Ingresa el ID del pedido" value="<?php echo htmlspecialchars($search_id ?? ''); ?>">
                            <?php if (!empty($search_error)): ?>
                                <div class="invalid-feedback d-block"><?php echo $search_error; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">Buscar</button>
                        </div>
                    </div>
                </form>

                <?php if (empty($pedidos_historial)): ?>
                    <div class="alert alert-info" role="alert">
                        <?php echo $search_id ? "No se encontró un pedido con el ID especificado." : "No hay pedidos registrados en el historial."; ?>
                    </div>
                <?php else: ?>
                    <div class="accordion" id="historialPedidosAccordion">
                        <?php foreach ($pedidos_historial as $index => $pedido): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading_historial_pedido_<?php echo $pedido['id_pedido']; ?>">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_historial_pedido_<?php echo $pedido['id_pedido']; ?>" aria-expanded="false" aria-controls="collapse_historial_pedido_<?php echo $pedido['id_pedido']; ?>">
                                        Pedido #<?php echo htmlspecialchars($pedido['id_pedido']); ?> (Estado: <?php echo htmlspecialchars($pedido['nombre_estado']); ?>) - Cliente: <?php echo htmlspecialchars($pedido['nombre_cliente']); ?> - Total Estimado: $<?php echo htmlspecialchars(number_format($pedido['total_estimado'], 2)); ?>
                                    </button>
                                </h2>
                                <div id="collapse_historial_pedido_<?php echo $pedido['id_pedido']; ?>" class="accordion-collapse collapse" aria-labelledby="heading_historial_pedido_<?php echo $pedido['id_pedido']; ?>" data-bs-parent="#historialPedidosAccordion">
                                    <div class="accordion-body">
                                        <p><strong>Cliente Asociado:</strong> <?php echo htmlspecialchars($pedido['nombre_cliente'] . ' (C.I.: ' . ($pedido['cliente_cedula_db'] ?? 'N/A') . ')'); ?></p>
                                        <p><strong>Estado del Pago:</strong> <?php echo htmlspecialchars($pedido['estado_pago']); ?></p>
                                        <p><strong>Creado por:</strong> <?php echo htmlspecialchars($pedido['nombre_usuario_creador']); ?></p>
                                        <p><strong>Fecha de Creación:</strong> <?php echo htmlspecialchars($pedido['fecha_creacion']); ?></p>
                                        <?php if (!empty($pedido['fecha_entrega_estimada'])): ?>
                                            <p><strong>Fecha de Entrega Estimada:</strong> <?php echo htmlspecialchars($pedido['fecha_entrega_estimada']); ?></p>
                                        <?php endif; ?>
                                        <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($pedido['telefono_cliente']); ?></p>
                                        <p><strong>Dirección:</strong> <?php echo htmlspecialchars($pedido['direccion_entrega']); ?></p>
                                        <?php if (!empty($pedido['notas_cliente'])): ?>
                                            <p><strong>Notas:</strong> <?php echo nl2br(htmlspecialchars($pedido['notas_cliente'])); ?></p>
                                        <?php endif; ?>

                                        <h5>Productos del Pedido:</h5>
                                        <?php if (empty($pedido['productos'])): ?>
                                            <p class="text-muted">No se encontraron detalles de productos para este pedido.</p>
                                        <?php else: ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach ($pedido['productos'] as $producto): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><?php echo htmlspecialchars($producto['nombre_producto']); ?> (<?php echo htmlspecialchars(ucfirst($producto['categoria'])); ?>) x <?php echo htmlspecialchars($producto['cantidad']); ?></span>
                                                        <span class="badge bg-success rounded-pill">$<?php echo htmlspecialchars(number_format($producto['precio_unitario_al_momento_pedido'] * $producto['cantidad'], 2)); ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>

                                        <h5>Historial de Estados:</h5>
                                        <?php if (empty($pedido['historial_estados'])): ?>
                                            <p class="text-muted">No se encontró historial de estados para este pedido.</p>
                                        <?php else: ?>
                                            <table class="table table-sm table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Fecha de Cambio</th>
                                                        <th>Estado Anterior</th>
                                                        <th>Estado Nuevo</th>
                                                        <th>Usuario que Cambió</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($pedido['historial_estados'] as $hist): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($hist['fecha_cambio']); ?></td>
                                                            <td><?php echo htmlspecialchars($hist['estado_anterior'] ?? 'Inicial'); ?></td>
                                                            <td><?php echo htmlspecialchars($hist['estado_nuevo']); ?></td>
                                                            <td><?php echo htmlspecialchars($hist['usuario_cambio']); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($total_pages > 1): ?>
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4">
                            <div class="text-muted">Mostrando página <?php echo $page; ?> de <?php echo $total_pages; ?> — <?php echo $total_rows; ?> pedidos encontrados</div>
                            <nav aria-label="Paginación de historial de pedidos">
                                <ul class="pagination mb-0">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)]))); ?>" aria-label="Anterior">Anterior</a>
                                    </li>
                                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                        <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $p]))); ?>"><?php echo $p; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)]))); ?>" aria-label="Siguiente">Siguiente</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Bootstrap Bundle con Popper -->
    <script src="../js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
