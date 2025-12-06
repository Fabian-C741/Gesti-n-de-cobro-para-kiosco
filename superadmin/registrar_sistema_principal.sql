-- Registrar el sistema principal como un tenant en el super admin
-- Esto permite gestionar los usuarios de u464516792_produccion desde el panel de super admin

INSERT INTO tenants (
    nombre,
    razon_social,
    dominio,
    email_contacto,
    telefono_contacto,
    plan,
    precio_mensual,
    estado,
    fecha_inicio,
    fecha_expiracion,
    bd_host,
    bd_nombre,
    bd_usuario,
    bd_password,
    activo,
    created_at
) VALUES (
    'Sistema Principal',
    'Gestión Principal',
    'principal',
    'admin@principal.com',
    '',
    'premium',
    0.00,
    'activo',
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 365 DAY),
    'localhost',
    'u464516792_produccion',
    'u464516792_gestion',
    'GestionVentas987#',
    1,
    NOW()
);

-- Verificar el registro
SELECT * FROM tenants WHERE dominio = 'principal';
