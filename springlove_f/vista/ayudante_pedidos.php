<?php
// ayudante_pedidos.php

session_start();

// Incluir el archivo de conexión a la base de datos
require_once '../controlador/db_connection.php';
require_once '../controlador/func_ayuda.php';

// Verificar si el usuario no ha iniciado sesión
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

registrarEnBitacora($conn, "Listado de Pedidos");

$error_message = "";
$success_message = "";

// Obtener todos los estados para los selectores
$estados_disponibles = [];
$sql_estados = "SELECT id_estado, nombre_estado FROM estados ORDER BY nombre_estado";
if ($result_estados = $conn->query($sql_estados)) {
    while ($row = $result_estados->fetch_assoc()) {
        $estados_disponibles[$row['id_estado']] = $row['nombre_estado'];
    }
    $result_estados->free();
} else {
    $error_message = "Error al cargar los estados disponibles.";
}

// Obtener opciones de Estado de Pago
$estados_pago_disponibles = [];
$sql_estados_pago = "SELECT DISTINCT estado_pago FROM pedidos WHERE estado_pago IS NOT NULL ORDER BY estado_pago";
if ($res_ep = $conn->query($sql_estados_pago)) {
    while ($r = $res_ep->fetch_assoc()) {
        $estados_pago_disponibles[] = $r['estado_pago'];
    }
    $res_ep->free();
} else {
    error_log("No se pudieron cargar estados de pago: " . $conn->error);
}

// Obtener los IDs de los estados 'Entregado', 'Cancelado' y 'Devuelto'
$id_estado_entregado = null;
$id_estado_cancelado = null;
$id_estado_devuelto = null;

$sql_get_estados_exclude = "SELECT id_estado, nombre_estado FROM estados WHERE nombre_estado IN ('Entregado', 'Cancelado','Devuelto')";
if ($result_get_estados_exclude = $conn->query($sql_get_estados_exclude)) {
    while ($row_estado_exclude = $result_get_estados_exclude->fetch_assoc()) {
        if ($row_estado_exclude['nombre_estado'] == 'Entregado') {
            $id_estado_entregado = $row_estado_exclude['id_estado'];
        } elseif ($row_estado_exclude['nombre_estado'] == 'Cancelado') {
            $id_estado_cancelado = $row_estado_exclude['id_estado'];
        } elseif ($row_estado_exclude['nombre_estado'] == 'Devuelto') {
            $id_estado_devuelto = $row_estado_exclude['id_estado'];
        }
    }
    $result_get_estados_exclude->free();
} else {
    error_log("Error al obtener IDs de estados para el listado: " . $conn->error);
}

$id_estado_entregado_js = $id_estado_entregado;
$id_estado_cancelado_js = $id_estado_cancelado;

// Cambiar estado de pago (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'change_pago') {
    header('Content-Type: application/json; charset=utf-8');
    $pedido_id = isset($_POST['pedido_id']) ? intval($_POST['pedido_id']) : 0;
    $nuevo_estado_pago = isset($_POST['new_estado_pago']) ? trim($_POST['new_estado_pago']) : '';
    $response = ['success' => false, 'message' => ''];

    if ($pedido_id <= 0 || $nuevo_estado_pago === '') {
        $response['message'] = 'Datos inválidos (pedido o estado de pago).';
        echo json_encode($response);
        exit;
    }

    $sql_up = "UPDATE pedidos SET estado_pago = ? WHERE id_pedido = ?";
    if ($stmt_up = $conn->prepare($sql_up)) {
        $stmt_up->bind_param("si", $nuevo_estado_pago, $pedido_id);
        if ($stmt_up->execute()) {
            $response['success'] = true;
            $response['message'] = "Estado de pago actualizado a '{$nuevo_estado_pago}'.";
        } else {
            $response['message'] = "Error al actualizar estado de pago: " . $stmt_up->error;
        }
        $stmt_up->close();
    } else {
        $response['message'] = "Error en la consulta de actualización: " . $conn->error;
    }

    echo json_encode($response);
    exit;
}

