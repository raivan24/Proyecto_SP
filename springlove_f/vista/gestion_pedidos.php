<?php
// gestion_pedidos.php

session_start();

// Incluir el archivo de conexión a la base de datos
require_once '../controlador/db_connection.php'; // Asegúrar  que la ruta es correcta
require_once '../controlador/func_ayuda.php'; // Asegúrar que la ruta es correcta
// Verificar si el usuario no ha iniciado sesión o no tiene permisos (solo admin y asistente)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || ($_SESSION["tipo_usuario"] !== 'admin' && $_SESSION["tipo_usuario"] !== 'asistente')) {
    header("location: ../index.php"); // Ajustar la ruta si es necesario
    exit;
}
registrarEnBitacora($conn, "Gestión de Pedidos"); //funcion registro bitacora_ruta

$nombre_cliente_form = $telefono_cliente_form = $direccion_entrega_form = $notas_cliente_form = $fecha_entrega_estimada = $cliente_cedula_form = $estado_pago_form = "";
$nombre_cliente_err = $productos_err = $total_estimado_err = $cliente_id_err = $estado_pago_err = $fecha_entrega_err = "";
$success_message = "";
$error_message = "";
$edit_mode = false;
$pedido_id_to_edit = null;
$selected_client_id = null; // Para almacenar el ID del cliente seleccionado/nuevo

//aqui codigo para procesar el estado de pago
if (isset($_POST['action']) && $_POST['action'] === 'changepago') {
    $pedidoid = intval($_POST['pedidoid']);
    $nuevo_pago = $_POST['nuevo_pago'];
    $valid_pagos = ["Abono 50%", "Pago"];
    $response = ["success" => false, "message" => ""];
    if (!in_array($nuevo_pago, $valid_pagos)) {
        $response['message'] = "Estado de pago no válido.";
    } else {
        $sql = "UPDATE pedidos SET estado_pago = ? WHERE id_pedido = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("si", $nuevo_pago, $pedidoid);
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Estado del pago actualizado exitosamente.';
            } else {
                $response['message'] = 'Error al actualizar el estado del pago.';
            }
            $stmt->close();
        } else {
            $response['message'] = 'Error al preparar la consulta.';
        }
    }
    echo json_encode($response);
    exit;
}
// fin guardar cambio de estado pago.

// --- Lógica de Búsqueda de Clientes (Endpoint AJAX) ---
if (isset($_GET['action']) && $_GET['action'] == 'search_client' && isset($_GET['query'])) {
    $search_query = '%' . $_GET['query'] . '%';
    $clientes_encontrados = [];

    $sql_search = "SELECT id_cliente, cedula, nombre, apellido, telefono, email, direccion FROM clientes WHERE nombre LIKE ? OR apellido LIKE ? OR telefono LIKE ? OR email LIKE ? OR cedula LIKE ? ORDER BY nombre LIMIT 10";
    if ($stmt_search = $conn->prepare($sql_search)) {
        $stmt_search->bind_param("sssss", $search_query, $search_query, $search_query, $search_query, $search_query);
        $stmt_search->execute();
        $result_search = $stmt_search->get_result();
        while ($row = $result_search->fetch_assoc()) {
            $clientes_encontrados[] = $row;
        }
        $stmt_search->close();
    }
    echo json_encode($clientes_encontrados);
    exit; // Terminar el script PHP para la respuesta AJAX
}

// --- Lógica de Registro de Nuevo Cliente (Endpoint AJAX) ---
if (isset($_POST['action']) && $_POST['action'] == 'register_client_ajax') {
    $new_cedula = trim($_POST['cedula'] ?? '');
    $new_nombre = trim($_POST['nombre'] ?? '');
    $new_apellido = trim($_POST['apellido'] ?? '');
    $new_telefono = trim($_POST['telefono'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_direccion = trim($_POST['direccion'] ?? '');

    $response = ['success' => false, 'message' => '', 'id_cliente' => null];

    // Validaciones básicas para el nuevo cliente
    if (empty($new_cedula)) {
        $response['message'] = 'La cédula es obligatoria.';
    } elseif (empty($new_nombre)) {
        $response['message'] = 'El nombre del cliente es obligatorio.';
    } elseif (!empty($new_email) && !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Formato de email inválido.';
    } else {
        // Verificar si la cédula o el email ya existen
        $sql_check = "SELECT id_cliente FROM clientes WHERE cedula = ? OR (email IS NOT NULL AND email = ?)";
        if ($stmt_check = $conn->prepare($sql_check)) {
            $stmt_check->bind_param("ss", $new_cedula, $new_email);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $response['message'] = 'Ya existe un cliente con esta cédula o email.';
            } else {
                // Insertar nuevo cliente
                $sql_insert = "INSERT INTO clientes (cedula, nombre, apellido, telefono, email, direccion) VALUES (?, ?, ?, ?, ?, ?)";
                if ($stmt_insert = $conn->prepare($sql_insert)) {
                    $param_email = !empty($new_email) ? $new_email : null; // Guardar null si está vacío

                    $stmt_insert->bind_param("ssssss", $new_cedula, $new_nombre, $new_apellido, $new_telefono, $param_email, $new_direccion);
                    if ($stmt_insert->execute()) {
                        $response['success'] = true;
                        $response['message'] = 'Cliente registrado exitosamente.';
                        $response['id_cliente'] = $conn->insert_id;
                        $response['cedula'] = $new_cedula; // Añadir cédula a la respuesta
                        $response['nombre'] = $new_nombre;
                        $response['apellido'] = $new_apellido;
                        $response['telefono'] = $new_telefono;
                        $response['email'] = $new_email;
                        $response['direccion'] = $new_direccion;
                    } else {
                        $response['message'] = 'Error al registrar el cliente: ' . $stmt_insert->error;
                    }
                    $stmt_insert->close();
                } else {
                    $response['message'] = 'Error al preparar la consulta de registro de cliente.';
                }
            }
            $stmt_check->close();
        } else {
            $response['message'] = 'Error al preparar la consulta de verificación de cliente.';
        }
    }
    echo json_encode($response);
    exit; // Terminar el script PHP para la respuesta AJAX
}

// Obtener productos desde la base de datos (para la selección en el formulario)
$productos_db = [];
$sql_select_productos = "SELECT id_producto, nombre_producto, categoria, precio, stock FROM productos WHERE stock > 0 ORDER BY categoria, nombre_producto";
if ($result_productos = $conn->query($sql_select_productos)) {
    $productos_db = $result_productos->fetch_all(MYSQLI_ASSOC);
} else {
    $error_message = "Error al cargar los productos disponibles.";
}

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

// Obtener el ID del estado 'Pendiente' por defecto para nuevos pedidos
$id_estado_pendiente = array_search('Pendiente', $estados_disponibles);
if ($id_estado_pendiente === false) {
    $error_message = "Error: El estado 'Pendiente' no se encontró en la base de datos de estados. Por favor, créalo.";
}

// Obtener los IDs de los estados 'Entregado', 'Cancelado' y 'Devuelto'
$id_estado_entregado = null;
$id_estado_cancelado = null;
$id_estado_devuelto = null; // NUEVO: Estado Devuelto

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
    error_log("Error al obtener IDs de estados de exclusión: " . $conn->error);
}

// Definir constantes globales para la lógica de stock
// Usamos 0 como fallback, pero se debe verificar si los estados existen
define('ID_ESTADO_CANCELADO', $id_estado_cancelado ?? 0);
define('ID_ESTADO_DEVUELTO', $id_estado_devuelto ?? 0);

// Pasar los IDs de estado 'Entregado' y 'Cancelado' a JavaScript para la eliminación dinámica
$id_estado_entregado_js = $id_estado_entregado;
$id_estado_cancelado_js = $id_estado_cancelado;

// --- Funciones de Gestión de Stock (NUEVO) ---

