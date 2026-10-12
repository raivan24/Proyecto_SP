<?php
// admin_dashboard.php

session_start();

// Incluir el archivo de conexión a la base de datos
require_once '../controlador/db_connection.php'; // Ajusta la ruta si es necesario
require_once '../controlador/func_ayuda.php'; // Asegurar que la ruta es correcta

// Verificar si el usuario no ha iniciado sesión o no es administrador
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["tipo_usuario"] !== 'admin') {
    header("location: ../index.php"); 
    exit;
}

//---------------------------

// Inicializar el tiempo si es la primera vez que entra
if (!isset($_SESSION['tiempo'])) {
    $_SESSION['tiempo'] = time();
}
//----------------------------

registrarEnBitacora($conn, "Dashboard de Administrador"); // funcion registro bitacora_ruta

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
    // Manejar el error de la consulta
    error_log("Error al obtener resumen de pedidos por estado: " . $conn->error);
}

// --- CONSULTAS EXTRAS PARA TARJETAS DE MÉTRICAS RÁPIDAS ---
$total_productos = 0;
if ($res_p = $conn->query("SELECT COUNT(*) AS total FROM productos")) {
    $total_productos = $res_p->fetch_assoc()['total'];
    $res_p->free();
}

$total_clientes = 0;
if ($res_c = $conn->query("SELECT COUNT(*) AS total FROM clientes")) {
    $total_clientes = $res_c->fetch_assoc()['total'];
    $res_c->free();
}

