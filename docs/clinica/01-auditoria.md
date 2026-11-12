# Fase 1 — Auditoría y propuesta técnica

Proyecto: agenda para una clínica de podología basada en Easy!Appointments.
Fecha de la auditoría: 12 de noviembre de 2026. Rama de trabajo: `develop`.

Este documento responde a la «primera entrega» de la especificación (apartados A a G). Todo lo que se afirma sobre el
código se ha comprobado en el repositorio y en una instalación de prueba con Docker; lo que no se ha podido comprobar
se marca como **por verificar**.

---

## A. Auditoría del repositorio

### A.1 Versión seleccionada

| Elemento | Decisión |
| --- | --- |
| Base | Easy!Appointments **1.6.1** (última versión estable publicada, 12/10/2026) |
| Parches adicionales | 4 correcciones de seguridad de `upstream/develop` posteriores a 1.6.1, integradas en `develop` (POST en ajustes, contraseña para cambiar email/usuario, servicios privados en la reserva pública, perfil de clientes en reservas anónimas) |
| Descartado | `upstream/master` (rama antigua, ya contenida en 1.6.1) y commits duplicados o de prueba de `upstream/develop` |
| PHP / BD | PHP 8.3 (imagen `php:8.3-apache-trixie`), MySQL 8.4 LTS |

Se mantiene `upstream` como remoto para poder incorporar futuras versiones (ver D.6).

### A.2 Arquitectura real

- **Framework**: CodeIgniter 3 parcheado en `system/` (no se modifica). Las extensiones del núcleo están en
  `application/core` con prefijo `EA_`.
- **Patrón**: controladores de página (`application/controllers`) que devuelven HTML o JSON; un cliente HTTP en
  JavaScript por controlador (`assets/js/http`); modelos por entidad; librerías de dominio (`Availability`,
  `Notifications`, `Synchronization`, `Webhooks_client`, `Permissions`...).
- **Frontend**: jQuery + Bootstrap 5 + FullCalendar 6, compilado con gulp. Sin framework SPA.
- **API REST v1**: `application/controllers/api/v1`, documentada en `openapi.yml`.
- **Usuarios**: una única tabla `ea_users` para administradores, profesionales (`provider`), secretaría
  (`secretary`) y clientes (`customer`), diferenciados por `id_roles`. Los ajustes de usuario están en
  `ea_user_settings` (horario semanal en JSON, preferencias de calendario, credenciales).
- **Tareas programadas**: comandos `php index.php console sync|cleanup|backup`, ejecutados por el contenedor `cron`.
- **Esquema**: 69 migraciones numeradas. Tablas: `appointments`, `users`, `user_settings`, `roles`, `services`,
  `service_categories`, `services_providers`, `secretaries_providers`, `blocked_periods`, `working_plan_exceptions`,
  `settings`, `consents`, `webhooks`, `migrations`.

### A.3 Funcionalidades nativas relevantes (comprobadas en el código)