// Cambiar estado del pedido (via AJAX)
if (isset($_POST['action']) && $_POST['action'] == 'change_status') {
    $pedido_id = intval($_POST['pedido_id']);
    $new_estado_id = intval($_POST['new_estado_id']);
    $usuario_id = $_SESSION['id_usuario'];

    $response = ['success' => false, 'message' => ''];
    $conn->begin_transaction();

    try {
        $sql_get_current_status = "SELECT id_estado, estado_pago FROM pedidos WHERE id_pedido = ? FOR UPDATE";
        if ($stmt_get_status = $conn->prepare($sql_get_current_status)) {
            $stmt_get_status->bind_param("i", $pedido_id);
            $stmt_get_status->execute();
            $stmt_get_status->bind_result($old_estado_id, $old_estado_pago);
            if (!$stmt_get_status->fetch()) {
                throw new Exception("Pedido no encontrado.");
            }
            $stmt_get_status->close();
        } else {
            throw new Exception("Error al preparar la consulta de estado actual: " . $conn->error);
        }

        if ($id_estado_entregado !== null && $new_estado_id === intval($id_estado_entregado)) {
            $old_estado_pago_str = is_null($old_estado_pago) ? '' : $old_estado_pago;
            if (strtolower(trim($old_estado_pago_str)) !== 'pago') {
                throw new Exception("No se puede marcar como 'Entregado' si el estado de pago no es 'Pago'.");
            }
        }

        $sql_update_status = "UPDATE pedidos SET id_estado = ? WHERE id_pedido = ?";
        if ($stmt_update_status = $conn->prepare($sql_update_status)) {
            $stmt_update_status->bind_param("ii", $new_estado_id, $pedido_id);
            if (!$stmt_update_status->execute()) {
                throw new Exception("Error al actualizar el estado del pedido: " . $stmt_update_status->error);
            }
            $stmt_update_status->close();
        } else {
            throw new Exception("Error al preparar la consulta de actualización: " . $conn->error);
        }

        $sql_insert_historial = "INSERT INTO historial_estado_pedido (id_pedido, id_estado_anterior, id_estado_nuevo, id_usuario_cambio) VALUES (?, ?, ?, ?)";
        if ($stmt_insert_historial = $conn->prepare($sql_insert_historial)) {
            $stmt_insert_historial->bind_param("iiii", $pedido_id, $old_estado_id, $new_estado_id, $usuario_id);
            if (!$stmt_insert_historial->execute()) {
                throw new Exception("Error al registrar el cambio de estado en el historial.");
            }
            $stmt_insert_historial->close();
        } else {
            throw new Exception("Error al preparar la consulta de historial.");
        }

        $conn->commit();
        $response['success'] = true;
        $response['message'] = "Estado del pedido #{$pedido_id} actualizado exitosamente.";
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = $e->getMessage();
    }
    echo json_encode($response);
    exit;
}

// Obtener todos los pedidos
$pedidos_db = [];
$sql_select_pedidos = "
    SELECT
        p.id_pedido,
        p.fecha_creacion,
        p.fecha_entrega_estimada,
        p.id_estado,
        e.nombre_estado,
        p.estado_pago,
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

$where_clauses = [];
if ($id_estado_entregado !== null) {
    $where_clauses[] = "p.id_estado != " . $id_estado_entregado;
}
if ($id_estado_cancelado !== null) {
    $where_clauses[] = "p.id_estado != " . $id_estado_cancelado;
}
if ($id_estado_devuelto !== null) {
     $where_clauses[] = "p.id_estado != " . $id_estado_devuelto;
}

