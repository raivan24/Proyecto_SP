<?php
// reportes_pedidos.php

session_start();

// Incluir el archivo de conexión a la base de datos
require_once '../controlador/db_connection.php'; // Asegúrate de que la ruta es correcta
require_once '../controlador/func_ayuda.php'; // Asegúrar que la ruta es correcta
registrarEnBitacora($conn, "Reportes de Pedidos"); //funcion registro bitacora_ruta

// Verificar si el usuario no ha iniciado sesión o no tiene permisos (solo admin y asistente)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || ($_SESSION["tipo_usuario"] !== 'admin' && $_SESSION["tipo_usuario"] !== 'asistente')) {
    header("location: ../index.php"); // Ajusta la ruta si es necesario
    exit;
}

// Variables para reportes
$report_type = $_GET['report_type'] ?? '';
$selected_date = $_GET['selected_date'] ?? '';
$selected_week = $_GET['selected_week'] ?? '';
$selected_month = $_GET['selected_month'] ?? '';
$selected_year = $_GET['selected_year'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$report_data = [];
$total_generated = 0.0;
$total_pedidos = 0;
$pedidos_excluidos = 0;
$error_message = '';

// Estados que no contribuyen al total generado
$excluded_states = ['Devuelto', 'Cancelado'];

if ($_SERVER["REQUEST_METHOD"] == "GET" && !empty($report_type)) {
    $conn->begin_transaction();
    try {
        if ($report_type === 'day' && !empty($selected_date)) {
            // Reporte por día
            $sql = "
                SELECT p.id_pedido, p.fecha_creacion, p.nombre_cliente, p.total_estimado, p.estado_pago, e.nombre_estado
                FROM pedidos p
                JOIN estados e ON p.id_estado = e.id_estado
                WHERE DATE(p.fecha_creacion) = ?
                ORDER BY p.fecha_creacion DESC
            ";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $selected_date);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $report_data[] = $row;
                    $total_pedidos++;
                    if (!in_array($row['nombre_estado'], $excluded_states)) {
                        $total_generated += $row['total_estimado'];
                    } else {
                        $pedidos_excluidos++;
                    }
                }
                $stmt->close();
            }
        } elseif ($report_type === 'week' && !empty($selected_week)) {
            // Reporte por semana
            $sql = "
                SELECT p.id_pedido, p.fecha_creacion, p.nombre_cliente, p.total_estimado, p.estado_pago, e.nombre_estado
                FROM pedidos p
                JOIN estados e ON p.id_estado = e.id_estado
                WHERE YEARWEEK(p.fecha_creacion, 1) = YEARWEEK(?, 1)
                ORDER BY p.fecha_creacion DESC
            ";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $selected_week);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $report_data[] = $row;
                    $total_pedidos++;
                    if (!in_array($row['nombre_estado'], $excluded_states)) {
                        $total_generated += $row['total_estimado'];
                    } else {
                        $pedidos_excluidos++;
                    }
                }
                $stmt->close();
            }
        } elseif ($report_type === 'month' && !empty($selected_month) && !empty($selected_year)) {
            // Reporte por mes
            $sql = "
                SELECT p.id_pedido, p.fecha_creacion, p.nombre_cliente, p.total_estimado, p.estado_pago, e.nombre_estado
                FROM pedidos p
                JOIN estados e ON p.id_estado = e.id_estado
                WHERE MONTH(p.fecha_creacion) = ? AND YEAR(p.fecha_creacion) = ?
                ORDER BY p.fecha_creacion DESC
            ";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ii", $selected_month, $selected_year);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $report_data[] = $row;
                    $total_pedidos++;
                    if (!in_array($row['nombre_estado'], $excluded_states)) {
                        $total_generated += $row['total_estimado'];
                    } else {
                        $pedidos_excluidos++;
                    }
                }
                $stmt->close();
            }
        } elseif ($report_type === 'range' && !empty($start_date) && !empty($end_date)) {
            // NUEVO: Reporte por rango de fechas (Asegurando incluir todo el día de la fecha de fin)
            $sql = "
                SELECT p.id_pedido, p.fecha_creacion, p.nombre_cliente, p.total_estimado, p.estado_pago, e.nombre_estado
                FROM pedidos p
                JOIN estados e ON p.id_estado = e.id_estado
                WHERE DATE(p.fecha_creacion) BETWEEN ? AND ?
                ORDER BY p.fecha_creacion DESC
            ";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ss", $start_date, $end_date);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $report_data[] = $row;
                    $total_pedidos++;
                    if (!in_array($row['nombre_estado'], $excluded_states)) {
                        $total_generated += $row['total_estimado'];
                    } else {
                        $pedidos_excluidos++;
                    }
                }
                $stmt->close();
            }
        } else {
            $error_message = "Selecciona un tipo de reporte y completa los campos requeridos.";
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error al generar el reporte: " . $e->getMessage();
    }
}