| Área | Qué hace hoy Easy!Appointments | Limitaciones para la clínica |
| --- | --- | --- |
| Profesionales | Alta, edición, servicios habilitados, horario semanal con descansos, `is_private` | No hay estado activo/inactivo ni color propio. Borrar un profesional borra sus citas |
| Secretaría | Rol `secretary` con lista de profesionales asignados; ve y gestiona sus agendas | Encaja con «Recepción». Permisos fijos por rol, sin interfaz para ajustarlos |
| Calendario | Vista estándar (un calendario) y **vista en tabla**: columnas por profesional y por día, una instancia de FullCalendar por columna, filtros múltiples de profesional y servicio, arrastrar y redimensionar | Intervalo fijo de 15 min en el código; sin refresco automático; la vista predeterminada es por usuario y no por rol; muestra datos genéricos de la cita |
| Servicios | Nombre, duración, precio, moneda, color, intervalo, categoría, `is_private` | Sin estado activo/inactivo; sin duración específica por profesional; borrar un servicio deja citas huérfanas |
| Clientes | Nombre, apellidos, email, teléfonos, dirección, notas, 5 campos personalizados | El email es obligatorio por defecto; la búsqueda es `LIKE %texto%` sobre 9 columnas sin índices; sin datos fiscales ni profesional habitual |
| Horarios | Plan semanal por profesional, descansos, **excepciones por fecha** (permiten abrir un día cerrado) | — |
| Bloqueos | `blocked_periods`: periodos globales (toda la clínica) con nombre y notas | Sin tipo, sin alcance por profesional, sin repetición |
| Ausencias | «Unavailabilities» por profesional (son filas de `appointments` con `is_unavailability = 1`); avisa si pisan citas | Sin tipo ni repetición |
| Estados | Texto libre con lista configurable (`Booked`, `Confirmed`, `Rescheduled`, `Cancelled`, `Draft`) | **El estado no tiene efecto**: una cita «Cancelled» sigue ocupando el hueco. Cancelar desde la web pública **borra** la cita |
| Conflictos | `has_provider_conflict()` detecta solapes con otras citas | Solo citas y ausencias (no horario, descansos ni bloqueos); el usuario puede forzar el guardado; sin bloqueo frente a guardados simultáneos |
| Disponibilidad | `Availability::get_available_hours()` por profesional, servicio y fecha: aplica horario, excepciones, descansos, bloqueos, ausencias, citas y límites de antelación | Diseñado para la reserva pública de un solo día; consulta la base de datos por cada fecha y profesional; no busca rangos ni ordena por preferencias |
| API REST | CRUD de citas, clientes, servicios, profesionales, secretarías, ajustes, disponibilidad, bloqueos | Solo administradores (Basic) o un único token global con permisos totales |
| Webhooks | Eventos de guardado y borrado de citas, clientes, servicios, etc. | Útiles para integraciones externas, no sustituyen a un motor de recordatorios |
| Notificaciones | Email (PHPMailer, SMTP o `mail()`) al crear, modificar o borrar | Sin WhatsApp ni SMS; sin recordatorios programados |
| Localización | Español incluido, formatos de fecha y hora, zona horaria por usuario, primer día de la semana | El primer día de la semana viene como domingo |
| Seguridad | CSRF activo, bcrypt (coste 12), cookies `HttpOnly`/`Secure`, cabeceras CSP/HSTS/X-Frame-Options, límite de intentos de login, sesiones ligadas a IP | Sin registro de auditoría; token de API único |
| Copias | `console backup` (volcado comprimido), ahora diario desde el contenedor `cron` | Sin cifrado ni copia externa |

### A.4 Dependencias

- **PHP** (`composer.lock`): google/apiclient, guzzle, phpmailer, monolog, sabre/vobject, jsvrcek/ics, htmlpurifier,
  gregwar/captcha, altcha, symfony/finder. Todas con licencias compatibles con GPL-3.0.
- **JS** (`package-lock.json`): jQuery, Bootstrap, FullCalendar 6, moment(-timezone), flatpickr, select2, tippy,
  trumbowyg, Font Awesome.
- **Nuevas que se proponen** (justificadas en C): `giggsey/libphonenumber-for-php` (teléfonos españoles e
  internacionales, Apache-2.0), `openspout/openspout` (lectura de XLSX/CSV en streaming, MIT) y, si se elige la vía
  propia de VERI\*FACTU, `josemmo/verifactu-php` (MIT, PHP ≥ 8.2).

---

## B. Tabla de cobertura

**N** = nativa, sirve tal cual. **M** = existe pero requiere modificación. **D** = desarrollo nuevo.

