<?php
// Generador de hash para contraseñas
// Ejecuta: php generar_password.php

$password = 'TuNuevaContraseñaSegura123!'; // Cambia esto

$hash = password_hash($password, PASSWORD_BCRYPT);

echo "Contraseña: $password\n";
echo "Hash: $hash\n";
echo "\nEjecuta este SQL en phpMyAdmin:\n\n";
echo "UPDATE usuarios SET password = '$hash' WHERE email = 'admin@admin.com';\n";
?>
