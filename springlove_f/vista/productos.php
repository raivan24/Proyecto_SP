<?php

session_start();

// Incluir el archivo de conexión a la base de datos
require_once '../controlador/db_connection.php';
require_once '../controlador/func_ayuda.php'; // Asegúrar que la ruta es correcta
registrarEnBitacora($conn, "Gestión de Productos"); //funcion registro bitacora_ruta

// Verificar si el usuario no ha iniciado sesión o no tiene permisos (solo admin y asistente)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || ($_SESSION["tipo_usuario"] !== 'admin' && $_SESSION["tipo_usuario"] !== 'asistente')) {
    header("location: index.php");
    exit;
}

$nombre_producto = $categoria = $precio = $stock = "";
$nombre_producto_err = $categoria_err = $precio_err = $stock_err = "";
$success_message = "";

// --- Obtener categorías desde la base de datos ---
$categorias_db = [];
$sql_categorias = "SELECT id_categoria, nombre_categoria FROM categoria ORDER BY nombre_categoria";
if ($result_categorias = $conn->query($sql_categorias)) {
    while ($row = $result_categorias->fetch_assoc()) {
        $categorias_db[] = $row;
    }
    $result_categorias->free();
} else {
    // Manejar el error si no se pueden cargar las categorías
    error_log("Error al cargar categorías: " . $conn->error);
}
// --- Fin Obtener categorías ---  *****

