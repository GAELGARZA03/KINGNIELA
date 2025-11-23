<?php
header('Content-Type: application/json');
require 'conexion.php';

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos']);
    exit;
}

try {
    // --- CAMBIO AQUÍ: JOIN con CORONA_ACTIVA y CORONA ---
    $sql = "SELECT u.Id_Usuario, u.Nombre_Usuario, u.Correo, u.Contrasena, u.Avatar, c.Imagen_Url as Corona
            FROM USUARIO u
            LEFT JOIN CORONA_ACTIVA ca ON u.Id_Usuario = ca.Id_Usuario
            LEFT JOIN CORONA c ON ca.Id_Corona = c.Id_Corona
            WHERE u.Correo = ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['Contrasena'])) {
        session_start();
        $_SESSION['user_id'] = $user['Id_Usuario'];
        
        echo json_encode([
            'success' => true, 
            'user' => [
                'id' => $user['Id_Usuario'],
                'nombre' => $user['Nombre_Usuario'],
                'email' => $user['Correo'],
                'avatar' => $user['Avatar'],
                'corona' => $user['Corona'] // Ahora enviamos la corona
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Correo o contraseña incorrectos']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>