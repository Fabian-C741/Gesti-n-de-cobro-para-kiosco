<?php
// Script para resetear contraseña del super admin

// Nueva contraseña
$password_nueva = 'Admin123';

// Generar hash
$hash = password_hash($password_nueva, PASSWORD_DEFAULT);

echo "=================================\n";
echo "RESETEAR SUPER ADMIN PASSWORD\n";
echo "=================================\n\n";
echo "Contraseña: $password_nueva\n";
echo "Hash: $hash\n\n";
echo "=================================\n";
echo "EJECUTA ESTE SQL EN PHPMYADMIN:\n";
echo "=================================\n\n";
echo "UPDATE super_admins SET password = '$hash' WHERE username = 'superadmin';\n\n";
echo "=================================\n";
?>
