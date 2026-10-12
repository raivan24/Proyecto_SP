<?php
// ver_bitacora_rutas.php

session_start();

// Incluir el archivo de conexión a la base de datos
require_once '../controlador/db_connection.php'; // Ajusta la ruta si está en otra carpeta (ej: '../controlador/db_connection.php')
require_once '../controlador/func_ayuda.php'; // Asegúrar que la ruta es correcta
registrarEnBitacora($conn, "Visualización de Bitácora de Rutas"); // Registrar esta acción en la bitácora

// Verificar si el usuario no ha iniciado sesión o no es administrador
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["tipo_usuario"] !== 'admin') {
    header("location: index.php");
    exit;
}

$error_message = "";
$success_message = "";

// --- 1. OBTENER LISTA DE USUARIOS PARA EL FILTRO ---
$usuarios_sistema = [];
$sql_users = "SELECT id_usuario, nombre, tipo_usuario FROM usuarios ORDER BY nombre ASC";
if ($result_users = $conn->query($sql_users)) {
    while ($row = $result_users->fetch_assoc()) {
        $usuarios_sistema[] = $row;
    }
    $result_users->free();
}

// --- 2. PROCESAR FILTROS DE BÚSQUEDA ---
$filtro_usuario = isset($_GET['usuario']) ? intval($_GET['usuario']) : 0;
$filtro_fecha_desde = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';
$filtro_buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20; // Registros por página
$offset = ($page - 1) * $limit;

// --- 3. CONSTRUIR CONSULTA CON FILTROS DINÁMICOS ---
$sql_bitacora = "
    SELECT 
        b.id_bitacora,
        b.seccion,
        b.ruta_archivo,
        b.metodo_peticion,
        b.direccion_ip,
        b.navegador_dispositivo,
        b.fecha_hora,
        u.nombre AS nombre_usuario,
        u.tipo_usuario
    FROM 
        bitacora_rutas b
    JOIN 
        usuarios u ON b.id_usuario = u.id_usuario
";

$where_clauses = [];
$params = [];
$types = "";

if ($filtro_usuario > 0) {
    $where_clauses[] = "b.id_usuario = ?";
    $params[] = $filtro_usuario;
    $types .= "i";
}

if (!empty($filtro_fecha_desde)) {
    $where_clauses[] = "DATE(b.fecha_hora) >= ?";
    $params[] = $filtro_fecha_desde;
    $types .= "s";
}

if (!empty($filtro_fecha_hasta)) {
    $where_clauses[] = "DATE(b.fecha_hora) <= ?";
    $params[] = $filtro_fecha_hasta;
    $types .= "s";
}

