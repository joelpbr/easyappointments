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

## Búsqueda de pacientes con Intro (fase 5)

Sustituye al autocompletado mientras se escribe: con unos 10.000 pacientes, unas pocas letras coinciden con demasiados.

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/helpers/patient_search_helper.php` | `is_specific_patient_search()`: una búsqueda necesita al menos tres letras o seis cifras del teléfono | Que una o dos letras no listen media base de pacientes |
| `application/models/Customers_model.php` | `autocomplete()` pasa a `quick_search()` y no busca si la búsqueda no es concreta | Ídem, también en el servidor |
| `application/controllers/Customers.php` | `quick_search` devuelve 10 pacientes como máximo y si hay más coincidencias (`more`) | Pedir que se afine en lugar de listar cientos |
| `assets/js/components/patient_search.js` | Sustituye a `patient_autocomplete.js`: busca solo al pulsar Intro, avisa si la búsqueda es demasiado amplia, sin consultar al servidor, y cierra los resultados al seguir escribiendo | Petición de la clínica |
| `application/views/components/appointments_modal.php` | Indicación de cómo buscar bajo «Detalles del paciente» | Ídem |

Medido con 10.000 pacientes ficticios (borrados después): unos 20 ms por búsqueda, incluida la petición.

Corrección posterior: bastan 3 letras, también en una sola palabra («lucia»), o 6 cifras del teléfono. Exigir nombre y
apellido rechazaba búsquedas habituales como un nombre solo; el límite de 10 resultados ya evita listas enormes.

## Posibles pacientes repetidos (fase 5)

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/models/Customers_model.php` | `find_possible_duplicates()`: pacientes con el mismo teléfono normalizado o el mismo nombre y apellidos (sin distinguir mayúsculas ni tildes), indicando cuál de las dos cosas coincide; la restricción por profesionales pasa a `restrict_to_providers()`, compartida con la búsqueda | Especificación 12.3 |
| `application/controllers/Customers.php` | `duplicates`, con los mismos permisos que la búsqueda | Ídem |
| `assets/js/components/patient_duplicates.js` | Nuevo. Antes de crear un paciente, si hay posibles repetidos, pregunta: usar uno de ellos, crear el nuevo igualmente o cerrar sin guardar | Nunca fusionar: un teléfono puede ser de una familia y dos personas pueden llamarse igual |
| `assets/js/components/appointments_modal.js` | Al guardar una cita con un paciente nuevo, hace la comprobación; si se elige uno existente, rellena sus datos y sigue guardando | Ídem |
| `assets/js/pages/customers.js`, `application/views/pages/customers.php` | Lo mismo al crear un paciente desde «Pacientes»; elegir uno existente lo abre en lugar de crear otro | Ídem |

## Profesional habitual y primera visita (fase 5)

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/migrations/081_add_patient_usual_provider.php` | `users.id_users_preferred_provider`, opcional, con clave foránea que lo deja vacío si se borra el profesional | Especificación 11.1 |
| `application/models/Customers_model.php` | Guarda y valida el profesional habitual (debe ser un profesional) y lo expone en la API como `preferredProviderId`; `get_visit_summary()` cuenta las visitas pasadas, sin canceladas ni no presentados, y da la última | Ídem; «primera visita según datos disponibles» |
| `application/controllers/Customers.php` | Campo permitido; `find` añade `visits` y `last_visit`; la página recibe los profesionales activos | Ídem |
| `application/views/pages/customers.php`, `assets/js/pages/customers.js` | Selector «Profesional habitual» en la ficha | Ídem |
| `assets/js/components/appointments_modal.js`, `appointments_modal.php` | Al elegir un paciente, muestra «Profesional habitual: …» bajo el profesional, con «Elegir» si es otro o «no hace este tratamiento»; nunca cambia el profesional por sí solo, porque puede venir de la columna donde se creó la cita. «Primera visita» se rellena con «No» si el paciente ya vino y «Sí» si no, y un paciente nuevo marca siempre «Sí» | Especificación 12.1 y 9.3 |
| `openapi.yml` | `preferredProviderId` en `CustomerRecord` y `CustomerPayload` | API documentada |

## Datos fiscales del paciente (fase 5)

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/migrations/082_create_fiscal_parties_table.php` | Tabla `fiscal_parties`, unida al paciente, con `relationship` («patient» para sus propios datos, «other» para otra persona o entidad que paga) | Especificación 11.2 y 11.4: paciente y destinatario de la factura no siempre coinciden |
| `application/helpers/fiscal_id_helper.php` | `validate_fiscal_id()`: DNI y NIE por su letra, NIF de entidades por su dígito o letra de control; distingue formato incorrecto, carácter de control incorrecto y documentos que no se pueden verificar (pasaporte, extranjero, otro) | Especificación 11.3 |
| `application/models/Fiscal_parties_model.php` | Valida sin exigir nada (un número necesita su tipo; código postal español de 5 cifras), guarda el número normalizado, borra el registro si se vacía y anota en la auditoría qué campos cambian, nunca su contenido | No bloquear el alta sin DNI; no guardar datos sensibles en el registro |
| `application/controllers/Customers.php` | `store` y `update` aceptan `fiscal` y lo validan antes de guardar al paciente; `find` lo devuelve | Guardar todo de una vez |
| `application/views/pages/customers.php`, `assets/js/pages/customers.js`, `assets/js/http/customers_http_client.js` | Sección «Datos fiscales» en la ficha, con una indicación de qué documentos se comprueban | Escenario M |

