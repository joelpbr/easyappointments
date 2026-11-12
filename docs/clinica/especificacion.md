En todo momento se trabajará con git. Haciendo gitflow de senior.
Con commits automaticos y todo.


1. CONTEXTO DE LA CLÍNICA

El sistema se utilizará en una clínica de podología española.

La clínica dispone inicialmente de 2 o 3 profesionales y una recepción que organiza sus citas.

El trabajo habitual consiste en atender llamadas o solicitudes de pacientes y asignarles una cita.

Los pacientes pueden presentar diferentes preferencias:

Quieren visitarse con un profesional concreto.

Les da igual el profesional, pero necesitan una hora concreta.

Quieren un profesional específico, pero tienen flexibilidad horaria.

Necesitan una cita un día específico.

Buscan la primera cita disponible.

Solo pueden acudir por la mañana.

Solo pueden acudir por la tarde.

Necesitan una cita de determinada duración.

Tienen preferencias simultáneas de profesional, fecha y horario.

El sistema debe resolver fácilmente todos estos casos.

La recepción debe ser capaz de encontrar y asignar una cita en pocos segundos.

No se pretende construir inicialmente una historia clínica completa.

El alcance principal es:

Agenda.

Profesionales.

Tratamientos.

Pacientes.

Disponibilidad.

Festivos y ausencias.

Búsqueda inteligente de huecos.

Recordatorios por WhatsApp.

Importación de pacientes.

Facturación.

Integración VERI*FACTU.

Seguridad y despliegue.

2. ANÁLISIS DEL PROYECTO ORIGINAL

Antes de implementar cualquier modificación, examina el repositorio.

La versión pública estable de referencia al redactar esta especificación es Easy!Appointments 1.6.1, publicada en octubre de 2026.

No obstante, debes comprobar la versión que realmente esté disponible y seleccionar una versión estable compatible.


2.2. Funcionalidades nativas que deben estudiarse

Revisar especialmente:

Gestión de profesionales.

Gestión de secretaría.

Calendario administrativo.

Vista en tabla.

Filtros de profesionales.

Servicios.

Duración de servicios.

Pacientes.

Campos personalizados.

Horarios de trabajo.

Periodos bloqueados.

Excepciones de jornada.

Vacaciones y ausencias.

API REST.

Webhooks.

Notificaciones.

Estados de citas.

Configuración regional.

Seguridad y permisos.

Preparar una matriz de funcionalidades:

Función

Nativa

Requiere modificación

Desarrollo nuevo

Varios profesionales

Por comprobar

Por comprobar

Por comprobar

Vista simultánea

Por comprobar

Por comprobar

Por comprobar

Autocompletado

Por comprobar

Por comprobar

Por comprobar

WhatsApp

Por comprobar

Por comprobar

Por comprobar

VERI*FACTU

Por comprobar

Por comprobar

Por comprobar

Rellenar esta matriz después de revisar el código.

No duplicar funcionalidades que ya funcionen correctamente.

3. CUENTA CENTRALIZADA DE RECEPCIÓN

3.1. Objetivo

Una única cuenta administrativa debe poder gestionar simultáneamente todos los profesionales.

No debe ser necesario cambiar de usuario para asignar una cita a un profesional diferente.

Desde recepción se podrán realizar las operaciones habituales:

Consultar todos los calendarios.

Crear citas.

Editar citas.

Cancelar citas.

Reprogramar citas.

Cambiar profesionales.

Gestionar pacientes.

Consultar tratamientos.

Consultar disponibilidad.

Gestionar bloqueos.

Consultar recordatorios.

Generar facturas si la cuenta tiene permisos.

3.2. Cuentas individuales opcionales

Aunque recepción pueda gestionarlo todo desde una cuenta, debe mantenerse la posibilidad de que cada profesional tenga su propia cuenta.

El profesional podrá consultar su calendario y realizar las operaciones expresamente autorizadas.

3.3. Roles

Implementar o adaptar los siguientes roles:

Administrador

Acceso completo a todas las funcionalidades autorizadas del sistema.

Recepción

Gestión de agendas, citas, pacientes y recordatorios. Facturación según permisos.

Profesional

Acceso a su propia agenda y a los datos necesarios para su actividad.

Los permisos deben aplicarse también en el backend y en las API, no únicamente ocultando botones.

4. GESTIÓN DE PROFESIONALES

Inicialmente existirán 2 o 3 profesionales.

El sistema debe poder ampliarse posteriormente sin modificar su arquitectura.

4.1. Campos del profesional

ID interno.

Nombre.

Apellidos.

Nombre visible en agenda.

Estado activo o inactivo.

Color identificativo opcional.

Horario habitual.

Vacaciones.

Ausencias.

Tratamientos que puede realizar.

Duración específica de tratamientos, si corresponde.

Preferencias de agenda.

4.2. Requisitos

Permitir:

Crear profesionales.

Modificar profesionales.

Desactivar profesionales.

Cambiar sus horarios.

Definir tratamientos habilitados.

Definir días libres.

Consultar sus citas.

Reasignar citas entre profesionales.

Un profesional desactivado debe conservar su historial.

El profesional habitual de un paciente no debe impedir asignar una cita a otro profesional.

5. AGENDA MULTIPROFESIONAL

Esta será la pantalla principal y la funcionalidad más importante.

5.1. Vista predeterminada

Al entrar en la aplicación, recepción debe visualizar por defecto los calendarios de los 2–3 profesionales simultáneamente.

Los profesionales se mostrarán en columnas y las horas en filas.

Ejemplo:

Hora

Ana

Carlos

Laura

09:00

Quiropodia

Libre

Cirugía

09:15

Quiropodia

Exploración

Cirugía

09:30

Libre

Exploración

Cirugía

09:45

Cura

Libre

Libre

10:00

Libre

Moldes

Ortonixia

10:15

Silicona

Libre

Libre

El ejemplo es ilustrativo.

Las citas reales deben representarse como bloques continuos cuya altura corresponda a su duración.

No generar visualmente una cita independiente por cada intervalo ocupado.

5.2. Modos de visualización

Implementar:

Todos los profesionales.

Un profesional individual.

Varios profesionales seleccionados.

Vista diaria.

Vista semanal.

Vista mensual, si resulta útil.

Navegación por fechas.

La vista diaria con todos los profesionales será la predeterminada para recepción.

5.3. Filtros

Incluir un filtro visible sobre el calendario:

Profesionales:

Todos.

Ana.

Carlos.

Laura.

Selección múltiple.

Los nombres reales serán configurables.

También permitir filtrar por tratamiento cuando resulte útil.

Los filtros deben aplicarse sin recargar toda la página innecesariamente.

Guardar preferentemente las últimas preferencias de visualización por usuario.

5.4. Información de las citas

Cada cita debe mostrar:

Nombre del paciente.

Apellidos, completos o abreviados según espacio.

Tratamiento.

Hora.

Duración.

Indicador de primera visita.

Estado cuando corresponda.

Los colores principales de los bloques deben corresponder al tratamiento.

El encabezado de cada columna puede utilizar un identificador visual del profesional.

No mezclar el color del profesional con el color del tratamiento de forma confusa.

5.5. Interacciones

Permitir:

Crear una cita haciendo clic en un hueco.

Abrir una cita existente.

Editar datos.

Cambiar profesional.

Cambiar fecha.

Cambiar hora.

