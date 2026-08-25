# LifeQuest

Dirección, constancia y progreso. Aplicación personal de **objetivos anuales**, **horizontes** (largo plazo), **hábitos**, **plan semanal** y **métricas**.

Versión 1: PHP + MySQL, sin framework de frontend. Pensada para desplegarse en un Home Lab / Apache (o IIS con PHP) copiando la carpeta.

---

## Qué incluye (v1)

| Pantalla | Para qué sirve |
|----------|----------------|
| **Hoy** | Hábitos del día, tareas del día, progreso del año |
| **Objetivos** | Plan anual por área (meses / unidades / sí-no) |
| **Libros** | Biblioteca del año y meta de lectura |
| **Hábitos** | Alta, edición y seguimiento (meses, unidades o diario) |
| **Plan semanal** | Tareas por día, filtro por día, carga diaria |
| **Métricas** | Completitud, gráficos y distribución |
| **Horizontes** | Objetivos mayores de largo plazo (binarios) |
| **Archivo** | Solo objetivos históricos (sin horizontes) |
| **Configuración** | Tema, contraseña, áreas anuales y de horizonte |

---

## Requisitos

- PHP **8.1+** (recomendado 8.2/8.3) con extensiones: `pdo_mysql`, `mbstring`, `json`, `session`
- MySQL **8+** (o MariaDB compatible)
- Servidor web que sirva la carpeta (Apache, nginx, IIS)
- No hace falta Composer ni Node para correr la app

---

## Seguridad del repositorio

Antes de pushear o compartir el repo, verificar:

| Archivo | Estado esperado |
|---------|-----------------|
| `.env` | **No** está en el repo (está en `.gitignore`). Solo existe en cada máquina/servidor. |
| `.env.example` | Plantilla sin contraseñas reales (`DB_PASS` vacío). |
| `sql/schema.sql` | Solo DDL (tablas). **Sin** datos de usuarios ni hashes. |
| `config/*.php` | Lee valores desde `.env`; no hardcodea secretos. |
| Semilla demo | `demo@lifequest.local` / `lifequest-demo` (solo instalación de ejemplo). |

Nunca commitear: `.env`, dumps SQL con datos reales, scripts `_diag*` / `_reset*`, ni capturas con credenciales.

---

## Deploy (instalación nueva)

### 1. Copiar el proyecto

Copiá la carpeta del repo al document root (ej. `W:\lifequest` → `http://servidor/lifequest`).

No hace falta `vendor/`: la app no depende de Composer.

### 2. Configurar entorno

```bash
cp .env.example .env
```

Editá `.env`:

```env
APP_URL=http://TU-SERVIDOR/lifequest
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=lifequest
DB_USER=root
DB_PASS=tu_password_mysql
```

- `APP_URL` **sin** barra final.
- En producción: `APP_DEBUG=false` y `APP_ENV=production`.

### 3. Instalar base y datos de ejemplo

Abrí en el navegador:

`http://TU-SERVIDOR/lifequest/install.php`

Pulsá **Instalar ahora**. Eso:

1. Crea la base si no existe  
2. Aplica `sql/schema.sql`  
3. Siembra áreas, objetivos y hábitos de ejemplo  

Usuario demo (solo tras instalar):

- **Email:** `demo@lifequest.local`  
- **Contraseña:** `lifequest-demo`  

Cambiá la contraseña en **Configuración** antes de usarlo en serio.

### 4. (Opcional) Quitar el instalador

Después de instalar, borrá o renombrá `install.php` en el servidor para que nadie lo vuelva a ejecutar.

### 5. Actualizar una instalación ya existente

Si ya tenés datos y solo cambió el esquema:

`http://TU-SERVIDOR/lifequest/migrate.php`

Luego copiá los archivos de código nuevos (PHP/CSS/JS/vistas) sobre la instalación. **No** pises el `.env` del servidor.

Ejemplo en Windows (robocopy, excluyendo `.env`):

```bat
robocopy C:\ruta\LifeQuest W:\lifequest /E /XD .git /XF .env
```

---

## URLs

Las rutas van por query string (sin `mod_rewrite`):

```
http://TU-SERVIDOR/lifequest/index.php?r=/today
http://TU-SERVIDOR/lifequest/index.php?r=/login
```

Atajos útiles: `?r=/goals`, `?r=/habits`, `?r=/weekly`, `?r=/horizon`, `?r=/archive`, `?r=/settings`.

---

## Guía de uso rápida

### Objetivos (año)

1. Elegí el año en la barra superior.  
2. Creá objetivos por área con **+ Nuevo objetivo**.  
3. Medición:
   - **Meses:** tildá los 12 meses del año.  
   - **Unidades:** cargá avance vs meta.  
   - **Sí / no:** marcar hecho o pendiente.  
4. Edición siempre en modal (sin panel lateral).  
5. Eliminar un objetivo te deja en el **mismo año** que estabas viendo.

### Horizontes

Objetivos de largo plazo, separados de los anuales, con áreas propias (Laborales, Salud, Financieros, etc.). Solo sí/no (logrado / en camino).

### Hábitos

- **Por meses:** marcar meses cumplidos.  
- **Por unidades:** sumar progreso (libros, km, etc.).  
- **Diario:** checkbox del día (también en Hoy).  

Edición en modal.

### Plan semanal / Hoy

- En **Plan semanal** filtrás por día o ves toda la semana.  
- En **Hoy**, hábitos y tareas del día comparten altura fija (~9 filas) con scroll si hay más.  
- Cuando el día está al 100%, se muestra *Carga diaria 100% ejecutada*.

### Archivo

Solo objetivos anuales completados/archivados o de años anteriores. **No** mezcla horizontes.

### Tema

Claro / oscuro desde el toggle de la barra o en Configuración.

---

## Variables de entorno

| Variable | Descripción |
|----------|-------------|
| `APP_NAME` | Nombre visible |
| `APP_ENV` | `local` / `production` |
| `APP_DEBUG` | Errores visibles (`true`/`false`) |
| `APP_URL` | URL base pública |
| `DB_*` | Conexión MySQL |
| `SESSION_NAME` | Nombre de la cookie |
| `SESSION_LIFETIME` | Vida de la cookie (segundos) |
| `SESSION_IDLE` | Timeout por inactividad (segundos) |
| `PATRIUM_URL` | Enlace opcional a finanzas externas |

---

## Estructura del proyecto

```
LifeQuest/
├── app/               # Controllers, Services, Views, Auth, Router
├── assets/            # CSS y JS
├── config/            # app.php, database.php, env.php (leen .env)
├── sql/schema.sql     # Esquema limpio (sin datos)
├── index.php          # Entrada de la app
├── install.php        # Instalador (una vez)
├── migrate.php        # Migraciones de esquema
├── .env.example       # Plantilla
└── README.md
```

---

## Notas de v1

- Una sola cuenta de usuario por instalación (uso personal).  
- Zona horaria por defecto: `America/Argentina/Buenos_Aires`.  
- El semillero (`SeedManifest`) genera datos inventados relativos al año en curso; no son datos reales del repo.  
- Tras el deploy, si el CSS/JS no se actualiza: hard refresh (Ctrl+F5); los assets llevan `?v=` de caché.
