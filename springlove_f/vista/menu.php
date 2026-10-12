<nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="<?php echo $_SESSION["tipo_usuario"]; ?>_dashboard.php"> <img src="../img/logospringloveSF2.png" alt="Spring Love" style="height:60px; width:auto; margin-right:4px;">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    
                    <?php if ($_SESSION["tipo_usuario"] == 'admin'): ?>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Herramientas </a>
                        <ul class="dropdown-menu">
                            <li><a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#restoreModal"><i class="bi bi-database-up"></i> Restaurar B.D.</a></li>

                           <!-- aqui puedo agregar mas opciones de herramientas si es necesario 
                           <li>
                                <a class="nav-link" href="estadisticas_ventas.php">Estadísticas de Pedidos</a>
                           </li> -->
                        </ul>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="gestion_usuarios.php">Gestión de Usuarios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" aria-current="page" href="ver_bitacora_rutas.php">Bitácora de Rutas</a>
                    </li>
                     <?php endif; ?>


                    <?php if ($_SESSION["tipo_usuario"] == 'admin' || $_SESSION["tipo_usuario"] == 'asistente'): ?>


                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Gestión de Pedidos </a>
                        <ul class="dropdown-menu">
                            <li><a class="nav-link" href="gestion_pedidos.php">Crear Pedido</a></li>
                            <li><a class="nav-link" href="ayudante_pedidos.php">Listado de Pedidos</a></li>
                            <!--<li><a class="nav-link" href="#">Something else here</a></li>-->
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Reportes</a>
                        <ul class="dropdown-menu">
                            <li><a class="nav-link" href="reportes_pedidos.php">Reportes de Pedidos</a></li>
                            <li><a class="nav-link" href="estadisticas_ventas.php">Estadísticas de Pedidos</a></li>
                        </ul>
                    </li>



                    <li class="nav-item">
                        <a class="nav-link" href="productos.php">Gestión de Productos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gestion_clientes.php">Gestión de Clientes</a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="historial_pedidos.php">Historial de Pedidos</a>
                    </li>
                    <!-- ya lo agruope en el dropdown de reportes
                     <li class="nav-item">
                        <a class="nav-link" href="reportes_pedidos.php">Reportes de Pedidos</a>
                    </li> -->
                    
                    <li class="nav-item">
                        <a class="nav-link" href="gestion_estados.php">Gestión de Estados</a>
                    </li>
                    
                    
                    <li class="nav-item">
                        <a class="nav-link btn btn-danger ms-lg-3" href="logout.php">Cerrar Sesión</a>
                    </li>
                    <?php elseif ($_SESSION["tipo_usuario"] == 'ayudante'): ?>
                         <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="ayudante_pedidos.php">Ver Pedidos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-danger ms-lg-3" href="logout.php">Cerrar Sesión</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>