/**
 * Ajusta el stock de los productos de un pedido específico.
 * Se usa para descontar (creación/edición) o sumar (cancelación/devolución).
 * @param mysqli $conn La conexión a la base de datos.
 * @param int $pedido_id ID del pedido a afectar.
 * @param string $operation 'ADD' para sumar stock (cancelación/devolución) o 'SUBTRACT' para restar (creación/edición).
 * @param array|null $productos_list Lista opcional de productos: [{id, cantidad}, ...] (usado en INSERT/UPDATE, ya que el detalle aún no existe o acaba de ser eliminado/insertado).
 * @throws Exception Si la operación de stock falla.
 */
function adjust_stock_for_order($conn, $pedido_id, $operation, $productos_list = null) {
    $products_to_process = [];

    if (is_array($productos_list) && !empty($productos_list)) {
        // Caso 1: Uso para creación/actualización (cuando ya tenemos la lista de productos del formulario)
        $products_to_process = $productos_list;
    } else {
        // Caso 2: Uso para cambio de estado (obtenemos los productos del detalle del pedido existente)
        $sql_get_details = "SELECT id_producto AS id, cantidad FROM detalle_pedido WHERE id_pedido = ?";
        if (!$stmt_get_details = $conn->prepare($sql_get_details)) {
            throw new Exception("Error al preparar la consulta de detalle de stock: " . $conn->error);
        }
        $stmt_get_details->bind_param("i", $pedido_id);
        $stmt_get_details->execute();
        $result_details = $stmt_get_details->get_result();
        // Mapear los resultados al formato esperado {id, cantidad}
        while($row = $result_details->fetch_assoc()) {
            $products_to_process[] = $row;
        }
        $stmt_get_details->close();
    }
    
    if (empty($products_to_process)) {
        return; // No hay stock que ajustar
    }

    // Definir la consulta de actualización
    $sql_update_stock = ($operation === 'ADD')
        ? "UPDATE productos SET stock = stock + ? WHERE id_producto = ?"
        : "UPDATE productos SET stock = stock - ? WHERE id_producto = ?";

    if (!$stmt_update_stock = $conn->prepare($sql_update_stock)) {
        throw new Exception("Error al preparar la consulta de actualización de stock: " . $conn->error);
    }

    // Ejecutar la actualización para cada producto
    foreach ($products_to_process as $producto) {
        $cantidad = intval($producto['cantidad']);
        $id_producto = intval($producto['id']);

        if ($cantidad <= 0) continue;

        // Bindeamos cantidad y id_producto (ambos enteros)
        $stmt_update_stock->bind_param("ii", $cantidad, $id_producto);
        if (!$stmt_update_stock->execute()) {
            throw new Exception("Error al ajustar stock para producto ID {$id_producto} (Op: {$operation}): " . $stmt_update_stock->error);
        }
    }
    $stmt_update_stock->close();
}


// --- Lógica para cargar datos del pedido en el formulario de edición (sin cambios) ---
$edit_order_products = [];
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $pedido_id_to_edit = intval($_GET['id']);
    $edit_mode = true;

    // ... (Lógica de fetch de pedido y detalle, igual que antes) ...
    $sql_fetch_pedido = "
        SELECT
            p.id_cliente,
            p.fecha_entrega_estimada,
            p.notas_cliente,
            p.nombre_cliente,    -- Instantánea de la tabla de pedidos
            p.telefono_cliente,  -- Instantánea de la tabla de pedidos
            p.direccion_entrega, -- Instantánea de la tabla de pedidos
            p.estado_pago,       -- NUEVO: Estado del Pago
            c.cedula,
            c.nombre AS cliente_nombre_db,
            c.apellido AS cliente_apellido_db,
            c.telefono AS cliente_telefono_db,
            c.direccion AS cliente_direccion_db
        FROM
            pedidos p
        JOIN
            clientes c ON p.id_cliente = c.id_cliente
        WHERE
            p.id_pedido = ?
    ";
    if ($stmt_fetch_pedido = $conn->prepare($sql_fetch_pedido)) {
        $stmt_fetch_pedido->bind_param("i", $pedido_id_to_edit);
        $stmt_fetch_pedido->execute();
        $result_fetch_pedido = $stmt_fetch_pedido->get_result();
        if ($result_fetch_pedido->num_rows == 1) {
            $pedido_data = $result_fetch_pedido->fetch_assoc();
            $selected_client_id = $pedido_data['id_cliente'];
            $fecha_entrega_estimada = $pedido_data['fecha_entrega_estimada'];
            $notas_cliente_form = $pedido_data['notas_cliente'];
            $estado_pago_form = $pedido_data['estado_pago'];

            // Usar los datos "instantánea" del pedido para el formulario
            $nombre_cliente_form = $pedido_data['nombre_cliente'];
            $telefono_cliente_form = $pedido_data['telefono_cliente'];
            $direccion_entrega_form = $pedido_data['direccion_entrega'];
            $cliente_cedula_form = $pedido_data['cedula'];

            // Cargar productos del detalle del pedido
            $sql_fetch_detalle = "
                SELECT
                    dp.id_producto,
                    dp.cantidad,
                    prod.nombre_producto,
                    prod.precio,
                    prod.stock
                FROM
                    detalle_pedido dp
                JOIN
                    productos prod ON dp.id_producto = prod.id_producto
                WHERE
                    dp.id_pedido = ?
            ";
            if ($stmt_detalle_fetch = $conn->prepare($sql_fetch_detalle)) {
                $stmt_detalle_fetch->bind_param("i", $pedido_id_to_edit);
                $stmt_detalle_fetch->execute();
                $result_detalle_fetch = $stmt_detalle_fetch->get_result();
                while ($row_detalle = $result_detalle_fetch->fetch_assoc()) {
                    $edit_order_products[] = [
                        'id' => $row_detalle['id_producto'],
                        'nombre' => $row_detalle['nombre_producto'],
                        'precio' => $row_detalle['precio'], // Usar el precio actual del producto
                        'cantidad' => $row_detalle['cantidad'],
                        'stock' => $row_detalle['stock'] // Stock actual
                    ];
                }
                $stmt_detalle_fetch->close();
            }
        } else {
            $error_message = "Pedido no encontrado para edición.";
            $edit_mode = false;
        }
        $stmt_fetch_pedido->close();
    } else {
        $error_message = "Error al preparar la consulta de obtención de pedido.";
        $edit_mode = false;
    }
}


// --- Lógica para eliminar pedido (sin cambios de stock, ya que el stock se devolverá en caso de cancelación o devolución) ---
// NOTA: Para un sistema más robusto, se debería devolver el stock si el estado actual NO es Cancelado/Devuelto antes de la eliminación.
// Sin embargo, por simplicidad y siguiendo el flujo de estado, asumimos que solo se elimina si ya no está activo, o que el usuario entiende que debe cancelar el pedido primero.
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_to_delete = intval($_GET['id']);
    $conn->begin_transaction();
    try {
        // IMPORTANTE: Si el pedido a eliminar NO está Cancelado/Devuelto, su stock quedará descontado permanentemente.
        // Se recomienda que los pedidos solo se puedan ELIMINAR si ya están Cancelados o Entregados (y el stock ya fue ajustado).

        // ... (Lógica de eliminación de historial, detalle y pedido, igual que antes) ...
        // Eliminar historial de estado
        $sql_delete_historial = "DELETE FROM historial_estado_pedido WHERE id_pedido = ?";
        if ($stmt_del_hist = $conn->prepare($sql_delete_historial)) {
            $stmt_del_hist->bind_param("i", $id_to_delete);
            if (!$stmt_del_hist->execute()) {
                throw new Exception("Error al eliminar historial del pedido: " . $stmt_del_hist->error);
            }
            $stmt_del_hist->close();
        } else {
            throw new Exception("Error al preparar la eliminación de historial: " . $conn->error);
        }

        // Eliminar detalles de pedido primero
        $sql_delete_detalle = "DELETE FROM detalle_pedido WHERE id_pedido = ?";
        if ($stmt_del_det = $conn->prepare($sql_delete_detalle)) {
            $stmt_del_det->bind_param("i", $id_to_delete);
            if (!$stmt_del_det->execute()) {
                throw new Exception("Error al eliminar detalles del pedido: " . $stmt_del_det->error);
            }
            $stmt_del_det->close();
        } else {
            throw new Exception("Error al preparar la eliminación de detalles: " . $conn->error);
        }

        // Eliminar el pedido
        $sql_delete_pedido = "DELETE FROM pedidos WHERE id_pedido = ?";
        if ($stmt_del_ped = $conn->prepare($sql_delete_pedido)) {
            $stmt_del_ped->bind_param("i", $id_to_delete);
            if (!$stmt_del_ped->execute()) {
                throw new Exception("Error al eliminar el pedido: " . $stmt_del_ped->error);
            }
            $stmt_del_ped->close();
        } else {
            throw new Exception("Error al preparar la eliminación del pedido: " . $conn->error);
        }
        // ... (Fin de la lógica de eliminación) ...

        $conn->commit();
        $success_message = "Pedido eliminado exitosamente.";
        header("location: gestion_pedidos.php?msg=" . urlencode($success_message));
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error al eliminar el pedido: " . $e->getMessage();
    }
}