// --- Manejar comprobación de nombre y actualización vía AJAX (modal) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'check_name') {
    header('Content-Type: application/json; charset=utf-8');
    $nombre_check = isset($_POST['nombre_producto']) ? trim($_POST['nombre_producto']) : '';
    $id_producto_check = isset($_POST['id_producto']) ? intval($_POST['id_producto']) : 0;
    $exists = false;

    if ($nombre_check !== '') {
        $sql_check = "SELECT id_producto FROM productos WHERE nombre_producto = ? AND id_producto <> ? LIMIT 1";
        if ($stmt_check = $conn->prepare($sql_check)) {
            $stmt_check->bind_param("si", $nombre_check, $id_producto_check);
            if ($stmt_check->execute()) {
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $exists = true;
                }
            }
            $stmt_check->close();
        }
    }

    echo json_encode(['exists' => $exists]);
    $conn->close();
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update_product') {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'errors' => []];

    $id_producto = isset($_POST['id_producto']) ? intval($_POST['id_producto']) : 0;
    $nombre_up = isset($_POST['nombre_producto']) ? trim($_POST['nombre_producto']) : '';
    $categoria_id_up = isset($_POST['categoria']) ? trim($_POST['categoria']) : '';
    $precio_up = isset($_POST['precio']) ? trim($_POST['precio']) : '';
    $stock_up = isset($_POST['stock']) ? trim($_POST['stock']) : '';

    // Validaciones básicas
    if ($id_producto <= 0) {
        $response['errors'][] = "ID de producto inválido.";
    }
    if ($nombre_up === '') {
        $response['errors'][] = "Nombre es requerido.";
    }

    // Validar que el nombre no se repita (excluyendo el producto actual)
    if ($nombre_up !== '' && $id_producto > 0) {
        $sql_dup = "SELECT id_producto FROM productos WHERE nombre_producto = ? AND id_producto <> ? LIMIT 1";
        if ($stmt_dup = $conn->prepare($sql_dup)) {
            $stmt_dup->bind_param("si", $nombre_up, $id_producto);
            if ($stmt_dup->execute()) {
                $stmt_dup->store_result();
                if ($stmt_dup->num_rows > 0) {
                    $response['errors'][] = "El nombre del producto ya está en uso por otro producto.";
                }
            } else {
                // si falla la comprobación, no bloquear sin razón; registrar error
                error_log("Error comprobando duplicado: " . $conn->error);
            }
            $stmt_dup->close();
        }
    }

    // Buscar nombre de categoría por id
    $categoria_nombre_up = '';
    $category_exists = false;
    foreach ($categorias_db as $cat) {
        if ($cat['id_categoria'] == $categoria_id_up) {
            $categoria_nombre_up = $cat['nombre_categoria'];
            $category_exists = true;
            break;
        }
    }
    if (empty($categoria_id_up) || !$category_exists) {
        $response['errors'][] = "Categoría inválida.";
    }
    if ($precio_up === '' || !is_numeric($precio_up) || $precio_up < 0) {
        $response['errors'][] = "Precio inválido.";
    }
    if ($stock_up === '' || !filter_var($stock_up, FILTER_VALIDATE_INT) || $stock_up < 0) {
        $response['errors'][] = "Stock inválido.";
    }

    if (empty($response['errors'])) {
        $sql_update = "UPDATE productos SET nombre_producto = ?, categoria = ?, precio = ?, stock = ? WHERE id_producto = ?";
        if ($stmt_up = $conn->prepare($sql_update)) {
            $stmt_up->bind_param("ssdii", $param_nombre, $param_categoria, $param_precio, $param_stock, $param_id);
            $param_nombre = $nombre_up;
            $param_categoria = $categoria_nombre_up;
            $param_precio = floatval($precio_up);
            $param_stock = intval($stock_up);
            $param_id = $id_producto;
            if ($stmt_up->execute()) {
                $response['success'] = true;
                $response['message'] = "Producto actualizado.";
            } else {
                $response['errors'][] = "Error al actualizar: " . $conn->error;
            }
            $stmt_up->close();
        } else {
            $response['errors'][] = "Error en la consulta: " . $conn->error;
        }
    }

    echo json_encode($response);
    $conn->close();
    exit;
}
// --- Fin comprobación y actualización AJAX ---
?>
<script>
(function(){
    // Inicializar modal bootstrap
    var modalEl = document.getElementById('modalEditarProducto');
    var bsModal;
    if (modalEl) {
        bsModal = new bootstrap.Modal(modalEl);
    }

    // Función para mostrar alert dentro del modal
    function showModalAlert(type, message) {
        var alertEl = document.getElementById('editarAlert');
        alertEl.className = 'alert alert-' + type;
        alertEl.textContent = message;
        alertEl.classList.remove('d-none');
    }
    function hideModalAlert() {
        var alertEl = document.getElementById('editarAlert');
        alertEl.classList.add('d-none');
    }

    var isNameDuplicate = false;
    var checkTimeout = null;

    // Función que consulta al servidor si el nombre ya existe (excluyendo el id actual)
    function checkNameDuplicate(nombre, id) {
        if (!nombre || nombre.trim() === '') {
            isNameDuplicate = false;
            hideModalAlert();
            return;
        }

        // debounce simple
        if (checkTimeout) clearTimeout(checkTimeout);
        checkTimeout = setTimeout(function(){
            var fd = new FormData();
            fd.append('action', 'check_name');
            fd.append('nombre_producto', nombre);
            fd.append('id_producto', id || 0);

            fetch(window.location.href, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
            .then(function(resp){ return resp.json(); })
            .then(function(data){
                if (data.exists) {
                    isNameDuplicate = true;
                    showModalAlert('danger', 'El nombre del producto ya existe. Elige otro.');
                } else {
                    isNameDuplicate = false;
                    hideModalAlert();
                }
            })
            .catch(function(err){
                // no bloquear por error de comprobación, solo registrar en consola
                console.error('Error comprobando nombre:', err);
            });
        }, 350);
    }

    // Rellenar modal al hacer click en editar
    document.querySelectorAll('.btn-edit').forEach(function(btn){
        btn.addEventListener('click', function(e){
            hideModalAlert();
            isNameDuplicate = false;
            var id = this.dataset.id || '';
            var nombre = this.dataset.nombre || '';
            var categoria = this.dataset.categoria || '';
            var precio = this.dataset.precio || '';
            var stock = this.dataset.stock || '';

            document.getElementById('edit_id_producto').value = id;
            document.getElementById('edit_nombre_producto').value = nombre;
            document.getElementById('edit_categoria').value = categoria;
            document.getElementById('edit_precio').value = precio;
            document.getElementById('edit_stock').value = stock;

            if (bsModal) bsModal.show();
        });
    });

    // Comprobar nombre mientras escribe en el modal
    var inputNombreEdit = document.getElementById('edit_nombre_producto');
    inputNombreEdit.addEventListener('input', function(e){
        var id = document.getElementById('edit_id_producto').value || 0;
        checkNameDuplicate(this.value, id);
    });

    // Envío del formulario por AJAX
    var formEditar = document.getElementById('formEditarProducto');
    formEditar.addEventListener('submit', function(e){
        e.preventDefault();
        hideModalAlert();

        // evitar envío si el nombre ya está duplicado
        if (isNameDuplicate) {
            showModalAlert('danger', 'No se puede guardar: el nombre ya está en uso.');
            return;
        }

        var formData = new FormData(formEditar);

        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(resp){ return resp.json(); })
        .then(function(data){
            if (data.success) {
                // Recargar para reflejar cambios
                location.reload();
            } else {
                if (data.errors && data.errors.length) {
                    showModalAlert('danger', data.errors.join(' '));
                } else {
                    showModalAlert('danger', 'Ocurrió un error desconocido.');
                }
            }
        })
        .catch(function(err){
            showModalAlert('danger', 'Error de comunicación con el servidor.');
            console.error(err);
        });
    });

})();
</script>