Cambiar duración.

Cancelar cita.

Reprogramar cita.

Si la biblioteca de calendario existente lo permite de forma fiable, implementar arrastrar y soltar y redimensionar citas.

Estas operaciones deberán comprobarse en el servidor antes de guardarse.

5.6. Actualización de la agenda

Si dos personas utilizan la agenda simultáneamente, los cambios deben reflejarse rápidamente.

Utilizar actualización periódica o mecanismos de eventos apropiados.

Evitar que recepción trabaje con información obsoleta.

6. INTERVALOS Y DURACIONES

6.1. Intervalos visuales

La agenda debe mostrar intervalos de 15 minutos por defecto.

Ejemplo:

09:00.

09:15.

09:30.

09:45.

10:00.

10:15.

Permitir modificar este intervalo desde configuración.

6.2. Duración de tratamientos

Cada tratamiento tendrá una duración predeterminada.

Ejemplos:

15 minutos.

30 minutos.

45 minutos.

60 minutos.

90 minutos.

Estas duraciones son ejemplos y no deben establecerse como valores clínicos definitivos.

6.3. Duración personalizada

Durante la creación de una cita debe poder modificarse su duración.

Permitir introducir duraciones personalizadas en minutos, incluso si no coinciden exactamente con los intervalos de la cuadrícula, siempre que sean válidas.

Por ejemplo:

Una cita puede comenzar a las 09:00 y durar 25 minutos.

El calendario debe representar correctamente la hora real de finalización.

6.4. Comprobación de conflictos

Antes de crear o modificar una cita comprobar:

Horario del profesional.

Citas existentes.

Descansos.

Vacaciones.

Festivos.

Bloqueos.

Duración solicitada.

Disponibilidad completa del intervalo.

No permitir que dos citas se solapen accidentalmente para el mismo profesional.

Controlar también los casos de creación simultánea desde dos sesiones.

7. TRATAMIENTOS Y COLORES

Crear un catálogo editable de tratamientos.

7.1. Tratamientos iniciales obligatorios

Quiropodia.

Exploración.

Cirugía.

Cura.

Cura PV.

Entrega SP.

Moldes.

Silicona.

Ortonixia.

Otro / tratamiento personalizado.

Mantener exactamente estas denominaciones inicialmente.

7.2. Información del tratamiento

Identificador.

Nombre.

Color.

Duración predeterminada.

Precio opcional.

Descripción.

Profesionales habilitados.

Estado activo/inactivo.

7.3. Paleta inicial

Tratamiento

Color

Quiropodia

Azul

Exploración

Verde

Cirugía

Rojo

Cura

Rosa

Cura PV

Naranja

Entrega SP

Turquesa

Moldes

Amarillo

Silicona

Violeta

Ortonixia

Morado

Otro

Gris

Los colores deberán almacenarse como valores configurables, preferiblemente hexadecimales.

No codificar esta lista de manera rígida dentro del frontend.

7.4. Personalización

Permitir:

Añadir tratamientos.

Cambiar nombres.

Cambiar colores.

Cambiar duraciones.

Definir precios.

Asociar tratamientos a profesionales.

Desactivar tratamientos.

Las citas históricas deben conservar correctamente la información del tratamiento correspondiente al momento de la visita.

7.5. Accesibilidad

No depender únicamente del color.

Mostrar siempre una etiqueta identificativa del tratamiento cuando el espacio lo permita.

Asegurar contraste adecuado entre texto y fondo.

8. BUSCADOR INTELIGENTE DE DISPONIBILIDAD

Esta funcionalidad es prioritaria y debe desarrollarse aunque sea necesario ampliar el buscador nativo de Easy!Appointments.

La agenda tendrá dos formas de trabajo:

Calendario visual.

Buscador inteligente de huecos.

Ambas funcionalidades deberán estar disponibles desde la pantalla principal.

8.1. Botón principal

Añadir un botón visible:

Buscar hueco

Debe abrir un formulario rápido de búsqueda.

8.2. Filtros de búsqueda

Permitir indicar:

Tratamiento.

Duración de cita.

Profesional.

Fecha.

Intervalo de fechas.

Hora concreta.

Franja horaria.

Margen de flexibilidad alrededor de una hora preferida.

Cualquier día.

Cualquier hora.

El campo profesional debe ofrecer:

Cualquiera.

Profesional específico.

Varios profesionales seleccionados.

8.3. Casos de uso obligatorios

Caso 1: importa el profesional, pero no la hora

El paciente dice:

"Quiero visitarme con Ana, me da igual cuándo".

El sistema deberá mostrar los próximos huecos disponibles de Ana, ordenados cronológicamente.

Caso 2: importa la hora, pero no el profesional

El paciente dice:

"Solo puedo a las 18:00, me da igual con quién".

El sistema deberá comprobar los tres calendarios y mostrar qué profesionales están disponibles a esa hora.

Si nadie está libre exactamente a las 18:00, podrá proponer horas cercanas según el margen seleccionado.

Caso 3: importan el profesional y la fecha

El paciente dice:

"Quiero con Carlos el jueves por la tarde".

El sistema deberá filtrar:

Carlos.

Jueves.

Horario de tarde.

Duración requerida.

Caso 4: primera cita disponible

El paciente dice:

"Me da igual quién y cuándo, quiero venir lo antes posible".

El sistema deberá buscar los primeros huecos entre todos los profesionales habilitados para ese tratamiento.

Los resultados se ordenarán por fecha y hora.

Caso 5: hora complicada

El paciente dice:

"Solo puedo aproximadamente a las 17:30".

El sistema deberá poder mostrar resultados cercanos.

Ejemplo:

Ana — 17:15.

Carlos — 17:45.

Laura — 18:00.

Estas horas son ejemplos, no resultados reales.

El buscador debe ordenar por proximidad a la hora solicitada, teniendo en cuenta la preferencia del usuario.

Caso 6: duración especial

El paciente necesita una cita de 45 minutos.

El sistema no debe mostrar huecos de 15 o 30 minutos.

Debe garantizar disponibilidad continua durante los 45 minutos.

Caso 7: profesional habitual

Un paciente ya registrado suele visitarse con Ana.

Al buscar disponibilidad:

Se puede sugerir Ana como profesional preferido.

Debe seguir siendo posible seleccionar cualquier otro profesional.

La preferencia histórica nunca debe convertirse en una restricción automática.

8.4. Resultados

Cada resultado mostrará:

Día.

Fecha.

Hora.

Profesional.

Duración.

Tratamiento seleccionado.

Ordenar según:

Primera fecha disponible.

Hora más cercana a la solicitada.

Profesional preferido, si corresponde.

Permitir seleccionar un resultado y crear la cita directamente.

8.5. Integración con la agenda

Al seleccionar un hueco, el formulario de cita deberá abrirse con los siguientes campos ya rellenados:

Profesional.

Fecha.

Hora.

Duración.

Tratamiento.

El usuario solo tendrá que seleccionar o registrar al paciente.

8.6. Reglas del motor

El buscador debe respetar:

Horarios laborales.

Vacaciones.

Festivos.

Bloqueos.

Descansos.

Citas existentes.

Tratamientos habilitados para cada profesional.

Duraciones.

Recursos compartidos, cuando se configuren.

No mostrar huecos falsos.

Revalidar la disponibilidad cuando se confirme una reserva.

8.7. Rendimiento