## Historial administrativo en la ficha (fase 5)

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `assets/js/pages/customers.js` | La lista plana de citas pasa a un historial: resumen (visitas, última visita, canceladas, no presentados, profesionales que le han atendido) y, debajo, «Próximas citas» y «Citas anteriores» con su estado y el nombre del tratamiento en el momento de la cita. Cada profesional y recepción siguen viendo solo las citas de sus profesionales | Especificación 11.5. Las facturas relacionadas llegarán con la fase de facturación |
| `application/controllers/Customers.php` | La página recibe los estados de cita para mostrar sus nombres | Ídem |
| `assets/css/backend.scss` | Estilo del resumen | Ídem |

Con este bloque la fase 5 queda terminada.

## Bloqueos y festivos (fase 6)

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/migrations/083_add_blocked_period_scope.php` | `blocked_periods`: `type`, `recurrence` y `recurrence_until`; tabla `blocked_periods_providers` (sin profesionales = toda la clínica); tabla `holidays` (festivos añadidos a mano); ajuste `holiday_region` (Cataluña) | Especificación 10.1 a 10.4 |
| `application/models/Blocked_periods_model.php` | `get_for_period()` expande las repeticiones (`expand_occurrences()`) y filtra por profesional; `get_affected_appointments()` lista las citas no canceladas que caen en el periodo | 10.2, 10.3 y 10.7 |
| `application/libraries/Availability.php`, `Slot_rules.php` | Usan los bloqueos del profesional y de toda la clínica, con sus repeticiones | 10.9: disponibilidad, buscador, reservas y API |
| `application/controllers/Blocked_periods.php`, página y JS | Tipo, profesionales y repetición en el formulario; si hay citas afectadas no guarda: las muestra con «Reprogramar» y pide «Bloquear igualmente». Nunca cancela citas | 10.5 y 10.7 |
| `assets/js/utils/calendar_blocked_periods.js`, vistas de agenda, `backend.scss` | Los bloqueos se pintan de fondo, en rojo con rayas como el fuera de horario y con su motivo, solo en las columnas afectadas; botón «Bloquear agenda» | 10.6; la clínica pidió el mismo rojo que el mediodía |
| `application/libraries/Holiday_calendar.php`, `application/controllers/Holidays.php`, página y JS | Festivos nacionales y de Cataluña o Madrid calculados (Pascua incluida), festivos locales a mano, y «Cerrar la clínica» por día, que crea un bloqueo de toda la clínica | 10.4: el festivo no cierra por sí solo |
| `openapi.yml` | `type`, `providerIds`, `recurrence` y `recurrenceUntil` en los bloqueos | API documentada |

Las aperturas excepcionales (10.8) ya existían como excepciones de horario. Con esto la fase 6 queda terminada.

## Precio de la cita y total del día

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/migrations/084_add_appointment_price.php` | `appointments.price`, con el precio actual del tratamiento para las citas existentes | Precio por defecto por tratamiento, editable en cada cita |
| `application/models/Appointments_model.php` | `set_price()`: el precio enviado (admite coma decimal) o, si es nueva o se deja vacío, el del tratamiento; los cambios de precio quedan en la auditoría; `price` en la API | Que cambiar el precio del tratamiento no reescriba el pasado |
| `application/controllers/Calendar.php`, `appointments_modal.php`, `appointments_modal.js`, vistas de agenda | Campo «Precio (€)» en la cita, que toma el precio del tratamiento al elegirlo | Petición de la clínica |
| `assets/js/utils/calendar_day_total.js`, `calendar.php` | «Total» en la barra de la agenda: suma de las citas mostradas, sin canceladas ni no presentados, y el desglose por día al pasar el ratón; sigue los filtros de profesional y tratamiento | Suma total al cabo del día |
| `openapi.yml` | `price` en las citas | API documentada |

