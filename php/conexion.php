<?php
$host = 'localhost';
$dbname = 'kingniela';
$username = 'root'; 
$password = ''; //Aqui borra la contraseña xampp no necesita

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>