<?php
// asistente_dashboard.php

session_start();

// Incluir el archivo de conexión a la base de datos
require_once '../controlador/db_connection.php'; // Ajusta la ruta si es necesario
require_once '../controlador/func_ayuda.php'; // Asegurar que la ruta es correcta

// Verificar si el usuario no ha iniciado sesión o no es asistente
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["tipo_usuario"] !== 'asistente') {
    header("location: ../index.php"); // Ajusta la ruta si es necesario
    exit;
}

registrarEnBitacora($conn, "Dashboard de Asistente"); // funcion registro bitacora_ruta

// Obtener los IDs de los estados 'Entregado', 'Cancelado' y 'Devuelto'
$id_estado_entregado = null;
$id_estado_cancelado = null;
$id_estado_devuelto = null;

$sql_get_estados_exclude = "SELECT id_estado, nombre_estado FROM estados WHERE nombre_estado IN ('Entregado', 'Cancelado', 'Devuelto')";
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
    error_log("Error al obtener IDs de estados 'Entregado' y 'Cancelado' para el resumen: " . $conn->error);
}

// CORRECCIÓN DEL ERROR SQL:
// Cambiado 'c.nombre_cliente' a 'p.nombre_cliente' y 'p.fecha_pedido' a 'p.fecha_creacion AS fecha_pedido'
$pedidos_recientes = [];
$sql_pedidos_recientes = "
    SELECT p.id_pedido, p.nombre_cliente, e.nombre_estado, p.fecha_creacion AS fecha_pedido
    FROM pedidos p
    JOIN clientes c ON p.id_cliente = c.id_cliente
    JOIN estados e ON p.id_estado = e.id_estado
    ORDER BY p.fecha_creacion DESC
    LIMIT 5
";
if ($result_recientes = $conn->query($sql_pedidos_recientes)) {
    while ($row = $result_recientes->fetch_assoc()) {
        $pedidos_recientes[] = $row;
    }
    $result_recientes->free();
} else {
    error_log("Error al obtener pedidos recientes: " . $conn->error);
}

$pedido_estados_resumen = [];
$sql_resumen_pedidos = "
    SELECT e.nombre_estado, COUNT(p.id_pedido) AS total_pedidos
    FROM pedidos p
    JOIN estados e ON p.id_estado = e.id_estado
";

$resumen_where_clauses = [];
if ($id_estado_entregado !== null) {
    $resumen_where_clauses[] = "e.id_estado != " . $id_estado_entregado;
}
if ($id_estado_cancelado !== null) {
    $resumen_where_clauses[] = "e.id_estado != " . $id_estado_cancelado;
}
if ($id_estado_devuelto !== null) {
    $resumen_where_clauses[] = "e.id_estado != " . $id_estado_devuelto;
}

if (!empty($resumen_where_clauses)) {
    $sql_resumen_pedidos .= " WHERE " . implode(" AND ", $resumen_where_clauses);
}

$sql_resumen_pedidos .= " GROUP BY e.nombre_estado ORDER BY e.nombre_estado;";

if ($result_resumen = $conn->query($sql_resumen_pedidos)) {
    while ($row = $result_resumen->fetch_assoc()) {
        $pedido_estados_resumen[] = $row;
    }
    $result_resumen->free();
} else {
    error_log("Error al obtener resumen de pedidos por estado: " . $conn->error);
}

