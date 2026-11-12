# Modificaciones sobre Easy!Appointments

Registro de los cambios hechos en archivos originales de Easy!Appointments, para revisarlos al fusionar nuevas
versiones de `upstream`. Los archivos nuevos del proyecto no se listan aquí.

## Catálogo de tratamientos (fase 3)

Migración nueva: `application/migrations/070_add_treatment_catalog.php` (`services.is_active`,
`appointments.service_name` y los 10 tratamientos iniciales).

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/models/Services_model.php` | Campo `is_active` (cast, API `isActive`); `delete()` rechaza servicios con citas; `get_available_services(true)` excluye los inactivos; la API devuelve `color` | Conservar el historial: las claves foráneas de `appointments` usan `ON DELETE CASCADE` y borrar un servicio borraba sus citas. `color` ya estaba documentado en `openapi.yml` pero no se devolvía |
| `application/models/Appointments_model.php` | `set_service_name()` guarda el nombre del servicio al crear la cita o al cambiar de servicio; API `serviceName` | La cita conserva el tratamiento del momento de la visita aunque luego se renombre |
| `application/controllers/Booking.php` | `check_bookable()` rechaza servicios inactivos | Un servicio inactivo no se puede reservar ni siquiera con peticiones directas |
| `application/controllers/Services.php` | `is_active` en los campos permitidos | Guardar el estado desde la pantalla de servicios |
| `application/controllers/Calendar.php` | Las variables de JavaScript solo incluyen servicios activos | Las citas nuevas solo pueden usar tratamientos activos |
| `application/controllers/Installation.php`, `application/libraries/Instance.php` | El profesional de ejemplo usa los tratamientos existentes y solo se crea el servicio «Service» si no hay ninguno | Evitar un servicio de ejemplo junto al catálogo de la clínica |
| `application/views/components/appointments_modal.php`, `assets/js/components/appointments_modal.js` | Los servicios inactivos se pintan como opciones ocultas con la etiqueta «(Inactivo)»; la preselección ignora las opciones ocultas | Editar una cita antigua sin perder su tratamiento |
| `application/views/pages/services.php`, `assets/js/pages/services.js` | Interruptor «Activo» y aviso «Inactivo» en el listado | Gestionar el estado desde la interfaz |
| `application/language/*/translations_lang.php` | Claves `inactive`, `service_active_hint`, `service_has_appointments` | Textos nuevos (español traducido, resto en inglés) |
| `openapi.yml` | `serviceName` en citas, `isActive` en servicios | Documentar la API |

## Estados de cita (fase 3)

Archivos nuevos: `application/helpers/appointment_status_helper.php` (lectura y normalización de los estados) y
`application/migrations/071_add_appointment_status_codes.php` (sustituye el ajuste `appointment_status_options` por
`appointment_statuses` y convierte el estado de las citas existentes a códigos).

Las citas guardan un código estable (`scheduled`, `confirmed`, `completed`, `cancelled`, `no_show` o `custom_*`). Los
estados del sistema siempre existen y conservan su significado; la clínica puede renombrarlos y añadir los suyos. Un
estado que «libera el hueco» (Cancelada) deja de contar como tiempo ocupado.

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/config/constants.php`, `application/config/autoload.php` | Constantes `APPOINTMENT_STATUS_*` y carga del helper | Códigos compartidos |
| `application/models/Appointments_model.php` | Validación del código; Programada por defecto; `get_slot_occupying()` y filtro en los contadores de asistentes y en `has_provider_conflict()` | Antes el estado no tenía efecto: una cita cancelada seguía ocupando el hueco |
| `application/libraries/Availability.php` | Usa `get_slot_occupying()` | Los huecos de citas canceladas vuelven a estar disponibles |
| `application/controllers/Booking_cancellation.php` | Cancelar marca la cita como Cancelada y añade el motivo a las notas, en lugar de borrarla; el webhook pasa a `appointment_save`; una cita ya cancelada no se puede volver a cancelar | No borrar en silencio citas canceladas (especificación 9.6) |
| `application/controllers/Booking.php` | Las reservas y reprogramaciones públicas empiezan como Programada; una cita cancelada no se puede gestionar desde su enlace | Coherencia con los estados |
| `application/controllers/Business_settings.php` | Normaliza los estados al guardar y rechaza quitar un estado que usan citas | Integridad de los datos |
| `application/controllers/Calendar.php`, `application/views/pages/calendar.php`, `application/views/components/appointments_modal.php` | El desplegable usa código y nombre | Mostrar el nombre configurado |
| `application/views/emails/appointment_*_email.php` | Muestran el nombre del estado | Los emails no deben enseñar el código |
| `assets/js/components/appointment_status_options.js`, `assets/js/pages/business_settings.js` | Editor reescrito: nombre, «libera el hueco» y borrado solo para los propios. Construye el HTML con jQuery en vez de interpolar el texto | Nuevo modelo y se elimina una inyección de HTML del original |
| `assets/js/utils/calendar_event_popover.js`, `calendar_default_view.js`, `calendar_table_view.js`, `assets/css/backend.scss` | Nombre del estado en el popover; las citas que liberan hueco se ven atenuadas y tachadas | Distinguir las canceladas sin ocultarlas |
| `openapi.yml` | `status` documentado como código | Documentar la API |

**Resuelto en «Acciones de la cita»**: cancelar desde el calendario también borra el evento de Google o CalDAV.

## Acciones de la cita (fase 3)

Archivo nuevo: `assets/js/utils/calendar_appointment_actions.js` (cancelar y confirmar el borrado, compartido por las dos
vistas del calendario).

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/controllers/Calendar.php` | Guardar una cita con un estado que libera el hueco no comprueba conflictos, borra el evento de Google/CalDAV y envía el email de cancelación, solo la primera vez | Cancelar desde el calendario es una cancelación de verdad y no un simple cambio |
| `assets/js/utils/calendar_event_popover.js` | Botón «Cancelar cita» en las citas que ocupan su hueco; el icono de «Cerrar» deja de ser el de prohibido | «Cerrar» parecía una cancelación |
| `assets/js/utils/calendar_default_view.js`, `calendar_table_view.js` | Acción «Cancelar cita»; «Eliminar» pide confirmación avisando de que se pierde el historial; los diálogos usan «Cerrar» en vez de «Cancelar» | Evitar confundir cerrar un diálogo con cancelar la cita |
| `application/views/components/appointments_modal.php`, `assets/js/components/appointments_modal.js` | «Cancelar» pasa a «Cerrar» | Ídem |
| `application/views/pages/calendar.php` | Carga el módulo nuevo | — |
