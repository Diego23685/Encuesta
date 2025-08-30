# Gestor de Encuestas (PHP + MySQL)

Mini gestor basado en tu esquema **Proyecto_Encuesta**. Incluye:
- Login/registro (roles: admin y respondent)
- Crear encuestas, agregar preguntas y opciones
- Responder encuestas públicas
- Ver resultados con agregados
- Exportar a **CSV** (servidor), **XLSX** y **PDF** (cliente)

## Requisitos
- PHP 8.1+ con PDO MySQL
- MySQL 8+
- Servidor web (Apache/Nginx) o `php -S localhost:8080` en modo dev

## Instalación
1. Importa el esquema base y migración de auth:

```sql
SOURCE db_migrations/00_base_schema.sql;
SOURCE db_migrations/01_auth.sql;
```

2. Ajusta credenciales en `api/config.php` si no usas root sin contraseña.

3. Sirve la carpeta `public` como raíz pública. Con PHP embebido:

```bash
php -S 127.0.0.1:8080 -t public
```

4. Abre `http://127.0.0.1:8080/`

## Uso rápido
- Regístrate como **admin** para poder crear encuestas.
- Crea una encuesta y luego usa **Constructor** para añadir preguntas y opciones.
- El enlace **Responder** abre la página pública para enviar respuestas.
- En **Resultados** puedes exportar CSV (servidor), XLSX/PDF (cliente).

## Notas
- El CSV se entrega en formato **largo**: una fila por (envío, pregunta).
- Si marcas una encuesta como anónima al crearla, en la página pública se ocultan nombre/email.
- La seguridad es básica (sesión + roles). Endpoints de administración requieren `admin`.