## Faltas de asistencia

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `assets/js/utils/calendar_appointment_actions.js`, `calendar_event_popover.js`, vistas de agenda | Botón «Falta de asistencia» en la ficha de la cita, solo cuando ya ha empezado y no está cancelada ni marcada: pide confirmación y la guarda como «No presentado», sin enviar mensajes | Registrar la falta en un clic |
| `application/models/Customers_model.php` | `get_visit_summary()` cuenta también las faltas y la última | Ídem |
| `assets/js/components/appointments_modal.js`, `appointments_modal.php` | Al elegir un paciente con faltas, aviso «Este paciente tiene N falta(s) de asistencia, la última el …» | Aviso al volver a darle cita |

## Catalán por defecto y castellano

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/language/catalan/translations_lang.php` | Traducidos al catalán los 146 textos nuevos de la clínica, y la terminología de la clínica en los textos originales (Pacient, Tractament, Professional, Recepcionista…) | Petición de la clínica |
| `application/config/config.php` | Solo se ofrecen catalán y castellano; el idioma del navegador ya no decide: se empieza en catalán | Catalán predeterminado |
| `application/views/components/backend_header.php`, `assets/js/layouts/backend_layout.js`, `application/controllers/Localization.php` | Cambio Català / Castellano en el menú de usuario, que queda guardado en el usuario | El selector de idioma estaba en el pie, que se quitó |
| `application/migrations/085_set_catalan_as_default_language.php` | Idioma por defecto catalán; los usuarios con un idioma que ya no se ofrece pasan a catalán | Ídem |
| `.env.example`, `docker-compose.yml` | `LANGUAGE=catalan` por defecto | Ídem |

Corrección posterior: al elegir un paciente con faltas en el aviso de «Posible paciente repetido», la cita ya no se guarda sola: el formulario queda abierto con el aviso visible, porque si no se cerraba sin que se viera.

Segunda corrección: el aviso de faltas era solo un recuadro en la sección del paciente y se podía guardar sin verlo. Al elegir un paciente con faltas para una cita nueva aparece además una ventana que hay que cerrar.

Tercera corrección, a petición de la clínica: sin ventana emergente, solo el aviso amarillo. Cada paciente guarda su número de faltas (`users.no_show_count`, migración 086), que se recalcula al guardar o borrar sus citas y se muestra, solo si tiene alguna, en la ficha que se abre al pulsar la cita en la agenda, en la ficha del paciente y en el aviso del formulario.

## Formulario de cita sin scroll

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/views/components/appointments_modal.php`, `assets/css/backend.scss` | En ordenador (≥ 992 px) el formulario es más ancho, con la cita a la izquierda y el paciente a la derecha, los campos de dos en dos, comentario y observaciones de dos líneas y márgenes más pequeños; en el móvil sigue en una columna | Que una cita nueva quepa en pantalla sin scroll (antes 1.216 px de alto; ahora cabe en 1366×768) |