if (!empty($filtro_buscar)) {
    $where_clauses[] = "(b.seccion LIKE ? OR b.ruta_archivo LIKE ? OR b.direccion_ip LIKE ? OR b.navegador_dispositivo LIKE ?)";
    $search_term = "%" . $filtro_buscar . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

if (!empty($where_clauses)) {
    $sql_bitacora .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql_count = "SELECT COUNT(*) AS total FROM bitacora_rutas b JOIN usuarios u ON b.id_usuario = u.id_usuario";
if (!empty($where_clauses)) {
    $sql_count .= " WHERE " . implode(" AND ", $where_clauses);
}

$total_rows = 0;
if ($stmt_count = $conn->prepare($sql_count)) {
    if (!empty($params)) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    if ($row_count = $result_count->fetch_assoc()) {
        $total_rows = intval($row_count['total']);
    }
    $stmt_count->close();
}

$total_pages = $total_rows > 0 ? ceil($total_rows / $limit) : 1;

// Ordenar por las visitas más recientes primero
$sql_bitacora .= " ORDER BY b.fecha_hora DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// Preparar y ejecutar la consulta principal de la bitácora
$registros_bitacora = [];
if ($stmt_bitacora = $conn->prepare($sql_bitacora)) {
    $stmt_bitacora->bind_param($types, ...$params);
    $stmt_bitacora->execute();
    $result_bitacora = $stmt_bitacora->get_result();
    while ($row = $result_bitacora->fetch_assoc()) {
        $registros_bitacora[] = $row;
    }
    $stmt_bitacora->close();
} else {
    $error_message = "Error al consultar la bitácora de rutas: " . $conn->error;
}


// --- 4. GENERAR ESTADÍSTICAS RÁPIDAS PARA LAS TARJETAS ---
$total_hoy = 0;
$seccion_mas_visitada = "Ninguna";
$usuario_mas_activo = "Ninguno";

// Total de accesos hoy
$sql_hoy = "SELECT COUNT(*) AS total FROM bitacora_rutas WHERE DATE(fecha_hora) = CURDATE()";
if ($res_hoy = $conn->query($sql_hoy)) {
    $row_hoy = $res_hoy->fetch_assoc();
    $total_hoy = $row_hoy['total'];
    $res_hoy->free();
}

// Sección más popular
$sql_popular = "SELECT seccion, COUNT(*) as visitas FROM bitacora_rutas GROUP BY seccion ORDER BY visitas DESC LIMIT 1";
if ($res_popular = $conn->query($sql_popular)) {
    if ($row_popular = $res_popular->fetch_assoc()) {
        $seccion_mas_visitada = $row_popular['seccion'] . " (" . $row_popular['visitas'] . ")";
    }
    $res_popular->free();
}

// Usuario más activo en el sistema
$sql_activo = "
    SELECT u.nombre, COUNT(*) as acciones 
    FROM bitacora_rutas b 
    JOIN usuarios u ON b.id_usuario = u.id_usuario 
    GROUP BY b.id_usuario 
    ORDER BY acciones DESC LIMIT 1
";
if ($res_activo = $conn->query($sql_activo)) {
    if ($row_activo = $res_activo->fetch_assoc()) {
        $usuario_mas_actv = $row_activo['nombre'] . " (" . $row_activo['acciones'] . ")";
    } else {
        $usuario_mas_actv = "Ninguno";
    }
    $res_activo->free();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bitácora de Rutas - Auditoría</title>
    <!-- Bootstrap CSS -->
    <link href="../css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="../img/bootstrap-icons.css">
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
            border: none;
        }
        .btn-success {
            background-color: #10b981;
            border-color: #10b981;
        }
        .btn-success:hover {
            background-color: #059669;
            border-color: #059669;
        }
        .badge-admin {
            background-color: #dc3545;
            color: white;
        }
        .badge-asistente {
            background-color: #fd7e14;
            color: white;
        }
        .badge-ayudante {
            background-color: #0dcaf0;
            color: black;
        }
        .text-truncate-custom {
            max-width: 220px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
<!-- Menú de Navegación -->
        <?php
        include 'menu.php';
        ?>
<!-- Menú Fin -->
    <main class="flex-grow-1 container mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-success fw-bold m-0"><i class="bi bi-journal-text"></i> Bitácora de Rutas del Sistema</h1>
            <a href="admin_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver al Panel</a>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <!-- Tarjetas de Resumen Estadístico -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card p-3 border-start border-4 border-success h-100">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.8rem;">Visitas Hoy</p>
                            <h3 class="fw-bold m-0 text-success"><?php echo $total_hoy; ?></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded-circle text-success">
                            <i class="bi bi-calendar-check fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3 border-start border-4 border-info h-100">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.8rem;">Sección más Visitada</p>
                            <h5 class="fw-bold m-0 text-info text-truncate-custom" title="<?php echo htmlspecialchars($seccion_mas_visitada); ?>">
                                <?php echo htmlspecialchars($seccion_mas_visitada); ?>
                            </h5>
                        </div>
                        <div class="bg-info bg-opacity-10 p-3 rounded-circle text-info">
                            <i class="bi bi-eye fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3 border-start border-4 border-warning h-100">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.8rem;">Usuario más Activo</p>
                            <h5 class="fw-bold m-0 text-warning text-truncate-custom" title="<?php echo htmlspecialchars($usuario_mas_actv); ?>">
                                <?php echo htmlspecialchars($usuario_mas_actv); ?>
                            </h5>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded-circle text-warning">
                            <i class="bi bi-person-badge fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel de Filtros -->
        <div class="card mb-4">
            <h5 class="card-header bg-success text-white fw-semibold"><i class="bi bi-funnel"></i> Filtrar y Buscar Registros</h5>
            <div class="card-body">
                <form method="GET" action="ver_bitacora_rutas.php" class="row g-3">
                    <div class="col-md-3">
                        <label for="usuario" class="form-label fw-medium">Usuario:</label>
                        <select name="usuario" id="usuario" class="form-select">
                            <option value="0">-- Todos los usuarios --</option>
                            <?php foreach ($usuarios_sistema as $user): ?>
                                <option value="<?php echo $user['id_usuario']; ?>" <?php echo $filtro_usuario === intval($user['id_usuario']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['nombre']) . " (" . ucfirst($user['tipo_usuario']) . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="fecha_desde" class="form-label fw-medium">Desde:</label>
                        <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="fecha_hasta" class="form-label fw-medium">Hasta:</label>
                        <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="buscar" class="form-label fw-medium">Búsqueda rápida:</label>
                        <input type="text" name="buscar" id="buscar" class="form-control" placeholder="Sección, IP, navegador..." value="<?php echo htmlspecialchars($filtro_buscar); ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-success w-100"><i class="bi bi-search"></i> Filtrar</button>
                        <a href="ver_bitacora_rutas.php" class="btn btn-secondary" title="Limpiar Filtros"><i class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Listado de la Bitácora -->
        <div class="card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="m-0 fw-semibold"><i class="bi bi-list-task"></i> Registros de Navegación (Máx. 20 por pág.)</h5>
                <span class="badge bg-white text-success fw-bold"><?php echo $total_rows; ?> registros encontrados</span>
            </div>
            <div class="card-body">
                <?php if (empty($registros_bitacora)): ?>
                    <div class="alert alert-info m-0" role="alert">
                        <i class="bi bi-info-circle-fill"></i> No se encontraron registros de navegación con los filtros seleccionados.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle" style="font-size: 0.9rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th>Sección / Módulo</th>
                                    <th>Archivo</th>
                                    <th class="text-center">Método</th>
                                    <th>Dirección IP</th>
                                    <th>Dispositivo / Navegador</th>
                                    <th class="text-end">Fecha y Hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registros_bitacora as $reg): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($reg['nombre_usuario']); ?></strong></td>
                                        <td>
                                            <?php 
                                            $badge_class = 'bg-secondary';
                                            if ($reg['tipo_usuario'] === 'admin') $badge_class = 'badge-admin';
                                            elseif ($reg['tipo_usuario'] === 'asistente') $badge_class = 'badge-asistente';
                                            elseif ($reg['tipo_usuario'] === 'ayudante') $badge_class = 'badge-ayudante';
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($reg['tipo_usuario']); ?></span>
                                        </td>
                                        <td><span class="text-success fw-semibold"><?php echo htmlspecialchars($reg['seccion']); ?></span></td>
                                        <td><code><?php echo htmlspecialchars($reg['ruta_archivo']); ?></code></td>
                                        <td class="text-center">
                                            <span class="badge <?php echo $reg['metodo_peticion'] === 'POST' ? 'bg-warning text-dark' : 'bg-primary'; ?>">
                                                <?php echo htmlspecialchars($reg['metodo_peticion']); ?>
                                            </span>
                                        </td>
                                        <td><i class="bi bi-pc-display"></i> <?php echo htmlspecialchars($reg['direccion_ip']); ?></td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($reg['navegador_dispositivo']); ?>">
                                                <small class="text-muted"><?php echo htmlspecialchars($reg['navegador_dispositivo']); ?></small>
                                            </div>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <strong><?php echo date('d/m/Y', strtotime($reg['fecha_hora'])); ?></strong>
                                            <span class="text-muted d-block" style="font-size: 0.8rem;"><?php echo date('H:i:s', strtotime($reg['fecha_hora'])); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_pages > 1 && !empty($registros_bitacora)): ?>
                        <nav aria-label="Paginación de bitácora" class="mt-3">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)]))); ?>" aria-label="Anterior">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>

                                <?php
                                $start = max(1, $page - 2);
                                $end = min($total_pages, $page + 2);
                                if ($start > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?' . htmlspecialchars(http_build_query(array_merge($_GET, ['page' => 1]))) . '">1</a></li>';
                                    if ($start > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                                    }
                                }
                                for ($p = $start; $p <= $end; $p++):
                                ?>
                                    <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $p]))); ?>"><?php echo $p; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <?php
                                if ($end < $total_pages) {
                                    if ($end < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?' . htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $total_pages]))) . '">' . $total_pages . '</a></li>';
                                }
                                ?>

                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)]))); ?>" aria-label="Siguiente">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="footer mt-auto py-3 bg-white border-top text-center text-muted">
        <div class="container">
            <span>&copy; <?php echo date('Y'); ?> Spring Love - Sistema de Auditoría Interna.</span>
        </div>
    </footer>
    <script src="../js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>