La búsqueda debe funcionar rápidamente con miles de citas almacenadas.

Optimizar consultas e índices.

Evitar realizar peticiones excesivas o consultas independientes innecesarias.

No cargar todos los registros históricos para encontrar un hueco.

9. CREACIÓN DE CITAS

La creación de citas debe ser extremadamente sencilla.

9.1. Campos

El formulario tendrá:

Datos principales

Paciente.

Nombre.

Apellidos.

Teléfono.

Primera visita: sí/no.

Profesional.

Tratamiento.

Fecha.

Hora.

Duración.

Datos opcionales

Comentario adicional.

Observaciones administrativas.

Estado de la cita.

Enviar recordatorio WhatsApp: sí/no, según la configuración aplicable.

9.2. Campos que NO deben solicitarse

No solicitar DNI ni dirección fiscal como requisito para dar una cita.

Los datos fiscales se gestionarán desde la ficha del paciente o durante la facturación.

9.3. Primera visita

Incluir un selector:

Sí.

No.

Cuando existan suficientes datos históricos, el sistema podrá sugerir automáticamente el valor.

Permitir corregirlo manualmente.

No marcar automáticamente como primera visita a todos los pacientes importados cuyo historial todavía no se conozca.

9.4. Creación rápida

Al seleccionar un hueco desde la agenda, precargar:

Profesional.

Día.

Hora.

Duración predeterminada, si ya se conoce el tratamiento.

El usuario podrá modificar cualquier campo permitido.

9.5. Estados

Los estados iniciales serán:

Programada.

Confirmada.

Realizada.

Cancelada.

No presentado.

Permitir ampliar estados desde configuración si el modelo existente lo soporta adecuadamente.

9.6. Modificaciones

Permitir:

Reprogramar.

Cambiar profesional.

Cambiar tratamiento.

Cambiar duración.

Cambiar comentarios.

Modificar información de contacto.

Cancelar.

Registrar los cambios importantes y su autor.

No eliminar silenciosamente citas canceladas.

9.7. Confirmaciones

Antes de guardar comprobar los datos y los conflictos de horario.

Mostrar mensajes claros en caso de error.

Evitar formularios excesivamente largos.

10. FESTIVOS, VACACIONES Y DÍAS NO LABORABLES

El programa debe permitir gestionar todos los días y periodos en los que no se trabaja.

10.1. Tipos de bloqueos

Festivos nacionales.

Festivos autonómicos.

Festivos municipales.

Cierres de clínica.

Vacaciones.

Puentes.

Días libres.

Ausencias personales.

Reuniones.

Formación.

Descansos.

Bloqueos extraordinarios.

Otros motivos personalizados.

10.2. Alcance del bloqueo

Un bloqueo puede afectar a:

Toda la clínica.

Un profesional.

Dos profesionales.

Varios profesionales seleccionados.

Ejemplo:

Ana está de vacaciones, pero Carlos y Laura siguen trabajando.

Solo debe bloquearse Ana.

10.3. Tipos de duración

Permitir:

Un día entero.

Varios días consecutivos.

Una franja horaria.

Varias horas.

Una semana completa.

Repeticiones semanales.

Repeticiones anuales cuando corresponda.

10.4. Gestión de festivos

Preparar una funcionalidad que permita importar o configurar festivos de:

España.

Comunidad autónoma.

Municipio.

La ubicación inicial prevista es Madrid, pero debe ser configurable.

No asumir que todos los festivos oficiales son obligatoriamente días de cierre.

El administrador debe decidir qué días se bloquean.

Diferenciar la fecha de un festivo oficial de la decisión de cerrar la clínica.

10.5. Interfaz

Añadir una opción destacada:

Bloquear agenda

Formulario:

Tipo de bloqueo.

Motivo.

Fecha inicial.

Fecha final.

Horas, si corresponde.

Profesionales afectados.

Repetición.

Observaciones opcionales.

10.6. Representación visual

Los bloqueos aparecerán diferenciados de las citas.

Ejemplo:

Gris: vacaciones.

Gris oscuro: clínica cerrada.

Otro estilo configurable: ausencia individual.

Mostrar una etiqueta con el motivo.

No utilizar los colores de los tratamientos para representar bloqueos.

10.7. Citas existentes

Si se intenta bloquear un periodo que ya tiene citas:

Detectar las citas afectadas.

Mostrar un aviso.

Mostrar un listado de dichas citas.

Solicitar confirmación.

Ofrecer la opción de reprogramarlas.

No cancelar citas automáticamente sin autorización expresa.

10.8. Horarios especiales

Permitir abrir excepcionalmente un día que normalmente está cerrado.

Ejemplo:

La clínica suele cerrar los sábados, pero excepcionalmente abre uno.

El sistema debe permitir esa apertura.

10.9. Integración con el buscador

Los bloqueos deben afectar inmediatamente a:

Calendario.

Disponibilidad.

Buscador inteligente.

API.

Reservas nuevas.

Los recordatorios pendientes deben actualizarse cuando una cita resulte cancelada o reprogramada.

11. BASE DE DATOS DE PACIENTES

La aplicación debe tener una única base de pacientes compartida por todos los profesionales.

Cada paciente tendrá un identificador único e independiente de su nombre o teléfono.

11.1. Campos principales

ID interno.

Nombre.

Apellidos.

Teléfono.

Teléfono normalizado.

Profesional habitual opcional.

Primera visita, según datos disponibles.

Fecha de creación.

Fecha de última visita, si se conoce.

Número de visitas, si puede determinarse.

Observaciones administrativas.

Estado activo/inactivo.

No crear información clínica ficticia.

11.2. Datos fiscales opcionales

Incluir una sección independiente:

Datos fiscales

Campos:

DNI/NIE/NIF.

Tipo de documento.

Nombre o razón social para facturación.

Dirección fiscal.

Código postal.

Municipio.

Provincia.

País.

Todos estos campos serán opcionales en la ficha general del paciente.

El DNI no debe solicitarse al crear una cita.

El usuario debe poder incorporarlo después.

11.3. Edición posterior

Debe permitirse:

Añadir DNI posteriormente.

Modificar DNI.

Corregir datos fiscales.

Añadir dirección.

Modificar dirección.

Implementar validaciones adecuadas de formato e identificación fiscal.

Diferenciar errores de formato, documentos extranjeros y documentos que no puedan validarse completamente.

No bloquear el alta de un paciente porque no tenga DNI.

11.4. Separación entre paciente y destinatario fiscal

La persona que recibe el tratamiento no siempre tiene por qué coincidir con la persona o entidad destinataria de una factura.

Diseñar el modelo de datos permitiendo, cuando sea necesario:

Paciente asistido.

Destinatario de la factura.

Emisor fiscal.

No confundir estas entidades.

11.5. Historial administrativo

Desde la ficha del paciente se podrá consultar:

Citas futuras.

Citas anteriores.

Profesionales con los que se ha visitado.

Tratamientos realizados, en el contexto administrativo.

Citas canceladas.

No presentaciones.

Facturas relacionadas, según permisos.

No convertir este historial administrativo en una historia clínica sin desarrollar las garantías y requisitos específicos correspondientes.

12. AUTOCOMPLETADO DE PACIENTES

La búsqueda rápida de pacientes es obligatoria.

12.1. Funcionamiento

Al empezar a escribir un nombre, apellido o número de teléfono, mostrar coincidencias.