// --- Lógica para cambiar estado del pedido (vía AJAX o POST) ---
if (isset($_POST['action']) && $_POST['action'] == 'change_status') {
    $pedido_id = intval($_POST['pedido_id']);
    $new_estado_id = intval($_POST['new_estado_id']);
    $usuario_id = $_SESSION['id_usuario'];

    $response = ['success' => false, 'message' => ''];
    $conn->begin_transaction();

    try {
        // 1. Obtener el estado actual del pedido
        $sql_get_current_status = "SELECT id_estado FROM pedidos WHERE id_pedido = ?";
        $old_estado_id = null;
        if ($stmt_get_status = $conn->prepare($sql_get_current_status)) {
            $stmt_get_status->bind_param("i", $pedido_id);
            $stmt_get_status->execute();
            $stmt_get_status->bind_result($old_estado_id);
            if (!$stmt_get_status->fetch()) {
                throw new Exception("Pedido no encontrado.");
            }
            $stmt_get_status->close();
        } else {
            throw new Exception("Error al preparar la consulta de estado actual: " . $conn->error);
        }
        
        // 2. Lógica de Ajuste de Stock por Cambio de Estado (NUEVO)
        $should_return_stock = (
            $new_estado_id == ID_ESTADO_CANCELADO || // Si el nuevo estado es Cancelado
            $new_estado_id == ID_ESTADO_DEVUELTO    // O si el nuevo estado es Devuelto
        );

        $was_stock_returned = (
            $old_estado_id == ID_ESTADO_CANCELADO || // Si el viejo estado era Cancelado
            $old_estado_id == ID_ESTADO_DEVUELTO    // O si el viejo estado era Devuelto
        );
        
        // Devolver stock solo si pasa a un estado de devolución Y el estado anterior NO era de devolución.
        if ($should_return_stock && !$was_stock_returned) {
            adjust_stock_for_order($conn, $pedido_id, 'ADD');
            $response['message'] .= " (Stock devuelto a inventario).";
        }
        
        // 3. Actualizar el estado del pedido (igual que antes)
        $sql_update_status = "UPDATE pedidos SET id_estado = ? WHERE id_pedido = ?";
        if ($stmt_update_status = $conn->prepare($sql_update_status)) {
            $stmt_update_status->bind_param("ii", $new_estado_id, $pedido_id);
            if (!$stmt_update_status->execute()) {
                throw new Exception("Error al actualizar el estado del pedido: " . $stmt_update_status->error);
            }
            $stmt_update_status->close();
        } else {
            throw new Exception("Error al preparar la consulta de actualización de estado: " . $conn->error);
        }

        // 4. Registrar el cambio en el historial (igual que antes)
        $sql_insert_historial = "INSERT INTO historial_estado_pedido (id_pedido, id_estado_anterior, id_estado_nuevo, id_usuario_cambio) VALUES (?, ?, ?, ?)";
        if ($stmt_insert_historial = $conn->prepare($sql_insert_historial)) {
            $stmt_insert_historial->bind_param("iiii", $pedido_id, $old_estado_id, $new_estado_id, $usuario_id);
            if (!$stmt_insert_historial->execute()) {
                throw new Exception("Error al registrar el cambio de estado en el historial: " . $stmt_insert_historial->error);
            }
            $stmt_insert_historial->close();
        } else {
            throw new Exception("Error al preparar la consulta de historial de estado: " . $conn->error);
        }

        $conn->commit();
        $response['success'] = true;
        $response['message'] = "Estado del pedido #{$pedido_id} actualizado a '" . htmlspecialchars($estados_disponibles[$new_estado_id]) . "' exitosamente." . $response['message'];
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = $e->getMessage();
    }
    echo json_encode($response);
    exit;
}


