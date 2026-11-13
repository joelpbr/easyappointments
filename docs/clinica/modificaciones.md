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

## Agenda de recepción (fase 3)

Archivos nuevos: `application/helpers/calendar_helper.php` (intervalo de la agenda),
`application/migrations/072_add_reception_agenda_settings.php` y `assets/js/utils/calendar_event_content.js` (tarjeta de
la cita y color del texto).

La migración 072 crea el ajuste `calendar_slot_minutes` (15 min), pone la vista partida a administradores y
secretarías, cambia la semana a lunes y la hora a 24 h si seguían con los valores de fábrica, suaviza dos colores de la
paleta (Quiropodia y Cirugía) para que el texto tenga un contraste superior a 4,5:1 y da el color de su tratamiento a
las citas que tenían el azul por defecto.

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/config/constants.php`, `application/config/autoload.php` | Constantes del intervalo y `DEFAULT_APPOINTMENT_COLOR`; carga del helper | — |
| `application/controllers/Installation.php`, `application/libraries/Instance.php`, `assets/js/pages/admins.js`, `assets/js/pages/secretaries.js` | Administradores y secretarías nuevos empiezan en la vista partida | Pantalla principal de recepción (especificación 5.1) |
| `application/controllers/Business_settings.php`, `application/views/pages/business_settings.php` | Campo «Intervalo de la agenda» validado entre 5 y 60 minutos | Especificación 6.1 |
| `application/controllers/Calendar.php` | Pasa el intervalo a JavaScript | — |
| `application/models/Appointments_model.php` | Al crear la cita o cambiar su servicio, toma el color del servicio salvo que se haya elegido otro | Los bloques se colorean por tratamiento (especificación 5.4); las citas creadas por la API quedaban con el azul por defecto |
| `assets/js/utils/calendar_default_view.js`, `calendar_table_view.js` | Intervalo configurable; tarjeta de cita propia; color del texto por contraste; `refresh()` para el refresco automático; en la vista partida: opción «Semana», columnas que se reparten el ancho (mínimo 200 px), filtros recordados por usuario en el navegador y nombres de día independientes del idioma al leer el horario | Especificación 5.2 a 5.6 |
| `assets/js/pages/calendar.js` | Refresco automático cada 30 s y al volver a la pestaña, salvo con un diálogo, popover o arrastre abiertos | Especificación 5.6 |
| `assets/js/http/calendar_http_client.js` | Modo silencioso para las consultas automáticas | Un fallo puntual no abre el aviso de error cada 30 s |
| `application/views/pages/calendar.php`, `gulpfile.js` | Carga los idiomas de moment y FullCalendar solo en el calendario | Los nombres de los días salían en inglés en todos los idiomas |
| `assets/css/backend.scss` | Estilos de la tarjeta | — |

## Gestión de profesionales (fase 3)

Migración nueva: `application/migrations/073_add_users_active_state.php` (`users.is_active`).

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/models/Providers_model.php` | `is_active` (API `isActive`); los inactivos no salen en `get_available_providers()`; el apellido es opcional y se guarda como texto vacío; `delete()` rechaza profesionales con citas | Desactivar conservando el historial (especificación 4.2): la clave foránea borraba sus citas en cascada. El apellido opcional permite que la agenda muestre solo «Sara» sin un campo «nombre visible» aparte (decisión D-006) |
| `application/controllers/Booking.php` | `check_bookable()` rechaza profesionales inactivos | No se les pueden dar citas ni con peticiones directas |
| `application/controllers/Providers.php`, `application/views/pages/providers.php`, `assets/js/pages/providers.js` | Interruptor «Activo», apellido no obligatorio y aviso «Inactivo» en el listado | Gestión desde la interfaz |
| `openapi.yml` | `isActive` en profesionales | Documentar la API |

## Formulario rápido de cita (fase 3)