Ejemplo:

Se escribe "Mar":

María García.

Marta Pérez.

Marcos López.

Cuando se selecciona un paciente:

Recuperar nombre.

Recuperar apellidos.

Recuperar teléfono.

Recuperar profesional habitual, si existe.

Recuperar los datos administrativos necesarios.

12.2. Creación de paciente nuevo

Si no existe, ofrecer:

Crear paciente nuevo

No obligar a salir de la agenda.

12.3. Prevención de duplicados

Detectar posibles duplicados por:

Teléfono.

Nombre y apellidos.

Otros identificadores disponibles.

No fusionar pacientes automáticamente.

Un mismo teléfono puede pertenecer a más de una persona.

12.4. Rendimiento

Optimizar búsquedas.

Utilizar índices y búsquedas parciales eficientes.

No descargar todos los pacientes al navegador.

12.5. Privacidad

No mostrar más datos de los necesarios en los resultados.

Respetar los permisos de acceso.

13. IMPORTACIÓN DE PACIENTES DESDE EXCEL

La clínica proporcionará más adelante un archivo Excel que contiene datos de pacientes.

El objetivo es realizar una migración inicial a la nueva base de datos.

13.1. Formatos

Aceptar preferentemente:

XLSX.

CSV.

13.2. Datos previstos

El archivo podrá contener:

Nombre.

Apellidos.

Teléfono.

Profesional habitual.

Profesional con el que se ha visitado.

Datos administrativos adicionales.

Otros campos todavía por determinar.

No asumir el esquema exacto antes de recibir el archivo.

13.3. Importador

Desarrollar un asistente con las siguientes etapas:

Seleccionar archivo.

Leer columnas.

Mostrar vista previa.

Relacionar columnas con campos del sistema.

Normalizar teléfonos.

Comprobar datos obligatorios.

Detectar duplicados.

Mostrar errores.

Ejecutar una simulación.

Confirmar importación.

Realizar importación.

Mostrar informe final.

13.4. Asignación de columnas

Permitir mapear manualmente:

"Teléfono móvil" del Excel → "Teléfono" de la aplicación.

"Podólogo" del Excel → "Profesional habitual".

No depender de nombres de columnas fijos.

13.5. Duplicados

El importador deberá distinguir:

Paciente nuevo.

Posible duplicado.

Registro coincidente.

Registro con errores.

Permitir revisión manual.

No sobrescribir información existente sin una regla clara y autorización.

13.6. Integridad

La importación debe:

Crear registros correctamente relacionados.

Conservar caracteres especiales y acentos.

Manejar teléfonos españoles e internacionales.

Detectar filas vacías.

Evitar duplicados por reimportación.

Registrar errores.

Tener posibilidad de recuperación.

Realizar copia de seguridad antes de importar.

13.7. Consentimientos

No asumir que todos los pacientes importados han autorizado mensajes por WhatsApp.

Importar únicamente información de consentimiento que pueda acreditarse.

Los pacientes sin habilitación válida para comunicaciones automáticas deberán quedar excluidos de los envíos hasta regularizar esa situación.

14. WHATSAPP AUTOMÁTICO

Esta funcionalidad es esencial.

Integrar la aplicación con WhatsApp Business Platform mediante una API oficial o proveedor autorizado.

No depender de automatizaciones no oficiales de WhatsApp Web.

14.1. Regla exacta de envío

Todos los días, a las 10:00 de la mañana, comprobar las citas correspondientes al día natural siguiente.

Enviar un recordatorio a los pacientes que cumplan las condiciones de envío.

Ejemplos:

Lunes a las 10:00 → citas del martes.

Martes a las 10:00 → citas del miércoles.

Viernes a las 10:00 → citas del sábado.

Domingo a las 10:00 → citas del lunes.

No cambiar esta regla por "24 horas antes".

La especificación es exactamente:

El día anterior a la cita, a las 10:00.

14.2. Zona horaria

Utilizar Europe/Madrid.

El sistema debe gestionar automáticamente:

Horario de verano.

Horario de invierno.

Cambio de fecha.

Cambio de año.

No depender de la hora UTC del servidor para interpretar las 10:00 locales.

14.3. Plantilla

Mensaje predeterminado propuesto:

"Hola, {nombre}.

Te recordamos que tienes una cita en {clinica} el día {fecha}, a las {hora}.

Si necesitas modificarla o cancelarla, contacta con nosotros.

Gracias."

La plantilla debe poder personalizarse desde configuración, respetando los requisitos del proveedor.

14.4. Privacidad

No incluir por defecto:

Tipo de tratamiento.

Diagnóstico.

Información clínica.

Comentarios internos.

DNI.

Dirección fiscal.

Enviar únicamente la información necesaria para recordar la cita.

14.5. Integración técnica

Implementar:

Configuración de Meta Business.

Número emisor.

Identificador de cuenta.

Credenciales seguras.

Plantillas aprobadas.

API de envío.

Webhooks de estados.

Registro de errores.

Reintentos.

Control de duplicados.

Supervisión de mensajes.

Si se utiliza un proveedor intermediario, documentar los costes y las condiciones de tratamiento de datos.

14.6. Estados de mensaje

Distinguir:

Pendiente.

En proceso.

Aceptado por proveedor.

Enviado, cuando pueda confirmarse.

Entregado.

Leído, cuando se disponga del estado.

Fallido.

Cancelado.

Estado desconocido.

No considerar entregado un mensaje únicamente porque la API haya aceptado la petición.

14.7. Registro de recordatorios

Guardar como mínimo:

ID.

ID de cita.

ID del paciente.

Teléfono destinatario.

Fecha programada.

Hora programada.

Fecha del intento.

Estado.

Identificador del proveedor.

Número de intentos.

Error, si lo hubiera.

14.8. Prevención de duplicados

Una misma cita no deberá recibir dos veces el mismo recordatorio por errores de ejecución.

Utilizar claves de idempotencia y transacciones apropiadas.

La repetición de una tarea programada no debe producir envíos duplicados.

14.9. Cancelaciones

Si se cancela una cita antes de enviar el recordatorio:

Anular el recordatorio pendiente.

No enviar el mensaje.

14.10. Reprogramaciones

Si se modifica una cita antes del recordatorio:

Actualizar fecha.

Actualizar hora.

Actualizar datos relacionados.

Si se modifica después de que el recordatorio haya sido enviado:

Registrar el cambio.

Permitir enviar un mensaje de modificación mediante una plantilla apropiada.

Evitar mensajes contradictorios.

14.11. Fallos de conexión

Si el servidor pierde internet:

No perder las tareas.

Registrar los errores.

Reintentar según una política configurada.

No enviar recordatorios de citas ya pasadas.

No duplicar mensajes.

Mostrar incidencias a recepción.

Definir un periodo máximo de recuperación para mensajes retrasados y una política que evite recordatorios inoportunos.

14.12. Panel de control

Añadir una sección:

Recordatorios WhatsApp

Mostrar:

Pendientes.

Programados para mañana.

Enviados.

Entregados.

Fallidos.

Cancelados.

Pendientes de revisión.

14.13. Envío manual

Además de los envíos automáticos, permitir enviar manualmente un recordatorio desde una cita cuando esté autorizado.

Utilizar la misma integración oficial.

No permitir que la opción manual eluda los controles de privacidad, plantillas o límites de envío.