| Requisito | N | M | D | Comentario |
| --- | :-: | :-: | :-: | --- |
| Varios profesionales | ✔ | | | Usuarios `provider` |
| Cuenta única de recepción | | ✔ | | Rol `secretary` con todos los profesionales; renombrar a «Recepción» y ampliar permisos |
| Cuentas individuales de profesional | ✔ | | | Rol `provider` |
| Roles y permisos en backend y API | | ✔ | | Nuevos permisos (facturación, recordatorios, datos fiscales, auditoría); API por usuario y no solo admin |
| Vista simultánea de 2–3 agendas | | ✔ | | Vista en tabla existente; hacerla predeterminada para recepción |
| Vista diaria/semanal/mensual y filtros | | ✔ | | Filtros múltiples ya existen; añadir semana en tabla y recordar preferencias por usuario |
| Intervalo visual configurable (15 min) | | ✔ | | Hoy fijo en el código |
| Bloques continuos por duración | ✔ | | | FullCalendar ya lo hace |
| Información de la cita (paciente, tratamiento, 1.ª visita, estado) | | ✔ | | Plantilla de evento nueva |
| Color por tratamiento | | ✔ | | Servicios ya tienen color; la cita copia el color, hay que derivarlo del tratamiento |
| Arrastrar, soltar y redimensionar | ✔ | | | Validar en servidor (ver conflictos) |
| Actualización entre sesiones | | | ✔ | Sondeo periódico de cambios |
| Duración personalizada | | ✔ | | El modal permite cambiar el fin; añadir campo «minutos» |
| Comprobación completa de conflictos | | ✔ | | Ampliar a horario, descansos, bloqueos, excepciones y estados; bloqueo transaccional |
| Catálogo de tratamientos con colores | | ✔ | | Servicios + estado activo + duración por profesional + migración con los 10 tratamientos |
| Conservación histórica del tratamiento | | | ✔ | Copia del nombre/color/duración en la cita y desactivar en lugar de borrar |
| **Buscador de huecos** | | | ✔ | Motor nuevo sobre las reglas de `Availability` |
| Formulario rápido de cita sin DNI | | ✔ | | Modal existente simplificado; email opcional |
| Primera visita | | | ✔ | Campo tri-estado (sí / no / desconocido) |
| Estados (Programada, Confirmada, Realizada, Cancelada, No presentado) | | ✔ | | Estados con significado: cancelar no borra y libera el hueco |
| Historial de cambios y autor | | | ✔ | Tabla de auditoría |
| Festivos y tipos de bloqueo | | ✔ | ✔ | Ampliar `blocked_periods` (tipo, alcance, repetición) + tabla de festivos oficiales |
| Apertura excepcional | ✔ | | | Excepciones de horario |
| Citas afectadas por un bloqueo | | ✔ | | Hoy solo avisa en ausencias individuales |
| Base única de pacientes | ✔ | | | Usuarios `customer` |
| Datos fiscales opcionales y destinatario distinto | | | ✔ | Tabla de destinatarios fiscales |
| **Autocompletado** de pacientes | | ✔ | | Hay búsqueda, pero lenta y devuelve demasiados datos; endpoint ligero e índices |
| Duplicados | | | ✔ | Por teléfono normalizado y nombre |
| Importación Excel | | | ✔ | Asistente con simulación y deshacer |
| **WhatsApp** | | | ✔ | No existe nada |
| Consentimientos de comunicación | | | ✔ | `ea_consents` es para el formulario web; hace falta un registro por canal |
| Facturación | | | ✔ | No existe nada |
| **VERI\*FACTU** | | | ✔ | No existe nada |
| Funcionamiento en LAN sin internet | ✔ | | | La aplicación no depende de servicios externos para la agenda |
| Copias automáticas | | ✔ | | Diarias; falta cifrado y copia externa |
| Auditoría de seguridad | | | ✔ | Registro de operaciones |

---

## C. Arquitectura propuesta

### C.1 Principios

1. **No reescribir lo que funciona**: se reutilizan usuarios, servicios, citas, horarios, excepciones, bloqueos y la
   vista en tabla.
2. **Aislar lo nuevo**: los módulos nuevos van en archivos propios (controladores, librerías, modelos, migraciones
   desde la 070, JS y vistas). Los archivos originales solo se tocan cuando no hay punto de extensión, con cambios
   pequeños y documentados, para poder seguir fusionando versiones de upstream.
3. **Reglas de disponibilidad en un único sitio**: el calendario, el buscador, la API y la reserva pública consultan
   el mismo motor.
4. **Todo valor de negocio es configurable**: nombres, colores, duraciones, intervalos, plantillas, horarios, festivos
   y datos fiscales viven en la base de datos, nunca en el código.

### C.2 Componentes

