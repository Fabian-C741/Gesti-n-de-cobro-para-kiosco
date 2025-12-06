INSTRUCCIONES PARA EJECUTAR SCRIPTS SQL
========================================

Ejecutar estos scripts EN ORDEN en la base de datos de producción:

1. create_puntos_venta_sucursales.sql
   - Crea las tablas sucursales y puntos_venta
   - Inserta datos por defecto

2. add_punto_venta_id.sql
   - Agrega la columna punto_venta_id a usuarios
   - Migra los datos existentes
   - Crea la relación con puntos_venta

IMPORTANTE:
- Ejecutar desde phpMyAdmin en Hostinger
- Hacer backup antes de ejecutar
- Verificar que no haya errores entre cada script