// Contar pedidos activos totales
$total_pedidos_activos = 0;
foreach ($pedido_estados_resumen as $resumen) {
    $total_pedidos_activos += $resumen['total_pedidos'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Asistente - Florería "Spring Love"</title>
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
        
        /* Dashboard Premium Header */
        .welcome-banner {
            background: linear-gradient(135deg, #065f46 0%, #0f766e 100%);
            border-radius: 16px;
            color: white;
            box-shadow: 0 10px 25px -5px rgba(6, 95, 70, 0.2);
            overflow: hidden;
            position: relative;
        }
        .welcome-banner::after {
            content: '';
            position: absolute;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            top: -50px;
            right: -50px;
        }
        
        /* Modern Cards */
        .card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border-radius: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        .metric-card {
            border-left: 4px solid #10b981;
        }
        
        /* Hover Effect for Access Cards */
        .access-card {
            cursor: pointer;
            height: 100%;
        }
        .access-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 20px -8px rgba(0, 0, 0, 0.15);
            border-color: #a7f3d0;
        }
        
        /* Status Badges Cards */
        .status-box {
            background-color: #f0fdf4;
            border: 1px solid #d1fae5;
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
            flex: 1;
            min-width: 150px;
            transition: transform 0.2s ease;
        }
        .status-box:hover {
            transform: scale(1.03);
        }
        
        /* Action Buttons */
        .btn-primary {
            background-color: #10b981;
            border-color: #10b981;
            color: white;
            font-weight: 600;
            border-radius: 10px;
            padding: 0.6rem 1.2rem;
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
            border-radius: 10px;
        }
        .btn-outline-primary:hover {
            background-color: #10b981;
            border-color: #10b981;
            color: white;
            transform: translateY(-1px);
        }
        
        /* Icon Containers */
        .icon-circle {
            width: 54px;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }
        .icon-circle-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        .icon-circle-info {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        .icon-circle-warning {
            background-color: #fef3c7;
            color: #b45309;
        }
        .icon-circle-purple {
            background-color: #ede9fe;
            color: #6d28d9;
        }
        
        /* Table styles */
        .recent-orders-table {
            font-size: 0.9rem;
        }
        .recent-orders-table th {
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
        }
        .badge-status {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
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
    </style>
</head>
<body class="min-vh-100 d-flex flex-column">
    <?php
    include 'menu.php';
    ?>

    <main class="flex-grow-1 container my-5">
        <!-- Banner de bienvenida moderno -->
        <div class="welcome-banner p-4 p-md-5 mb-5 d-flex align-items-center justify-content-between">
            <div>
                <span class="badge bg-white bg-opacity-20 text-warning mb-2 px-3 py-2 rounded-pill fw-semibold">
                    <i class="bi bi-person-badge"></i> Rol: Asistente
                </span>
                <h2 class="display-6 fw-bold mb-2">¡Bienvenido, <?php echo htmlspecialchars($_SESSION["nombre"]); ?>!</h2>
                <p class="lead mb-0 text-white-50">Gestión de pedidos, ventas y atención al cliente para "Spring Love".</p>
                <div class="mt-3">
                    <span class="badge bg-white bg-opacity-25 text-white me-2">
                        <i class="bi bi-person-circle"></i> ID: <?php echo htmlspecialchars($_SESSION["id_usuario"]); ?>
                    </span>
                </div>
            </div>
            <div class="d-none d-lg-block text-white bg-white bg-opacity-10 p-4 rounded-3 text-center" style="backdrop-filter: blur(5px);">
                <i class="bi bi-clock-history fs-3 d-block mb-1"></i>
                <span class="small fw-semibold"><?php echo date('d/m/Y'); ?></span>
            </div>
        </div>

        <!-- Fila de métricas rápidas -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card metric-card p-4 h-100">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.8rem; letter-spacing: 0.05em;">Pedidos Activos</span>
                            <h2 class="fw-bold m-0 mt-1 text-dark"><?php echo $total_pedidos_activos; ?></h2>
                        </div>
                        <div class="icon-circle icon-circle-success">
                            <i class="bi bi-cart-check fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card metric-card p-4 h-100" style="border-left-color: #0284c7;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.8rem; letter-spacing: 0.05em;">Estados Activos</span>
                            <h2 class="fw-bold m-0 mt-1 text-dark"><?php echo count($pedido_estados_resumen); ?></h2>
                        </div>
                        <div class="icon-circle icon-circle-info">
                            <i class="bi bi-diagram-3 fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card metric-card p-4 h-100" style="border-left-color: #d97706;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.8rem; letter-spacing: 0.05em;">Pedidos Recientes</span>
                            <h2 class="fw-bold m-0 mt-1 text-dark"><?php echo count($pedidos_recientes); ?></h2>
                        </div>
                        <div class="icon-circle icon-circle-warning">
                            <i class="bi bi-clock fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección: Resumen de Pedidos por Estado -->
        <div class="card mb-5 border-0 shadow-sm">
            <h4 class="card-header bg-dark text-white py-3 px-4 fw-semibold d-flex align-items-center justify-content-between">
                <span><i class="bi bi-hourglass-split me-2"></i> Pedidos Activos por Estado</span>
                <span class="text-white-50 small" style="font-size: 0.85rem;">Excluye Entregados, Cancelados y Devueltos</span>
            </h4>
            <div class="card-body p-4">
                <?php if (empty($pedido_estados_resumen)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-info-circle fs-2 d-block mb-2"></i>
                        <p class="m-0">No hay pedidos pendientes o activos registrados actualmente.</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-wrap justify-content-start gap-4">
                        <?php foreach ($pedido_estados_resumen as $resumen): ?>
                            <?php 
                                // Determinar icono y clase de color basada en el estado
                                $status_color_class = "text-success bg-success bg-opacity-10 border-success border-opacity-30";
                                $status_icon = "bi-clipboard";
                                
                                if (stripos($resumen['nombre_estado'], 'Pendiente') !== false) {
                                    $status_color_class = "text-warning bg-warning bg-opacity-10 border-warning border-opacity-30";
                                    $status_icon = "bi-exclamation-circle";
                                } elseif (stripos($resumen['nombre_estado'], 'Proceso') !== false) {
                                    $status_color_class = "text-info bg-info bg-opacity-10 border-info border-opacity-30";
                                    $status_icon = "bi-gear-wide-connected";
                                } elseif (stripos($resumen['nombre_estado'], 'Listo') !== false) {
                                    $status_color_class = "text-primary bg-primary bg-opacity-10 border-primary border-opacity-30";
                                    $status_icon = "bi-gift";
                                } elseif (stripos($resumen['nombre_estado'], 'Camino') !== false) {
                                    $status_color_class = "text-teal bg-teal bg-opacity-10 border-teal border-opacity-30";
                                    $status_icon = "bi-truck";
                                }
                            ?>
                            <div class="status-box flex-grow-1 border d-flex flex-column align-items-center justify-content-center <?php echo $status_color_class; ?>">
                                <i class="bi <?php echo $status_icon; ?> fs-3 mb-2"></i>
                                <h6 class="fw-bold m-0"><?php echo htmlspecialchars($resumen['nombre_estado']); ?></h6>
                                <h3 class="fw-bold m-0 mt-1"><?php echo htmlspecialchars($resumen['total_pedidos']); ?></h3>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sección: Pedidos Recientes -->
        <div class="card mb-5 border-0 shadow-sm">
            <h4 class="card-header bg-dark text-white py-3 px-4 fw-semibold">
                <i class="bi bi-clock-history me-2"></i> Últimos Pedidos Registrados
            </h4>
            <div class="card-body p-0">
                <?php if (empty($pedidos_recientes)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                        <p class="m-0">No hay pedidos registrados recientemente.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover recent-orders-table mb-0">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3">ID Pedido</th>
                                    <th class="py-3">Cliente</th>
                                    <th class="py-3">Estado</th>
                                    <th class="py-3">Fecha de Creación</th>
                                    <th class="px-4 py-3 text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos_recientes as $pedido): ?>
                                    <tr>
                                        <td class="px-4 py-2 fw-semibold">#<?php echo $pedido['id_pedido']; ?></td>
                                        <td class="py-2"><?php echo htmlspecialchars($pedido['nombre_cliente']); ?></td>
                                        <td class="py-2">
                                            <?php
                                                $badge_class = 'bg-secondary';
                                                if (stripos($pedido['nombre_estado'], 'Pendiente') !== false) $badge_class = 'bg-warning text-dark';
                                                elseif (stripos($pedido['nombre_estado'], 'Proceso') !== false) $badge_class = 'bg-info text-dark';
                                                elseif (stripos($pedido['nombre_estado'], 'Listo') !== false) $badge_class = 'bg-primary';
                                                elseif (stripos($pedido['nombre_estado'], 'Camino') !== false) $badge_class = ' bg-secondary text-white';
                                                elseif (stripos($pedido['nombre_estado'], 'Entregado') !== false) $badge_class = 'bg-success';
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?> badge-status">
                                                <?php echo htmlspecialchars($pedido['nombre_estado']); ?>
                                            </span>
                                        </td>
                                        <td class="py-2 text-muted"><?php echo date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])); ?></td>
                                        <td class="px-4 py-2 text-end">
                                            <a href="gestion_pedidos.php?action=edit&id=<?php echo $pedido['id_pedido']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> Ver / Editar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent text-end border-top py-3">
                <a href="gestion_pedidos.php" class="btn btn-link text-success text-decoration-none">
                    Ver todos los pedidos <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Sección: Accesos Rápidos a Módulos del Sistema -->
        <h4 class="text-dark fw-bold mb-4"><i class="bi bi-grid-3x3-gap"></i> Accesos Rápidos del Sistema</h4>
        <div class="row g-4">
            <!-- Gestión de Pedidos -->
            <div class="col-md-6 col-lg-4">
                <div class="card access-card h-100 p-4 d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="icon-circle icon-circle-success">
                            <i class="bi bi-receipt-cutoff fs-4"></i>
                        </div>
                        <h4 class="card-title text-xl font-bold text-gray-800 m-0">Gestión de Pedidos</h4>
                    </div>
                    <p class="card-text text-secondary">Crea, revisa y actualiza los pedidos de arreglos florales, define detalles de entrega y cambia estados.</p>
                    <div class="mt-auto pt-3">
                        <a href="gestion_pedidos.php" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle me-2"></i>Gestionar Pedidos
                        </a>
                    </div>
                </div>
            </div>

            <!-- Consultar Stock / Productos -->
            <div class="col-md-6 col-lg-4">
                <div class="card access-card h-100 p-4 d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="icon-circle icon-circle-info">
                            <i class="bi bi-flower2 fs-4"></i>
                        </div>
                        <h4 class="card-title text-xl font-bold text-gray-800 m-0">Productos & Stock</h4>
                    </div>
                    <p class="card-text text-secondary">Verifica la disponibilidad de flores, arreglos florales y artículos en el inventario actual.</p>
                    <div class="mt-auto pt-3">
                        <a href="productos.php" class="btn btn-primary w-100">
                            <i class="bi bi-search me-2"></i>Consultar Productos
                        </a>
                    </div>
                </div>
            </div>

            <!-- Gestión de Clientes -->
            <div class="col-md-6 col-lg-4">
                <div class="card access-card h-100 p-4 d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="icon-circle icon-circle-purple">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                        <h4 class="card-title text-xl font-bold text-gray-800 m-0">Gestión de Clientes</h4>
                    </div>
                    <p class="card-text text-secondary">Administra la base de datos de clientes, actualiza información de contacto y preferencias de envío.</p>
                    <div class="mt-auto pt-3">
                        <a href="gestion_clientes.php" class="btn btn-primary w-100">
                            <i class="bi bi-person-plus me-2"></i>Gestionar Clientes
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer mt-auto py-3 bg-white border-top text-center text-muted">
        <div class="container">
            <span>&copy; <?php echo date('Y'); ?> "Spring Love" - Panel de Asistente.</span>
        </div>
    </footer>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="../js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>