<?php

// Procesar el formulario cuando se envía para agregar un nuevo producto
if ($_SERVER["REQUEST_METHOD"] == "POST" && !(isset($_POST['action']) && $_POST['action'] === 'update_product')) {
    // Validar nombre del producto
    if (empty(trim($_POST["nombre_producto"]))) {
        $nombre_producto_err = "Por favor, ingresa el nombre del producto.";
    } else {
        $nombre_producto = trim($_POST["nombre_producto"]);
        // Verificar si el nombre del producto ya existe en la base de datos
        $sql_check = "SELECT id_producto FROM productos WHERE nombre_producto = ?";
        if ($stmt_check = $conn->prepare($sql_check)) {
            $stmt_check->bind_param("s", $nombre_producto);
            if ($stmt_check->execute()) {
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $nombre_producto_err = "El producto ya se encuentra registrado.";
                }
            } else {
                error_log("Error al verificar el nombre del producto: " . $conn->error);
            }
            $stmt_check->close();
        }
    }

    // Validar categoría
    $selected_category_id = trim($_POST["categoria"]);
    $category_exists = false;
    foreach ($categorias_db as $cat) {
        if ($cat['id_categoria'] == $selected_category_id) {
            $categoria = $cat['nombre_categoria'];
            $category_exists = true;
            break;
        }
    }

    if (empty($selected_category_id) || !$category_exists) {
        $categoria_err = "Por favor, selecciona una categoría válida.";
    }


    // Validar precio
    if (empty(trim($_POST["precio"]))) {
        $precio_err = "Por favor, ingresa el precio.";
    } elseif (!is_numeric($_POST["precio"]) || $_POST["precio"] < 0) {
        $precio_err = "El precio debe ser un número positivo.";
    } else {
        $precio = trim($_POST["precio"]);
    }

    // Validar stock
    if (empty(trim($_POST["stock"]))) {
        $stock_err = "Por favor, ingresa el stock.";
    } elseif (!filter_var($_POST["stock"], FILTER_VALIDATE_INT) || $_POST["stock"] < 0) {
        $stock_err = "El stock debe ser un número entero positivo.";
    } else {
        $stock = trim($_POST["stock"]);
    }

    // Si no hay errores de entrada, insertar en la base de datos
    if (empty($nombre_producto_err) && empty($categoria_err) && empty($precio_err) && empty($stock_err)) {
        $sql = "INSERT INTO productos (nombre_producto, categoria, precio, stock) VALUES (?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssdi", $param_nombre, $param_categoria, $param_precio, $param_stock);

            $param_nombre = $nombre_producto;
            $param_categoria = $categoria;
            $param_precio = $precio;
            $param_stock = $stock;

            if ($stmt->execute()) {
                $success_message = "Producto agregado exitosamente.";
                $nombre_producto = $precio = $stock = "";
                $categoria = "";
                header("location: productos.php");
            } else {
                echo "<div class='alert alert-danger'>Error al agregar el producto. Por favor, inténtalo de nuevo.</div>";
            }
            $stmt->close();
        }
    }
}

