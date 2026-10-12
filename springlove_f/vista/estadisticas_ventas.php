<?php
// estadisticas_ventas.php

session_start();

// Incluir el archivo de conexión a la base de datos y utilidades
require_once '../controlador/db_connection.php'; 
require_once '../controlador/func_ayuda.php'; 
registrarEnBitacora($conn, "Reporte Estadístico de Productos");

// Verificar si el usuario no ha iniciado sesión o no tiene permisos (solo admin y asistente)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || ($_SESSION["tipo_usuario"] !== 'admin' && $_SESSION["tipo_usuario"] !== 'asistente')) {
    header("location: ../index.php");
    exit;
}

// Variables para el control de filtros
$report_type = $_GET['report_type'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$report_data = [];
$total_unidades_global = 0;
$total_ingresos_global = 0.0;
$error_message = '';

// Estados de pedido excluidos de las métricas comerciales por no representar ventas efectivas
$excluded_states = ['Devuelto', 'Cancelado'];

// Procesar la consulta cuando se definan los parámetros
if ($_SERVER["REQUEST_METHOD"] == "GET" && !empty($report_type)) {
    $conn->begin_transaction();
    try {
        if ($report_type === 'range' && !empty($start_date) && !empty($end_date)) {
            
            
            $sql = "
                SELECT 
                    p.id_producto,
                    p.nombre_producto,
                    
                    SUM(dp.cantidad) AS total_unidades,
                    SUM(dp.cantidad * dp.precio_unitario_al_momento_pedido) AS total_ingresos
                FROM 
                    detalle_pedido dp
                JOIN 
                    productos p ON dp.id_producto = p.id_producto
                JOIN 
                    pedidos ped ON dp.id_pedido = ped.id_pedido
                JOIN 
                    estados e ON ped.id_estado = e.id_estado
                WHERE 
                    DATE(ped.fecha_creacion) BETWEEN ? AND ?
                    AND e.nombre_estado NOT IN (?, ?)
                GROUP BY 
                    p.id_producto, p.nombre_producto
                ORDER BY 
                    total_unidades DESC, total_ingresos DESC
            ";
            
            if ($stmt = $conn->prepare($sql)) {
                $state1 = $excluded_states[0];
                $state2 = $excluded_states[1];
                
                $stmt->bind_param("ssss", $start_date, $end_date, $state1, $state2);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $report_data[] = $row;
                    $total_unidades_global += $row['total_unidades'];
                    $total_ingresos_global += $row['total_ingresos'];
                }
                $stmt->close();
            }
        } else {
            $error_message = "Por favor, configure un rango de fechas válido para procesar las estadísticas.";
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error en la extracción estadística de datos: " . $e->getMessage();
    }
}

$conn->close();

// Definición del contexto del filtro para el encabezado formal
$filtro_aplicado = "Ninguno (Seleccione un rango)";
if ($report_type === 'range') {
    $filtro_aplicado = "Desde: " . htmlspecialchars($start_date) . " | Hasta: " . htmlspecialchars($end_date);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas de Ventas - Productos</title>
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
        .ranking-number {
            font-weight: 700;
            color: #059669;
            background-color: #e6f4ea;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
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
        <h1 class="mb-4 text-center text-success no-print">Análisis de Rendimiento Comercial</h1>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-5 no-print">
            <h2 class="card-header bg-success text-white h5 py-3">Configurar Parámetros del Reporte Estadístico</h2>
            <div class="card-body">
                <form method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <input type="hidden" name="report_type" value="range">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Fecha Inicial (Desde):</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">Fecha Final (Hasta):</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">Generar Análisis de Movimiento</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($_SERVER["REQUEST_METHOD"] == "GET" && $report_type === 'range' && empty($error_message)): ?>
            <div class="card p-4">
                
                <div class="report-header row align-items-center">
                    <div class="col-sm-3 text-center text-sm-start mb-3 mb-sm-0">
                        <img src="../img/logospringloveSF.png" alt="Logo Spring Love" class="report-logo">
                    </div>
                    <div class="col-sm-5 text-center text-sm-start">
                        <h2 class="text-success h5 mb-1">Reporte de Rotación y Movimiento de Productos</h2>
                        <p class="text-muted mb-0" style="font-size: 0.95rem;">Spring Love E-Commerce — Control Analítico</p>
                    </div>
                    <div class="col-sm-4 text-center text-sm-end report-metadata text-secondary mt-2 mt-sm-0">
                        <p><strong>Generado el:</strong> <?php echo date('d/m/Y h:i A'); ?></p>
                        <p><strong>Rango evaluado:</strong> <span class="badge bg-secondary"><?php echo $filtro_aplicado; ?></span></p>
                        <p><strong>Auditor técnico:</strong> <?php echo htmlspecialchars($_SESSION["tipo_usuario"]); ?></p>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-light">
                            <small class="text-muted d-block text-uppercase font-weight-bold">Unidades Totales Distribuidas</small>
                            <span class="h3 text-success font-weight-bold"><?php echo number_format($total_unidades_global); ?> </span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-light">
                            <small class="text-muted d-block text-uppercase font-weight-bold">Recaudación Total Bruta</small>
                            <span class="h3 text-dark font-weight-bold">$<?php echo number_format($total_ingresos_global, 2); ?></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border rounded bg-light">
                            <small class="text-muted d-block text-uppercase font-weight-bold">Productos con Tráfico</small>
                            <span class="h3 text-primary"><?php echo count($report_data); ?> Ítems</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-info py-2 mb-0" style="font-size: 0.85rem;">
                            <i class="bi bi-graph-up-arrow me-1"></i> <strong>Criterio Estadístico:</strong> El listado se encuentra ordenado en orden estrictamente descendente según el **Volumen de Unidades**. Los productos en la cima representan los artículos con mayor rotación/movimiento en el inventario durante el lapso establecido.
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle">
                        <thead class="table-success text-dark">
                            <tr>
                                <th class="text-center" style="width: 8%;">Top</th>
                                <th style="width: 15%;">Código</th>
                                <th style="width: 42%;">Descripción del Producto</th>
                                <th class="text-center" style="width: 15%;">Unidades Vendidas</th>
                                <th class="text-end" style="width: 20%;">Total Recaudado ($)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($report_data)): ?>
                                <?php $ranking = 1; ?>
                                <?php foreach ($report_data as $producto): ?>
                                    <tr class="<?php echo ($ranking <= 3) ? 'table-light fw-semibold' : ''; ?>">
                                        <td class="text-center">
                                            <span class="ranking-number"><?php echo $ranking++; ?></span>
                                        </td>
                                        <td class="text-monospace text-secondary"><?php echo htmlspecialchars($producto['id_producto'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="text-dark"><?php echo htmlspecialchars($producto['nombre_producto']); ?></span>
                                            <?php if ($ranking <= 4): ?>
                                                <span class="badge bg-warning text-dark ms-1" style="font-size: 0.7rem;"><i class="bi bi-star-fill"></i> Alta Rotación</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center font-weight-bold text-success">
                                            <?php echo number_format($producto['total_unidades']); ?>
                                        </td>
                                        <td class="text-end text-dark font-weight-bold">
                                            $<?php echo number_format($producto['total_ingresos'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No se registraron movimientos de productos en el rango de fechas seleccionado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 pt-2 border-top text-muted" style="font-size: 0.8rem;">
                    <strong>Nota</strong>: Los datos omiten transacciones de pedidos en estado <em>Devuelto</em> o <em>Cancelado</em> para blindar la veracidad financiera.
                </div>

                <div class="text-end mt-4 no-print">
                    <button onclick="window.print();" class="btn btn-secondary btn-sm">
                        <i class="bi bi-printer-fill me-1"></i> Imprimir Reporte Estadístico
                    </button>
                </div>

            </div>
        <?php endif; ?>
    </main>

    <footer class="bg-dark text-white text-center py-3 mt-auto border-top border-secondary">
        <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center" style="font-size: 0.85rem;">
            <div>
                <span class="text-warning font-weight-bold">CLASIFICACIÓN:</span> CONFIDENCIAL — INFORME DE INTELIGENCIA DE NEGOCIO (BI)
            </div>
            <div class="my-1 my-md-0">
                Auditoría Segura Base de Datos Spring Love
            </div>
            <div>
                Módulo Estadístico de Ventas &copy; <?php echo date('Y'); ?>
            </div>
        </div>
    </footer>

    <script src="../js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>