```
┌────────────────────────── Navegador (recepción / profesionales) ──────────────────────────┐
│ Agenda multiprofesional · Buscar hueco · Fichas · Importador · Recordatorios · Facturas   │
└───────────────┬───────────────────────────────────────────────────────────────────────────┘
                │ HTTP (JSON + CSRF, sesión)                       API v1 (token por usuario)
┌───────────────▼───────────────────────── app (Apache + PHP) ──────────────────────────────┐
│ Agenda: citas, pacientes, tratamientos, profesionales            (modelos EA ampliados)   │
│ Motor de disponibilidad: Slot_rules + Slot_finder              (sustituye consultas sueltas)│
│ Bloqueos y festivos                                                                         │
│ Servicio de recordatorios ── adaptador WhatsApp Cloud API                                   │
│ Facturación ── adaptador fiscal (motor propio + VERI*FACTU, o proveedor externo)            │
│ Auditoría                                                                                   │
└───────────────┬──────────────────────────────────────────────────────┬──────────────────────┘
                │                                                      │ cola en BD
┌───────────────▼──────┐                                ┌──────────────▼──────────────┐
│ db (MySQL 8.4)       │                                │ cron: sync, limpieza, copia │
│                      │                                │ recordatorios, envíos AEAT  │
└──────────────────────┘                                └─────────────────────────────┘
```

No se añaden contenedores para colas ni caché: la escala (3 profesionales, miles de citas) no lo justifica. Las
tareas asíncronas usan tablas en MySQL como cola, procesadas por el contenedor `cron`.

### C.3 Motor de disponibilidad

- `Slot_rules`: dado un profesional y un intervalo [inicio, fin), responde si es válido y por qué no: fuera de
  horario, descanso, festivo o bloqueo de clínica, bloqueo o ausencia del profesional, solape con una cita que ocupa
  hueco, tratamiento no habilitado.
- `Slot_finder`: dado un rango de fechas, tratamiento, duración, profesionales y preferencias, carga **de una vez**
  (una consulta por tipo de dato y rango) horarios, excepciones, bloqueos, ausencias y citas; calcula los intervalos
  libres en memoria, genera candidatos sobre la cuadrícula configurada y los ordena por fecha, por proximidad a la
  hora pedida o por profesional preferido.
- **Garantía frente a guardados simultáneos**: crear o mover una cita abre una transacción, bloquea la fila del
  profesional (`SELECT … FOR UPDATE`), vuelve a validar con `Slot_rules` y guarda. Dos recepcionistas no pueden
  ocupar el mismo hueco.
- Índice nuevo `appointments (id_users_provider, start_datetime, end_datetime)`.
- Forzar un solape queda reservado a un permiso explícito, con motivo obligatorio y registro en auditoría.

### C.4 Modelo de datos (ampliaciones)

| Entidad | Cambio |
| --- | --- |
| `users` (profesionales) | `is_active`, `color`, `display_name` |
| `users` (pacientes) | `phone_normalized` (E.164, indexado), `id_users_preferred_provider`, `is_active`; índices en nombre y apellidos |
| `services` (tratamientos) | `is_active`; migración con los 10 tratamientos iniciales y su paleta |
| `services_providers` | `duration` opcional por profesional |
| `appointments` | `is_first_visit` (nulo = desconocido), `service_name` y `service_color` copiados al guardar, `id_users_created_by`, `id_users_updated_by`, `cancelled_at`; el campo `status` pasa a guardar códigos |
| `appointment_statuses` (nueva) | código, etiqueta, color, `blocks_slot`, `is_final`, orden; iniciales: programada, confirmada, realizada, cancelada, no presentado |
| `blocked_periods` | `type`, `scope` (clínica / profesionales), `all_day`, `recurrence` (ninguna / semanal / anual) y `recurrence_until` |
| `blocked_periods_providers` (nueva) | profesionales afectados por un bloqueo |
| `holidays` (nueva) | fecha, nombre, nivel (nacional / autonómico / local), región. Un festivo no bloquea nada por sí solo: el administrador decide crear el bloqueo |
| `fiscal_parties` (nueva) | destinatarios fiscales: tipo y número de documento, nombre o razón social, dirección, CP, municipio, provincia, país, relación con el paciente |
| `communication_consents` (nueva) | paciente, canal, base jurídica, evidencia, origen, fecha, revocación |
| `reminders` (nueva) | cita, paciente, canal, teléfono, programado para, estado, id del proveedor, intentos, último error; **clave única** (cita, tipo, fecha programada) |
| `reminder_events` (nueva) | historial de estados recibidos por webhook |
| `import_batches` / `import_rows` (nuevas) | lotes de importación, mapeo, resultado por fila y lote para deshacer |
| `audit_log` (nueva) | usuario, acción, entidad, id, fecha, id de operación, cambios relevantes sin datos sensibles |
| Facturación (nuevas) | `invoice_issuers`, `invoice_series`, `invoices`, `invoice_lines`, `invoice_taxes`, `payments`, `fiscal_records` (registro VERI\*FACTU: huella, encadenamiento, estado de envío) |