// Obtener todos los productos para mostrarlos en la tabla
$productos = [];
$sql_select = "SELECT id_producto, nombre_producto, categoria, precio, stock FROM productos ORDER BY nombre_producto";
if ($result = $conn->query($sql_select)) {
    $productos = $result->fetch_all(MYSQLI_ASSOC);
} else {
    echo "<div class='alert alert-danger'>Error al cargar los productos.</div>";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos</title>
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
        <h1 class="mb-4 text-center text-success">Gestión de Productos</h1>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-5">
            <h2 class="card-header bg-success text-white">Agregar Nuevo Producto</h2>
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nombre_producto" class="form-label">Nombre del Producto:</label>
                            <input type="text" id="nombre_producto" name="nombre_producto" class="form-control <?php echo (!empty($nombre_producto_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($nombre_producto); ?>">
                            <div class="invalid-feedback"><?php echo $nombre_producto_err; ?></div>
                        </div>
                        <div class="col-md-6">
                            <label for="categoria" class="form-label">Categoría:</label>
                            <select id="categoria" name="categoria" class="form-select <?php echo (!empty($categoria_err)) ? 'is-invalid' : ''; ?>">
                                <option value="">Selecciona una categoría</option>
                                <?php foreach ($categorias_db as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['id_categoria']); ?>" <?php echo ($categoria == $cat['id_categoria']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"><?php echo $categoria_err; ?></div>
                        </div>
                        <div class="col-md-6">
                            <label for="precio" class="form-label">Precio:</label>
                            <input type="number" step="0.01" id="precio" name="precio" class="form-control <?php echo (!empty($precio_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($precio); ?>">
                            <div class="invalid-feedback"><?php echo $precio_err; ?></div>
                        </div>
                        <div class="col-md-6">
                            <label for="stock" class="form-label">Stock:</label>
                            <input type="number" id="stock" name="stock" class="form-control <?php echo (!empty($stock_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($stock); ?>">
                            <div class="invalid-feedback"><?php echo $stock_err; ?></div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Agregar Producto</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <h2 class="card-header bg-success text-white">Listado de Productos</h2>
            <div class="card-body">
                <?php if (empty($productos)): ?>
                    <div class="alert alert-info" role="alert">
                        No hay productos registrados. ¡Agrega algunos!
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Categoría</th>
                                    <th>Precio</th>
                                    <th>Stock</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos as $producto): 
                                    // Obtener id de categoría correspondiente al nombre guardado (si existe)
                                    $categoria_id_row = '';
                                    foreach ($categorias_db as $cat) {
                                        if (trim($cat['nombre_categoria']) === trim($producto['categoria'])) {
                                            $categoria_id_row = $cat['id_categoria'];
                                            break;
                                        }
                                    }
                                ?>
                                    <tr id="producto-row-<?php echo htmlspecialchars($producto['id_producto']); ?>">
                                        <td><?php echo htmlspecialchars($producto['id_producto']); ?></td>
                                        <td><?php echo htmlspecialchars($producto['nombre_producto']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($producto['categoria'])); ?></td>
                                        <td>$<?php echo htmlspecialchars(number_format($producto['precio'], 2)); ?></td>
                                        <td><?php echo htmlspecialchars($producto['stock']); ?></td>
                                        <td>
                                            <button 
                                                type="button"
                                                class="btn btn-sm btn-info btn-edit"
                                                data-id="<?php echo htmlspecialchars($producto['id_producto']); ?>"
                                                data-nombre="<?php echo htmlspecialchars($producto['nombre_producto']); ?>"
                                                data-categoria="<?php echo htmlspecialchars($categoria_id_row); ?>"
                                                data-precio="<?php echo htmlspecialchars($producto['precio']); ?>"
                                                data-stock="<?php echo htmlspecialchars($producto['stock']); ?>"
                                            >Editar</button>
                                            <a href="#" class="btn btn-sm btn-danger">Eliminar</a>
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

    <!-- Modal Editar Producto -->
    <div class="modal fade" id="modalEditarProducto" tabindex="-1" aria-labelledby="modalEditarProductoLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <form id="formEditarProducto">
            <div class="modal-header">
              <h5 class="modal-title" id="modalEditarProductoLabel">Editar Producto</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="editarAlert" class="alert d-none" role="alert"></div>

                <input type="hidden" name="action" value="update_product">
                <input type="hidden" id="edit_id_producto" name="id_producto" value="">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="edit_nombre_producto" class="form-label">Nombre del Producto:</label>
                        <input type="text" id="edit_nombre_producto" name="nombre_producto" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="edit_categoria" class="form-label">Categoría:</label>
                        <select id="edit_categoria" name="categoria" class="form-select" required>
                            <option value="">Selecciona una categoría</option>
                            <?php foreach ($categorias_db as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['id_categoria']); ?>"><?php echo htmlspecialchars($cat['nombre_categoria']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="edit_precio" class="form-label">Precio:</label>
                        <input type="number" step="0.01" id="edit_precio" name="precio" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="edit_stock" class="form-label">Stock:</label>
                        <input type="number" id="edit_stock" name="stock" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="../js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <script>
    (function(){
        // Inicializar modal bootstrap
        var modalEl = document.getElementById('modalEditarProducto');
        var bsModal;
        if (modalEl) {
            bsModal = new bootstrap.Modal(modalEl);
        }

        // Función para mostrar alert dentro del modal
        function showModalAlert(type, message) {
            var alertEl = document.getElementById('editarAlert');
            alertEl.className = 'alert alert-' + type;
            alertEl.textContent = message;
            alertEl.classList.remove('d-none');
        }
        function hideModalAlert() {
            var alertEl = document.getElementById('editarAlert');
            alertEl.classList.add('d-none');
        }

        // Rellenar modal al hacer click en editar
        document.querySelectorAll('.btn-edit').forEach(function(btn){
            btn.addEventListener('click', function(e){
                hideModalAlert();
                var id = this.dataset.id || '';
                var nombre = this.dataset.nombre || '';
                var categoria = this.dataset.categoria || '';
                var precio = this.dataset.precio || '';
                var stock = this.dataset.stock || '';

                document.getElementById('edit_id_producto').value = id;
                document.getElementById('edit_nombre_producto').value = nombre;
                document.getElementById('edit_categoria').value = categoria;
                document.getElementById('edit_precio').value = precio;
                document.getElementById('edit_stock').value = stock;

                if (bsModal) bsModal.show();
            });
        });

        // Envío del formulario por AJAX
        var formEditar = document.getElementById('formEditarProducto');
        formEditar.addEventListener('submit', function(e){
            e.preventDefault();
            hideModalAlert();

            var formData = new FormData(formEditar);

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(resp){ return resp.json(); })
            .then(function(data){
                if (data.success) {
                    // Recargar para reflejar cambios (simplifica actualización del listado)
                    location.reload();
                } else {
                    if (data.errors && data.errors.length) {
                        showModalAlert('danger', data.errors.join(' '));
                    } else {
                        showModalAlert('danger', 'Ocurrió un error desconocido.');
                    }
                }
            })
            .catch(function(err){
                showModalAlert('danger', 'Error de comunicación con el servidor.');
                console.error(err);
            });
        });

    })();
    </script>
</body>
</html>
