<?php
// registro.php
require 'php/conexion.php';

// Indicar que la respuesta será JSON (para que JS la entienda)
header('Content-Type: application/json');

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // 1. Recibir datos del formulario
    // Usamos el operador null coalescing (??) para evitar errores si falta algún campo
    $nombreReal = $_POST['Nombre'] ?? ''; // No lo guardamos en BD por ahora, pero lo recibimos
    $usuario    = $_POST['Usuario'] ?? '';
    $correo     = $_POST['Correo'] ?? '';
    $password   = $_POST['Contraseña'] ?? '';
    $genero     = $_POST['Genero'] ?? 'Otro';
    
    // Construir fecha de nacimiento (YYYY-MM-DD)
    $dia = $_POST['dia'] ?? '01';
    $mes = $_POST['mes'] ?? '01';
    $ano = $_POST['año'] ?? '2000';
    $fechaNacimiento = "$ano-$mes-$dia";

    // 2. Validaciones Básicas
    if (empty($usuario) || empty($correo) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Por favor llena todos los campos obligatorios.']);
        exit;
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'El formato del correo no es válido.']);
        exit;
    }

    // 3. Verificar si el usuario o correo ya existen
    $stmtCheck = $pdo->prepare("SELECT Id_Usuario FROM USUARIO WHERE Correo = ? OR Nombre_Usuario = ?");
    $stmtCheck->execute([$correo, $usuario]);
    
    if ($stmtCheck->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'El usuario o el correo ya están registrados.']);
        exit;
    }

    // 4. Encriptar contraseña (¡Seguridad básica indispensable!)
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // 5. Insertar en la Base de Datos
    // Nota: 'Preferencias_Encriptacion' es TRUE por defecto en la BD, así que no hace falta enviarlo.
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
    // En producción no mostrarías el error exacto, pero para desarrollo ayuda
    echo json_encode(['success' => false, 'message' => 'Error de servidor: ' . $e->getMessage()]);
}
?>