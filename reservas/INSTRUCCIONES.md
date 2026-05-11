# 🛺 TukTuk Norris — Instrucciones de instalación en Plesk

## Estructura de archivos

```
tu-dominio.com/
├── index.html
├── about.html
├── services.html
├── blog.html
├── blog-single.html
├── contact.html
├── reservar.html          ← Formulario de reservas
├── assets/                ← CSS, JS, imágenes
└── reservas/              ← Backend PHP (esta carpeta)
    ├── config.php         ← ⚠️ Editar con tus credenciales
    ├── reservar.php       ← API que guarda reservas en MySQL
    ├── admin.php          ← Panel de administración
    ├── setup.sql          ← Script SQL de instalación
    ├── .htaccess          ← Protege config.php
    └── INSTRUCCIONES.md   ← Este archivo
```

---

## PASO 1 — Crear la base de datos en Plesk

1. Entra a tu **panel Plesk**
2. Ve a **Bases de datos** (en la sección de tu dominio)
3. Haz clic en **Añadir base de datos**
4. Rellena:
   - **Nombre de la base de datos:** `tuktuk_norris`
   - **Usuario:** `tuktuk_user` (o el nombre que prefieras)
   - **Contraseña:** elige una contraseña segura y **anótala**
5. Haz clic en **Aceptar**

---

## PASO 2 — Importar el esquema SQL

1. En Plesk, junto a la base de datos recién creada, haz clic en **phpMyAdmin**
2. En phpMyAdmin, selecciona la base de datos `tuktuk_norris` en el panel izquierdo
3. Haz clic en la pestaña **Importar**
4. Selecciona el archivo `reservas/setup.sql`
5. Haz clic en **Continuar** (o **Go**)
6. Deberías ver el mensaje: *"Importación ejecutada correctamente"*

---

## PASO 3 — Editar config.php con tus credenciales

Abre el archivo `reservas/config.php` y edita estas líneas:

```php
define('DB_HOST',    'localhost');          // Normalmente localhost en Plesk
define('DB_NAME',    'tuktuk_norris');      // El nombre de la BD que creaste
define('DB_USER',    'tuktuk_user');        // El usuario MySQL que creaste
define('DB_PASS',    'TU_CONTRASEÑA_AQUI'); // ← CAMBIA ESTO por tu contraseña real

define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'tuktuk2024');         // ← CAMBIA esto por una contraseña segura
```

> ⚠️ **Importante:** El archivo `config.php` está protegido por `.htaccess` para que nadie pueda verlo desde el navegador, pero igualmente elige una contraseña de admin segura.

---

## PASO 4 — Subir todos los archivos al servidor

### Opción A — Administrador de archivos de Plesk
1. En Plesk ve a **Administrador de archivos**
2. Navega a `httpdocs/` (o la carpeta raíz de tu dominio)
3. Sube todos los archivos del proyecto manteniendo la estructura de carpetas

### Opción B — FTP/SFTP
1. Usa FileZilla u otro cliente FTP
2. Credenciales FTP en Plesk: **Sitios web y dominios → Acceso FTP**
3. Sube todo a la carpeta `httpdocs/` o `public_html/`

### Opción C — Git en Plesk (recomendado)
1. En Plesk ve a **Git**
2. Conecta el repositorio GitHub: `https://github.com/ebravounda/turismo`
3. Rama: `claude/upload-html-github-PxIAD`
4. Directorio de despliegue: `httpdocs/`
5. Haz clic en **Desplegar**

---

## PASO 5 — Verificar la instalación

1. **Página web:** `https://tu-dominio.com/` → debe cargar la web de TukTuk Norris
2. **Formulario de reservas:** `https://tu-dominio.com/reservar.html` → rellena y envía una reserva de prueba
3. **Panel admin:** `https://tu-dominio.com/reservas/admin.php`
   - Usuario: `admin`
   - Contraseña: la que pusiste en `config.php`
4. Comprueba que la reserva de prueba aparece en el panel

---

## PASO 6 — Configurar PHP en Plesk (si hay errores)

En Plesk ve a **PHP Settings** de tu dominio y asegúrate de que:
- **Versión PHP:** 7.4 o superior (recomendado 8.1+)
- **Extensiones activas:** `pdo_mysql`, `mbstring`, `json`

---

## Estructura de la base de datos

La tabla `reservas` tiene estos campos:

| Campo       | Tipo                              | Descripción                        |
|-------------|-----------------------------------|------------------------------------|
| `id`        | VARCHAR(20)                       | ID único: `RES-A1B2C3D4`           |
| `nombre`    | VARCHAR(100)                      | Nombre del cliente                 |
| `email`     | VARCHAR(150)                      | Correo electrónico                 |
| `telefono`  | VARCHAR(30)                       | Teléfono                           |
| `fecha`     | DATE                              | Fecha del tour                     |
| `hora`      | TIME                              | Hora del tour                      |
| `personas`  | TINYINT                           | Número de personas (1–20)          |
| `tipo_tour` | VARCHAR(100)                      | Nombre del tour                    |
| `idioma`    | VARCHAR(30)                       | Idioma preferido del cliente       |
| `mensaje`   | TEXT                              | Comentarios o peticiones           |
| `estado`    | ENUM(pendiente/confirmada/cancelada) | Estado de la reserva            |
| `created_at`| DATETIME                          | Fecha y hora de creación           |

---

## Panel de administración

Accede en: `https://tu-dominio.com/reservas/admin.php`

**Funciones disponibles:**
- 📅 **Vista por día** — Ver todas las reservas de un día concreto
- 📆 **Vista por semana** — Calendario semanal con indicadores de color
- 📋 **Todas las reservas** — Listado completo ordenado por fecha
- ✅ **Confirmar / ❌ Cancelar** reservas con un clic
- 🗑️ **Eliminar** reservas
- 📞 Click en el teléfono para llamar directamente
- 📧 Click en el email para escribir directamente

---

## Solución de problemas frecuentes

| Problema | Solución |
|----------|----------|
| `SQLSTATE[HY000] [2002]` | El host de la BD es incorrecto. En Plesk suele ser `localhost` |
| `Access denied for user` | Usuario o contraseña de MySQL incorrectos en `config.php` |
| `Table doesn't exist` | No se importó `setup.sql`. Repite el Paso 2 |
| La web carga pero el formulario no envía | Revisa que `reservas/reservar.php` existe y tiene permisos 644 |
| Error 500 en `admin.php` | Activa el log de errores PHP en Plesk para ver el error exacto |
| `.htaccess` no funciona | Asegúrate de que `AllowOverride All` está activado en Apache (Plesk lo activa por defecto) |

---

## Seguridad recomendada antes de lanzar

- [ ] Cambia `ADMIN_PASS` en `config.php` por una contraseña fuerte
- [ ] Borra los datos de demo del `setup.sql` o desde phpMyAdmin (`DELETE FROM reservas WHERE id LIKE 'RES-DEMO%'`)
- [ ] Asegúrate de que el dominio tiene **certificado SSL** (HTTPS) — Plesk tiene Let's Encrypt gratuito
- [ ] Activa SSL en Plesk: **Sitios web y dominios → Let's Encrypt**

---

## Soporte

- **Web:** [github.com/ebravounda/turismo](https://github.com/ebravounda/turismo)
- **Rama:** `claude/upload-html-github-PxIAD`