Migración nueva: `application/migrations/074_add_quick_appointment_fields.php` (`appointments.is_first_visit`, nulo =
sin indicar; el email del paciente deja de ser obligatorio).

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/views/components/appointments_modal.php` | Campos «Duración (minutos)» y «Primera visita»; color, ubicación, enlace, idioma, zona horaria, dirección y campos personalizados pasan detrás de «Más datos» (salvo los obligatorios) | Formulario corto para recepción (especificación 9.1 y 9.7) |
| `assets/js/components/appointments_modal.js` | Duración y fin sincronizados (cualquier número de minutos ≥ 5); «Más datos» se abre solo si alguno de esos campos tiene valor; un paciente nuevo sugiere primera visita «Sí»; «Nueva cita» empieza en el siguiente intervalo de la agenda y sin segundos | Especificación 6.3 y 9.3; el original saltaba a la hora siguiente en los minutos 0, 15, 30 y 45 |
| `assets/js/utils/calendar_default_view.js`, `calendar_table_view.js`, `calendar_event_content.js` | Cargan la primera visita al editar y la tarjeta muestra «1.ª visita» | Especificación 5.4 |
| `application/models/Appointments_model.php`, `application/helpers/appointment_status_helper.php` | `normalize_first_visit()`; API `isFirstVisit` | Validar sí/no/sin indicar |
| `application/controllers/Calendar.php` | `is_first_visit` en los campos permitidos | — |
| `assets/css/backend.scss` | Oculta los campos secundarios | — |
| `openapi.yml` | `isFirstVisit` | Documentar la API |

## Registro de auditoría (fase 3)

Archivos nuevos: `application/helpers/audit_helper.php` (`audit_changes()`, `audit_log()`) y
`application/migrations/075_create_audit_log_table.php` (tabla `audit_log`: fecha, usuario, origen —backend, api,
public o console—, acción, entidad, id y cambios en JSON).

Se registra desde los modelos, así que cubre el calendario, la API, la reserva pública y la consola. Las notas solo
anotan que cambiaron (`***`), nunca su contenido, para no copiar datos sensibles al registro.

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/models/Appointments_model.php` | Registra creación, modificación («cancelled» si pasa a un estado que libera el hueco) y borrado de citas | Especificación 9.6 y 19.2 |
| `application/models/Services_model.php` | Registra alta, cambios (nombre, duración, precio, color, privado, activo) y borrado de tratamientos | Ídem |
| `application/models/Providers_model.php` | Registra alta, cambios (datos, activo, horario) y borrado de profesionales | Ídem, incluye «modificación de horarios» |
| `application/libraries/Api.php` | Guarda el usuario autenticado por Basic auth | Autor de las operaciones de la API |
| `application/config/autoload.php` | Carga el helper | — |

Pendiente para la fase 11: pantalla de consulta del registro para administradores.

## Terminología de la clínica (fase 3)

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/language/spanish/translations_lang.php` | Servicio → Tratamiento, Cliente → Paciente, Proveedor → Profesional, Secretario/a → Recepcionista, en 76 textos. Se mantienen los usos genéricos («nuestro servicio para agendar», «ID de cliente» de Google y los textos «Acerca de») | Interfaz en el lenguaje de la clínica (especificación 17.3). Solo afecta al español; los demás idiomas conservan sus términos |
| `application/views/components/appointments_modal.php` | «Notas» de la cita pasa a «Comentario» y las del paciente a «Observaciones administrativas» (claves nuevas `appointment_comment` y `administrative_notes`) | Había dos campos «Notas» en el mismo formulario (especificación 9.1) |

## Tema «Podología Sabadell»

Archivos nuevos: `assets/css/themes/podologia-sabadell.scss` (tema de Bootstrap con la identidad de la clínica: azul
`#063f7e`, cian `#08a2c9` y fuente Inter; retoca sobre todo la página pública de reserva) y
`application/migrations/076_set_podologia_sabadell_theme.php` (lo activa si la instalación seguía con el tema por
defecto). Gulp lo compila como el resto de temas y aparece en *Configuración → General → Tema*. No modifica archivos
originales.

**Pendiente (fase 11)**: la fuente Inter se descarga de Google Fonts, igual que en los temas incluidos. Para no enviar
la IP de los pacientes a Google conviene servirla desde la propia aplicación antes de producción.

## Reglas de disponibilidad al guardar (fase 4)

Archivos nuevos: `application/libraries/Slot_rules.php` (comprueba un hueco con las mismas reglas del motor de
disponibilidad y guarda dentro de un bloqueo por profesional) y `application/migrations/077_add_appointments_period_index.php`
(índice `appointments (id_users_provider, start_datetime, end_datetime)`).

