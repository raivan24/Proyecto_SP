<?php
// crear_venta.php

session_start();

// Incluir el archivo de conexión a la base de datos
require_once '../controlador/db_connection.php';

// Verificar si el usuario no ha iniciado sesión o no tiene permisos (solo admin y asistente)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || ($_SESSION["tipo_usuario"] !== 'admin' && $_SESSION["tipo_usuario"] !== 'asistente')) {
    header("location: ../index.php");
    exit;
}

$success_message = "";
$error_message = "";

// Lógica para procesar la venta cuando se envía el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $productos_seleccionados = json_decode($_POST['productos_venta'], true); // Decodificar el JSON enviado desde JS
    $total_venta = floatval($_POST['total_venta']);
    $id_usuario = $_SESSION['id_usuario'];

    if (empty($productos_seleccionados)) {
        $error_message = "No se han seleccionado productos para la venta.";
    } else {
        // Iniciar una transacción para asegurar la atomicidad de la operación
        $conn->begin_transaction();
        try {
            // 1. Insertar la venta en la tabla 'ventas'
            $sql_venta = "INSERT INTO ventas (total_venta, id_usuario) VALUES (?, ?)";
            if ($stmt_venta = $conn->prepare($sql_venta)) {
                $stmt_venta->bind_param("di", $total_venta, $id_usuario);
                if (!$stmt_venta->execute()) {
                    throw new Exception("Error al insertar la venta.");
                }
                $id_venta = $conn->insert_id; // Obtener el ID de la venta recién insertada
                $stmt_venta->close();
            } else {
                throw new Exception("Error al preparar la consulta de venta.");
            }

            // 2. Insertar los detalles de la venta y actualizar el stock
            $sql_detalle = "INSERT INTO detalle_venta (id_venta, id_producto, cantidad, precio_unitario) VALUES (?, ?, ?, ?)";
            $sql_update_stock = "UPDATE productos SET stock = stock - ? WHERE id_producto = ?";

            foreach ($productos_seleccionados as $producto) {
                $id_producto = $producto['id'];
                $cantidad = $producto['cantidad'];
                $precio_unitario = $producto['precio'];

                // Insertar detalle de venta
                if ($stmt_detalle = $conn->prepare($sql_detalle)) {
                    $stmt_detalle->bind_param("iiid", $id_venta, $id_producto, $cantidad, $precio_unitario);
                    if (!$stmt_detalle->execute()) {
                        throw new Exception("Error al insertar detalle de venta para producto ID: " . $id_producto);
                    }
                    $stmt_detalle->close();
                } else {
                    throw new Exception("Error al preparar la consulta de detalle de venta.");
                }

                // Actualizar stock
                if ($stmt_stock = $conn->prepare($sql_update_stock)) {
                    $stmt_stock->bind_param("ii", $cantidad, $id_producto);
                    if (!$stmt_stock->execute()) {
                        throw new Exception("Error al actualizar stock para producto ID: " . $id_producto);
                    }
                    $stmt_stock->close();
                } else {
                    throw new Exception("Error al preparar la consulta de actualización de stock.");
                }
            }

            $conn->commit(); // Confirmar la transacción
            $success_message = "Venta registrada exitosamente con ID: " . $id_venta;

        } catch (Exception $e) {
            $conn->rollback(); // Revertir la transacción en caso de error
            $error_message = "Error al procesar la venta: " . $e->getMessage();
        }
    }
}