## Estado y primera visita automáticos al crear una cita

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/views/components/appointments_modal.php`, `assets/js/components/appointments_modal.js`, vistas de agenda | El estado solo se muestra al editar una cita: una nueva empieza siempre como «Programada». Se quita el campo «Primera visita» del formulario | Petición de la clínica: no son decisiones de recepción al dar cita |
| `application/models/Appointments_model.php` | `set_first_visit()`: al crear una cita sin indicarlo, es primera visita si el paciente no tiene ninguna cita anterior; un paciente elegido de la lista o que coincide con uno existente la tiene, uno creado ahora no | Ídem; la API puede seguir indicándolo |
| `assets/js/components/patient_search.js` | `onCreate` pasa a ser opcional | Ya no hay nada que marcar al crear un paciente |

## Sin botones de paciente nuevo

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/views/components/appointments_modal.php`, `assets/js/components/appointments_modal.js` | Se quita el botón «Nuevo» de «Datos del paciente». Cambiar el nombre o el apellido de un paciente elegido lo suelta y lo trata como paciente nuevo (al guardar se siguen comprobando los posibles repetidos); cambiar el teléfono o el correo actualiza el elegido | Petición de la clínica: el botón no aportaba nada |
| `assets/js/components/patient_search.js` | Se quita la opción «Crear paciente nuevo» de los resultados: si el paciente no está, se sigue escribiendo | Ídem |

## Paciente existente o nuevo

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `application/views/components/appointments_modal.php`, `assets/js/components/appointments_modal.js`, `backend.scss` | Selector «Paciente existente / Paciente nuevo» en «Datos del paciente» (existente por defecto). Existente: hay que buscarlo y elegirlo (si no, no se guarda y lo dice); sus datos quedan protegidos (en gris) hasta pulsar «Editar datos», y «Cambiar de paciente» lo suelta. Nuevo: se escribe libremente, sin búsqueda, y al guardar se avisa si el teléfono o el nombre coinciden con otro paciente, con la opción de usar ese o seguir como nuevo. Al editar una cita, su paciente queda protegido | Petición de la clínica: dejar claro si el paciente es nuevo y no cambiar los datos de uno existente por error |

## Logo de la clínica y favicon

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `assets/img/logo.png`, `logo-192.png`, `logo-16x16.png`, `favicon.ico` | Generados a partir del logo nuevo de la clínica (1254 px): logo de 512 px para la reserva y los correos, 192 px para la cabecera y el icono de la página, y un `favicon.ico` de 16, 32 y 48 px | Logo propio en lugar del de Easy!Appointments |
| `application/views/components/backend_header.php` | Logo de la clínica cargado con `asset_url()` y nombre «Clínica Podològica» | Que el navegador no siga mostrando una versión antigua |
| `application/views/layouts/*.php` | Icono de 192 px y `apple-touch-icon` | Icono al guardar la página en el móvil |

## Días, sumas por profesional y columnas en lista

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `assets/js/utils/calendar_table_view.js`, `calendar_default_view.js`, `backend.scss` | Días con el nombre completo; en la vista partida, en relación con hoy («Avui, divendres 13/11», «Demà», «Demà passat», «Pròxim dijous 19/11» solo dentro de la semana que viene), en una segunda línea bajo el nombre; las vistas de semana y mes también con el nombre completo | Petición de la clínica |
| `assets/js/utils/calendar_table_view.js` | Botón «Avui» en el calendario desplegable de la fecha | Ídem |
| `assets/js/utils/calendar_table_view.js`, `backend.scss` | Una columna en modo lista se queda fija y tiene su propio scroll (la rueda sobre ella no mueve las demás), mientras las columnas en modo calendario siguen con el scroll compartido | Ídem |
| `assets/js/utils/calendar_day_total.js`, `calendar_table_view.js`, `calendar_event_content.js`, `calendar.php` | Se quita el «Total» de la barra; cada profesional muestra la suma de su día junto al nombre («Sebas (330€)»), y cada tarjeta el precio de esa cita junto al tratamiento («Quiropodia (30€)») | Ídem; el precio puede ser distinto del del tratamiento |

Corrección posterior: el plegado se hace con una hoja de estilo que selecciona las filas del descanso por su hora, en lugar de marcar las filas (FullCalendar las vuelve a dibujar al pasar una columna de lista a calendario, lo que deshacía el plegado y hacía que el botón fallara). Las columnas en modo lista se ignoran: las de calendario siguen plegadas y el botón no desaparece.