14.14. Confirmación opcional

Preparar la arquitectura para poder incorporar posteriormente respuestas del paciente.

Ejemplo:

Confirmar cita.

Solicitar cambio.

Solicitar cancelación.

No cancelar automáticamente una cita ante un mensaje ambiguo.

Las respuestas deberán procesarse con seguridad y, cuando sea necesario, quedar pendientes de validación por recepción.

14.15. Consentimiento y base jurídica

Implementar la gestión de habilitación para recordatorios conforme al RGPD y a las condiciones de WhatsApp Business.

Registrar cuando corresponda:

Preferencia de comunicación.

Base jurídica.

Consentimiento u otra evidencia pertinente.

Fecha.

Origen.

Revocación u oposición.

No usar los datos para publicidad sin una base jurídica independiente.

15. FACTURACIÓN INTEGRADA

El programa debe permitir generar facturas desde la aplicación, utilizando la información de los pacientes y citas.

La facturación no debe interferir con el funcionamiento de la agenda.

15.1. Arquitectura

Crear un módulo de facturación separado lógicamente de las citas.

Se recomienda conectar Easy!Appointments con un motor de facturación ya adaptado a la normativa española cuando exista una opción mantenida y verificable.

Evitar desarrollar desde cero el motor fiscal completo sin necesidad.

15.2. Configuración del emisor

Permitir configurar:

Nombre o razón social.

NIF.

Dirección fiscal.

Código postal.

Municipio.

Provincia.

País.

Datos de contacto.

Series de facturación.

Numeración.

Configuración tributaria.

15.3. Diferentes entidades fiscales

La clínica podría operar como:

Una sociedad.

Un autónomo.

Varios profesionales que facturan separadamente.

La estructura deberá permitir configurar más de un emisor fiscal si es necesario.

No asumir que profesional y emisor fiscal son la misma entidad.

Mantener separación de series, registros y permisos por emisor cuando corresponda.

15.4. Creación de factura

Desde una cita realizada, añadir:

Crear factura

Al pulsar:

Recuperar paciente.

Recuperar tratamiento.

Recuperar fecha.

Recuperar profesional.

Recuperar precio configurado, si existe.

Mostrar el borrador antes de emitir.

15.5. Campos de la factura

Emisor.

Destinatario, cuando sea necesario.

Fecha de expedición.

Fecha de operación, si corresponde.

Serie.

Número.

Concepto.

Cantidad.

Precio.

Base imponible.

Tratamiento de IVA.

Cuota, si corresponde.

Total.

Tipo de factura.

Estado.

15.6. Tipos de factura

Prever:

Factura completa.

Factura simplificada.

Factura rectificativa.

Factura emitida en sustitución de una simplificada cuando proceda.

No habilitar tipos especiales sin implementar sus reglas legales.

15.7. Datos fiscales del paciente

El DNI/NIE/NIF y la dirección fiscal serán opcionales en la ficha del paciente.

Sin embargo, el sistema debe comprobar si son necesarios para emitir el tipo de factura seleccionado.

Si faltan datos exigibles:

Mostrar los campos correspondientes.

Permitir rellenarlos.

Validarlos.

Guardarlos si está autorizado.

Continuar con la emisión.

No exigir DNI para crear una cita.

No exigir DNI en una factura que legalmente pueda emitirse sin identificar al destinatario.

No asumir que nombre y DNI son siempre suficientes para una factura completa.

15.8. Conservación de datos

Una vez emitida una factura, conservar los datos fiscales utilizados en ella.

Si posteriormente se modifica el DNI o la dirección del paciente, no alterar las facturas ya emitidas.

15.9. Impuestos

Configurar tratamientos fiscales de acuerdo con la legislación española aplicable.

No asumir que todos los servicios de podología tienen automáticamente el mismo tratamiento de IVA.

Permitir definir exenciones, operaciones sujetas y otros supuestos conforme a la normativa.

Las reglas fiscales deberán ser verificadas antes del uso en producción.

15.10. Cobros

Permitir registrar:

Importe.

Fecha de cobro.

Método de pago.

Referencia opcional.

Estado de cobro.

Diferenciar claramente:

Cita realizada.

Factura emitida.

Factura cobrada.

No tratar estos tres estados como equivalentes.

16. INTEGRACIÓN CON VERI*FACTU

El objetivo es que la facturación del sistema pueda cumplir los requisitos españoles aplicables y utilizar la modalidad VERI*FACTU cuando corresponda.

No confundir VERI*FACTU con la simple generación de un PDF o de un código QR.

16.1. Revisión normativa

Consultar la documentación oficial actualizada:

https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu.html

Analizar:

Real Decreto 1007/2023 y modificaciones.

Orden HAC/1177/2024 y modificaciones.

Reglamento de facturación.

Especificaciones técnicas AEAT.

Esquemas de registros.

Requisitos de productores de software.

Plazos de aplicación.

Modalidades de cumplimiento.

No utilizar documentación antigua si ha sido modificada.

16.2. Modalidad de trabajo

Priorizar el estudio de la modalidad VERI*FACTU, que remite registros de facturación a la AEAT.

Diferenciarla de la modalidad NO VERI*FACTU.

La elección definitiva deberá confirmarse antes de configurar producción.

16.3. Datos exigibles

El motor deberá generar los datos reglamentarios del registro de facturación, incluyendo, según corresponda:

Emisor

NIF.

Nombre o razón social.

Destinatario

Identificación fiscal y nombre cuando la normativa lo exija.

Factura

Tipo.

Número.

Serie.

Fecha de expedición.

Fecha de operación, cuando corresponda.

Descripción de operaciones.

Importe total.

Información de IVA o del régimen tributario aplicable.

Referencias a facturas rectificadas o sustituidas, cuando corresponda.

Datos técnicos

Identificación del sistema.

Información del productor.

Fecha y hora de generación.

Encadenamiento con registros anteriores.

Huella digital.

Otros campos reglamentarios.

Generar todos los datos técnicos automáticamente.

16.4. Facturas sin DNI

El sistema deberá permitir facturas sin identificación del destinatario únicamente en los casos permitidos por la normativa.

Implementar correctamente las diferencias entre registros de tipo F1, F2 y los demás tipos aplicables.

No exigir DNI indiscriminadamente.

16.5. Dirección fiscal

La dirección del destinatario podrá resultar obligatoria en la factura aunque no forme parte de todos los campos del registro remitido a la AEAT.

No confundir los requisitos del documento factura con los requisitos del registro de facturación.

16.6. Encadenamiento

Los registros deberán mantener las referencias y huellas exigidas.

No permitir alterar registros históricos.

Las rectificaciones y anulaciones deben realizarse mediante procedimientos reglamentarios.

16.7. Envío a AEAT

Para la modalidad VERI*FACTU:

Preparar el registro.

Validarlo.

Remitirlo a la AEAT.

Procesar la respuesta.

Registrar identificadores y estados.

Gestionar errores.

Aplicar reintentos reglamentarios.

Respetar los requisitos de seguridad, autenticación y comunicación.

16.8. Estados

Diferenciar claramente:

Borrador.

Pendiente de emisión.

Emitida.

Registro pendiente de envío.

Registro enviado.

Aceptado.

Aceptado con incidencias, si corresponde.

Rechazado.

Pendiente de subsanación.

El estado exacto debe ajustarse a la API y al motor de facturación seleccionado.