$conn->close();

// Array de meses en español
$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// Determinar el título dinámico del reporte basado en el filtro
$titulo_reporte = "Reporte General de Pedidos";
$filtro_aplicado = "Ninguno";
if ($report_type === 'day') {
    $titulo_reporte = "Reporte Diario de Pedidos";
    $filtro_aplicado = "Día: " . htmlspecialchars($selected_date);
} elseif ($report_type === 'week') {
    $titulo_reporte = "Reporte Semanal de Pedidos";
    $filtro_aplicado = "Semana del (Lunes): " . htmlspecialchars($selected_week);
} elseif ($report_type === 'month') {
    $titulo_reporte = "Reporte Mensual de Pedidos";
    $filtro_aplicado = "Mes: " . $meses[$selected_month] . " de " . htmlspecialchars($selected_year);
} elseif ($report_type === 'range') {
    $titulo_reporte = "Reporte Personalizado de Pedidos";
    $filtro_aplicado = "Desde: " . htmlspecialchars($start_date) . " | Hasta: " . htmlspecialchars($end_date);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Pedidos</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        .status-badge {
            font-size: 1.1em;
            padding: 0.5em 0.8em;
            border-radius: 0.5rem;
        }
        /* Estilos para el área de impresión formal del reporte */
        .report-header {
            border-bottom: 2px solid #065f46;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .report-logo {
            max-height: 65px;
            width: auto;
            object-fit: contain;
        }
        .report-metadata p {
            margin-bottom: 2px;
            font-size: 0.9rem;
        }
        @media print {
            body { background-color: #fff; }
            .navbar, form, .btn, .no-print { display: none !important; }
            .card { box-shadow: none; padding: 0; border: none; }
            footer { position: fixed; bottom: 0; width: 100%; }
        }
    </style>
</head>
<body class="min-vh-100 d-flex flex-column">
    <?php include 'menu.php'; ?>

    <main class="flex-grow-1 container my-5">
        <h1 class="mb-4 text-center text-success no-print">Módulo de Auditoría de Ventas</h1>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-5 no-print">
            <h2 class="card-header bg-success text-white h5 py-3">Configurar Criterios del Reporte</h2>
            <div class="card-body">
                <form method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="report_type" class="form-label">Tipo de Filtro:</label>
                            <select class="form-select" id="report_type" name="report_type" required>
                                <option value="">Seleccionar...</option>
                                <option value="day" <?php echo ($report_type === 'day') ? 'selected' : ''; ?>>Por Día</option>
                                <option value="week" <?php echo ($report_type === 'week') ? 'selected' : ''; ?>>Por Semana</option>
                                <option value="month" <?php echo ($report_type === 'month') ? 'selected' : ''; ?>>Por Mes</option>
                                <option value="range" <?php echo ($report_type === 'range') ? 'selected' : ''; ?>>Por Rango de Fechas</option>
                            </select>
                        </div>
                        
                        <div class="col-md-5" id="date_field" style="display: none;">
                            <label for="selected_date" class="form-label">Seleccionar Fecha:</label>
                            <input type="date" class="form-control" id="selected_date" name="selected_date" value="<?php echo htmlspecialchars($selected_date); ?>">
                        </div>
                        
                        <div class="col-md-5" id="week_field" style="display: none;">
                            <label for="selected_week" class="form-label">Seleccionar Inicio de Semana (Lunes):</label>
                            <input type="date" class="form-control" id="selected_week" name="selected_week" value="<?php echo htmlspecialchars($selected_week); ?>">
                        </div>
                        
                        <div class="col-md-2" id="month_field" style="display: none;">
                            <label for="selected_month" class="form-label">Mes:</label>
                            <select class="form-select" id="selected_month" name="selected_month">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo ($selected_month == $m) ? 'selected' : ''; ?>><?php echo $meses[$m]; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3" id="year_field" style="display: none;">
                            <label for="selected_year" class="form-label">Año:</label>
                            <select class="form-select" id="selected_year" name="selected_year">
                                <?php for ($y = date('Y') - 5; $y <= date('Y'); $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($selected_year == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="col-md-2" id="start_date_field" style="display: none;">
                            <label for="start_date" class="form-label">Desde:</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="col-md-3" id="end_date_field" style="display: none;">
                            <label for="end_date" class="form-label">Hasta:</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>

                        <div class="col-md-4 d-flex align-items-end ms-auto">
                            <button type="submit" class="btn btn-primary w-100">Consultar y Estructurar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($report_data) || ($_SERVER["REQUEST_METHOD"] == "GET" && !empty($report_type) && empty($error_message))): ?>
            <div class="card p-4">
                
                <div class="report-header row align-items-center">
                    <div class="col-sm-3 text-center text-sm-start mb-3 mb-sm-0">
                        <img src="../img/logospringloveSF.png" alt="Logo Spring Love" class="report-logo">
                    </div>
                    <div class="col-sm-5 text-center text-sm-start">
                        <h2 class="text-success h4 mb-1"><?php echo $titulo_reporte; ?></h2>
                        <p class="text-muted mb-0" style="font-size: 0.95rem;">Spring Love E-Commerce — Módulo de Pedidos</p>
                    </div>
                    <div class="col-sm-4 text-center text-sm-end report-metadata text-secondary mt-2 mt-sm-0">
                        <p><strong>Generado el:</strong> <?php echo date('d/m/Y h:i A'); ?></p>
                        <p><strong>Filtro aplicado:</strong> <span class="badge bg-secondary"><?php echo $filtro_aplicado; ?></span></p>
                        <p><strong>Operador:</strong> <?php echo htmlspecialchars($_SESSION["tipo_usuario"]); ?></p>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-light">
                            <small class="text-muted d-block text-uppercase font-weight-bold">Total Neto Financiero</small>
                            <span class="h3 text-success font-weight-bold">$<?php echo number_format($total_generated, 2); ?></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-light">
                            <small class="text-muted d-block text-uppercase font-weight-bold">Volumen de Órdenes</small>
                            <span class="h3 text-dark"><?php echo $total_pedidos; ?> Transacciones</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-light">
                            <small class="text-muted d-block text-uppercase font-weight-bold">Órdenes Excluidas (Dev/Can)</small>
                            <span class="h3 text-danger"><?php echo $pedidos_excluidos; ?></span>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-warning py-2 mb-0" style="font-size: 0.85rem;">
                            <i class="bi bi-info-circle-fill me-1"></i> <strong>Nota de Auditoría:</strong> Los pedidos con estado logístico <em>"Devuelto"</em> o <em>"Cancelado"</em> se desglosan en la tabla con fines informativos, pero sus valores monetarios están excluidos del cálculo del Total Neto Financiero.
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle">
                        <thead class="table-success text-dark">
                            <tr>
                                <th class="text-center" style="width: 10%;">ID Pedido</th>
                                <th style="width: 20%;">Fecha Creación</th>
                                <th style="width: 25%;">Cliente</th>
                                <th class="text-end" style="width: 15%;">Monto Estimado</th>
                                <th style="width: 15%;">Estado Pago</th>
                                <th class="text-center" style="width: 15%;">Estado Logístico</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($report_data)): ?>
                                <?php foreach ($report_data as $pedido): ?>
                                    <?php $is_excluded = in_array($pedido['nombre_estado'], $excluded_states); ?>
                                    <tr class="<?php echo $is_excluded ? 'table-danger text-danger' : ''; ?>">
                                        <td class="text-center font-weight-bold">#<?php echo htmlspecialchars($pedido['id_pedido']); ?></td>
                                        <td><?php echo htmlspecialchars($pedido['fecha_creacion']); ?></td>
                                        <td><?php echo htmlspecialchars($pedido['nombre_cliente']); ?></td>
                                        <td class="text-end font-weight-bold">
                                            <?php 
                                            $total_display = $is_excluded ? '-' . number_format($pedido['total_estimado'], 2) : number_format($pedido['total_estimado'], 2);
                                            echo '$' . $total_display;
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo ($pedido['estado_pago'] === 'Pago') ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                                <?php echo htmlspecialchars($pedido['estado_pago']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php echo htmlspecialchars($pedido['nombre_estado']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No se encontraron registros de pedidos para el rango temporal seleccionado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 pt-2 border-top text-muted" style="font-size: 0.8rem;">
                    <strong>Estructura técnica de extracción:</strong> SQL Query con ordenamiento cronológico descendente (`ORDER BY p.fecha_creacion DESC`). Transacción verificada vía DB Engine.
                </div>

                <div class="text-end mt-4 no-print">
                    <button onclick="window.print();" class="btn btn-secondary btn-sm">
                        <i class="bi bi-printer-fill me-1"></i> Imprimir Reporte Formal
                    </button>
                </div>

            </div>
        <?php endif; ?>
    </main>

    <footer class="bg-dark text-white text-center py-3 mt-auto border-top border-secondary">
        <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center" style="font-size: 0.85rem;">
            <div>
                <span class="text-warning font-weight-bold">CLASIFICACIÓN:</span> CONFIDENCIAL — CONTROL INTERNO DE OPERACIONES
            </div>
            <div class="my-1 my-md-0">
                Petición de Base de Datos Segura (Commit Validado)
            </div>
            <div>
                Módulo de Soporte Operacional &copy; <?php echo date('Y'); ?>
            </div>
        </div>
    </footer>

    <script src="../js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const reportType = document.getElementById('report_type');
            const dateField = document.getElementById('date_field');
            const weekField = document.getElementById('week_field');
            const monthField = document.getElementById('month_field');
            const yearField = document.getElementById('year_field');
            const startDateField = document.getElementById('start_date_field');
            const endDateField = document.getElementById('end_date_field');

            function toggleFields() {
                // Ocultar todos los campos inicialmente
                dateField.style.display = 'none';
                weekField.style.display = 'none';
                monthField.style.display = 'none';
                yearField.style.display = 'none';
                startDateField.style.display = 'none';
                endDateField.style.display = 'none';
                
                // Mostrar según la opción seleccionada
                if (reportType.value === 'day') {
                    dateField.style.display = 'block';
                } else if (reportType.value === 'week') {
                    weekField.style.display = 'block';
                } else if (reportType.value === 'month') {
                    monthField.style.display = 'block';
                    yearField.style.display = 'block';
                } else if (reportType.value === 'range') {
                    startDateField.style.display = 'block';
                    endDateField.style.display = 'block';
                }
            }

            reportType.addEventListener('change', toggleFields);
            toggleFields(); // Inicializar al cargar la página
        });
    </script>
</body>
</html>