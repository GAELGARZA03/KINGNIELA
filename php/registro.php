<?php
// php/register.php (o registro.php, asegúrate que el nombre coincida con lo que tienes)
header('Content-Type: application/json');
require 'conexion.php'; // Asumiendo que conexion.php está en la misma carpeta 'php/'

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // 1. Recibir datos
    $usuario    = $_POST['Usuario'] ?? '';
    $correo     = $_POST['Correo'] ?? '';
    $password   = $_POST['Contraseña'] ?? '';
    $genero     = $_POST['Genero'] ?? 'Otro';
    
    // CAMBIO: Recibir la fecha directamente del input type="date"
    // El formato que envía el navegador es YYYY-MM-DD, justo lo que MySQL necesita.
    $fechaNacimiento = $_POST['FechaNacimiento'] ?? null;

    // 2. Validaciones
    if (empty($usuario) || empty($correo) || empty($password) || empty($fechaNacimiento)) {
        echo json_encode(['success' => false, 'message' => 'Por favor llena todos los campos obligatorios.']);
        exit;
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'El formato del correo no es válido.']);
        exit;
    }

    // 3. Verificar duplicados
    $stmtCheck = $pdo->prepare("SELECT Id_Usuario FROM USUARIO WHERE Correo = ? OR Nombre_Usuario = ?");
    $stmtCheck->execute([$correo, $usuario]);
    
    if ($stmtCheck->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'El usuario o el correo ya están registrados.']);
        exit;
    }

    // 4. Encriptar contraseña
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // 5. Insertar
    $sql = "INSERT INTO USUARIO (Nombre_Usuario, Fecha_Nacimiento, Genero, Correo, Contrasena) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$usuario, $fechaNacimiento, $genero, $correo, $passwordHash]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => '¡Registro exitoso! Ahora puedes iniciar sesión.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar en la base de datos.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de servidor: ' . $e->getMessage()]);
}
?>