16.9. Fallos de internet

La clínica puede seguir utilizando su agenda por LAN aunque se interrumpa internet.

Sin embargo, la facturación en modalidad VERI*FACTU deberá seguir las reglas legales de comunicación y tratamiento de incidencias.

No emitir facturas en un modo sin conexión que pueda incumplir dichas reglas.

El módulo debe indicar claramente cuándo puede emitirse una factura y qué incidencias necesitan atención.

16.10. Facturas rectificativas

Permitir corregir errores sin borrar ni alterar una factura original emitida.

Aplicar los tipos de rectificación y referencias reglamentarias.

16.11. Declaración responsable

Comprobar la obligación de disponer de la correspondiente declaración responsable del productor del software.

Si se integra un proveedor externo, delimitar su responsabilidad y la del sistema desarrollado.

16.12. Validación previa a producción

Antes de emitir facturas reales:

Revisar normativa vigente.

Validar esquemas y tipos de registro.

Probar los servicios oficiales disponibles.

Probar errores y reintentos.

Verificar el QR.

Comprobar integridad y trazabilidad.

Validar rectificaciones.

Comprobar la documentación del productor.

Obtener la revisión profesional necesaria.

No declarar conformidad únicamente porque la aplicación genere registros XML.

17. INTERFAZ GENERAL

El diseño debe estar optimizado para recepción.

Priorizar escritorio y tablet, manteniendo un funcionamiento razonable en móvil.

17.1. Pantalla principal

Distribución sugerida:

Barra superior

Logo de clínica.

Nombre del programa.

Fecha actual.

Día anterior / siguiente.

Selector de fecha.

Vista diaria/semanal.

Botón "Hoy".

Botón "Nueva cita".

Botón "Buscar hueco".

Zona de filtros

Todos los profesionales.

Profesionales individuales.

Selección múltiple.

Tratamientos.

Zona principal

Calendarios simultáneos.

Horas en vertical.

Profesionales en columnas.

Citas coloreadas.

Huecos libres claramente visibles.

Menú administrativo

Agenda.

Pacientes.

Profesionales.

Tratamientos.

Bloqueos y festivos.

WhatsApp.

Facturación.

Configuración.

17.2. Citas rápidas

El formulario de citas debe poder utilizarse sin salir de la pantalla principal.

17.3. Diseño visual

Aplicar:

Colores suaves.

Tipografía legible.

Botones claros.

Buena separación.

Formularios compactos.

Mensajes de error comprensibles.

Compatibilidad con pantallas pequeñas.

Interfaz en español.

17.4. Privacidad visual

Considerar que el programa se utiliza en recepción.

Evitar mostrar datos fiscales o información sensible innecesaria en el calendario general.

17.5. Rendimiento

La interfaz debe responder rápidamente al:

Cambiar de día.

Cambiar de profesional.

Buscar paciente.

Crear cita.

Modificar cita.

Buscar disponibilidad.

No realizar recargas completas innecesarias.

18.4. Funcionamiento local sin internet

Si se pierde la conexión a internet, mientras el servidor y la red local sigan funcionando, debe mantenerse la posibilidad de:

Consultar agenda.

Crear citas.

Modificar citas.

Buscar pacientes.

Consultar datos locales.

Las funcionalidades que requieren internet, como WhatsApp y los envíos a AEAT, dependerán de su conectividad y de las reglas de sus respectivos servicios.

18.5. Servicios externos y webhooks

Resolver de forma segura la recepción de webhooks del proveedor WhatsApp cuando la aplicación esté alojada en una LAN privada.

No abrir puertos indiscriminadamente hacia el servidor de pacientes.

Si se necesita un relay o pasarela externa, documentar su arquitectura, protección, costes y tratamiento de datos.

18.6. Disponibilidad

Configurar:

Inicio automático.

Reinicio de servicios.

Comprobaciones de salud.

Logs.

Alertas.

Supervisión de disco.

Supervisión de base de datos.

Recuperación después de reinicio.

Considerar el uso de un SAI para proteger el servidor ante cortes de electricidad.

19. SEGURIDAD Y PROTECCIÓN DE DATOS

La aplicación almacenará datos personales de pacientes.

Tratar estos datos conforme al RGPD, la LOPDGDD y las obligaciones aplicables al contexto sanitario.

19.1. Medidas obligatorias

HTTPS.

Contraseñas protegidas mediante algoritmos modernos.

Control de sesiones.

Control de permisos.

Protección CSRF.

Protección XSS.

Protección frente a inyección SQL.

Validación de entradas.

Límites de intentos de acceso.

Protección de API.

Gestión segura de tokens.

Protección de secretos.

Actualizaciones de seguridad.

19.2. Auditoría

Registrar operaciones relevantes:

Alta de pacientes.

Modificaciones importantes.

Cambios de DNI.

Cambios de citas.

Cancelaciones.

Modificación de horarios.

Cambios de permisos.

Emisión de facturas.

Rectificaciones.

Acciones administrativas.

Registrar quién, cuándo y qué operación realizó, evitando almacenar datos sensibles innecesarios en logs.

19.3. Acceso a datos

Aplicar el principio de mínimo privilegio.

No permitir que una cuenta de profesional consulte información que no necesita.

19.4. Copias de seguridad

Configurar copias automáticas cifradas:

Base de datos.

Archivos necesarios.

Configuración.

Información de facturación.

Elementos necesarios para restaurar la integridad del sistema.

Almacenar copias fuera del servidor principal.

Definir retención conforme a las obligaciones aplicables.

Probar periódicamente la restauración.

19.5. Protección de datos

Documentar:

Responsables y encargados de tratamiento.

Finalidades.

Bases jurídicas.

Periodos de conservación.

Derechos de los interesados.

Medidas de seguridad.

Transferencias internacionales, si las hubiera.

Integraciones de terceros.

Valorar la necesidad de una evaluación de impacto de protección de datos.

20. MODELO DE BASE DE DATOS

Analizar primero el esquema de Easy!Appointments.

No duplicar entidades existentes si pueden ampliarse correctamente.

Las entidades funcionales requeridas incluyen:

Profesionales

ID.

Nombre.

Horarios.

Tratamientos.

Estado.

Pacientes

ID.

Nombre.

Apellidos.

Teléfono.

Profesional habitual.

Datos administrativos.

Datos fiscales opcionales.

Preferencias de comunicación.

Tratamientos

ID.

Nombre.

Duración.

Color.

Precio.

Estado.

Citas

ID.

Paciente.

Profesional.

Tratamiento.

Inicio.

Fin.

Duración.

Primera visita.

Estado.

Comentario.

Bloqueos

ID.

Tipo.

Alcance.

Profesional.

Fecha inicial.

Fecha final.

Horas.

Motivo.

Repetición.

Recordatorios

ID.

Cita.

Canal.

Fecha programada.

Estado.

Proveedor.

Intentos.

Resultado.

Facturación

Entidad emisora.

Destinatario fiscal.

Facturas.

Líneas.

Impuestos.

Cobros.

Referencias a registros VERI*FACTU.

Estados de comunicación.

Auditoría

Usuario.

Acción.

Entidad afectada.

Fecha.

Identificador de operación.

Definir relaciones, restricciones e índices.

Usar migraciones versionadas y reversibles cuando sea posible.

21. ARQUITECTURA DE INTEGRACIONES