// Procesar el formulario cuando se envía para agregar o editar un pedido
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type'])) {
    $submitted_form_type = $_POST['form_type'];

    $selected_client_id_post = intval($_POST['selected_client_id'] ?? 0);
    $nombre_cliente_form_post = trim($_POST["nombre_cliente_display"]);
    $telefono_cliente_form_post = trim($_POST["telefono_cliente_display"]);
    $direccion_entrega_form_post = trim($_POST["direccion_entrega_display"]);
    $notas_cliente_form_post = trim($_POST["notas_cliente"]);
    $fecha_entrega_estimada_post = !empty(trim($_POST["fecha_entrega_estimada"])) ? trim($_POST["fecha_entrega_estimada"]) : null;
    $estado_pago_post = trim($_POST["estado_pago"] ?? ''); 

    $productos_seleccionados_json_post = $_POST['productos_pedido'];
    $total_estimado_post = floatval($_POST['total_estimado']);

    // Validaciones (igual que antes)
    if (empty($selected_client_id_post)) {
        $cliente_id_err = "Debe seleccionar un cliente o registrar uno nuevo.";
    }
    if (empty($nombre_cliente_form_post)) {
        $nombre_cliente_err = "El nombre del cliente es requerido.";
    }

    $valid_payment_statuses = ['Abono 50%', 'Pago'];
    if (!in_array($estado_pago_post, $valid_payment_statuses)) {
        $estado_pago_err = "Estado de pago no válido.";
    }

    $productos_seleccionados_post = json_decode($productos_seleccionados_json_post, true);
    if (empty($productos_seleccionados_post)) {
        $productos_err = "Debe seleccionar al menos un producto para el pedido.";
    } elseif ($total_estimado_post <= 0) {
        $total_estimado_err = "El total estimado del pedido debe ser mayor a cero.";
    }
    
    // Convertir el array de productos del formulario al formato {id, cantidad}
    $productos_para_stock = array_map(function($p) {
        return ['id' => $p['id'], 'cantidad' => $p['cantidad']];
    }, $productos_seleccionados_post);

    // Validar que la fecha estimada de entrega no sea menor a la fecha de creación
    if (!empty($fecha_entrega_estimada_post)) {
        if ($submitted_form_type === 'editar_pedido') {
            $pedido_id_check = intval($_POST['pedido_id_to_edit'] ?? 0);
            if ($pedido_id_check > 0) {
                $sql_get_fecha_creacion = "SELECT fecha_creacion FROM pedidos WHERE id_pedido = ?";
                if ($stmt_fecha = $conn->prepare($sql_get_fecha_creacion)) {
                    $stmt_fecha->bind_param("i", $pedido_id_check);
                    $stmt_fecha->execute();
                    $stmt_fecha->bind_result($fecha_creacion_db);
                    if ($stmt_fecha->fetch()) {
                        $min_date = date('Y-m-d', strtotime($fecha_creacion_db));
                    } else {
                        $min_date = date('Y-m-d');
                    }
                    $stmt_fecha->close();
                } else {
                    $min_date = date('Y-m-d');
                }
            } else {
                $min_date = date('Y-m-d');
            }
        } else {
            // Para creación, la fecha mínima es hoy
            $min_date = date('Y-m-d');
        }

        if (strtotime($fecha_entrega_estimada_post) < strtotime($min_date)) {
            $fecha_entrega_err = "La fecha estimada de entrega no puede ser anterior a la fecha de creación del pedido ({$min_date}).";
        }
    }


    if (empty($cliente_id_err) && empty($nombre_cliente_err) && empty($productos_err) && empty($total_estimado_err) && empty($estado_pago_err) && empty($fecha_entrega_err) && !empty($id_estado_pendiente)) {
        $conn->begin_transaction();
        $id_pedido_affected = null;
        try {
            if ($submitted_form_type === 'crear_pedido') {
                // INSERTAR NUEVO PEDIDO
                $sql_pedido = "INSERT INTO pedidos (id_cliente, id_usuario_creador, fecha_entrega_estimada, id_estado, nombre_cliente, telefono_cliente, direccion_entrega, notas_cliente, total_estimado, estado_pago) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                if ($stmt_pedido = $conn->prepare($sql_pedido)) {
                    $stmt_pedido->bind_param("iissssssss",
                        $selected_client_id_post,
                        $_SESSION['id_usuario'],
                        $fecha_entrega_estimada_post,
                        $id_estado_pendiente,
                        $nombre_cliente_form_post,
                        $telefono_cliente_form_post,
                        $direccion_entrega_form_post,
                        $notas_cliente_form_post,
                        $total_estimado_post,
                        $estado_pago_post
                    );
                    if (!$stmt_pedido->execute()) {
                        throw new Exception("Error al insertar el pedido: " . $stmt_pedido->error);
                    }
                    $id_pedido_affected = $conn->insert_id;
                    $stmt_pedido->close();

                    // Registrar el primer estado en el historial
                    $sql_insert_historial = "INSERT INTO historial_estado_pedido (id_pedido, id_estado_anterior, id_estado_nuevo, id_usuario_cambio) VALUES (?, ?, ?, ?)";
                    if ($stmt_insert_historial = $conn->prepare($sql_insert_historial)) {
                        $null_prev_state = null;
                        $stmt_insert_historial->bind_param("iiii", $id_pedido_affected, $null_prev_state, $id_estado_pendiente, $_SESSION['id_usuario']);
                        if (!$stmt_insert_historial->execute()) {
                            throw new Exception("Error al registrar el estado inicial en el historial: " . $stmt_insert_historial->error);
                        }
                        $stmt_insert_historial->close();
                    } else {
                        throw new Exception("Error al preparar la consulta de historial de estado inicial: " . $conn->error);
                    }
                } else {
                    throw new Exception("Error al preparar la consulta de inserción de pedido: " . $conn->error);
                }
                
                // --- STOCK: Descontar stock para el nuevo pedido (NUEVO) ---
                //adjust_stock_for_order($conn, $id_pedido_affected, 'SUBTRACT', $productos_para_stock);  aqui no es necesario ejecutarlo, lo ejecuto mas abajo junto al SUBTRACT de editar.

                $success_message = "Pedido #".$id_pedido_affected." creado exitosamente.";

            } elseif ($submitted_form_type === 'editar_pedido') {
                // ACTUALIZAR PEDIDO EXISTENTE
                $pedido_id_to_edit = intval($_POST['pedido_id_to_edit']);
                if (empty($pedido_id_to_edit)) {
                    throw new Exception("ID de pedido para editar no proporcionado.");
                }
                $id_pedido_affected = $pedido_id_to_edit;
                
                // --- STOCK: 1. Obtener detalles antiguos ANTES de eliminarlos (NUEVO) ---
                $sql_get_old_details = "SELECT id_producto AS id, cantidad FROM detalle_pedido WHERE id_pedido = ?";
                $old_products_to_restore = [];
                if ($stmt_get_old = $conn->prepare($sql_get_old_details)) {
                    $stmt_get_old->bind_param("i", $pedido_id_to_edit);
                    $stmt_get_old->execute();
                    $result_old = $stmt_get_old->get_result();
                    while($row = $result_old->fetch_assoc()) {
                        $old_products_to_restore[] = $row;
                    }
                    $stmt_get_old->close();
                } else {
                    throw new Exception("Error al obtener detalles antiguos: " . $conn->error);
                }

                // --- STOCK: 2. Devolver stock de los productos antiguos (NUEVO) ---
                if (!empty($old_products_to_restore)) {
                    adjust_stock_for_order($conn, $pedido_id_to_edit, 'ADD', $old_products_to_restore);
                }
                
                // 3. Eliminar detalles de pedido anteriores para re-insertar
                $sql_delete_old_details = "DELETE FROM detalle_pedido WHERE id_pedido = ?";
                if ($stmt_delete_old = $conn->prepare($sql_delete_old_details)) {
                    $stmt_delete_old->bind_param("i", $pedido_id_to_edit);
                    if (!$stmt_delete_old->execute()) {
                        throw new Exception("Error al eliminar detalles de pedido antiguos: " . $stmt_delete_old->error);
                    }
                    $stmt_delete_old->close();
                } else {
                    throw new Exception("Error al preparar la eliminación de detalles antiguos: " . $conn->error);
                }


                $sql_update_pedido = "UPDATE pedidos SET id_cliente = ?, fecha_entrega_estimada = ?, notas_cliente = ?, nombre_cliente = ?, telefono_cliente = ?, direccion_entrega = ?, total_estimado = ?, estado_pago = ? WHERE id_pedido = ?";
                if ($stmt_update_pedido = $conn->prepare($sql_update_pedido)) {
                    $stmt_update_pedido->bind_param("isssssdsi",
                        $selected_client_id_post,
                        $fecha_entrega_estimada_post,
                        $notas_cliente_form_post,
                        $nombre_cliente_form_post,
                        $telefono_cliente_form_post,
                        $direccion_entrega_form_post,
                        $total_estimado_post,
                        $estado_pago_post,
                        $pedido_id_to_edit
                    );
                    if (!$stmt_update_pedido->execute()) {
                        throw new Exception("Error al actualizar el pedido: " . $stmt_update_pedido->error);
                    }
                    $stmt_update_pedido->close();
                } else {
                    throw new Exception("Error al preparar la consulta de actualización de pedido: " . $conn->error);
                }

                $success_message = "Pedido #".$id_pedido_affected." actualizado exitosamente.";
            }

            // Insertar/Re-insertar los detalles del pedido
            $sql_detalle_pedido = "INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario_al_momento_pedido) VALUES (?, ?, ?, ?)";
            foreach ($productos_seleccionados_post as $producto) {
                $id_producto = $producto['id'];
                $cantidad = $producto['cantidad'];
                $precio_unitario = $producto['precio'];

                if ($stmt_detalle = $conn->prepare($sql_detalle_pedido)) {
                    $stmt_detalle->bind_param("iiid", $id_pedido_affected, $id_producto, $cantidad, $precio_unitario);
                    if (!$stmt_detalle->execute()) {
                        throw new Exception("Error al insertar detalle de pedido para producto ID: " . $id_producto . ": " . $stmt_detalle->error);
                    }
                    $stmt_detalle->close();
                } else {
                    throw new Exception("Error al preparar la consulta de detalle de pedido: " . $conn->error);
                }
            }
            
            // --- STOCK: 4. Descontar stock para los nuevos productos (NUEVO - Solo si es Edición o si es Creación) ---
            if ($submitted_form_type === 'editar_pedido') {
                adjust_stock_for_order($conn, $id_pedido_affected, 'SUBTRACT', $productos_para_stock);
            }
            // NOTA: Para la Creación, ya se hizo el ajuste antes de la inserción de detalles para simplificar.
            // Si $productos_seleccionados_post se usa como source, es mejor hacerlo DESPUÉS de la inserción de detalles.
            // MOVEMOS la resta de stock para creación aquí, después de los detalles.
            if ($submitted_form_type === 'crear_pedido') {
                adjust_stock_for_order($conn, $id_pedido_affected, 'SUBTRACT', $productos_para_stock);
            }
            // Corrección: La resta de stock para Creación debe ser después de insertar el pedido para tener el $id_pedido_affected.

            $conn->commit();
            header("location: gestion_pedidos.php?msg=" . urlencode($success_message));
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error al procesar el pedido: " . $e->getMessage();
            
            // Lógica de recuperación de datos de formulario en caso de error (igual que antes)
            $nombre_cliente_form = $nombre_cliente_form_post;
            $telefono_cliente_form = $telefono_cliente_form_post;
            $direccion_entrega_form = $direccion_entrega_form_post;
            $notas_cliente_form = $notas_cliente_form_post;
            $fecha_entrega_estimada = $fecha_entrega_estimada_post;
            $selected_client_id = $selected_client_id_post;
            $estado_pago_form = $estado_pago_post;
            
            if ($submitted_form_type === 'editar_pedido') {
                $pedido_id_to_edit = intval($_POST['pedido_id_to_edit'] ?? 0);
                if ($pedido_id_to_edit > 0) {
                    $edit_mode = true;
                } else {
                    $edit_mode = false;
                    $pedido_id_to_edit = null;
                }
            } else {
                $edit_mode = false;
                $pedido_id_to_edit = null;
            }
            $edit_order_products = $productos_seleccionados_post;
        }
    } else {
         // Lógica de recuperación de datos de formulario en caso de error (igual que antes)
        $nombre_cliente_form = $nombre_cliente_form_post;
        $telefono_cliente_form = $telefono_cliente_form_post;
        $direccion_entrega_form = $direccion_entrega_form_post;
        $notas_cliente_form = $notas_cliente_form_post;
        $fecha_entrega_estimada = $fecha_entrega_estimada_post;
        $selected_client_id = $selected_client_id_post;
        $estado_pago_form = $estado_pago_post;
        
        $submitted_form_type = $_POST['form_type'];
        if ($submitted_form_type === 'editar_pedido') {
            $pedido_id_to_edit = intval($_POST['pedido_id_to_edit'] ?? 0);
            if ($pedido_id_to_edit > 0) {
                $edit_mode = true;
            } else {
                $edit_mode = false;
                $pedido_id_to_edit = null;
            }
        } else {
            $edit_mode = false;
            $pedido_id_to_edit = null;
        }
        $edit_order_products = $productos_seleccionados_post;
    }
}