Segunda corrección: al abrir o plegar el descanso, FullCalendar no recolocaba las citas (quedaban a la altura de antes). Ahora, tras cambiar el estilo, se cambia una opción de las franjas para que dibuje y mida de nuevo, y cada cita queda en su hora, con o sin columnas en modo lista.

## Filtro de profesionales con selección múltiple

| Archivo | Cambio | Motivo |
| --- | --- | --- |
| `assets/js/utils/calendar_table_view.js` | El filtro de profesionales pasa a ser una lista con una casilla por profesional (sin opción «todos»); siempre queda al menos uno marcado; cerrado dice «Professional: tots» si están todos y, si no, los nombres marcados. La selección se recuerda como antes | Petición de la clínica: elegir varios profesionales a la vez |

Cambio posterior: el botón de la agenda se llama «Bloquejos» y lleva a la lista de bloqueos (para añadir, editar o quitar), en lugar de abrir directamente uno nuevo; se muestra a quien puede ver los bloqueos.

Corrección posterior: el aviso de faltas también aparece al abrir una cita existente de un paciente con faltas (antes solo al elegirlo en la búsqueda, por eso unas veces salía y otras no).

Cambio posterior: en el tema `podologia-sabadell` (el activo), los botones principales usan el azul cian `#029ecb` de fondo y de borde; al pasar el ratón siguen en azul oscuro.

Cambio posterior: los botones de la barra de la agenda (anterior, siguiente y «Migdia») tienen fondo `#029ecb`, texto negro y sin borde.

Cambio posterior: toda la barra de la agenda (selector de días, fecha, flechas, filtros y botones) tiene el mismo aspecto: fondo `#029ecb`, texto negro, la misma letra y sin bordes; al pasar el ratón el fondo se oscurece un poco (`#028bb3`).

Cambio posterior: los desplegables de la barra de la agenda («1 Dia», «Tractament» y el filtro de la vista normal) se muestran como el de profesionales, con `initializeSelectDropdown()` de `ui.js`: un botón con la opción elegida y una lista con selector redondo por opción (y cabecera por grupo). El `select` original sigue oculto debajo, así que su valor y su evento `change` funcionan igual.

Cambio posterior: la barra de la agenda vuelve a fondo blanco (texto negro, misma letra, sin bordes; gris claro `#e9ecef` al pasar el ratón); solo las dos flechas de cambiar de día mantienen el fondo `#029ecb`.

Cambio posterior: la barra de desplazamiento vertical de la agenda se oculta (se sigue desplazando con la rueda o el dedo), así no ocupa ancho y las columnas caben sin desplazamiento horizontal.

Cambio posterior: en el modo lista de la agenda se quita el punto de color y el día de arriba (ya está en la cabecera del profesional); cada fila tiene el color de su tratamiento, como las tarjetas del modo calendario, y las citas que ya han acabado llevan encima las rayas de las horas fuera de horario, manteniendo su color (se revisa cada minuto, `markEndedListEvents()`).

Cambio posterior: nuevo tratamiento «Revisión SP» (30 min, 40 €, color `#f7d6ae`), ofrecido por todos los profesionales y situado entre «Entrega SP» y «Cura» en los desplegables (migración `087_add_sp_review_treatment.php` y `Services_model::DROPDOWN_ORDER`).

## Ficha clínica del paciente

Amplía el alcance inicial («No se pretende construir inicialmente una historia clínica completa»): la clínica pide una ficha con datos clínicos y notas por visita.