Evitar acoplar excesivamente todos los módulos.

21.1. Agenda

Responsable de:

Citas.

Profesionales.

Disponibilidad.

Pacientes.

Tratamientos.

21.2. Motor de disponibilidad

Responsable de:

Horarios.

Conflictos.

Bloqueos.

Búsqueda inteligente.

Comprobación de duración.

21.3. Servicio WhatsApp

Responsable de:

Programación.

Plantillas.

Envíos.

Estados.

Reintentos.

Idempotencia.

21.4. Módulo de facturación

Responsable de:

Borradores.

Facturas.

Datos fiscales.

Cobros.

Integración con motor fiscal.

21.5. Motor VERI*FACTU

Responsable de:

Registros reglamentarios.

Encadenamiento.

Huellas.

Comunicaciones.

Respuestas.

Integridad.

Cumplimiento técnico.

Definir contratos de integración claros y versionados.

22. REQUISITOS DE CALIDAD

22.1. Estabilidad

Evitar modificaciones que afecten negativamente al funcionamiento original.

22.2. Rendimiento

Optimizar para el uso diario en recepción.

22.3. Mantenimiento

Documentar extensiones, migraciones y configuraciones.

22.4. Actualizaciones

Preparar procedimientos para actualizar Easy!Appointments sin perder personalizaciones o datos.

Evitar modificar archivos principales si puede resolverse mediante una extensión o módulo independiente.

22.5. Licencias

Respetar GPL-3.0 y las licencias de dependencias.

Documentar las obligaciones que correspondan cuando se distribuya el software modificado.

22.6. Localización

Español de España.

Formato de fecha español.

Zona horaria Europe/Madrid.

Importes en euros.

Documentos fiscales españoles.

Festivos configurables.

23. PRUEBAS OBLIGATORIAS

No entregar una fase sin pruebas.

23.1. Calendario

Probar:

Tres agendas simultáneas.

Vista de un profesional.

Vista de dos profesionales.

Cambio de día.

Cambio de semana.

Creación de cita.

Edición.

Reprogramación.

Cancelación.

Actualización desde sesiones simultáneas.

23.2. Duraciones

Comprobar:

15 minutos.

30 minutos.

45 minutos.

60 minutos.

Duración personalizada.

Solapamientos.

Horarios límite.

23.3. Buscador

Probar:

Cualquier profesional.

Profesional específico.

Fecha fija.

Hora fija.

Primera disponibilidad.

Franja horaria.

Duración suficiente.

Tratamiento no disponible para un profesional.

Bloqueos.

Vacaciones.

Revalidación antes de reservar.

23.4. Pacientes

Probar:

Nuevo paciente.

Paciente existente.

Autocompletado.

Búsqueda por teléfono.

Duplicados.

Primera visita.

Profesional habitual.

DNI vacío.

DNI informado.

Edición posterior del DNI.

Dirección fiscal vacía.

Datos fiscales completos.

23.5. Excel

Probar:

Archivo válido.

Columnas diferentes.

Datos incompletos.

Teléfonos mal formateados.

Duplicados.

Reimportación.

Errores.

Restauración.

23.6. Festivos

Probar:

Festivo general.

Festivo individual.

Vacaciones.

Bloqueo de horas.

Bloqueo de varios días.

Repeticiones.

Citas existentes.

Aperturas excepcionales.

23.7. WhatsApp

Probar:

Envío a las 10:00.

Cambio de día.

Fin de semana.

Cambio de horario.

Cancelación.

Reprogramación.

Fallo del proveedor.

Fallo de internet.

Reintentos.

Duplicados.

Estados de entrega.

Paciente excluido de comunicaciones.

Teléfono inválido.

23.8. Facturación

Probar:

Factura completa.

Factura simplificada.

DNI opcional en paciente.

Solicitud de datos fiscales cuando proceda.

Diferentes emisores.

Series y numeración.

Impuestos.

Rectificaciones.

PDF.

Registro de cobro.

23.9. VERI*FACTU

Probar según especificaciones oficiales:

Tipos de registro.

Validación.

Huellas.

Encadenamiento.

QR.

Comunicaciones.

Respuestas.

Rechazos.

Reintentos.

Rectificaciones.

Protección de registros.

23.10. Infraestructura

Probar:

Instalación Docker.

Acceso LAN.

VPN.

Caída de internet.

Reinicio del servidor.

Copia de seguridad.

Restauración.

Actualización.

Seguridad de credenciales.

24. CASOS DE ACEPTACIÓN REALES

El sistema deberá superar como mínimo los siguientes escenarios completos.

Escenario A — Paciente nuevo

Un paciente llama y necesita una quiropodia.

Recepción abre una hora libre, introduce nombre, apellidos y teléfono, selecciona primera visita y guarda.

La cita aparece inmediatamente en la agenda.

No se solicita DNI.

Escenario B — Paciente existente

Un paciente habitual llama.

Recepción escribe parte de su nombre, lo selecciona mediante autocompletado y crea una cita sin volver a introducir sus datos.

Escenario C — Cualquier profesional

Un paciente necesita una cita a las 18:00 y no tiene preferencia de profesional.

Recepción busca esa hora entre los tres profesionales.

El programa muestra únicamente los que pueden atenderlo.

Escenario D — Profesional específico

Un paciente quiere visitarse exclusivamente con Carlos.

Se filtra Carlos y se muestran sus próximas horas disponibles.

Escenario E — Primera cita disponible

Un paciente necesita venir lo antes posible.

Se busca entre todos los profesionales habilitados y se muestra la primera cita posible.

Escenario F — Duración de 45 minutos

Un paciente necesita una exploración de 45 minutos.

El programa descarta huecos más cortos y propone únicamente intervalos completos.

Escenario G — Vacaciones individuales

Laura está de vacaciones una semana.

Su columna aparece bloqueada mientras Ana y Carlos siguen disponibles.

Escenario H — Festivo general

La clínica cierra un día festivo.

Los tres profesionales quedan sin disponibilidad.

Escenario I — Recordatorio WhatsApp

Existe una cita para mañana.

A las 10:00 de hoy, el sistema genera el recordatorio, lo remite mediante la integración configurada y registra el resultado.

Escenario J — Cancelación

Se cancela una cita antes de las 10:00.

El recordatorio pendiente se anula.

Escenario K — Migración Excel

Se importa el Excel inicial de pacientes.

Los pacientes quedan disponibles para el autocompletado.

No se generan duplicados innecesarios.

Escenario L — Factura sin DNI previo

Un paciente acude a la clínica sin tener DNI registrado.

Se puede gestionar su cita normalmente.

Al facturar, se comprueba qué datos exige el tipo de factura y únicamente se solicitan los necesarios.

Escenario M — Datos fiscales posteriores

Un paciente solicita posteriormente una factura que requiere identificación y domicilio.

Recepción abre su ficha, añade DNI y dirección, y puede continuar el procedimiento de facturación correspondiente.

Escenario N — Acceso externo

El administrador se encuentra fuera de la clínica.

Accede mediante VPN y consulta las agendas.

Escenario O — Fallo de internet

Se pierde la conexión de internet.

Los ordenadores de la LAN siguen utilizando la agenda local, mientras las integraciones externas registran o gestionan la interrupción conforme a sus reglas.

25. ORDEN DE IMPLEMENTACIÓN

Dividir obligatoriamente el proyecto en fases.