Reglas: solape con otra cita que ocupa hueco, ausencia del profesional, periodo bloqueado y fuera de la jornada
(horario, descansos, excepciones) — recepción puede confirmarlas a propósito —, y profesional inactivo, tratamiento no
realizado por el profesional o periodo inválido, que nunca se pueden forzar. Una cita que se edita sin cambiar de hueco
no se vuelve a comprobar, para no bloquear cambios de notas en citas confirmadas fuera de las reglas.

**Doble reserva simultánea**: comprobar y guardar se hace en una transacción que bloquea antes la fila del profesional
(`SELECT … FOR UPDATE`). Probado con dos peticiones a la vez: una entra y la otra recibe 409.

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/controllers/Calendar.php` | `check_provider_conflict` y `save_appointment` usan `Slot_rules` y guardan bajo el bloqueo; la respuesta indica si se puede confirmar (`forceable`) | Antes solo se miraban solapes con otras citas (especificación 6.4) |
| `application/controllers/api/v1/Appointments_api_v1.php` | Crear y editar respetan las reglas; si no, 409 con `violations` y `message` | La API aceptaba citas solapadas o fuera de horario |
| `application/controllers/Booking.php` | La reserva pública vuelve a comprobar y guarda bajo el bloqueo | Evitar dos reservas en el mismo hueco entre la comprobación y el guardado |
| `assets/js/http/calendar_http_client.js`, `assets/js/components/appointments_modal.js` | Envía el tratamiento al comprobar; un rechazo no forzable se muestra en el formulario y una cita arrastrada vuelve a su sitio | Mensajes claros (especificación 9.7) |
| `openapi.yml` | Respuesta 409 | Documentar la API |

## Buscador de huecos (fase 4)

Archivos nuevos: `application/libraries/Slot_finder.php` (motor), `application/controllers/Slot_search.php`
(`POST slot_search/search`), `application/views/components/slot_search_modal.php`,
`assets/js/components/slot_search_modal.js` y `assets/js/http/slot_search_http_client.js`.

El motor recorre los días del rango y, para cada profesional activo que realiza el tratamiento y que el usuario puede
gestionar, toma los periodos libres de `Availability::get_available_periods()`, genera inicios sobre la cuadrícula de
la agenda en los que cabe la duración completa y aplica la franja horaria, la hora preferida con su margen y los días
de la semana. Ordena por fecha y después por cercanía a la hora preferida (o por hora), con el profesional preferido
primero en los empates. Sin hora preferida se detiene en cuanto tiene resultados suficientes. Rango máximo 92 días y
30 resultados.

Los 7 casos de la especificación (8.3) están probados contra datos reales y la generación y el orden con tests
unitarios.

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/views/pages/calendar.php` | Botones «Nueva cita» y «Buscar hueco» en la barra de la agenda e inclusión del buscador | Pantalla principal (especificación 17.1 y 8.1) |
| `assets/css/backend.scss` | Ancho de las horas de la franja | Maquetación |

## Rendimiento del buscador (fase 4)

Medido con 50.000 citas repartidas en dos años: la búsqueda de 14 días tardaba 1,1 s y la de 92 días 7,3 s, porque
`Availability::get_available_periods()` consulta las citas de cada día con una condición que recorre todo el historial
del profesional, y el buscador la llama una vez por profesional y día.

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/libraries/Availability.php` | `preload_period_data()` carga una vez las citas que ocupan hueco, las ausencias, los bloqueos y las excepciones de todo un rango; `get_available_periods()` acepta esos datos en un parámetro opcional `$preloaded` y aplica sobre ellos la misma selección que sus consultas. Sin el parámetro funciona igual que antes | Rendimiento (especificación 8.7) |
| `application/libraries/Slot_finder.php` | Precarga los datos de cada profesional antes de recorrer los días | — |

Resultado: 0,11 s (14 días) y 0,26 s (92 días), con resultados idénticos a los anteriores en cinco búsquedas
comparadas byte a byte.

## Fuera de horario en un solo tono

Más tarde el tono pasó a rayas rojas (ver «Mediodía plegable»).

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `assets/js/utils/calendar_table_view.js`, `calendar_default_view.js` | Antes de abrir, descansos, después de cerrar y días cerrados llevan la clase `fc-out-of-hours` y la misma etiqueta; el descanso deja de llamarse «Pausa» | La clínica quiere ver todo lo que queda fuera de la jornada igual, sin distinguir mañana, mediodía o noche |
| `assets/css/backend.scss` | `fc-out-of-hours`: fondo rojizo suave (`#f6d5d8`) con texto `#8c2f39`, sin la trama gris | Ídem. Las ausencias y los bloqueos conservan su estilo propio |
| `application/language/spanish/translations_lang.php` | `not_working` pasa a «Fuera de horario» | Antes decía «Fuera de la oficina» |