Todas las migraciones serán numeradas y reversibles (`up`/`down`).

### C.5 Integraciones

- **WhatsApp**: WhatsApp Business Platform (Cloud API de Meta) directamente, sin intermediario, mediante una interfaz
  `Messaging_channel` que permite cambiar de proveedor. Plantilla de tipo *utility*. Los estados de entrega llegan por
  webhook y requieren una URL HTTPS pública (ver F).
- **Facturación y VERI\*FACTU**: interfaz `Fiscal_engine` con dos implementaciones posibles. La decisión está
  pendiente (ver D.5 y F).
- **Festivos**: importación manual (CSV/ICS) y, opcionalmente, descarga desde una fuente pública. El ayuntamiento y
  la comunidad son configurables (Madrid por defecto).

---

## D. Plan de modificaciones

### D.1 Backend

- Rol `secretary` mostrado como «Recepción», con todos los profesionales asignados por defecto. Nuevos permisos:
  `invoices`, `reminders`, `fiscal_data`, `audit`, `imports`.
- API v1: autenticación por usuario (no solo administrador) aplicando los mismos permisos que la web.
- `Slot_rules` y `Slot_finder` (C.3), endpoint `slot_search` en la web y `availabilities/search` en la API.
- Guardado transaccional de citas y estados con significado: cancelar no borra la cita y libera el hueco.
- Endpoints ligeros: autocompletado (máx. 10 resultados, campos mínimos) y «cambios desde» para el refresco de la
  agenda.
- Servicios nuevos: bloqueos y festivos, importador, recordatorios, facturación y auditoría.

### D.2 Frontend

- La vista en tabla pasa a ser la predeterminada para recepción (día, todos los profesionales); se añade la semana,
  el intervalo configurable, un nuevo diseño de los eventos y el recuerdo de filtros por usuario.
- Barra superior: Hoy, anterior/siguiente, selector de fecha, día/semana, «Nueva cita», «Buscar hueco», «Bloquear
  agenda».
- Modal de cita simplificado: paciente con autocompletado y alta en línea, primera visita, tratamiento (rellena la
  duración), minutos editables, estado y recordatorio.
- Panel «Buscar hueco» con resultados clicables que abren el modal ya relleno.
- Pantallas nuevas: Bloqueos y festivos, Importar pacientes, Recordatorios WhatsApp, Facturación.
- Interfaz en español, semana empezando en lunes, formato de fecha DD/MM/AAAA, euros.

### D.3 Base de datos

Ver C.4. Datos iniciales: los 10 tratamientos con su paleta y los 5 estados. Los datos reales de la clínica
(nombres, horarios, precios) se configuran desde la interfaz.

### D.4 Archivos originales que se tocarán

Se limitará a: `Calendar.php` y `Appointments_model.php` (guardado transaccional y estados), `Availability.php`
(delegar en el motor nuevo), `calendar_table_view.js` y `appointments_modal.js` (agenda y modal), `Api.php`
(autenticación por usuario) y las traducciones al español. Cada cambio se documentará en
`docs/clinica/modificaciones.md`.

### D.5 Facturación: dos vías