// Mostrar mensajes de éxito después de redirección
if (isset($_GET['msg'])) {
    $success_message = htmlspecialchars($_GET['msg']);
}


// Obtener todos los pedidos para mostrarlos en la tabla (igual que antes)
$pedidos_db = [];
$sql_select_pedidos = "
    SELECT
        p.id_pedido,
        p.fecha_creacion,
        p.fecha_entrega_estimada,
        p.id_estado, -- Obtener el ID del estado
        p.estado_pago,       -- Estado del Pago
        e.nombre_estado,
        p.nombre_cliente,    -- Instantánea del nombre del cliente
        p.telefono_cliente,  -- Instantánea del teléfono del cliente
        p.direccion_entrega, -- Instantánea de la dirección de entrega
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

// Añadir la condición WHERE para excluir pedidos 'Entregado' y 'Cancelado'
$where_clauses = [];
if ($id_estado_entregado !== null) {
    $where_clauses[] = "p.id_estado != " . $id_estado_entregado;
}
if ($id_estado_cancelado !== null) {
    $where_clauses[] = "p.id_estado != " . $id_estado_cancelado;
}
// Excluir también el estado 'Devuelto' del listado principal
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
            } else {
                error_log("Error al obtener detalle de pedido para ID " . $pedido['id_pedido'] . ": " . $stmt_detalle->error);
            }
            $stmt_detalle->close();
        } else {
            error_log("Error al preparar la consulta de detalle de pedido: " . $conn->error);
        }
        $pedidos_db[] = $pedido;
    }
    $result_pedidos->free();
} else {
    $error_message = "Error al cargar los pedidos: " . $conn->error;
    error_log($error_message);
}