## Versión de los archivos estáticos

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/helpers/asset_helper.php` | `asset_url()` añade al token fijo la fecha de modificación del archivo | El token (`cache_busting_token`) solo cambia con cada versión de Easy!Appointments, así que tras una actualización los navegadores seguían usando el CSS y el JavaScript antiguos |

## Tiempo pasado en amarillo

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `assets/js/utils/calendar_past_time.js` | Nuevo. Añade a cada calendario un evento de fondo `fc-past-time` desde el inicio de la vista hasta la hora actual y lo mueve cada minuto | La clínica quiere ver de un vistazo qué parte del día ya ha pasado |
| `assets/js/utils/calendar_table_view.js`, `calendar_default_view.js` | Llaman a `CalendarPastTime.update()` tras cargar los eventos y exponen `getCalendars()` | Ídem |
| `assets/js/pages/calendar.js`, `application/views/pages/calendar.php` | Arrancan el refresco por minuto e incluyen el script | Ídem |
| `assets/css/backend.scss` | El tiempo libre pasa a blanco (se quita el amarillo de «hoy» de FullCalendar) y ese amarillo (`#f7f2cf`) marca el tiempo pasado, por encima del tono de fuera de horario | Ídem |

## Huecos fuera de la cuadrícula en el buscador

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/libraries/Slot_finder.php` | `slots_in_periods()` ofrece también el inicio exacto de un periodo libre que no cae en la cuadrícula (p. ej. 10:40), además de las horas de la cuadrícula | Un hueco es un hueco: si quedan 10 minutos libres dentro del horario y se buscan 10, debe encontrarse aunque no empiece en punto |
| `tests/Unit/Library/SlotFinderTest.php` | Casos del hueco de 10 minutos que solo cabe fuera de la cuadrícula y del hueco demasiado corto | — |

## Orden y colores de los tratamientos

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/migrations/078_add_wallet_and_infiltracion.php` | Añade Wallet e Infiltración (30 min) y los asigna a los profesionales que ya tienen tratamientos; no toca los existentes | Faltaban en el catálogo de la clínica |
| `application/migrations/079_improve_treatment_color_contrast.php` | Nueva paleta de fondos claros y distintos, legibles con texto negro; las citas que usaban el color anterior del tratamiento lo siguen, las de color propio no cambian | Contraste y distinción entre tratamientos |
| `application/models/Services_model.php` | `DROPDOWN_ORDER`: los desplegables (`get_available_services()`, `to_options()`) siguen el orden de frecuencia de la clínica; el resto de servicios va después, por nombre | Los tratamientos más habituales, primero |

## Mediodía plegable

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `assets/js/utils/calendar_midday.js` | Nuevo. En la vista partida pliega el descanso de mediodía común a todas las columnas de un día, con un botón para abrirlo; si hay visitas o reservas en el intervalo (aunque estén ocultas por un filtro) no se puede plegar, y mientras está plegado no se puede seleccionar ni arrastrar a él. `isMiddayBreak()` decide qué descanso es el de mediodía (el que toca la franja de 12:00 a 16:00) | Ganar altura para las horas de trabajo |
| `assets/js/utils/calendar_table_view.js`, `calendar_default_view.js` | El descanso de mediodía se titula «Mediodía» y se marca con `middayBreak`; la vista partida llama al plegado al cargar eventos y fechas | Ídem |
| `assets/css/backend.scss` | Filas plegadas; el fuera de horario pasa a rayas rojas con texto blanco en negrita y borde negro | Ídem, y que el fuera de horario se distinga mejor |
| `application/language/*/translations_lang.php` | `midday`, `open_midday`, `fold_midday`, `midday_has_appointments` | Textos traducibles |

