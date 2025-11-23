<?php
// php/profile.php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];

// --- OBTENER DATOS (GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT Nombre_Usuario, Fecha_Nacimiento, Genero, Correo, Avatar, Preferencias_Encriptacion FROM USUARIO WHERE Id_Usuario = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo json_encode(['success' => true, 'data' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
    }
    exit;
}

// --- ACTUALIZAR DATOS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibimos los campos (puede venir Nombre pero no lo guardamos en BD si no existe columna, usaremos Usuario)
    $nuevoUsuario = $_POST['Usuario'] ?? '';
    $nuevoCorreo  = $_POST['Correo'] ?? '';
    $fechaNac     = $_POST['FechaNacimiento'] ?? '';
    $genero       = $_POST['Genero'] ?? '';
    $nuevaPass    = $_POST['Contraseña'] ?? '';
    
    // El checkbox: si está marcado viene "on", si no, no viene.
    $encriptacion = (isset($_POST['Preferencias_Encriptacion'])) ? 1 : 0;

    if (empty($nuevoUsuario) || empty($nuevoCorreo)) {
        echo json_encode(['success' => false, 'message' => 'Usuario y Correo son obligatorios']);
        exit;
    }

    try {
        // 1. Manejo de Archivo (Foto)
        $avatarSqlPart = "";
        $params = [$nuevoUsuario, $nuevoCorreo, $fechaNac, $genero, $encriptacion];

        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/avatars/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $fileName = 'u' . $userId . '_' . time() . '_' . basename($_FILES['foto']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $targetPath)) {
                $webPath = 'uploads/avatars/' . $fileName; // Ruta para guardar en BD
                $avatarSqlPart = ", Avatar = ?";
                $params[] = $webPath; // Añadimos la ruta a los parámetros
            }
        }

        // 2. Manejo de Contraseña
        $passSqlPart = "";
        if (!empty($nuevaPass)) {
            $passHash = password_hash($nuevaPass, PASSWORD_DEFAULT);
            $passSqlPart = ", Contrasena = ?";
            $params[] = $passHash;
        }

        // 3. Añadir ID al final para el WHERE
        $params[] = $userId;

        // Construir Query Dinámico
        $sql = "UPDATE USUARIO SET 
                Nombre_Usuario = ?, 
                Correo = ?, 
                Fecha_Nacimiento = ?, 
                Genero = ?, 
                Preferencias_Encriptacion = ? 
                $avatarSqlPart 
                $passSqlPart 
                WHERE Id_Usuario = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Perfil actualizado correctamente']);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()]);
    }
}

// --- OBTENER DATOS (GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // CAMBIO: Añadimos el JOIN para traer la corona
        $sql = "SELECT u.Nombre_Usuario, u.Fecha_Nacimiento, u.Genero, u.Correo, u.Avatar, u.Preferencias_Encriptacion, c.Imagen_Url as Corona
                FROM USUARIO u
                LEFT JOIN CORONA_ACTIVA ca ON u.Id_Usuario = ca.Id_Usuario
                LEFT JOIN CORONA c ON ca.Id_Corona = c.Id_Corona
                WHERE u.Id_Usuario = ?";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo json_encode(['success' => true, 'data' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
    }
    exit;
}
?>