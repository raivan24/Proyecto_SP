-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: florerias_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `bitacora_rutas`
--

DROP TABLE IF EXISTS `bitacora_rutas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bitacora_rutas` (
  `id_bitacora` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `seccion` varchar(150) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `metodo_peticion` varchar(10) NOT NULL,
  `direccion_ip` varchar(45) NOT NULL,
  `navegador_dispositivo` varchar(500) DEFAULT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_bitacora`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `bitacora_rutas_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bitacora_rutas`
--

LOCK TABLES `bitacora_rutas` WRITE;
/*!40000 ALTER TABLE `bitacora_rutas` DISABLE KEYS */;
INSERT INTO `bitacora_rutas` VALUES (1,1,'Dashboard de Administrador','admin_dashboard.php','GET','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-14 04:49:49'),(2,1,'Gestión de Productos','productos.php','GET','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-14 04:57:27'),(3,1,'Gestión de Clientes','gestion_clientes.php','GET','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-14 04:57:28'),(4,1,'Historial de Pedidos','historial_pedidos.php','GET','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-14 04:57:29'),(5,1,'Reportes de Pedidos','reportes_pedidos.php','GET','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-14 04:57:29'),(6,1,'Gestión de Estados','gestion_estados.php','GET','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-14 04:57:30'),(7,1,'Visualización de Bitácora de Rutas','ver_bitacora_rutas.php','GET','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-14 04:57:32'),(8,1,'Dashboard de Administrador','admin_dashboard.php','GET','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-14 04:57:43');
/*!40000 ALTER TABLE `bitacora_rutas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categoria`
--

DROP TABLE IF EXISTS `categoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categoria` (
  `id_categoria` int(4) NOT NULL AUTO_INCREMENT,
  `nombre_categoria` varchar(25) NOT NULL,
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categoria`
--

LOCK TABLES `categoria` WRITE;
/*!40000 ALTER TABLE `categoria` DISABLE KEYS */;
INSERT INTO `categoria` VALUES (1,'Flores'),(2,'Bases'),(3,'Globos'),(4,'Golosinas'),(5,'Peluches'),(6,'Lazos'),(7,'Bebidas'),(8,'Desayunos'),(9,'Postres'),(10,'Envoltura'),(11,'Cintas');
/*!40000 ALTER TABLE `categoria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clientes`
--

DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL AUTO_INCREMENT,
  `cedula` varchar(25) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `cedula` (`cedula`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clientes`
--

LOCK TABLES `clientes` WRITE;
/*!40000 ALTER TABLE `clientes` DISABLE KEYS */;
INSERT INTO `clientes` VALUES (1,'0','Maria','Perez','02712210000','mariaperez@flores.com','Ejido Urb . San Rafael calle 1 casa 7','2025-08-15 21:08:45'),(2,'1','Jose','Leon','04220000000','joseleon@casa.com','ejido aguas calientes','2025-08-16 23:37:36'),(3,'12333000','Rosa','Rojas','0221123123','rosarojas@rrss.com','Ejido Centenario','2025-11-04 19:15:48'),(4,'2456123','Ramon','Cañas','04120123245',NULL,'Av Fernandez Peña, Ejido','2026-06-08 15:34:59');
/*!40000 ALTER TABLE `clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_pedido`
--

DROP TABLE IF EXISTS `detalle_pedido`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_pedido` (
  `id_detalle_pedido` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario_al_momento_pedido` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_detalle_pedido`),
  KEY `id_pedido` (`id_pedido`),
  KEY `id_producto` (`id_producto`),
  CONSTRAINT `detalle_pedido_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE,
  CONSTRAINT `detalle_pedido_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`)
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_pedido`
--

LOCK TABLES `detalle_pedido` WRITE;
/*!40000 ALTER TABLE `detalle_pedido` DISABLE KEYS */;
INSERT INTO `detalle_pedido` VALUES (1,1,55,1,1.00),(2,1,56,2,1.00),(3,1,60,1,0.80),(7,3,55,1,1.00),(8,3,56,1,1.00),(9,3,60,1,0.80),(16,6,55,1,1.00),(17,6,56,1,1.00),(18,6,60,1,0.80),(19,7,55,1,1.00),(20,7,56,1,1.00),(21,8,60,1,0.80),(22,9,55,1,1.00),(23,9,56,1,1.00),(24,9,60,1,0.80),(25,10,55,1,1.00),(26,11,56,1,1.00),(27,12,55,1,1.00),(29,14,60,1,0.80),(30,15,56,1,1.00),(31,21,55,1,1.00),(32,21,60,1,0.80),(34,22,55,1,1.00),(35,23,55,1,1.00),(36,23,56,2,1.00),(37,23,60,1,0.80),(38,24,55,1,1.00),(39,24,56,5,1.00),(40,24,60,1,0.80),(41,25,55,1,1.00),(42,25,60,1,0.80),(43,26,55,1,1.00),(44,26,60,1,0.80),(45,27,55,1,1.00),(46,28,55,1,1.00),(47,29,60,1,0.80),(49,31,55,1,1.00),(51,33,55,1,1.00),(52,32,55,1,1.00),(53,32,56,1,1.00),(54,35,55,1,1.00),(55,35,56,3,1.00),(59,39,55,1,1.00),(60,39,56,5,1.00),(61,39,60,1,0.80),(62,39,61,7,1.00),(63,40,55,1,1.00),(64,40,56,2,1.00),(65,40,61,3,1.00),(66,40,62,1,0.50),(67,41,64,1,3.00),(68,42,55,1,1.00),(69,42,61,3,1.00),(70,43,55,1,1.00),(71,43,64,1,3.00),(72,44,56,1,1.00),(73,44,62,1,0.50),(74,45,56,2,1.00),(75,45,62,2,0.50),(77,47,56,1,1.00),(83,49,61,2,1.00),(84,48,61,1,1.00),(85,48,62,1,0.50),(86,46,55,1,1.00),(87,46,61,1,1.00);
/*!40000 ALTER TABLE `detalle_pedido` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estados`
--

DROP TABLE IF EXISTS `estados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estados` (
  `id_estado` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_estado` varchar(100) NOT NULL,
  PRIMARY KEY (`id_estado`),
  UNIQUE KEY `nombre_estado` (`nombre_estado`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estados`
--

LOCK TABLES `estados` WRITE;
/*!40000 ALTER TABLE `estados` DISABLE KEYS */;
INSERT INTO `estados` VALUES (7,'Cancelado'),(6,'Devuelto'),(4,'En Camino'),(2,'En Proceso'),(5,'Entregado'),(3,'Listo para Recoger'),(1,'Pendiente');
/*!40000 ALTER TABLE `estados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historial_estado_pedido`
--

DROP TABLE IF EXISTS `historial_estado_pedido`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historial_estado_pedido` (
  `id_historial` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_estado_anterior` int(11) DEFAULT NULL,
  `id_estado_nuevo` int(11) NOT NULL,
  `id_usuario_cambio` int(11) NOT NULL,
  `fecha_cambio` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_historial`),
  KEY `id_pedido` (`id_pedido`),
  KEY `id_estado_anterior` (`id_estado_anterior`),
  KEY `id_estado_nuevo` (`id_estado_nuevo`),
  KEY `id_usuario_cambio` (`id_usuario_cambio`),
  CONSTRAINT `historial_estado_pedido_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE,
  CONSTRAINT `historial_estado_pedido_ibfk_2` FOREIGN KEY (`id_estado_anterior`) REFERENCES `estados` (`id_estado`),
  CONSTRAINT `historial_estado_pedido_ibfk_3` FOREIGN KEY (`id_estado_nuevo`) REFERENCES `estados` (`id_estado`),
  CONSTRAINT `historial_estado_pedido_ibfk_4` FOREIGN KEY (`id_usuario_cambio`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=139 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historial_estado_pedido`
--

LOCK TABLES `historial_estado_pedido` WRITE;
/*!40000 ALTER TABLE `historial_estado_pedido` DISABLE KEYS */;
INSERT INTO `historial_estado_pedido` VALUES (1,1,1,2,1,'2025-08-16 00:24:14'),(2,1,2,4,1,'2025-08-16 00:27:57'),(3,6,NULL,1,1,'2025-08-16 00:55:24'),(4,3,1,5,1,'2025-08-16 00:56:50'),(5,7,NULL,1,1,'2025-08-16 01:15:22'),(6,8,NULL,1,1,'2025-08-16 01:22:24'),(7,8,1,2,3,'2025-08-16 01:25:22'),(8,7,1,7,3,'2025-08-16 01:25:36'),(9,6,1,2,3,'2025-08-16 01:25:49'),(10,9,NULL,1,1,'2025-08-16 01:32:12'),(11,9,1,2,1,'2025-08-16 01:43:20'),(12,9,2,4,1,'2025-08-16 01:48:06'),(13,9,4,5,1,'2025-08-16 01:57:05'),(14,10,NULL,1,1,'2025-08-16 02:04:00'),(15,10,1,2,3,'2025-08-16 02:04:27'),(16,11,NULL,1,1,'2025-08-16 02:08:01'),(17,12,NULL,1,1,'2025-08-16 02:25:55'),(18,12,1,2,3,'2025-08-16 02:26:25'),(19,1,4,5,1,'2025-08-16 20:56:57'),(20,6,2,5,3,'2025-08-16 21:37:22'),(21,8,2,3,3,'2025-08-16 21:37:55'),(22,11,1,2,3,'2025-08-16 21:38:21'),(23,8,3,5,1,'2025-08-16 22:25:18'),(24,10,2,4,1,'2025-08-16 22:25:51'),(25,10,4,3,1,'2025-08-16 22:27:36'),(26,10,3,5,3,'2025-08-16 22:42:37'),(27,11,2,3,1,'2025-08-16 22:54:45'),(28,11,3,5,3,'2025-08-16 22:55:07'),(29,12,2,3,1,'2025-08-16 23:03:45'),(30,11,5,3,3,'2025-08-16 23:07:42'),(31,11,3,5,3,'2025-08-16 23:08:10'),(32,11,5,3,3,'2025-08-16 23:08:19'),(33,11,3,5,3,'2025-08-16 23:08:33'),(34,12,3,5,3,'2025-08-16 23:09:51'),(35,12,5,3,3,'2025-08-16 23:10:33'),(36,12,3,5,1,'2025-08-16 23:10:50'),(37,12,5,7,1,'2025-08-16 23:11:00'),(38,12,7,1,1,'2025-08-16 23:11:15'),(39,11,5,2,1,'2025-08-16 23:11:23'),(40,11,2,5,1,'2025-08-16 23:11:39'),(41,12,1,5,3,'2025-08-16 23:12:59'),(42,12,5,3,3,'2025-08-16 23:25:21'),(43,12,3,5,1,'2025-08-16 23:26:26'),(44,12,5,2,1,'2025-08-16 23:30:53'),(45,12,2,7,1,'2025-08-16 23:32:45'),(49,14,NULL,1,1,'2025-08-16 23:37:57'),(50,14,1,2,1,'2025-08-16 23:43:59'),(51,15,NULL,1,1,'2025-08-16 23:54:30'),(57,21,NULL,1,1,'2025-08-17 00:16:40'),(58,22,NULL,1,1,'2025-08-17 00:38:08'),(59,22,1,2,1,'2025-09-22 09:19:26'),(60,21,1,2,1,'2025-09-22 09:20:12'),(61,23,NULL,1,1,'2025-10-27 21:45:04'),(62,24,NULL,1,1,'2025-10-27 21:54:09'),(63,25,NULL,1,1,'2025-10-27 22:48:23'),(64,26,NULL,1,1,'2025-10-27 22:50:09'),(65,27,NULL,1,1,'2025-10-27 23:00:55'),(66,28,NULL,1,1,'2025-10-27 23:02:17'),(67,29,NULL,1,1,'2025-10-27 23:05:03'),(69,31,NULL,1,1,'2025-10-27 23:11:21'),(70,32,NULL,1,1,'2025-10-27 23:12:07'),(71,33,NULL,1,1,'2025-10-27 23:16:14'),(73,35,NULL,1,1,'2025-10-27 23:34:07'),(77,35,1,2,3,'2025-11-03 13:49:17'),(78,35,2,4,1,'2025-11-03 15:17:44'),(79,35,4,1,1,'2025-11-03 15:18:01'),(80,35,1,2,1,'2025-11-03 15:20:52'),(81,35,2,6,1,'2025-11-03 15:21:02'),(82,33,1,6,3,'2025-11-03 15:21:47'),(83,32,1,2,3,'2025-11-03 16:12:13'),(84,25,1,2,1,'2025-11-03 16:14:07'),(85,25,2,6,1,'2025-11-03 16:15:43'),(86,29,1,2,1,'2025-11-03 16:22:39'),(87,31,1,3,1,'2025-11-03 16:24:40'),(88,21,2,4,1,'2025-11-03 16:40:02'),(89,21,4,3,1,'2025-11-03 16:43:30'),(90,25,6,3,3,'2025-11-03 16:44:49'),(91,24,1,2,3,'2025-11-03 19:52:29'),(92,1,2,5,3,'2025-11-03 20:08:56'),(93,32,2,5,3,'2025-11-03 21:38:54'),(94,31,3,4,1,'2025-11-03 22:32:29'),(95,14,2,5,1,'2025-11-03 23:02:57'),(96,15,1,7,1,'2025-11-03 23:03:23'),(97,21,3,5,1,'2025-11-03 23:03:58'),(98,31,4,5,1,'2025-11-04 01:13:46'),(99,28,1,2,1,'2025-11-04 18:53:47'),(100,39,NULL,1,1,'2025-11-04 19:33:44'),(101,39,1,2,1,'2025-11-04 19:34:42'),(102,39,2,3,1,'2025-11-04 19:34:56'),(103,29,2,4,1,'2025-11-04 19:35:21'),(104,39,3,5,3,'2025-11-04 19:40:41'),(105,29,4,3,2,'2025-11-05 02:16:36'),(106,40,NULL,1,1,'2025-11-05 08:54:16'),(107,40,1,2,1,'2025-11-05 08:55:09'),(108,40,2,3,2,'2025-11-05 08:57:24'),(109,40,3,5,3,'2025-11-05 09:00:25'),(110,29,3,4,2,'2025-11-27 08:44:19'),(111,26,1,5,1,'2025-12-07 18:35:19'),(112,26,5,2,1,'2025-12-07 18:35:37'),(113,25,3,5,1,'2025-12-07 21:29:24'),(114,26,2,5,3,'2025-12-07 22:36:34'),(115,29,4,5,1,'2025-12-07 22:52:38'),(116,29,5,2,1,'2025-12-07 22:52:50'),(117,22,2,3,3,'2025-12-07 23:03:45'),(118,29,2,5,3,'2025-12-07 23:12:40'),(119,41,NULL,1,1,'2025-12-08 01:06:10'),(120,41,1,2,3,'2025-12-08 01:24:47'),(121,42,NULL,1,1,'2026-01-28 20:56:59'),(122,42,1,2,1,'2026-01-28 21:26:47'),(123,42,2,3,1,'2026-01-28 21:30:39'),(124,43,NULL,1,2,'2026-01-28 22:14:25'),(125,44,NULL,1,2,'2026-01-28 23:55:19'),(126,44,1,2,2,'2026-01-28 23:55:44'),(127,22,3,5,1,'2026-03-08 14:59:20'),(128,45,NULL,1,1,'2026-04-23 09:56:44'),(129,44,2,5,3,'2026-04-23 10:04:11'),(130,46,NULL,1,1,'2026-04-23 10:34:26'),(131,47,NULL,1,1,'2026-04-23 11:03:03'),(132,48,NULL,1,1,'2026-04-23 11:04:32'),(133,49,NULL,1,1,'2024-04-28 11:32:08'),(134,49,1,4,2,'2026-06-08 15:46:58'),(135,48,1,2,2,'2026-06-08 20:59:14'),(136,48,2,3,2,'2026-06-08 21:30:00'),(137,47,1,2,2,'2026-06-08 21:30:42'),(138,43,1,2,3,'2026-06-08 22:06:47');
/*!40000 ALTER TABLE `historial_estado_pedido` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pedidos`
--

DROP TABLE IF EXISTS `pedidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pedidos` (
  `id_pedido` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `id_usuario_creador` int(11) NOT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_entrega_estimada` date DEFAULT NULL,
  `id_estado` int(11) NOT NULL,
  `nombre_cliente` varchar(255) NOT NULL,
  `telefono_cliente` varchar(20) DEFAULT NULL,
  `direccion_entrega` varchar(255) DEFAULT NULL,
  `notas_cliente` text DEFAULT NULL,
  `total_estimado` decimal(10,2) DEFAULT NULL,
  `estado_pago` varchar(10) NOT NULL,
  PRIMARY KEY (`id_pedido`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_usuario_creador` (`id_usuario_creador`),
  KEY `id_estado` (`id_estado`),
  CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`id_usuario_creador`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `pedidos_ibfk_3` FOREIGN KEY (`id_estado`) REFERENCES `estados` (`id_estado`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pedidos`
--

LOCK TABLES `pedidos` WRITE;
/*!40000 ALTER TABLE `pedidos` DISABLE KEYS */;
INSERT INTO `pedidos` VALUES (1,1,1,'2025-08-15 23:08:53','2025-08-16',5,'Maria Perez','02712210000','Merida AV 3 Calle 26 Edificio Don Molesto Apto 34','Para: Jose Perez. Telf: 041200022211.  Dedicatoria: Feliz Cumpleaños Hermano.',3.80,'Abono 50%'),(3,1,1,'2025-08-15 23:21:34','2025-08-18',5,'Maria Perez','02712210000','Ejido Urb . San Rafael calle 1 casa 7','',2.80,'Abono 50%'),(6,1,1,'2025-08-16 00:55:24','2025-08-18',5,'Maria Perez','02712210000','Ejido Urb . San Rafael calle 1 casa 7','',2.80,'Abono 50%'),(7,1,1,'2025-08-16 01:15:22','2025-08-15',7,'Maria Perez','02712210000','Ejido Urb . San Rafael calle 1 casa 7','',2.00,'Abono 50%'),(8,1,1,'2025-08-16 01:22:24','2025-08-18',5,'Maria Perez','02712210000','Ejido Urb . San Rafael calle 1 casa 7','',0.80,'Abono 50%'),(9,1,1,'2025-08-16 01:32:12','2025-08-18',5,'Maria Perez','02712210000','Ejido Urb . San Rafael calle 1 casa 7','',2.80,'Abono 50%'),(10,1,1,'2025-08-16 02:04:00','2025-08-16',5,'Maria Perez','02712210000','Ejido Urb . San Rafael calle 1 casa 7','',1.00,'Abono 50%'),(11,1,1,'2025-08-16 02:08:01','2025-08-20',5,'Maria Perez','02712210000','Ejido Urb . San Rafael calle 1 casa 7','',1.00,'Abono 50%'),(12,1,1,'2025-08-16 02:25:55','2025-08-20',7,'Maria Perez','02712210000','Ejido Urb . San Rafael calle 1 casa 7','',1.00,'Abono 50%'),(14,2,1,'2025-08-16 23:37:57','2025-08-18',5,'Jose Leon','04220000000','ejido aguas calientes','',0.80,'Abono 50%'),(15,1,1,'2025-08-16 23:54:30','2025-08-20',7,'Maria Perez','02712210000','Ejido Urb . San Rafael calle 1 casa 7','',1.00,'Abono 50%'),(21,2,1,'2025-08-17 00:16:40','2025-08-21',5,'Jose Leon','04220000000','ejido aguas calientes','',1.80,'Abono 50%'),(22,2,1,'2025-08-17 00:38:08','2025-08-21',5,'Jose Leon','04220000000','ejido aguas calientes','',1.00,'Pago'),(23,2,1,'2025-10-27 21:45:04','2025-10-28',1,'Jose Leon','04220000000','ejido aguas calie','0',3.80,'Pago'),(24,1,1,'2025-10-27 21:54:09','2025-10-29',2,'Maria Perez','02712210000','Cc ejido local 2','0',6.80,'Abono 50%'),(25,1,1,'2025-10-27 22:48:23','2025-10-30',5,'Maria Perez','02712210000','Ejido Urb . San Rafael calle 1 casa 7','0',1.80,'Pago'),(26,2,1,'2025-10-27 22:50:09','2025-10-28',5,'Jose Leon','04220000000','ejido aguas calientes','0',1.80,'Pago'),(27,1,1,'2025-10-27 23:00:55','2025-10-30',1,'Maria Perez','02712210000','Ejido Urb . San Rafael calle 1 casa 7','0',1.00,'Abono 50%'),(28,2,1,'2025-10-27 23:02:17','2025-10-31',2,'Jose Leon','04220000000','ejido aguas calientes','prueba nueva de carga de notas',1.00,'Pago'),(29,1,1,'2025-10-27 23:05:03','2025-10-30',5,'Maria Perez','02712210000','Ejido Urb . San Rafael calle 1 casa 7','para mi amor',0.80,'Pago'),(31,1,1,'2025-10-27 23:11:21','2025-10-31',5,'Maria Perez','02712210000','Ejido Urb . San Rafael calle 1 casa 7','cuentas pagas',1.00,'Pago'),(32,2,1,'2025-10-27 23:12:07','2025-10-29',5,'Jose Leon','04220000000','ejido aguas calientes','gfggg',2.00,'Abono 50%'),(33,1,1,'2025-10-27 23:16:14','2025-10-31',6,'Maria Perez','02712210000','Ejido Urb . San Rafael calle 1 casa 7','vbbbbb',1.00,'Abono 50%'),(35,2,1,'2025-10-27 23:34:07','2025-10-31',6,'Jose Leon','04220000000','ejido aguas calientes','hola',4.00,'Abono 50%'),(39,2,1,'2025-11-04 19:33:44','2025-11-04',5,'Jose Leon','04220000000','ejido aguas','',13.80,'Pago'),(40,1,1,'2025-11-05 08:54:16','2025-11-06',5,'Maria Perez','02712210000','Calle Andres Bello','',6.50,'Pago'),(41,2,1,'2025-12-08 01:06:10','2025-12-07',2,'Jose Leon','04220000000','ejido aguas calientes','bbbb',3.00,'Abono 50%'),(42,2,1,'2026-01-28 20:56:59','2026-01-27',3,'Jose Leon','04220000000','ejido aguas calientes calle 2','',4.00,'Abono 50%'),(43,1,2,'2026-01-28 22:14:25','2026-01-29',2,'Maria Perez','02712210000','Ejido Urb . San Rafael calle 1 casa 7','',4.00,'Pago'),(44,2,2,'2026-01-28 23:55:19','2026-01-29',5,'Jose Leon','04220000000','ejido aguas calientes','Para mi querida',1.50,'Pago'),(45,3,1,'2026-04-23 09:56:44','2026-04-23',1,'Rosa Rojas','0221123123','Ejido Centenario','prueba',3.00,'Abono 50%'),(46,2,1,'2026-04-23 10:34:26','2026-04-29',1,'Jose Leon','04220000000','Hacienda El Pilar','',2.00,'Abono 50%'),(47,3,1,'2026-04-23 11:03:03','2026-04-24',2,'Rosa Rojas','0221123123','Ejido montalban','',1.00,'Abono 50%'),(48,3,1,'2026-04-23 11:04:32','2026-04-23',3,'Rosa Rojas','0221123123','Lagunillas','',1.50,'Abono 50%'),(49,3,1,'2026-04-23 12:04:32','2026-04-28',4,'Rosa Rojas','0221123123','Ejido Centenario','',2.00,'Abono 50%');
/*!40000 ALTER TABLE `pedidos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productos`
--

DROP TABLE IF EXISTS `productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `productos` (
  `id_producto` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_producto` varchar(150) NOT NULL,
  `categoria` varchar(25) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `imagen_url` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_producto`),
  UNIQUE KEY `nombre_producto` (`nombre_producto`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productos`
--

LOCK TABLES `productos` WRITE;
/*!40000 ALTER TABLE `productos` DISABLE KEYS */;
INSERT INTO `productos` VALUES (55,'cesta tejida','Bases',1.00,0,NULL),(56,'Rosas Rojas','Flores',1.00,1,NULL),(60,'globo forma de estrella','Globos',0.80,8,''),(61,'Rosas rosadas','Flores',1.00,1,NULL),(62,'Globo Azul','Globos',0.50,7,NULL),(64,'Peluche de Oso Navideño Pequeño','Peluches',3.00,2,NULL);
/*!40000 ALTER TABLE `productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `tipo_usuario` enum('admin','asistente','ayudante') NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'Administrador Principal','admin@florerias.com','$2y$10$aJu9bRgPd7miKZi4a/m7HODLyRUBu6zzm9aTsdmBR/eJH65lxAr1C','admin','2025-07-31 21:32:24'),(2,'Maria Paz','mariap@florerias.com','$2y$10$P9NfHn1rAibeKgsRqL8h3uzIvhNX/QMVpLZyJLc24RangWDyu6GEa','asistente','2025-08-15 02:29:18'),(3,'Pedro Lopez','pedrol@nti.com','$2y$10$Gw86c7dDihjkGoFZQFYKyeMdCfVQ/fWzunBhb6Vy.VJo.oo.zJhyC','ayudante','2025-08-16 05:12:01');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-14  0:57:51