| | Vía A: motor propio + librería VERI\*FACTU | Vía B: proveedor de facturación externo con API |
| --- | --- | --- |
| Qué se desarrolla | Emisores, series, facturas, PDF, cobros + registros VERI\*FACTU con `josemmo/verifactu-php` | Borradores y datos fiscales en la app; el proveedor emite, numera y remite a la AEAT |
| Responsabilidad legal | El producto modificado necesita su **declaración responsable** de productor (RD 1007/2023) | La asume el proveedor para el sistema de facturación |
| Coste | Desarrollo y mantenimiento normativo propios | Cuota del proveedor |
| Sin internet | Hay que gestionar la remisión diferida según la norma | Depende del proveedor |
| Decisión | **Elegida** (ver `decisiones.md`) | Descartada |

### D.6 Actualizaciones de Easy!Appointments

`upstream` queda como remoto. Cada nueva versión se fusiona en una rama `chore/upstream-x.y.z`, se ejecutan las
pruebas y se integra en `develop`. Aislar el código nuevo reduce los conflictos.

---

## E. Fases de desarrollo

| Fase | Contenido | Depende de | Criterio de aceptación |
| --- | --- | --- | --- |
| 1 Auditoría | Este documento | — | Revisión y aprobación |
| 2 Infraestructura | Docker, MySQL, copias diarias, restauración probada, logs, salud | — | **Hecha**: `docker compose up -d`, instalación, cita, email y restauración probados |
| 3 Agenda multiprofesional | Recepción, vista en tabla predeterminada, intervalos, tratamientos y colores, estados, modal rápido, refresco | 2 | Escenarios A, B, G, H visibles en la agenda; pruebas 23.1 y 23.2 |
| 4 Motor de disponibilidad | `Slot_rules`, `Slot_finder`, «Buscar hueco», bloqueo transaccional | 3 | Escenarios C, D, E, F; pruebas 23.3; búsqueda < 1 s con 50 000 citas |
| 5 Pacientes | Autocompletado, duplicados, profesional habitual, primera visita, datos fiscales opcionales | 3 | Pruebas 23.4; escenario M |
| 6 Festivos y ausencias | Tipos, alcance, repetición, festivos, citas afectadas | 4 | Pruebas 23.6; escenarios G y H |
| 7 Importación | Asistente XLSX/CSV, simulación, copia previa, deshacer | 5 + **Excel de la clínica** | Pruebas 23.5; escenario K |
| 8 WhatsApp | Consentimientos, cola, programación a las 10:00, estados, reintentos, panel | 5 + **cuenta de Meta** | Pruebas 23.7; escenarios I y J |
| 9 Facturación | Emisores, series, facturas, cobros, PDF | 5 + **decisión D.5 y forma jurídica** | Pruebas 23.8; escenario L |
| 10 VERI\*FACTU | Registros, huella, encadenamiento, envío, QR | 9 + **certificado y entorno de pruebas AEAT** | Pruebas 23.9 contra el entorno oficial de pruebas |
| 11 Producción | Seguridad, VPN, copia externa cifrada, monitorización, manuales | todas | Criterios del apartado 27 |

Las fases 3 a 6 no dependen de información externa y pueden empezar ya con datos ficticios.

---

## F. Riesgos