if (!empty($where_clauses)) {
    $sql_select_pedidos .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql_select_pedidos .= " ORDER BY p.fecha_creacion DESC";

if ($result_pedidos = $conn->query($sql_select_pedidos)) {
    while ($pedido = $result_pedidos->fetch_assoc()) {
        $pedido['productos'] = [];

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
            }
            $stmt_detalle->close();
        }
        $pedidos_db[] = $pedido;
    }
    $result_pedidos->free();
} else {
    $error_message = "Error al cargar los pedidos: " . $conn->error;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - Florería "Spring Love"</title>
    <!-- Bootstrap CSS -->
    <link href="../css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
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

        /* Premium Header Banner */
        .page-header {
            background: linear-gradient(135deg, #065f46 0%, #0f766e 100%);
            border-radius: 20px;
            color: white;
            box-shadow: 0 10px 25px -5px rgba(6, 95, 70, 0.2);
            overflow: hidden;
            position: relative;
        }
        .page-header::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            top: -80px;
            right: -80px;
        }
        .page-header::before {
            content: '';
            position: absolute;
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
            bottom: -40px;
            left: -40px;
        }

        /* Modern Cards */
        .card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Accordion Custom Styles */
        .accordion-item {
            border: none;
            margin-bottom: 1rem;
            border-radius: 16px !important;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            background-color: transparent;
        }
        .accordion-item:last-child {
            margin-bottom: 0;
        }
        .accordion-header {
            background-color: #ffffff;
            border-radius: 16px;
        }
        .accordion-button {
            background-color: #ffffff;
            color: #1e293b;
            font-weight: 600;
            padding: 1.25rem 1.5rem;
            border-radius: 16px !important;
            box-shadow: none;
            transition: all 0.2s ease;
        }
        .accordion-button:not(.collapsed) {
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            color: #065f46;
            border-bottom-left-radius: 0 !important;
            border-bottom-right-radius: 0 !important;
        }
        .accordion-button:focus {
            box-shadow: none;
            border-color: transparent;
        }
        .accordion-button::after {
            background-size: 1rem;
        }
        .accordion-collapse {
            background-color: #ffffff;
            border-bottom-left-radius: 16px;
            border-bottom-right-radius: 16px;
        }
        .accordion-body {
            padding: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }

        /* Status Badges */
        .badge-status {
            padding: 0.35rem 0.85rem;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Product List Styles */
        .product-list {
            background-color: #f8fafc;
            border-radius: 14px;
            overflow: hidden;
        }
        .product-item {
            border-bottom: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
            transition: background-color 0.2s ease;
        }
        .product-item:last-child {
            border-bottom: none;
        }
        .product-item:hover {
            background-color: #f1f5f9;
        }

        /* Action Buttons */
        .btn-primary {
            background-color: #10b981;
            border-color: #10b981;
            color: white;
            font-weight: 600;
            border-radius: 12px;
            padding: 0.5rem 1.2rem;
            transition: all 0.2s ease-in-out;
        }
        .btn-primary:hover {
            background-color: #059669;
            border-color: #059669;
            transform: translateY(-1px);
        }
        .btn-outline-primary {
            border-color: #10b981;
            color: #065f46;
            font-weight: 500;
            border-radius: 12px;
        }
        .btn-outline-primary:hover {
            background-color: #10b981;
            border-color: #10b981;
            color: white;
            transform: translateY(-1px);
        }
        .btn-warning-custom {
            background-color: #f59e0b;
            border-color: #f59e0b;
            color: white;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.2s ease;
        }
        .btn-warning-custom:hover {
            background-color: #d97706;
            border-color: #d97706;
            transform: translateY(-1px);
        }

        /* Form Selects */
        .form-select-sm {
            border-radius: 10px;
            border-color: #e2e8f0;
            font-size: 0.875rem;
            padding: 0.4rem 2rem 0.4rem 0.8rem;
            background-color: #f8fafc;
            transition: all 0.2s ease;
        }
        .form-select-sm:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        /* Metric Badge */
        .metric-badge {
            background-color: #d1fae5;
            color: #065f46;
            padding: 0.5rem 1rem;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.875rem;
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

        /* Footer */
        footer {
            background-color: white;
            border-top: 1px solid #e2e8f0;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background-color: #ffffff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }
        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .accordion-item {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        .accordion-item:nth-child(1) { animation-delay: 0.05s; }
        .accordion-item:nth-child(2) { animation-delay: 0.1s; }
        .accordion-item:nth-child(3) { animation-delay: 0.15s; }
        .accordion-item:nth-child(4) { animation-delay: 0.2s; }
        .accordion-item:nth-child(5) { animation-delay: 0.25s; }
    </style>
</head>
<body class="min-vh-100 d-flex flex-column">
    <?php include 'menu.php'; ?>

    <main class="flex-grow-1 container my-5">
        <!-- Header Banner Moderno -->
        <div class="page-header p-4 p-md-5 mb-5">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="metric-badge">
                            <i class="bi bi-person-badge me-1"></i> Rol: <?php echo ucfirst($_SESSION["tipo_usuario"] ?? 'Ayudante'); ?>
                        </span>
                        <span class="metric-badge">
                            <i class="bi bi-calendar3 me-1"></i> <?php echo date('d/m/Y'); ?>
                        </span>
                    </div>
                    <h1 class="display-5 fw-bold mb-2">
                        <i class="bi bi-receipt me-3"></i>Gestión de Pedidos
                    </h1>
                    <p class="lead mb-0 text-white-50">
                        Administra los pedidos activos, actualiza estados y gestiona pagos.
                    </p>
                </div>
                <div class="mt-3 mt-md-0">
                    <div class="bg-white bg-opacity-15 p-3 rounded-4 text-secondary text-center" style="backdrop-filter: blur(10px);">
                        <i class="bi bi-cart-check fs-1 d-block mb-1"></i>
                        <span class="fw-semibold"><?php echo count($pedidos_db); ?> Pedidos Activos</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertas -->
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <!-- Listado de Pedidos -->
        <?php if (empty($pedidos_db)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h4 class="fw-semibold text-gray-600 mb-2">No hay pedidos activos</h4>
                <p class="text-muted mb-0">No se encontraron pedidos pendientes o en proceso actualmente.</p>
            </div>
        <?php else: ?>
            <div class="accordion" id="pedidosListAccordion">
                <?php foreach ($pedidos_db as $index => $pedido): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading_pedido_<?php echo $pedido['id_pedido']; ?>">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#collapse_pedido_<?php echo $pedido['id_pedido']; ?>" 
                                    aria-expanded="false" aria-controls="collapse_pedido_<?php echo $pedido['id_pedido']; ?>">
                                <div class="d-flex flex-wrap align-items-center gap-3 w-100">
                                    <!-- aqui quité el # despues de la etiqueta <i class="bi bi-hash"></i> X -->
                                    <span class="badge bg-dark rounded-pill px-3 py-2">
                                        <i class="bi bi-hash"></i> <?php echo $pedido['id_pedido']; ?>
                                    </span>
                                    <span class="fw-semibold text-truncate" style="max-width: 200px;">
                                        <i class="bi bi-person-circle me-1 text-success"></i>
                                        <?php echo htmlspecialchars($pedido['nombre_cliente']); ?>
                                    </span>
                                    <span class="badge-status <?php 
                                        $estado = $pedido['nombre_estado'];
                                        if (stripos($estado, 'Pendiente') !== false) echo 'bg-warning text-white';
                                        elseif (stripos($estado, 'Proceso') !== false) echo 'bg-info text-white';
                                        elseif (stripos($estado, 'Listo') !== false) echo 'bg-primary text-white';
                                        elseif (stripos($estado, 'Camino') !== false) echo 'bg-secondary text-white';
                                        else echo 'bg-success text-white';
                                    ?>">
                                        <i class="bi bi-circle-fill me-1" style="font-size: 0.6rem;"></i>
                                        <?php echo htmlspecialchars($pedido['nombre_estado']); ?>
                                    </span>
                                    <span class="text-muted ms-auto">
                                        <i class="bi bi-currency-dollar"></i> <?php echo number_format($pedido['total_estimado'], 2); ?>
                                    </span>
                                    <i class="bi bi-chevron-down text-muted"></i>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse_pedido_<?php echo $pedido['id_pedido']; ?>" class="accordion-collapse collapse" 
                             aria-labelledby="heading_pedido_<?php echo $pedido['id_pedido']; ?>" data-bs-parent="#pedidosListAccordion">
                            <div class="accordion-body">
                                <!-- Información del Pedido -->
                                <div class="row g-4 mb-4">
                                    <div class="col-md-6">
                                        <div class="bg-light rounded-3 p-3 h-100">
                                            <h6 class="fw-bold text-success mb-3">
                                                <i class="bi bi-info-circle me-2"></i>Información General
                                            </h6>
                                            <div class="d-flex flex-column gap-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bi bi-person text-muted" style="width: 24px;"></i>
                                                    <span class="text-muted">Cliente:</span>
                                                    <strong><?php echo htmlspecialchars($pedido['nombre_cliente']); ?></strong>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bi bi-card-list text-muted" style="width: 24px;"></i>
                                                    <span class="text-muted">Cédula:</span>
                                                    <span><?php echo htmlspecialchars($pedido['cliente_cedula_db'] ?? 'N/A'); ?></span>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bi bi-person-badge text-muted" style="width: 24px;"></i>
                                                    <span class="text-muted">Creado por:</span>
                                                    <span><?php echo htmlspecialchars($pedido['nombre_usuario_creador']); ?></span>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bi bi-calendar-plus text-muted" style="width: 24px;"></i>
                                                    <span class="text-muted">Fecha Creación:</span>
                                                    <span><?php echo date('d/m/Y H:i', strtotime($pedido['fecha_creacion'])); ?></span>
                                                </div>
                                                <?php if (!empty($pedido['fecha_entrega_estimada'])): ?>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i class="bi bi-truck text-muted" style="width: 24px;"></i>
                                                        <span class="text-muted">Entrega Estimada:</span>
                                                        <span><?php echo date('d/m/Y', strtotime($pedido['fecha_entrega_estimada'])); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="bg-light rounded-3 p-3 h-100">
                                            <h6 class="fw-bold text-success mb-3">
                                                <i class="bi bi-geo-alt me-2"></i>Información de Contacto
                                            </h6>
                                            <div class="d-flex flex-column gap-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bi bi-telephone text-muted" style="width: 24px;"></i>
                                                    <span class="text-muted">Teléfono:</span>
                                                    <span><?php echo htmlspecialchars($pedido['telefono_cliente']); ?></span>
                                                </div>
                                                <div class="d-flex gap-2">
                                                    <i class="bi bi-house-door text-muted" style="width: 24px;"></i>
                                                    <span class="text-muted">Dirección:</span>
                                                    <span class="flex-grow-1"><?php echo htmlspecialchars($pedido['direccion_entrega']); ?></span>
                                                </div>
                                                <?php if (!empty($pedido['notas_cliente'])): ?>
                                                    <div class="d-flex gap-2">
                                                        <i class="bi bi-chat-text text-muted" style="width: 24px;"></i>
                                                        <span class="text-muted">Notas:</span>
                                                        <span class="flex-grow-1"><?php echo nl2br(htmlspecialchars($pedido['notas_cliente'])); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Productos del Pedido -->
                                <h6 class="fw-bold text-success mb-3">
                                    <i class="bi bi-flower2 me-2"></i>Productos del Pedido
                                </h6>
                                <?php if (empty($pedido['productos'])): ?>
                                    <div class="text-center py-3 text-muted bg-light rounded-3">
                                        <i class="bi bi-box-seam fs-4 d-block mb-2"></i>
                                        <p class="mb-0">No se encontraron productos para este pedido.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="product-list">
                                        <?php foreach ($pedido['productos'] as $producto): ?>
                                            <div class="product-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="bi bi-flower2 text-success me-2"></i>
                                                    <strong><?php echo htmlspecialchars($producto['nombre_producto']); ?></strong>
                                                    <span class="text-muted ms-2">
                                                        (<?php echo ucfirst($producto['categoria']); ?>)
                                                    </span>
                                                </div>
                                                <div class="d-flex align-items-center gap-3">
                                                    <span class="text-muted">
                                                        <i class="bi bi-cart"></i> x<?php echo $producto['cantidad']; ?>
                                                    </span>
                                                    <span class="fw-semibold text-success">
                                                        $<?php echo number_format($producto['precio_unitario_al_momento_pedido'] * $producto['cantidad'], 2); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Acciones -->
                                <div class="mt-4 pt-3 border-top">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold small text-muted mb-1">
                                                <i class="bi bi-arrow-repeat"></i> Cambiar Estado
                                            </label>
                                            <select class="form-select form-select-sm status-changer"
                                                    id="status_select_<?php echo $pedido['id_pedido']; ?>"
                                                    data-pedido-id="<?php echo $pedido['id_pedido']; ?>"
                                                    data-current-status-id="<?php echo $pedido['id_estado']; ?>">
                                                <?php foreach ($estados_disponibles as $id_estado => $nombre_estado): ?>
                                                    <option value="<?php echo $id_estado; ?>" <?php echo ($pedido['id_estado'] == $id_estado) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($nombre_estado); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold small text-muted mb-1">
                                                <i class="bi bi-credit-card"></i> Estado del Pago
                                            </label>
                                            <select class="form-select form-select-sm payment-changer"
                                                    id="payment_select_<?php echo $pedido['id_pedido']; ?>"
                                                    data-pedido-id="<?php echo $pedido['id_pedido']; ?>">
                                                <option value="<?php echo htmlspecialchars($pedido['estado_pago'] ?? ''); ?>" selected>
                                                    <?php echo htmlspecialchars($pedido['estado_pago'] ?? 'Sin definir'); ?>
                                                </option>
                                                <?php foreach ($estados_pago_disponibles as $ep): 
                                                    if ($ep === ($pedido['estado_pago'] ?? '')) continue;
                                                ?>
                                                    <option value="<?php echo htmlspecialchars($ep); ?>">
                                                        <?php echo htmlspecialchars($ep); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <a href="gestion_pedidos.php?action=edit&id=<?php echo $pedido['id_pedido']; ?>" 
                                               class="btn btn-outline-primary w-100">
                                                <i class="bi bi-pencil-square me-2"></i>Modificar Pedido
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="footer mt-auto py-3 bg-white border-top text-center text-muted">
        <div class="container">
            <span>&copy; <?php echo date('Y'); ?> "Spring Love" - Sistema de Gestión de Pedidos.</span>
        </div>
    </footer>

    <!-- Bootstrap Bundle con Popper -->
    <script src="../js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script>
        const ID_ESTADO_ENTREGADO = <?php echo json_encode($id_estado_entregado_js); ?>;
        const ID_ESTADO_CANCELADO = <?php echo json_encode($id_estado_cancelado_js); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            // Cambio de estado del pedido
            document.querySelectorAll('.status-changer').forEach(selectElement => {
                selectElement.addEventListener('change', function() {
                    const pedidoId = this.dataset.pedidoId;
                    const newEstadoId = parseInt(this.value, 10);
                    const currentStatusId = parseInt(this.dataset.currentStatusId, 10);

                    if (newEstadoId === currentStatusId) return;

                    if (!confirm(`¿Cambiar estado del pedido #${pedidoId} a "${this.options[this.selectedIndex].text}"?`)) {
                        this.value = currentStatusId;
                        return;
                    }

                    fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=change_status&pedido_id=${encodeURIComponent(pedidoId)}&new_estado_id=${encodeURIComponent(newEstadoId)}`
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            location.reload();
                        } else {
                            showNotification('Error: ' + data.message, 'danger');
                            this.value = currentStatusId;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        showNotification('Error en la conexión al cambiar estado.', 'danger');
                        this.value = currentStatusId;
                    });
                });
            });

            // Cambio de estado de pago
            document.querySelectorAll('.payment-changer').forEach(selectEl => {
                selectEl.addEventListener('change', function() {
                    const pedidoId = this.dataset.pedidoId;
                    const nuevoPago = this.value;
                    const oldValue = this.querySelector('option:first-child').value;

                    if (!confirm(`¿Cambiar Estado del Pago del pedido #${pedidoId} a "${nuevoPago}"?`)) {
                        this.value = oldValue;
                        return;
                    }

                    const fd = new URLSearchParams();
                    fd.append('action', 'change_pago');
                    fd.append('pedido_id', pedidoId);
                    fd.append('new_estado_pago', nuevoPago);

                    fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: fd.toString()
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            const option = document.createElement('option');
                            option.value = nuevoPago;
                            option.textContent = nuevoPago;
                            option.selected = true;
                            this.insertBefore(option, this.firstChild);
                            this.removeChild(this.querySelector('option:first-child'));
                            if (this.options.length > 1) {
                                this.removeChild(this.lastChild);
                            }
                        } else {
                            showNotification('Error: ' + data.message, 'danger');
                            this.value = oldValue;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        showNotification('Error de conexión al actualizar estado de pago.', 'danger');
                    });
                });
            });

            // Función para mostrar notificaciones
            function showNotification(message, type) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-dismissible fade show rounded-4 border-0 shadow-sm position-fixed top-0 end-0 m-3`;
                alertDiv.style.zIndex = '9999';
                alertDiv.style.minWidth = '300px';
                alertDiv.innerHTML = `
                    <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                `;
                document.body.appendChild(alertDiv);
                setTimeout(() => alertDiv.remove(), 4000);
            }

            // Inicializar tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>