// Obtener todos los productos para mostrarlos en la interfaz de selección
$productos_db = [];
$sql_select_productos = "SELECT id_producto, nombre_producto, categoria, precio, stock FROM productos WHERE stock > 0 ORDER BY categoria, nombre_producto";
if ($result = $conn->query($sql_select_productos)) {
    $productos_db = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $error_message = "Error al cargar los productos disponibles.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Nueva Venta</title>
    <!-- Bootstrap CSS -->
    <link href="../css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0fdf4;
        }
        .navbar {
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
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            transition: background-color 0.2s ease-in-out, border-color 0.2s ease-in-out;
        }
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        .product-item:last-child {
            border-bottom: none;
        }
        .quantity-controls button {
            width: 30px;
            height: 30px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            border-radius: 50%;
            border: 1px solid #ccc;
            background-color: #f8f9fa;
            cursor: pointer;
            font-weight: bold;
        }
        .quantity-controls input {
            width: 50px;
            text-align: center;
            margin: 0 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
    </style>
</head>
<body class="min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="<?php echo $_SESSION["tipo_usuario"]; ?>_dashboard.php">Dashboard</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="productos.php">Gestión de Productos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="crear_venta.php">Crear Venta</a>
                    </li>
                    <?php if ($_SESSION["tipo_usuario"] == 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Gestión de Usuarios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Reportes de Ventas</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link btn btn-danger ms-lg-3" href="logout.php">Cerrar Sesión</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="flex-grow-1 container my-5">
        <h1 class="mb-4 text-center text-success">Crear Nueva Venta</h1>

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

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <h2 class="card-header bg-success text-white">Seleccionar Productos</h2>
                    <div class="card-body">
                        <?php if (empty($productos_db)): ?>
                            <div class="alert alert-info" role="alert">
                                No hay productos disponibles en stock. Por favor, agrega productos desde la gestión de productos.
                            </div>
                        <?php else: ?>
                            <div class="accordion" id="productosAccordion">
                                <?php
                                $current_category = '';
                                foreach ($productos_db as $producto) {
                                    if ($producto['categoria'] !== $current_category) {
                                        if ($current_category !== '') {
                                            echo '</div></div></div>'; // Cerrar el item anterior
                                        }
                                        $current_category = $producto['categoria'];
                                        $collapse_id = 'collapse' . ucfirst($current_category);
                                        $heading_id = 'heading' . ucfirst($current_category);
                                        echo '
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="' . $heading_id . '">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#' . $collapse_id . '" aria-expanded="false" aria-controls="' . $collapse_id . '">
                                                    ' . ucfirst($current_category) . '
                                                </button>
                                            </h2>
                                            <div id="' . $collapse_id . '" class="accordion-collapse collapse" aria-labelledby="' . $heading_id . '" data-bs-parent="#productosAccordion">
                                                <div class="accordion-body">
                                        ';
                                    }
                                    ?>
                                    <div class="form-check product-item">
                                        <div>
                                            <input class="form-check-input product-checkbox" type="checkbox"
                                                id="product_<?php echo $producto['id_producto']; ?>"
                                                data-id="<?php echo $producto['id_producto']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($producto['nombre_producto']); ?>"
                                                data-precio="<?php echo $producto['precio']; ?>"
                                                data-stock="<?php echo $producto['stock']; ?>">
                                            <label class="form-check-label" for="product_<?php echo $producto['id_producto']; ?>">
                                                <?php echo htmlspecialchars($producto['nombre_producto']); ?> ($<?php echo number_format($producto['precio'], 2); ?>) - Stock: <?php echo $producto['stock']; ?>
                                            </label>
                                        </div>
                                        <div class="quantity-controls d-none" id="qty_controls_<?php echo $producto['id_producto']; ?>">
                                            <button type="button" class="btn btn-sm btn-outline-secondary decrease-qty" data-id="<?php echo $producto['id_producto']; ?>">-</button>
                                            <input type="number" class="form-control form-control-sm d-inline-block text-center product-qty"
                                                id="qty_<?php echo $producto['id_producto']; ?>" value="1" min="1" max="<?php echo $producto['stock']; ?>"
                                                data-id="<?php echo $producto['id_producto']; ?>">
                                            <button type="button" class="btn btn-sm btn-outline-secondary increase-qty" data-id="<?php echo $producto['id_producto']; ?>">+</button>
                                        </div>
                                    </div>
                                <?php
                                }
                                if ($current_category !== '') {
                                    echo '</div></div></div>'; // Cerrar el último item
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <h2 class="card-header bg-primary text-white">Resumen del Arreglo</h2>
                    <div class="card-body">
                        <div id="resumen-pedido">
                            <p class="text-muted">No hay productos seleccionados.</p>
                        </div>
                        <hr>
                        <h4 class="d-flex justify-content-between">
                            <span>Total:</span>
                            <span id="total-venta">$0.00</span>
                        </h4>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="formFinalizarVenta">
                            <input type="hidden" name="productos_venta" id="inputProductosVenta">
                            <input type="hidden" name="total_venta" id="inputTotalVenta">
                            <button type="submit" class="btn btn-primary w-100 mt-3" id="btnFinalizarVenta" disabled>Finalizar Venta</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap Bundle with Popper -->
    <script src="../js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const productCheckboxes = document.querySelectorAll('.product-checkbox');
            const resumenPedidoDiv = document.getElementById('resumen-pedido');
            const totalVentaSpan = document.getElementById('total-venta');
            const btnFinalizarVenta = document.getElementById('btnFinalizarVenta');
            const inputProductosVenta = document.getElementById('inputProductosVenta');
            const inputTotalVenta = document.getElementById('inputTotalVenta');

            let selectedProducts = {}; // { id: { nombre, precio, cantidad, stock } }

            function updateResumen() {
                let total = 0;
                let resumenHtml = '';

                if (Object.keys(selectedProducts).length === 0) {
                    resumenHtml = '<p class="text-muted">No hay productos seleccionados.</p>';
                    btnFinalizarVenta.disabled = true;
                } else {
                    resumenHtml = '<ul class="list-unstyled">';
                    for (const id in selectedProducts) {
                        const product = selectedProducts[id];
                        const subtotal = product.precio * product.cantidad;
                        total += subtotal;
                        resumenHtml += `
                            <li class="d-flex justify-content-between align-items-center py-1">
                                <span>${product.nombre} x ${product.cantidad}</span>
                                <span>$${subtotal.toFixed(2)}</span>
                            </li>
                        `;
                    }
                    resumenHtml += '</ul>';
                    btnFinalizarVenta.disabled = false;
                }

                resumenPedidoDiv.innerHTML = resumenHtml;
                totalVentaSpan.textContent = `$${total.toFixed(2)}`;

                // Actualizar inputs ocultos para el envío del formulario
                inputProductosVenta.value = JSON.stringify(Object.values(selectedProducts).map(p => ({
                    id: p.id,
                    cantidad: p.cantidad,
                    precio: p.precio // Guardar el precio unitario en el detalle de venta
                })));
                inputTotalVenta.value = total.toFixed(2);
            }

            productCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const productId = this.dataset.id;
                    const productName = this.dataset.nombre;
                    const productPrice = parseFloat(this.dataset.precio);
                    const productStock = parseInt(this.dataset.stock);
                    const qtyControls = document.getElementById(`qty_controls_${productId}`);
                    const qtyInput = document.getElementById(`qty_${productId}`);

                    if (this.checked) {
                        selectedProducts[productId] = {
                            id: productId,
                            nombre: productName,
                            precio: productPrice,
                            cantidad: 1, // Cantidad inicial al seleccionar
                            stock: productStock
                        };
                        qtyInput.value = 1; // Reset quantity to 1
                        qtyInput.max = productStock; // Set max based on current stock
                        qtyControls.classList.remove('d-none');
                    } else {
                        delete selectedProducts[productId];
                        qtyControls.classList.add('d-none');
                    }
                    updateResumen();
                });
            });

            document.addEventListener('click', function(event) {
                if (event.target.classList.contains('increase-qty')) {
                    const productId = event.target.dataset.id;
                    const qtyInput = document.getElementById(`qty_${productId}`);
                    let currentQty = parseInt(qtyInput.value);
                    const maxQty = parseInt(qtyInput.max);

                    if (currentQty < maxQty) {
                        qtyInput.value = currentQty + 1;
                        selectedProducts[productId].cantidad = parseInt(qtyInput.value);
                        updateResumen();
                    }
                } else if (event.target.classList.contains('decrease-qty')) {
                    const productId = event.target.dataset.id;
                    const qtyInput = document.getElementById(`qty_${productId}`);
                    let currentQty = parseInt(qtyInput.value);

                    if (currentQty > 1) {
                        qtyInput.value = currentQty - 1;
                        selectedProducts[productId].cantidad = parseInt(qtyInput.value);
                        updateResumen();
                    }
                }
            });

            document.addEventListener('input', function(event) {
                if (event.target.classList.contains('product-qty')) {
                    const productId = event.target.dataset.id;
                    let newQty = parseInt(event.target.value);
                    const maxQty = parseInt(event.target.max);
                    const minQty = parseInt(event.target.min);

                    if (isNaN(newQty) || newQty < minQty) {
                        newQty = minQty;
                        event.target.value = minQty;
                    } else if (newQty > maxQty) {
                        newQty = maxQty;
                        event.target.value = maxQty;
                    }

                    if (selectedProducts[productId]) {
                        selectedProducts[productId].cantidad = newQty;
                        updateResumen();
                    }
                }
            });

            updateResumen(); // Llamar al inicio para inicializar el resumen
        });
    </script>
</body>
</html>