| Riesgo | Impacto | Mitigación |
| --- | --- | --- |
| **Despliegue en VPS** (decidido, ver `decisiones.md`): la agenda depende de la conexión a internet de la clínica | Medio | HTTPS obligatorio delante de `app`; copias externas; los webhooks de WhatsApp llegan directamente a la VPS |
| Disponibilidad: huecos falsos o dobles reservas | Alto | Un único motor, revalidación al guardar y bloqueo transaccional; pruebas con concurrencia |
| Estados sin efecto en la versión original | Alto: una cita cancelada sigue bloqueando | Estados con `blocks_slot`; migración de los estados existentes |
| WhatsApp: plantillas no aprobadas, coste por mensaje, número verificado | Medio | Alta temprana en Meta; la plantilla *utility* tiene coste por mensaje en España (consultar la tarifa vigente de Meta) |
| WhatsApp: consentimiento | Alto (RGPD) | No se envía a quien no tenga habilitación registrada; los importados quedan excluidos por defecto |
| WhatsApp: envíos duplicados o tardíos | Medio | Clave única por cita y fecha, estado «en proceso» con bloqueo, ventana máxima de envío y no enviar citas pasadas |
| **VERI\*FACTU: calendario** | Alto | Según el RDL 15/2025 es obligatorio el 1/1/2027 para el Impuesto sobre Sociedades y el 1/7/2027 para el resto; en octubre de 2026 Hacienda anunció la intención de aplazarlo a octubre de 2028, **todavía no publicada en el BOE** (por verificar antes de la fase 9). Desde el 29/7/2025 solo se pueden comercializar productos adaptados |
| VERI\*FACTU: responsabilidad del productor | Alto | Ver D.5; revisión profesional antes de producción |
| IVA de los servicios de podología | Alto | Exención sanitaria (art. 20.Uno.3.º LIVA) **por verificar** con asesoría; los productos (plantillas, siliconas) pueden tributar. El tipo de IVA será configurable por tratamiento |
| Seguridad: API con un único token de administrador | Medio | Tokens por usuario con permisos |
| Seguridad: sin auditoría | Medio | Tabla de auditoría desde la fase 3 |
| Copias en el mismo disco | Alto | Copia externa cifrada en la fase 11 (destino pendiente) |
| Divergencia con upstream | Medio | Código aislado y fusiones periódicas (D.6) |
| Datos personales en desarrollo | Alto | Solo datos ficticios; el Excel real nunca se sube al repositorio |

### Decisiones externas pendientes

1. ~~Servidor local o en la nube~~: VPS en la nube (decidido).
2. Nombre de la clínica, profesionales, horarios, duraciones y precios.
3. Excel de pacientes.
4. Cuenta de Meta Business, número de WhatsApp y plantilla.
5. Forma jurídica y emisor o emisores fiscales (la vía de facturación ya está decidida: motor propio).
6. Modalidad VERI\*FACTU / NO VERI\*FACTU y asesoría fiscal.
7. Municipio para los festivos (Madrid por defecto).
8. Destino de la copia externa y proveedor de la VPS.

---

## G. Plan de pruebas

- **Unitarias (PHPUnit, `tests/Unit`)**: `Slot_rules` y `Slot_finder` (cada regla del 8.6, duraciones de 15/30/45/60
  minutos y personalizadas, límites de horario, solapes, casos 1–7), normalización de teléfonos, validación de
  DNI/NIE/NIF, cálculo de la ejecución de las 10:00 en Europe/Madrid (cambio de hora, fin de semana, cambio de año),
  idempotencia de recordatorios, numeración de series y huella y encadenamiento de VERI\*FACTU.
- **Integración con base de datos real** (contenedor de pruebas): guardado transaccional con dos sesiones
  simultáneas, importación con duplicados y reimportación, ciclo completo de un recordatorio con un proveedor
  simulado, copia y restauración.
- **Rendimiento**: datos generados con 50 000 citas y 10 000 pacientes; búsqueda de hueco y autocompletado por
  debajo de 1 s y 200 ms respectivamente.
- **Aceptación**: los escenarios A–O del apartado 24 se ejecutan en el navegador al cerrar cada fase y se registran en
  `docs/clinica/pruebas.md`.
- **VERI\*FACTU**: entorno oficial de pruebas de la AEAT; nunca se declarará conformidad solo por generar XML.
- **Infraestructura**: instalación desde cero, reinicio, caída de internet, copia y restauración (ya probadas en la
  fase 2 salvo la caída de internet).

---

## Fuentes consultadas

- Agencia Tributaria, sistemas informáticos de facturación y VERI\*FACTU:
  <https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu.html>
- Aplazamiento a 2027 (RDL 15/2025): <https://www.iberley.es/noticias/se-retrasan-2027-las-fechas-aplicacion-efectiva-veri-factu-35738>
- Nuevo aplazamiento anunciado: <https://asesores-consultores.com/nuevo-aplazamiento-de-verifactu-con-la-vista-puesta-en-2028/>
- Precios de WhatsApp Business Platform: <https://developers.facebook.com/docs/whatsapp/pricing>
- Librería `josemmo/verifactu-php`: <https://packagist.org/packages/josemmo/verifactu-php>
