Actúa como un Senior Laravel Engineer. Necesito diseñar e implementar un SaaS multi-tenant en Laravel usando UNA SOLA base de datos (single-DB) con columna tenant_id.

Objetivo:
- Tener 2 áreas:
  1) /superadmin (admin global): ve y administra todas las tiendas (tenants), usuarios, planes, métricas.
  2) /app (admin tenant): cada cliente/tienda solo ve sus propios datos.
- Tener login para superadmin y login para clientes (puede ser 1 login con redirección por rol o 2 logins separados). Propon la opción más simple y segura.

Requisitos técnicos obligatorios:
- Tablas: tenants (id, name, slug, domain opcional, status, timestamps)
- users: incluir tenant_id nullable (NULL = superadmin). Un usuario tenant siempre tiene tenant_id.
- Todas las tablas del negocio (products, orders, etc.) deben tener tenant_id, indexado.
- Debe existir un CurrentTenant resolver:
  - Identificar tenant por subdominio (tenant.example.com) O por ruta (/t/{slug}). Elige una opción y aplícala completa.
- Middleware IdentifyTenant:
  - Detecta el tenant y lo guarda en un servicio CurrentTenant (singleton).
  - Si no hay tenant y el usuario NO es superadmin → bloquear/redirigir.
- TenantScope (Global Scope):
  - Todas las queries de modelos “tenant-owned” deben filtrar automáticamente por tenant_id = CurrentTenant->id.
  - En /superadmin debe ser posible ver todo usando withoutGlobalScope o un query especial.
- Autorización:
  - Usar spatie/laravel-permission con roles: superadmin, tenant_admin, tenant_staff.
  - Middlewares en rutas: /superadmin -> auth + role:superadmin ; /app -> auth + tenant + role:tenant_admin|tenant_staff
- Seguridad:
  - Nunca permitir acceso cross-tenant.
  - En create/update, forzar tenant_id desde CurrentTenant (no desde request).
  - Policies o FormRequest deben validar pertenencia al tenant.
- Entregables:
  1) Estructura de rutas (route files o group prefixes) + middlewares.
  2) Código: Middleware IdentifyTenant, clase CurrentTenant, TenantScope, trait BelongsToTenant.
  3) Ejemplo de 2 modelos tenant-owned (Product, Order) con el scope aplicado.
  4) Ejemplo de login flow (redirección según rol) y cómo impedir que un tenant user entre a /superadmin.
  5) Checklist final de pruebas (casos para asegurar aislamiento).

Responde con:
- Arquitectura (bullet points)
- Código en bloques (PHP) listo para copiar
- Pasos de instalación (comandos composer / artisan)
- Notas de “errores típicos” en multi-tenant single-DB