// Determinar la fecha mínima que usará la validación del lado cliente
$min_date_for_js = date('Y-m-d');
if (!empty($edit_mode) && !empty($pedido_id_to_edit)) {
    $sql_min_date = "SELECT fecha_creacion FROM pedidos WHERE id_pedido = ?";
    if ($stmt_min = $conn->prepare($sql_min_date)) {
        $stmt_min->bind_param("i", $pedido_id_to_edit);
        $stmt_min->execute();
        $stmt_min->bind_result($fecha_creacion_for_js);
        if ($stmt_min->fetch()) {
            $min_date_for_js = date('Y-m-d', strtotime($fecha_creacion_for_js));
        }
        $stmt_min->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pedidos</title>
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
        <h1 class="mb-4 text-center text-success">Gestión de Pedidos</h1>
        <!--  -->

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-5">
            <h2 class="card-header bg-success text-white"><?php echo $edit_mode ? 'Editar Pedido #' . htmlspecialchars($pedido_id_to_edit) : 'Crear Nuevo Pedido'; ?></h2>
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="formCrearPedido">
                    <input type="hidden" name="form_type" value="<?php echo $edit_mode ? 'editar_pedido' : 'crear_pedido'; ?>">
                    <input type="hidden" name="pedido_id_to_edit" value="<?php echo htmlspecialchars($pedido_id_to_edit ?? ''); ?>">
                    <input type="hidden" name="selected_client_id" id="selected_client_id" value="<?php echo htmlspecialchars($selected_client_id ?? ''); ?>">

                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label class="form-label">Cliente:</label>
                            <div class="input-group">
                                <input type="text" id="nombre_cliente_display" name="nombre_cliente_display" class="form-control <?php echo (!empty($nombre_cliente_err) || !empty($cliente_id_err)) ? 'is-invalid' : ''; ?>" placeholder="Nombre del Cliente" value="<?php echo htmlspecialchars($nombre_cliente_form); ?>" required readonly>
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#clientModal">
                                    <i class="bi bi-search"></i> Seleccionar Cliente
                                </button>
                            </div>
                            <div class="invalid-feedback d-block"><?php echo $nombre_cliente_err . ' ' . $cliente_id_err; ?></div>
                        </div>
                        <div class="col-md-6">
                            <label for="telefono_cliente_display" class="form-label">Teléfono:</label>
                            <input type="text" id="telefono_cliente_display" name="telefono_cliente_display" class="form-control" value="<?php echo htmlspecialchars($telefono_cliente_form); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="direccion_entrega_display" class="form-label">Dirección de Entrega:</label>
                            <input type="text" id="direccion_entrega_display" name="direccion_entrega_display" class="form-control" value="<?php echo htmlspecialchars($direccion_entrega_form); ?>">
                        </div>
                        
                        <!-- Estado del Pago -->
                        <div class="col-md-6">
                            <label for="estado_pago" class="form-label">Estado del Pago:</label>
                            <select id="estado_pago" name="estado_pago" class="form-select <?php echo (!empty($estado_pago_err)) ? 'is-invalid' : ''; ?>" required>
                                <option value="Abono 50%" <?php echo ($estado_pago_form == 'Abono 50%' || $estado_pago_form == '') ? 'selected' : ''; ?>>Abono 50%</option>
                                <option value="Pago" <?php echo ($estado_pago_form == 'Pago') ? 'selected' : ''; ?>>Pago</option>
                            </select>
                            <div class="invalid-feedback"><?php echo $estado_pago_err; ?></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="fecha_entrega_estimada" class="form-label">Fecha de Entrega Estimada:</label>
                            <input type="date" id="fecha_entrega_estimada" name="fecha_entrega_estimada" class="form-control" value="<?php echo htmlspecialchars($fecha_entrega_estimada); ?>" data-min="<?php echo htmlspecialchars($min_date_for_js ?? date('Y-m-d')); ?>">
                            <div class="invalid-feedback d-block"><?php echo $fecha_entrega_err; ?></div>
                        </div>
                        
                        <div class="col-12">
                            <label for="notas_cliente" class="form-label">Notas para el Arreglo/Cliente:</label>
                            <textarea id="notas_cliente" name="notas_cliente" class="form-control" rows="3"><?php echo htmlspecialchars($notas_cliente_form); ?></textarea>
                        </div>
                    </div>

                    <div class="col-12 mt-4">
                        <h4 class="mb-3">Seleccionar Productos para el Arreglo:</h4>
                        <?php if (empty($productos_db)): ?>
                            <div class="alert alert-info" role="alert">
                                No hay productos disponibles en stock para seleccionar.
                            </div>
                        <?php else: ?>
                            <div class="accordion" id="productosPedidoAccordion">
                                <?php
                                $current_category = '';
                                foreach ($productos_db as $producto) {
                                    if ($producto['categoria'] !== $current_category) {
                                        if ($current_category !== '') {
                                            echo '</div></div></div>'; // Cerrar el item anterior
                                        }
                                        $current_category = $producto['categoria'];
                                        $collapse_id = 'collapsePedido' . ucfirst(str_replace(' ', '', $current_category));
                                        $heading_id = 'headingPedido' . ucfirst(str_replace(' ', '', $current_category));
                                        echo '
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="' . $heading_id . '">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#' . $collapse_id . '" aria-expanded="false" aria-controls="' . $collapse_id . '">
                                                    ' . htmlspecialchars(ucfirst($current_category)) . '
                                                </button>
                                            </h2>
                                            <div id="' . $collapse_id . '" class="accordion-collapse collapse" aria-labelledby="' . $heading_id . '" data-bs-parent="#productosPedidoAccordion">
                                                <div class="accordion-body">
                                        ';
                                    }
                                    ?>
                                    <div class="form-check product-item">
                                        <div>
                                            <input class="form-check-input product-pedido-checkbox" type="checkbox"
                                                id="product_pedido_<?php echo $producto['id_producto']; ?>"
                                                data-id="<?php echo $producto['id_producto']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($producto['nombre_producto']); ?>"
                                                data-precio="<?php echo $producto['precio']; ?>"
                                                data-stock="<?php echo $producto['stock']; ?>">
                                            <label class="form-check-label" for="product_pedido_<?php echo $producto['id_producto']; ?>">
                                                <?php echo htmlspecialchars($producto['nombre_producto']); ?> ($<?php echo number_format($producto['precio'], 2); ?>) - Stock: <?php echo $producto['stock']; ?>
                                            </label>
                                        </div>
                                        <div class="quantity-controls d-none" id="qty_controls_pedido_<?php echo $producto['id_producto']; ?>">
                                            <button type="button" class="btn btn-sm btn-outline-secondary decrease-qty-pedido" data-id="<?php echo $producto['id_producto']; ?>">-</button>
                                            <input type="number" class="form-control form-control-sm d-inline-block text-center product-qty-pedido"
                                                id="qty_pedido_<?php echo $producto['id_producto']; ?>" value="1" min="1" max="<?php echo $producto['stock']; ?>"
                                                data-id="<?php echo $producto['id_producto']; ?>">
                                            <button type="button" class="btn btn-sm btn-outline-secondary increase-qty-pedido" data-id="<?php echo $producto['id_producto']; ?>">+</button>
                                        </div>
                                    </div>
                                <?php
                                }
                                if ($current_category !== '') {
                                    echo '</div></div></div>'; // Cerrar el último item
                                }
                                ?>
                            </div>
                            <div class="invalid-feedback d-block"><?php echo $productos_err; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 mt-4 text-end">
                        <h4 class="d-flex justify-content-end align-items-center">
                            <span>Total Estimado:</span>
                            <span id="total-estimado-pedido" class="ms-3">$0.00</span>
                        </h4>
                         <div class="invalid-feedback d-block text-start"><?php echo $total_estimado_err; ?></div>
                        <input type="hidden" name="productos_pedido" id="inputProductosPedido">
                        <input type="hidden" name="total_estimado" id="inputTotalEstimadoPedido">
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary w-100" id="btnCrearPedido"><?php echo $edit_mode ? 'Actualizar Pedido' : 'Crear Pedido'; ?></button>
                        <?php if ($edit_mode): ?>
                            <a href="gestion_pedidos.php" class="btn btn-secondary w-100 mt-2">Cancelar Edición</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

       

        <!-- Modal para Selección/Registro de Cliente (igual que antes) -->
        <div class="modal fade" id="clientModal" tabindex="-1" aria-labelledby="clientModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="clientModalLabel">Seleccionar o Registrar Cliente</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <ul class="nav nav-tabs" id="clientTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="search-client-tab" data-bs-toggle="tab" data-bs-target="#search-client" type="button" role="tab" aria-controls="search-client" aria-selected="true"><p class="text-secondary">Buscar Cliente</p></button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="register-client-tab" data-bs-toggle="tab" data-bs-target="#register-client" type="button" role="tab" aria-controls="register-client" aria-selected="false"><p class="text-secondary">Registrar Nuevo </p></button>
                            </li>
                        </ul>
                        <div class="tab-content mt-3">
                            <!-- Pestaña para Buscar Cliente -->
                            <div class="tab-pane fade show active" id="search-client" role="tabpanel" aria-labelledby="search-client-tab">
                                <div class="mb-3">
                                    <label for="clientSearchInput" class="form-label">Buscar por Nombre, Teléfono, Cédula o Email:</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="clientSearchInput" placeholder="Ej: Juan Pérez, 0412xxxxxxx, V-12345678, juan@example.com">
                                        <button type="button" class="btn btn-outline-secondary" id="btnSearchClient">Buscar</button>
                                    </div>
                                </div>
                                <div id="clientSearchResults" class="table-responsive">
                                    <p class="text-muted">Ingresa un término de búsqueda y haz clic en "Buscar".</p>
                                </div>
                            </div>
                            <!-- Pestaña para Registrar Nuevo Cliente -->
                            <div class="tab-pane fade" id="register-client" role="tabpanel" aria-labelledby="register-client-tab">
                                <form id="formRegisterClient">
                                     <div class="mb-3">
                                        <label for="new_cedula" class="form-label">Cédula:</label>
                                        <input type="text" class="form-control" id="new_cedula" name="cedula" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_nombre" class="form-label">Nombre:</label>
                                        <input type="text" class="form-control" id="new_nombre" name="nombre" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_apellido" class="form-label">Apellido:</label>
                                        <input type="text" class="form-control" id="new_apellido" name="apellido">
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_telefono" class="form-label">Teléfono:</label>
                                        <input type="text" class="form-control" id="new_telefono" name="telefono">
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_email" class="form-label">Email (Opcional):</label>
                                        <input type="email" class="form-control" id="new_email" name="email">
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_direccion" class="form-label">Dirección:</label>
                                        <input type="text" class="form-control" id="new_direccion" name="direccion">
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Registrar y Seleccionar Cliente</button>
                                </form>
                                <div id="registerClientMessage" class="mt-3"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal de Éxito al crear pedido -->
        <div class="modal fade" id="createdSuccessModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Pedido creado</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
              </div>
              <div class="modal-body">
                <p id="createdSuccessMessage"><?php echo htmlspecialchars($success_message); ?></p>
              </div>
              <div class="modal-footer">
                <a href="gestion_pedidos.php" class="btn btn-secondary">Crear otro pedido</a>   
                <a href="ayudante_pedidos.php" class="btn btn-primary">Listado de Pedidos</a>
              </div>
            </div>
          </div>
    </main>

    <!-- Bootstrap Bundle con Popper -->
    <script src="../js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script>
    // Mostrar modal de éxito si PHP definió $success_message
    <?php if (!empty($success_message)): ?>
    document.addEventListener('DOMContentLoaded', function(){
        var modalEl = document.getElementById('createdSuccessModal');
        if (modalEl) {
            // actualizar texto (por si se muestra también en alert)
            var msgEl = document.getElementById('createdSuccessMessage');
            if (msgEl) msgEl.textContent = <?php echo json_encode($success_message); ?>;
            var successModal = new bootstrap.Modal(modalEl);
            successModal.show();
        }
    });
    <?php endif; ?>
    </script>
    <script>
        // Variables globales para los IDs de estado Entregado y Cancelado
        const ID_ESTADO_ENTREGADO = <?php echo json_encode($id_estado_entregado_js); ?>;
        const ID_ESTADO_CANCELADO = <?php echo json_encode($id_estado_cancelado_js); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            const productCheckboxes = document.querySelectorAll('.product-pedido-checkbox');
            const totalEstimadoSpan = document.getElementById('total-estimado-pedido');
            const btnCrearPedido = document.getElementById('btnCrearPedido');
            const inputProductosPedido = document.getElementById('inputProductosPedido');
            const inputTotalEstimadoPedido = document.getElementById('inputTotalEstimadoPedido');
            const formCrearPedido = document.getElementById('formCrearPedido');
            const selectedClientIdInput = document.getElementById('selected_client_id');
            const nombreClienteDisplay = document.getElementById('nombre_cliente_display');
            const telefonoClienteDisplay = document.getElementById('telefono_cliente_display');
            const direccionEntregaDisplay = document.getElementById('direccion_entrega_display');
            const notasClienteTextarea = document.getElementById('notas_cliente');

            let selectedProducts = {}; // { id: { nombre, precio, cantidad, stock } }

            // PHP pasa los productos iniciales para el modo edición a través de una variable global
            <?php if ($edit_mode && !empty($edit_order_products)): ?>
                selectedProducts = <?php echo json_encode($edit_order_products); ?>.reduce((obj, item) => {
                    obj[item.id] = item;
                    return obj;
                }, {});
                // Marcar las casillas de verificación y establecer las cantidades para los productos precargados
                Object.values(selectedProducts).forEach(product => {
                    const checkbox = document.getElementById(`product_pedido_${product.id}`);
                    if (checkbox) {
                        checkbox.checked = true;
                        const qtyControls = document.getElementById(`qty_controls_pedido_${product.id}`);
                        const qtyInput = document.getElementById(`qty_pedido_${product.id}`);
                        if (qtyControls && qtyInput) {
                            qtyInput.value = product.cantidad;
                            qtyInput.max = product.stock; // Asegurarse de que el máximo sea el stock actual
                            qtyControls.classList.remove('d-none');
                        }
                    }
                });
            <?php endif; ?>


            // --- Funciones para la gestión de productos en el pedido ---
            function updatePedidoSummary() {
                let total = 0;
                if (Object.keys(selectedProducts).length > 0) {
                    for (const id in selectedProducts) {
                        const product = selectedProducts[id];
                        total += product.precio * product.cantidad;
                    }
                }

                totalEstimadoSpan.textContent = `$${total.toFixed(2)}`;
                inputProductosPedido.value = JSON.stringify(Object.values(selectedProducts).map(p => ({
                    id: p.id,
                    cantidad: p.cantidad,
                    precio: p.precio
                })));
                inputTotalEstimadoPedido.value = total.toFixed(2);
            }

            productCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const productId = this.dataset.id;
                    const productName = this.dataset.nombre;
                    const productPrice = parseFloat(this.dataset.precio);
                    const productStock = parseInt(this.dataset.stock);
                    const qtyControls = document.getElementById(`qty_controls_pedido_${productId}`);
                    const qtyInput = document.getElementById(`qty_pedido_${productId}`);

                    if (this.checked) {
                        selectedProducts[productId] = {
                            id: productId,
                            nombre: productName,
                            precio: productPrice,
                            cantidad: 1, // Cantidad inicial al seleccionar
                            stock: productStock
                        };
                        qtyInput.value = 1; // Restablecer cantidad a 1
                        qtyInput.max = productStock; // Establecer máximo basado en el stock actual
                        qtyControls.classList.remove('d-none');
                    } else {
                        delete selectedProducts[productId];
                        qtyControls.classList.add('d-none');
                    }
                    updatePedidoSummary();
                });
            });

            document.addEventListener('click', function(event) {
                if (event.target.classList.contains('increase-qty-pedido')) {
                    const productId = event.target.dataset.id;
                    const qtyInput = document.getElementById(`qty_pedido_${productId}`);
                    let currentQty = parseInt(qtyInput.value);
                    const maxQty = parseInt(qtyInput.max);

                    if (currentQty < maxQty) {
                        qtyInput.value = currentQty + 1;
                        selectedProducts[productId].cantidad = parseInt(qtyInput.value);
                        updatePedidoSummary();
                    }
                } else if (event.target.classList.contains('decrease-qty-pedido')) {
                    const productId = event.target.dataset.id;
                    const qtyInput = document.getElementById(`qty_pedido_${productId}`);
                    let currentQty = parseInt(qtyInput.value);

                    if (currentQty > 1) {
                        qtyInput.value = currentQty - 1;
                        selectedProducts[productId].cantidad = parseInt(qtyInput.value);
                        updatePedidoSummary();
                    }
                }
            });

            document.addEventListener('input', function(event) {
                if (event.target.classList.contains('product-qty-pedido')) {
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
                        updatePedidoSummary();
                    }
                }
            });

            // --- Funciones para la gestión de clientes en el modal (igual que antes) ---
            const clientSearchInput = document.getElementById('clientSearchInput');
            const btnSearchClient = document.getElementById('btnSearchClient');
            const clientSearchResults = document.getElementById('clientSearchResults');
            const clientModal = new bootstrap.Modal(document.getElementById('clientModal'));

            const formRegisterClient = document.getElementById('formRegisterClient');
            const registerClientMessage = document.getElementById('registerClientMessage');

            function populateClientForm(cliente) {
                selectedClientIdInput.value = cliente.id_cliente;
                nombreClienteDisplay.value = `${cliente.nombre} ${cliente.apellido || ''}`.trim();
                telefonoClienteDisplay.value = cliente.telefono || '';
                direccionEntregaDisplay.value = cliente.direccion || '';
                nombreClienteDisplay.classList.remove('is-invalid');
                document.querySelector('.invalid-feedback.d-block').textContent = '';
                clientModal.hide();
            }

            btnSearchClient.addEventListener('click', function() {
                const query = clientSearchInput.value.trim();
                if (query.length < 3) {
                    clientSearchResults.innerHTML = '<p class="text-danger">Ingresa al menos 3 caracteres para buscar.</p>';
                    return;
                }
                clientSearchResults.innerHTML = '<p>Buscando clientes...</p>';
                fetch(`<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?action=search_client&query=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length > 0) {
                            let resultsHtml = `
                                <table class="table table-striped table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Cédula</th>
                                            <th>Nombre Completo</th>
                                            <th>Teléfono</th>
                                            <th>Email</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                            `;
                            data.forEach(cliente => {
                                resultsHtml += `
                                    <tr>
                                        <td>${cliente.cedula || 'N/A'}</td>
                                        <td>${cliente.nombre} ${cliente.apellido || ''}</td>
                                        <td>${cliente.telefono || 'N/A'}</td>
                                        <td>${cliente.email || 'N/A'}</td>
                                        <td><button type="button" class="btn btn-sm btn-success select-client-btn" data-client='${JSON.stringify(cliente)}'>Seleccionar</button></td>
                                    </tr>
                                `;
                            });
                            resultsHtml += `</tbody></table>`;
                            clientSearchResults.innerHTML = resultsHtml;

                            document.querySelectorAll('.select-client-btn').forEach(button => {
                                button.addEventListener('click', function() {
                                    const clientData = JSON.parse(this.dataset.client);
                                    populateClientForm(clientData);
                                });
                            });

                        } else {
                            clientSearchResults.innerHTML = '<p class="text-info">No se encontraron clientes. Puedes registrar uno nuevo.</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Error al obtener clientes:', error);
                        clientSearchResults.innerHTML = '<p class="text-danger">Error al buscar clientes. Inténtalo de nuevo.</p>';
                    });
            });

            formRegisterClient.addEventListener('submit', function(event) {
                event.preventDefault();
                registerClientMessage.innerHTML = '<div class="alert alert-info">Registrando cliente...</div>';

                const formData = new FormData(this);
                formData.append('action', 'register_client_ajax');

                fetch('<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        registerClientMessage.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                        formRegisterClient.reset();
                        populateClientForm({
                            id_cliente: data.id_cliente,
                            cedula: data.cedula,
                            nombre: data.nombre,
                            apellido: data.apellido,
                            telefono: data.telefono,
                            email: data.email,
                            direccion: data.direccion
                        });
                    } else {
                        registerClientMessage.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error al registrar cliente:', error);
                    registerClientMessage.innerHTML = '<div class="alert alert-danger">Error al registrar el cliente.</div>';
                });
            });

            // Validación al enviar el formulario de pedido principal (igual que antes)
            formCrearPedido.addEventListener('submit', function(event) {
                let isValid = true;

                if (selectedClientIdInput.value.trim() === '' || nombreClienteDisplay.value.trim() === '') {
                    nombreClienteDisplay.classList.add('is-invalid');
                    document.querySelector('.invalid-feedback.d-block').textContent = 'Debe seleccionar un cliente o registrar uno nuevo.';
                    isValid = false;
                } else {
                    nombreClienteDisplay.classList.remove('is-invalid');
                    document.querySelector('.invalid-feedback.d-block').textContent = '';
                }

                if (Object.keys(selectedProducts).length === 0) {
                    document.querySelector('#productosPedidoAccordion + .invalid-feedback').textContent = 'Debe seleccionar al menos un producto para el pedido.';
                    isValid = false;
                } else {
                    document.querySelector('#productosPedidoAccordion + .invalid-feedback').textContent = '';
                }

                if (parseFloat(inputTotalEstimadoPedido.value) <= 0) {
                     document.querySelector('#inputTotalEstimadoPedido + .invalid-feedback').textContent = 'El total estimado del pedido debe ser mayor a cero.';
                     isValid = false;
                } else {
                     document.querySelector('#inputTotalEstimadoPedido + .invalid-feedback').textContent = '';
                }
                
                const estadoPagoSelect = document.getElementById('estado_pago');
                if (!['Abono 50%', 'Pago'].includes(estadoPagoSelect.value)) {
                    estadoPagoSelect.classList.add('is-invalid');
                    isValid = false;
                } else {
                    estadoPagoSelect.classList.remove('is-invalid');
                }


                if (!isValid) {
                    event.preventDefault();
                    const firstInvalid = document.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });

            // --- Lógica para cambiar el estado del pedido (AJAX) ---
            document.querySelectorAll('.status-changer').forEach(selectElement => {
                selectElement.addEventListener('change', function() {
                    const pedidoId = this.dataset.pedidoId;
                    const newEstadoId = parseInt(this.value);
                    const currentStatusId = parseInt(this.dataset.currentStatusId);

                    if (newEstadoId === currentStatusId) {
                        console.log('No hay cambio de estado para el ID de pedido:', pedidoId);
                        return;
                    }

                    const newStatusName = this.options[this.selectedIndex].text;
                    let confirmationMessage = `¿Estás seguro de que quieres cambiar el estado del pedido #${pedidoId} a ${newStatusName}?`;

                  /*  // Mostrar advertencia si se va a Cancelar o Devolver stock
                    if (newEstadoId === ID_ESTADO_CANCELADO || newEstadoId === ID_ESTADO_DEVUELTO) {
                        // Solo advertir si el estado actual no era ya Cancelado/Devuelto
                        if (currentStatusId !== ID_ESTADO_CANCELADO && currentStatusId !== ID_ESTADO_DEVUELTO) {
                            confirmationMessage += " ATENCIÓN: El stock de este pedido será devuelto al inventario.";
                        }
                    } */  //está comentado para probar el cambio de estado por medio del select en funcion javascript

                    const confirmChange = confirm(confirmationMessage);
                    if (!confirmChange) {
                        this.value = currentStatusId;
                        return;
                    }

                    fetch('<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=change_status&pedido_id=${pedidoId}&new_estado_id=${newEstadoId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            const pedidoEstadoDisplay = document.getElementById(`pedido_estado_display_${pedidoId}`);
                            if (pedidoEstadoDisplay) {
                                pedidoEstadoDisplay.textContent = newStatusName;
                            }
                            selectElement.dataset.currentStatusId = newEstadoId;
                            
                            // Si el nuevo estado es "Entregado", "Cancelado" o "Devuelto", remover el pedido de la vista
                            if (newEstadoId === ID_ESTADO_ENTREGADO || newEstadoId === ID_ESTADO_CANCELADO || newEstadoId === <?php echo json_encode($id_estado_devuelto); ?>) {
                                const accordionItem = document.getElementById(`heading_pedido_${pedidoId}`).closest('.accordion-item');
                                if (accordionItem) {
                                    accordionItem.remove();
                                }
                            }

                        } else {
                            alert('Error al cambiar el estado: ' + data.message);
                            selectElement.value = currentStatusId;
                        }
                    })
                    .catch(error => {
                        console.error('Error en la solicitud AJAX:', error);
                        alert('Hubo un error de conexión al intentar cambiar el estado.');
                        selectElement.value = currentStatusId;
                    });
                });
            });

             document.querySelectorAll('.pago-changer').forEach(function(selectElement) {
                selectElement.addEventListener('change', function() {
                    var pedidoId = this.dataset.pedidoId;
                    var nuevoPago = this.value;
                    var currentPago = this.dataset.currentPago;
                    if (nuevoPago === currentPago) return;
                    var confirmChange = confirm('¿Deseas cambiar el estado del pago a "' + nuevoPago + '"?');
                    if (!confirmChange) {
                    this.value = currentPago;
                    return;
                    }
                    fetch('<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=changepago&pedidoid=' + pedidoId + '&nuevo_pago=' + encodeURIComponent(nuevoPago)
                    })
                    .then(response => response.json())
                    .then(data => {
                    if (data.success) {
                        alert(data.message);
                        selectElement.dataset.currentPago = nuevoPago;
                    } else {
                        alert('Error: ' + data.message);
                        selectElement.value = currentPago;
                    }
                    })
                    .catch(() => {
                    alert('Hubo un error de conexión');
                    selectElement.value = currentPago;
                    });
                });
            });



            updatePedidoSummary();
        });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const dateInput = document.getElementById('fecha_entrega_estimada');
        const formCrearPedido = document.getElementById('formCrearPedido');
        if (!dateInput) return;

        const minDate = dateInput.dataset.min || new Date().toISOString().slice(0,10);
        // Establecer atributo min para el control de fecha
        dateInput.setAttribute('min', minDate);

        function showDateError(msg) {
            const errDiv = dateInput.parentElement.querySelector('.invalid-feedback.d-block');
            if (errDiv) errDiv.textContent = msg;
            dateInput.classList.add('is-invalid');
        }

        function clearDateError() {
            const errDiv = dateInput.parentElement.querySelector('.invalid-feedback.d-block');
            if (errDiv) errDiv.textContent = '';
            dateInput.classList.remove('is-invalid');
        }

        dateInput.addEventListener('change', function() {
            if (this.value && this.value < minDate) {
                showDateError('La fecha estimada no puede ser anterior a ' + minDate + '.');
            } else {
                clearDateError();
            }
        });

        if (formCrearPedido) {
            formCrearPedido.addEventListener('submit', function(e) {
                const val = dateInput.value;
                if (val && val < minDate) {
                    e.preventDefault();
                    showDateError('La fecha estimada no puede ser anterior a ' + minDate + '.');
                    dateInput.focus();
                    return false;
                }
            });
        }
    });
    </script>
</body>
</html>