Fase 1 — Auditoría

Entregar:

Análisis del repositorio.

Versión seleccionada.

Dependencias.

Funcionalidades reutilizables.

Propuesta técnica.

Riesgos.

Arquitectura.

Plan de desarrollo.

Fase 2 — Infraestructura

Entorno Docker.

MySQL.

Configuración.

Instalación.

Acceso LAN.

Backups iniciales.

Entorno de pruebas.

Fase 3 — Agenda multiprofesional

Gestión centralizada.

Tres calendarios.

Filtros.

Vistas.

Duraciones.

Tratamientos.

Colores.

Formulario rápido.

Fase 4 — Motor de disponibilidad

Buscador inteligente.

Búsqueda por profesional.

Búsqueda por hora.

Primera disponibilidad.

Duraciones.

Conflictos.

Bloqueos.

Fase 5 — Pacientes

Fichas.

Autocompletado.

Profesional habitual.

Primera visita.

DNI opcional.

Datos fiscales opcionales.

Fase 6 — Festivos y ausencias

Bloqueos generales.

Bloqueos individuales.

Vacaciones.

Festivos.

Horarios especiales.

Gestión de citas afectadas.

Fase 7 — Importación

Excel.

Mapeo.

Validación.

Duplicados.

Importación.

Informes.

Fase 8 — WhatsApp

API oficial.

Configuración.

Plantillas.

Programación de las 10:00.

Estados.

Reintentos.

Gestión de errores.

Fase 9 — Facturación

Emisores.

Datos fiscales.

Facturas.

Series.

Impuestos.

Cobros.

PDF.

Fase 10 — VERI*FACTU

Integración fiscal.

Generación de registros.

Comunicaciones.

Validaciones.

Pruebas oficiales.

Documentación normativa.

Fase 11 — Producción

Seguridad.

Acceso remoto.

Copias de seguridad.

Monitorización.

Pruebas integrales.

Documentación.

Despliegue.

No mezclar modificaciones de todas las fases en una sola entrega.

Mantener el sistema funcional después de cada fase.

26. ENTREGABLES FINALES

El proyecto deberá proporcionar:

Repositorio Git completo.

Código Easy!Appointments adaptado.

Documentación de modificaciones.

Migraciones de base de datos.

Docker Compose de producción.

Archivo de configuración de ejemplo.

Instrucciones de instalación.

Instrucciones de actualización.

Manual de recepción.

Manual de profesionales.

Manual de administración.

Documentación de WhatsApp.

Documentación del importador Excel.

Documentación de facturación.

Documentación de integración VERI*FACTU.

Resultados de pruebas.

Procedimiento de backups.

Procedimiento de restauración.

Guía de acceso LAN.

Guía de acceso remoto.

Lista de limitaciones conocidas.

Registro de decisiones técnicas.

Inventario de dependencias y licencias.

Instrucciones de mantenimiento.

27. CRITERIOS DEFINITIVOS DE FINALIZACIÓN

El proyecto no se considerará completo hasta comprobar que:

Recepción puede gestionar los 2–3 profesionales con una sola cuenta.

Los tres calendarios se muestran simultáneamente.

Es posible filtrar por uno o varios profesionales.

Las citas tienen intervalos visuales de 15 minutos.

Las duraciones pueden personalizarse.

Existen todos los tratamientos solicitados.

Cada tratamiento utiliza su color.

La recepción puede crear citas rápidamente.

Los pacientes existentes se autocompletan.

El buscador inteligente encuentra huecos según preferencias.

El sistema permite buscar por hora, fecha y profesional.

Se pueden bloquear festivos, vacaciones y ausencias.

Se pueden gestionar bloqueos individuales.

Los bloqueos afectan correctamente a la disponibilidad.

Se pueden importar pacientes desde Excel.

El DNI puede permanecer vacío.

El DNI puede añadirse o modificarse posteriormente.

La dirección fiscal también es opcional en la ficha.

El formulario de cita no exige datos fiscales.

Los recordatorios WhatsApp se programan a las 10:00 del día anterior.

Los recordatorios no se duplican.

Las cancelaciones y reprogramaciones actualizan los recordatorios.

Se pueden crear facturas relacionadas con citas.

Los datos fiscales se validan según el tipo de factura.

La integración VERI*FACTU está probada y su conformidad se verifica antes de producción.

La aplicación funciona en LAN.

El acceso remoto funciona mediante VPN.

Existen copias de seguridad verificadas.

El sistema mantiene controles de seguridad adecuados.

Las funcionalidades críticas superan las pruebas.

28. INSTRUCCIONES FINALES DE EJECUCIÓN

Lee y comprende toda esta especificación antes de modificar el repositorio.

No comiences programando indiscriminadamente.

Primero:

Inspecciona el proyecto original.

Identifica su arquitectura real.

Revisa la compatibilidad de versiones.

Detecta qué funcionalidades ya existen.

Diseña las ampliaciones.

Prepara una matriz de requisitos.

Define el modelo de datos.

Define la arquitectura de las integraciones.

Ordena el desarrollo por fases.

Identifica las decisiones externas pendientes.

Después comienza a implementar, probar y documentar las fases que tengan sus dependencias resueltas.

Normas de trabajo

Trabaja mediante cambios pequeños.

Utiliza control de versiones.

Escribe código legible.

Añade pruebas.

No rompas funcionalidades originales.

Evita dependencias innecesarias.

No codifiques valores configurables de forma rígida.

No almacenes contraseñas ni tokens en el repositorio.

No utilices datos reales de pacientes durante el desarrollo.

No simules envíos reales que no se han producido.

No presentes una integración fiscal como conforme sin verificarla.

No afirmes que la aplicación está lista para producción si quedan fallos críticos.

Documenta cualquier limitación.

Decisiones pendientes que no deben bloquear la agenda

Quedan por concretar durante la configuración:

Nombre de la clínica.

Nombres reales de los 2–3 profesionales.

Horarios habituales.

Duraciones reales de tratamientos.

Precios de los tratamientos, si procede.

Archivo Excel de pacientes.

Número de WhatsApp Business.

Cuenta y proveedor de la API de WhatsApp.

Forma jurídica y entidad o entidades emisoras de facturas.

Motor o proveedor de facturación.

Modalidad fiscal seleccionada.

Características del servidor físico.

Configuración definitiva de la VPN.

Municipio y calendario de festivos aplicable.

Estas decisiones deberán quedar parametrizadas.

Su ausencia no debe impedir desarrollar y probar la agenda, utilizando datos ficticios.

No inventar datos fiscales ni clínicos.

Primera respuesta requerida al agente

Como primera entrega, proporciona:

A. Auditoría del repositorio

Descripción de la arquitectura y funcionalidades existentes.

B. Tabla de cobertura

Qué requisitos ya cumple Easy!Appointments y cuáles deben programarse.

C. Arquitectura propuesta

Componentes, servicios, base de datos e integraciones.

D. Plan de modificaciones

Cambios de frontend, backend y base de datos.

E. Fases de desarrollo

Orden, dependencias y criterios de aceptación.

F. Riesgos

Especialmente disponibilidad, WhatsApp, seguridad, facturación y VERI*FACTU.

G. Plan de pruebas

Cómo comprobarás cada requisito.

Tras completar la auditoría, inicia el desarrollo incremental de los componentes que no dependan de información externa todavía pendiente.