| Archivo | Cambio |
| --- | --- |
| `application/migrations/088_add_clinical_records.php` | Tablas `patient_records` (fecha de nacimiento, procedencia, profesión, actividad física, antecedentes médicos, alergias; una por paciente) y `appointment_records` (motivo, diagnóstico, tratamiento, observaciones, ortesis; una por cita), con borrado en cascada; columna `services.record_template` con el tipo de ficha de cada tratamiento |
| `application/libraries/Clinical_record_templates.php` | Tipos de ficha y sus campos, ortesis y precios, limpieza de datos y precio que da la ficha a la visita; solo admin y profesionales (`can_access()`) |
| `application/models/Patient_records_model.php`, `Appointment_records_model.php` | Lectura y guardado; el registro de auditoría solo anota qué campos cambian, nunca su contenido |
| `application/controllers/Clinical_records.php` | `patient`, `save_patient`, `appointment`, `save_appointment`; comprueba rol y acceso al paciente y registra cada apertura (`viewed`) |
| `application/views/components/clinical_record_modal.php`, `assets/js/components/clinical_record_modal.js`, `assets/js/http/clinical_records_http_client.js` | Ventana de la ficha y panel lateral de cada visita, con aviso si se cierra con cambios sin guardar |
| `assets/js/utils/calendar_event_popover.js`, `calendar_table_view.js`, `calendar_default_view.js`, `Calendar.php`, `calendar.php` | Botón «Fitxa» en la tarjeta de la cita (solo admin y profesionales) |
| `application/views/pages/services.php`, `assets/js/pages/services.js`, `Services_model.php`, `Services.php`, `openapi.yml` | Campo «Tipus de fitxa» del tratamiento (`recordTemplate` en la API) |

Tipos de ficha (asignados por la migración, editables en Tractaments):

- **Peus i observacions** (Quiropodia, Cura, Cura PV, Ortonixia, Wallet): `[DIBUJO PIES]` y Observaciones.
- **Cirurgia** (Cirugía): `[DIBUJO PIES]`, Motivo, Diagnóstico, Tratamiento, Precio de la cirugía (pasa a ser el precio de la visita) y Observaciones.
- **Exploració** (Exploración): Motivo, Diagnóstico y Tratamiento.
- **Silicona** (Silicona): Motivo, Diagnóstico y Ortesis (cresta 30 €, interdigital 1º 27 €, interdigital 4º 25 €, omega 30 €), cuyo precio pasa a ser el de la visita.
- **Només observacions** (Entrega SP, Revisión SP, Infiltración, Moldes, Otro y los tratamientos nuevos): Observaciones en un cuadro grande.

El NIF/NIE de la ficha es el número de documento de los datos fiscales: se guarda una sola vez. El dibujo de los pies es de momento un marcador `[DIBUJO PIES]`.

Cambio posterior: la ficha clínica deja el panel lateral y pasa a pestañas («Dades», «Antecedents» con antecedentes y alergias, «Visites»). En «Visites» la lista de visitas queda a la izquierda y la ficha de la visita elegida a la derecha, dentro de la misma ventana, con su propio botón «Desa la visita». La cabecera muestra el paciente con edad, documento y teléfono, y un aviso rojo con las alergias cuando tiene. Abierta desde una cita de la agenda, se abre en «Visites» con esa visita; si se cambia de visita o se cierra con cambios sin guardar, pregunta antes.

Cambio posterior: la ficha queda con dos pestañas, «Dades» y «Visites»; los antecedentes médicos y las alergias pasan a «Dades», uno al lado del otro debajo de los datos.

Cambio posterior: la pestaña «Dades» de la ficha cabe sin desplazamiento desde 1280×720 (con o sin aviso de alergias): tres filas de campos (nombre, apellidos y NIF/NIE; nacimiento, teléfono y dirección; procedencia, profesión y actividad física) y debajo antecedentes y alergias, que ocupan el alto restante en pantallas grandes.

Cambio posterior: en la página de Pacients manda la ficha (para admin y profesionales). Nombre, apellidos, teléfono, dirección y NIF/NIE ya no salen en el formulario de la página: se editan en la ficha, que se abre con el botón «Fitxa» junto al nombre del paciente. Al guardar la página se conservan los valores de la ficha. «Afegir» abre una ficha vacía: al guardarla se crea el paciente (con el aviso de posibles repetidos) y la página lo muestra. La secretaría, que no ve la ficha, sigue con el formulario completo.

Cambio posterior: el aviso rojo de alergias no sale cuando el texto dice que no tiene («No refereix al·lèrgies conegudes», «Sin alergias», «Cap», «Ninguna»...).