## Barra única y columnas compactas en la vista partida

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `assets/js/utils/calendar_table_view.js` | La barra de la agenda se mueve dentro del calendario y reúne navegación, filtros y acciones; los filtros de profesional y tratamiento pasan a desplegables simples con «todos»; las columnas crecen a toda su altura y la vista tiene un único scroll, con la misma barra sea cual sea el número de columnas; la cabecera de cada columna, fija al hacer scroll, muestra profesional, día y el cambio Lista / Calendario, y la fila «Todo el día» solo aparece cuando alguna columna tiene un bloqueo o una excepción (`syncAllDaySlot()`) | Más altura para las horas, que es lo que usa recepción |
| `assets/js/utils/calendar_midday.js` | Un único botón de mediodía en la barra pliega o abre el descanso común de todas las columnas a la vez, para que las horas sigan alineadas; no se puede plegar si alguna columna tiene visitas en ese intervalo | Ídem |
| `assets/js/utils/calendar_default_view.js`, `calendar_table_view.js` | El calendario abre a las 08:30 | La clínica abre a las 9:00 |
| `assets/css/backend.scss` | Estilos de la barra única y de las columnas; se quitan las reglas de Select2 y de las etiquetas de los filtros, que ya no se usan | Ídem |
| `application/language/*/translations_lang.php` | `filter_all_providers`, `filter_all_services` | Textos traducibles |

## Sin pie de página en la gestión

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/views/components/backend_footer.php`, `application/views/layouts/backend_layout.php` | Se elimina el pie (créditos de Easy!Appointments, selector de idioma, enlace a la página de reservas y saludo) | Más altura para la agenda. La licencia y los créditos siguen en «Acerca de» |
| `assets/js/layouts/backend_layout.js`, `assets/js/pages/account.js` | Se quita el código del selector de idioma y del saludo del pie | Ya no existen |
| `assets/js/utils/calendar_table_view.js`, `calendar_default_view.js` | La altura de la agenda deja de descontar el pie | Ídem |

## Autocompletado de pacientes (fase 5)

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/migrations/080_add_patient_search_fields.php` | `users.phone_normalized`, rellenado para los pacientes existentes, e índices en nombre, apellidos y teléfono normalizado | Búsqueda rápida y detección de duplicados por teléfono (especificación 11.1, 12.4) |
| `application/helpers/phone_helper.php` | `normalize_phone()`: quita separadores, convierte 00 en +, y añade +34 a los números españoles de 9 cifras | Encontrar un teléfono se escriba como se escriba |
| `application/models/Customers_model.php` | `save()` guarda el teléfono normalizado; `autocomplete()` busca por el principio del nombre, de los apellidos o de cualquiera de sus palabras (cada palabra escrita debe coincidir), o por cifras del teléfono, y devuelve solo id, nombre, apellidos y teléfono de 10 pacientes como máximo | Especificación 12.1, 12.4 y 12.5 |
| `application/controllers/Customers.php` | `autocomplete` con el permiso de ver pacientes; con «limitar el acceso a los pacientes», cada profesional solo ve los suyos y recepción los de sus profesionales | Respetar los permisos (12.5) |
| `assets/js/components/patient_autocomplete.js` | Nuevo. Sugiere pacientes al escribir nombre, apellidos o teléfono de un paciente nuevo, con teclado (flechas, Intro, Escape) y la opción «Crear paciente nuevo» | Especificación 12.1 y 12.2, sin salir de la agenda |
| `assets/js/components/appointments_modal.js`, `appointments_modal.php` | Usa el autocompletado; se quita el botón «Seleccionar» y su lista | La lista partía de los pacientes cargados con la página |
| `application/controllers/Calendar.php` | Deja de cargar 50 pacientes en la página | No descargar pacientes al navegador (12.4) |
| `assets/js/http/customers_http_client.js` | `autocomplete()`; `find()` usa GET como exige el controlador | `find()` fallaba siempre con POST |