$total_usuarios = 0;
if ($res_u = $conn->query("SELECT COUNT(*) AS total FROM usuarios")) {
    $total_usuarios = $res_u->fetch_assoc()['total'];
    $res_u->free();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Florería "Spring Love"</title>
    <!-- Bootstrap CSS -->
    <link href="../css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="../img/bootstrap-icons-1.13.1/bootstrap-icons.css">
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
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            transition: background-color 0.2s ease-in-out, border-color 0.2s ease-in-out;
        }
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
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
                <span class="badge bg-white bg-opacity-20 text-danger mb-2 px-3 py-2 rounded-pill fw-semibold"><i class="bi bi-shield-check"></i> Modo Administrador</span>
                
                <h2 class="display-6 fw-bold mb-2">¡Bienvenido, <?php echo htmlspecialchars($_SESSION["nombre"]); ?>!</h2>
                <p class="lead mb-0 text-white-50">Control de inventario, ventas, usuarios y monitoreo de auditoría en tiempo real para "Spring Love".</p>
            </div>
            <div class="d-none d-lg-block text-white bg-white bg-opacity-10 p-4 rounded-3 text-center" style="backdrop-filter: blur(5px);">
                <i class="bi bi-clock-history fs-3 d-block mb-1"></i>
                <span id="todayDate" class="small fw-semibold"><?php echo date('d/m/Y'); ?></span>
            </div>
        </div>

        <!-- Fila de métricas rápidas (True Dashboard) -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card metric-card p-4 h-100">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.8rem; letter-spacing: 0.05em;">Productos en Catálogo</span>
                            <h2 class="fw-bold m-0 mt-1 text-dark"><?php echo $total_productos; ?></h2>
                        </div>
                        <div class="icon-circle icon-circle-success">
                            <i class="bi bi-flower2 fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card metric-card p-4 h-100" style="border-left-color: #0284c7;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.8rem; letter-spacing: 0.05em;">Clientes Registrados</span>
                            <h2 class="fw-bold m-0 mt-1 text-dark"><?php echo $total_clientes; ?></h2>
                        </div>
                        <div class="icon-circle icon-circle-info">
                            <i class="bi bi-people-fill fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card metric-card p-4 h-100" style="border-left-color: #d97706;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.8rem; letter-spacing: 0.05em;">Usuarios Activos</span>
                            <h2 class="fw-bold m-0 mt-1 text-dark"><?php echo $total_usuarios; ?></h2>
                        </div>
                        <div class="icon-circle icon-circle-warning">
                            <i class="bi bi-person-workspace fs-3"></i>
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

        <!-- Sección: Accesos Rápidos a Módulos del Sistema -->
        <h4 class="text-dark fw-bold mb-4"><i class="bi bi-grid-3x3-gap"></i> Accesos Rápidos del Sistema</h4>
        <div class="row g-4">
            <!-- Productos -->
            <div class="col-md-6 col-lg-4">
                <div class="card access-card h-100 p-4 d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="icon-circle icon-circle-success">
                            <i class="bi bi-flower1 fs-4"></i>
                        </div>
                        <h4 class="card-title text-xl font-bold text-gray-800 m-0">Productos</h4>
                    </div>
                    <p class="card-text text-secondary">Administra el inventario de flores, arreglos florales, golosinas, peluches y otros artículos disponibles en el catálogo.</p>
                    <div class="mt-auto pt-3">
                        <a href="productos.php" class="btn btn-primary w-100">Gestionar Productos</a>
                    </div>
                </div>
            </div>

            <!-- Usuarios -->
            <div class="col-md-6 col-lg-4">
                <div class="card access-card h-100 p-4 d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="icon-circle icon-circle-info">
                            <i class="bi bi-shield-lock-fill fs-4"></i>
                        </div>
                        <h4 class="card-title text-xl font-bold text-gray-800 m-0">Usuarios</h4>
                    </div>
                    <p class="card-text text-secondary">Gestiona los accesos, información confidencial, claves seguras e introduce o edita el rol de los empleados del sistema.</p>
                    <div class="mt-auto pt-3">
                        <a href="gestion_usuarios.php" class="btn btn-primary w-100">Gestionar Usuarios</a>
                    </div>
                </div>
            </div>

            <!-- Estados -->
            <div class="col-md-6 col-lg-4">
                <div class="card access-card h-100 p-4 d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="icon-circle icon-circle-warning">
                            <i class="bi bi-tags fs-4"></i>
                        </div>
                        <h4 class="card-title text-xl font-bold text-gray-800 m-0">Estados</h4>
                    </div>
                    <p class="card-text text-secondary">Define y personaliza los estados de seguimiento que asocian el flujo de diseño y distribución de cada arreglo floral.</p>
                    <div class="mt-auto pt-3">
                        <a href="gestion_estados.php" class="btn btn-primary w-100">Gestionar Estados</a>
                    </div>
                </div>
            </div>

            <!-- Pedidos -->
            <div class="col-md-6 col-lg-4">
                <div class="card access-card h-100 p-4 d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-3" style="color: #ea580c;">
                        <div class="icon-circle" style="background-color: #ffedd5; color: #ea580c;">
                            <i class="bi bi-receipt-cutoff fs-4"></i>
                        </div>
                        <h4 class="card-title text-xl font-bold text-gray-800 m-0">Pedidos</h4>
                    </div>
                    <p class="card-text text-secondary">Genera solicitudes de arreglos, define detalles de entrega y administra los estados activos de cada preparación floral.</p>
                    <div class="mt-auto pt-3">
                        <a href="gestion_pedidos.php" class="btn btn-primary w-100">Gestionar Pedidos</a>
                    </div>
                </div>
            </div>

            <!-- Clientes -->
            <div class="col-md-6 col-lg-4">
                <div class="card access-card h-100 p-4 d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-3" style="color: #db2777;">
                        <div class="icon-circle" style="background-color: #fce7f3; color: #db2777;">
                            <i class="bi bi-person-vcard fs-4"></i>
                        </div>
                        <h4 class="card-title text-xl font-bold text-gray-800 m-0">Clientes</h4>
                    </div>
                    <p class="card-text text-secondary">Mantén organizada la información de contacto de los clientes frecuentes, su número de identificación fiscal o cédula.</p>
                    <div class="mt-auto pt-3">
                        <a href="gestion_clientes.php" class="btn btn-primary w-100">Gestionar Clientes</a>
                    </div>
                </div>
            </div>

            <!-- Bitácora de Rutas -->
            <div class="col-md-6 col-lg-4">
                <div class="card access-card h-100 p-4 d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-3" style="color: #475569;">
                        <div class="icon-circle" style="background-color: #f1f5f9; color: #475569;">
                            <i class="bi bi-file-earmark-text-fill fs-4"></i>
                        </div>
                        <h4 class="card-title text-xl font-bold text-gray-800 m-0">Bitácora de Rutas</h4>
                    </div>
                    <p class="card-text text-secondary">Supervisa y audita las rutas que cada usuario ha visitado dentro del sistema con marcas de hora, fecha, e IPs de acceso.</p>
                    <div class="mt-auto pt-3">
                        <a href="ver_bitacora_rutas.php" class="btn btn-primary w-100" style="background-color: #475569; border-color: #475569;">Ver Bitácora de Rutas</a>
                    </div>
                </div>
            </div>
        </div>
        
             <!-- Modal para Restauración de Base de Datos -->
    <div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="restoreModalLabel">
                        <i class="bi bi-exclamation-triangle-fill"></i> Restaurar Base de Datos
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="restoreForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-circle-fill"></i>
                            <strong>¡Advertencia!</strong> Esta acción reemplazará todos los datos actuales. 
                            Se recomienda hacer una copia de seguridad antes de continuar.
                        </div>
                        <div class="mb-3">
                            <label for="backupFile" class="form-label">Seleccionar archivo SQL:</label>
                            <input type="file" class="form-control" id="backupFile" name="backupFile" accept=".sql" required>
                            <div class="form-text">Solo archivos .sql (máx. 50MB)</div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirmRestore" required>
                                <label class="form-check-label" for="confirmRestore">
                                    Confirmo que deseo restaurar la base de datos y entiendo que esta acción es irreversible
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning" id="btnRestore">
                            <i class="bi bi-arrow-repeat"></i> Restaurar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Fin del Modal para Restauración de Base de Datos -->
    </main>

    <footer class="footer mt-auto py-3 bg-white border-top text-center text-muted">
        <div class="container">
            <span>&copy; <?php echo date('Y'); ?>  "Spring Love" - Panel de Administración Seguro.</span>
        </div>
    </footer>

    <!-- Script de control de sesión -->
    <script src="../js/session-monitor.js"></script>
     <!-- SweetAlert2 -->
    <script src="../js/sweetalert2.all.js"></script>
    <!-- Bootstrap Bundle with Popper -->
    <script src="../js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <script>
    document.getElementById('restoreForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const confirmCheckbox = document.getElementById('confirmRestore');
        if (!confirmCheckbox.checked) {
            Swal.fire({
                icon: 'warning',
                title: 'Confirmación requerida',
                text: 'Debes marcar la casilla de confirmación para proceder con la restauración.',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }
        
        const fileInput = document.getElementById('backupFile');
        if (fileInput.files.length === 0) {
            Swal.fire({
                icon: 'error',
                title: 'Archivo no seleccionado',
                text: 'Por favor, selecciona un archivo SQL para restaurar.',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }
        
        const formData = new FormData();
        formData.append('backupFile', fileInput.files[0]);
        
        Swal.fire({
            title: 'Restaurando base de datos...',
            text: 'Por favor espera, este proceso puede tomar varios minutos.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        const btnRestore = document.getElementById('btnRestore');
        btnRestore.disabled = true;
        btnRestore.innerHTML = '<i class="bi bi-hourglass-split"></i> Procesando...';
        
        fetch('../controlador/restaurar_basedatos.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text().then(text => ({ ok: response.ok, status: response.status, text })))
        .then(({ ok, status, text }) => {
            let data;
            try {
                data = text ? JSON.parse(text) : null;
            } catch (parseError) {
                throw new Error(`Respuesta no válida del servidor (HTTP ${status}): ${text}`);
            }

            if (!ok) {
                throw new Error(data && data.message ? data.message : `HTTP ${status} - ${text}`);
            }

            if (data && data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Restauración exitosa!',
                    html: `<p>${data.message}</p>`,
                    confirmButtonColor: '#f59e0b'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error en la restauración',
                    html: `<p>${data && data.message ? data.message : 'La respuesta del servidor no contiene éxito.'}</p>${data && data.error_detail ? `<p class="text-muted small">Detalle: ${data.error_detail}</p>` : `<p class="text-muted small">Respuesta del servidor: ${text}</p>`}`,
                    confirmButtonColor: '#f59e0b'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                html: `<p>No se pudo completar la restauración.</p><p class="text-muted small">Error: ${error.message}</p>`,
                confirmButtonColor: '#f59e0b'
            });
        })
        .finally(() => {
            btnRestore.disabled = false;
            btnRestore.innerHTML = '<i class="bi bi-arrow-repeat"></i> Restaurar';
            document.getElementById('restoreForm').reset();
            const modal = bootstrap.Modal.getInstance(document.getElementById('restoreModal'));
            if (modal) {
                modal.hide();
            }
        });
    });
    </script>



</body>
</html>