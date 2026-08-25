# LifeQuest

Dirección, constancia y progreso. Plataforma de objetivos anuales, objetivos de largo plazo, hábitos y métricas.

## Stack

- PHP 8+
- MySQL 8
- HTML/CSS/JS sin framework (Chart.js para los gráficos)

## Instalación rápida

1. Copiar el proyecto al document root del servidor.
2. Copiar `.env.example` a `.env` y ajustar `DB_*` y `APP_URL`.
3. Abrir `<APP_URL>/install.php` y pulsar **Instalar ahora**. Crea la base, aplica el esquema y carga datos de ejemplo.
4. Entrar con el usuario de ejemplo que muestra el instalador:
   - **Email:** `demo@lifequest.local`
   - **Contraseña:** `lifequest-demo`
5. Cambiar la contraseña desde **Configuración** antes de usarlo en serio.

Para actualizar una instalación existente al último esquema, abrir `<APP_URL>/migrate.php`.

## Rutas principales

| Ruta | Función |
|------|---------|
| `/today` | Hábitos del día y objetivos en foco |
| `/goals` | Objetivos del año por área |
| `/books` | Biblioteca del año y meta de lectura |
| `/habits` | Gestión y registro diario |
| `/metrics` | Panel de métricas y gráficos por área |
| `/reviews` | Revisiones periódicas |
| `/horizon` | Objetivos de largo plazo |
| `/archive` | Objetivos históricos por año |
| `/settings` | Perfil, contraseña y áreas |

Las URLs usan `index.php?r=/ruta`, así que funciona en Apache sin `mod_rewrite`.

## Modos de progreso

Cada objetivo anual se mide de una de tres formas:

- **Meses:** doce casillas, una por mes. El porcentaje sale de los meses tildados.
- **Unidades:** una cantidad actual contra una meta (por ejemplo, artículos publicados).
- **Sí / no:** un único resultado binario.

Los objetivos de largo plazo son siempre binarios.

## Finanzas

LifeQuest guarda metas financieras anuales simples, sin montos ni movimientos. Si se define `PATRIUM_URL` en el `.env`, el instalador agrega un enlace al sistema externo de finanzas; si queda vacío, no se crea nada.