Datos de demostración (13/11/2026): se borran los pacientes y las visitas de prueba y se generan 636 pacientes y 763 visitas del 26/10 al 03/12 (71% quiropodias, 15% exploraciones, el resto otros tratamientos; el 20% de los pacientes vienen dos veces con al menos 3 semanas entre visitas), con todos los datos rellenados (contacto, NIF/NIE válidos, ficha clínica y notas de las visitas realizadas). Los emails usan dominios reservados example.com/.net/.org. Copia previa en storage/backups/easyappointments-backup-2026-11-13-181816.gz.

Cambio posterior: a la derecha de la fecha de nacimiento, la ficha muestra la edad («Edat», p. ej. «35 anys»), calculada al momento al escribir o elegir la fecha; no se guarda.

Cambio posterior: nuevo tratamiento «Revisión» (10 min, gratuito, color `#b9e4f0`, ficha «Només observacions»), ofrecido por todos los profesionales y situado justo después de «Quiropodia» en los desplegables (migración `089_add_review_treatment.php` y `Services_model::DROPDOWN_ORDER`).

Cambio posterior: la página de Pacients ya no muestra las notas, el código postal (el de los datos fiscales sigue) ni la zona horaria. Al guardar, las notas y el código postal que ya tenga el paciente se conservan, y la zona horaria es siempre la de la clínica (Europe/Madrid).

Datos de demostración de un año (13/11/2026): 10.000 pacientes en el fichero con todos los datos (9.827 con DNI/NIE; los niños pequeños sin documento), 7.451 visitas del 17/11/2025 al 03/12/2026 (69% quiropodias, 15% exploraciones, sobre todo primeras visitas) y las notas clínicas de las 6.259 visitas realizadas. 4.615 pacientes tienen visitas ese año: los habituales de quiropodia vuelven cada 6-10 semanas (hasta 6 visitas) y siempre hay al menos 21 días entre visitas; el resto son pacientes de años anteriores. La agenda respeta los festivos de Cataluña (cerrados como bloqueos), las vacaciones de Navidad (24/12-06/01) y de verano (03/08-23/08), una semana de vacaciones de cada profesional y el bloqueo de diciembre de Sara y Sebas. Copia previa en storage/backups/easyappointments-backup-2026-11-13-184518.gz.

Cambio posterior: la búsqueda de la página de Pacients busca en un solo campo elegido en un desplegable: Nombre, Apellidos (cada palabra al inicio de una palabra, sin importar acentos ni mayúsculas), NIF/NIE (contiene, sin espacios ni guiones) o Teléfono (los dígitos, en cualquier formato). Muestra «X de 10.000 pacients» (o el total sin búsqueda), ordena los resultados por nombre (sin búsqueda, los modificados últimamente primero) y cada fila incluye el documento. `Customers_model::search_by_field()` y `count_patients()`; la restricción de pacientes por profesional se aplica en la propia consulta para que los números cuadren.

Cambio posterior: la búsqueda de Pacients deja el desplegable y busca a la vez en nombre, apellidos, teléfono y NIF/NIE: cada palabra escrita tiene que coincidir con alguno («mar gar», «vidal 614»); un texto sin letras se busca como teléfono o número de documento en cualquier formato. `Customers_model::search_patients()`.

Cambio posterior: la lista de Pacients muestra una línea por paciente (unos 34 px en lugar de unos 80): el nombre a la izquierda y, a la derecha, solo lo que también se busca, el teléfono y el DNI/NIE (sin email). Cabe más del doble de pacientes a la vista.

Cambio posterior: todo el tiempo fuera de horario (madrugada, noche, mediodía, días sin trabajo, festivos, bloqueos e indisponibilidades «No disponible») usa el mismo fondo gris `#7f7f7f` con las rayas diagonales; el texto de las indisponibilidades va en blanco con sombra para leerse sobre el gris.

Cambio posterior: la lista de Pacients alterna filas blancas y gris claro (`#f1f3f5`, más oscuro al pasar el ratón) y muestra el teléfono y el DNI en dos columnas alineadas y más separadas.

Cambio posterior: todo lo que está fuera de horario tiene exactamente el mismo aspecto que el mediodía (fondo `#7f7f7f` con rayas, sin borde, texto blanco en negrita y cursiva, sin contorno ni sombra), también las indisponibilidades «No disponible», que antes tenían borde y otro tamaño de letra. Una sola regla en `backend.scss`.
