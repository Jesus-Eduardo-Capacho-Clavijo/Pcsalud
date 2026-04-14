# Despliegue de Pc Salud (gratis)

Este proyecto usa PHP nativo + MySQL, por lo que no se puede desplegar en GitHub Pages (solo estatico).

## Opcion recomendada
- Aplicacion PHP: Render (Web Service) o Railway
- Base de datos MySQL: PlanetScale o Railway MySQL

## 1) Variables de entorno requeridas
Configura estas variables en el proveedor donde despliegues:

- `APP_ENV=production`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_CHARSET=utf8mb4`

Guia local: usa `.env.example` como referencia.

## 2) Base de datos
En la base de datos remota ejecuta:

1. `base_datos.sql` (si es instalacion limpia)
2. Si migras una BD existente, aplica tambien:
   - `alter_premium.sql`
   - `alter_enterprise.sql`
   - `alter_finalizacion.sql`
   - `alter_emails_queue.sql`

## 3) Punto de entrada
El proyecto incluye `index.php` para mejorar compatibilidad con hostings PHP.

## 4) Archivos subidos por usuarios
Las evidencias se guardan en `backend/uploads/`.
En hostings con filesystem efimero, esas imagenes pueden perderse al reiniciar.
Para produccion robusta, migra uploads a un almacenamiento externo (S3/Cloudinary).

## 5) Correo
Actualmente se usa `mail()` con trazabilidad en `emails_salida`.
En muchos hostings cloud `mail()` puede fallar.
Recomendado: integrar SMTP (PHPMailer + Brevo/Mailgun/SendGrid).

## 6) Checklist final
- [ ] Variables de entorno cargadas
- [ ] DB conectada correctamente
- [ ] Login admin funciona
- [ ] Crear mantenimiento genera token
- [ ] WhatsApp abre correctamente
- [ ] Evidencia fotografica sube y se visualiza
- [ ] Presupuesto y aprobacion cliente funcionan
- [ ] Ticket imprime
- [ ] Cola de correos registra estados
