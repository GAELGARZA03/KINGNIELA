<?php
header('Content-Type: application/json');
require 'conexion.php'; // Nota: están en la misma carpeta php/

$data = json_decode(file_get_contents('php://input'), true);

$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos']);
    exit;
}

try {
    // Usamos los nombres de columnas de TU base de datos Kingniela
    $stmt = $pdo->prepare("SELECT Id_Usuario, Nombre_Usuario, Correo, Contrasena, Avatar FROM USUARIO WHERE Correo = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['Contrasena'])) {
        // Iniciar sesión PHP (opcional pero recomendado)
        session_start();
        $_SESSION['user_id'] = $user['Id_Usuario'];
        
        echo json_encode([
            'success' => true, 
            'user' => [
                'id' => $user['Id_Usuario'],
                'nombre' => $user['Nombre_Usuario'],
                'email' => $user['Correo'],
                'avatar' => $user['Avatar']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Correo o contraseña incorrectos']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en el login: ' . $e->getMessage()]);
}
?>