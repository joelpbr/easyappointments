# Modificaciones sobre Easy!Appointments

Al menú de la visita del mode simple, «Contacta» no té vora i «Fitxa» fa servir el botó primari ple, amb els mateixos colors i ajustos de marca que al calendari complet.

Al menú d'una visita del mode simple, «Trucar» i «WhatsApp» queden dins del desplegable «Contacta». Les dues accions mantenen la confirmació prèvia; «Fitxa» continua com a botó directe separat. El desplegable només apareix si el pacient té telèfon i funciona també per als usuaris sense accés clínic.

Verificats 9 tests JavaScript i la renderització per administrador/professional. Comprovat en navegador real a 390 i 1280 px: menú Contacta, confirmacions cancel·lades sense contactar, fitxa oberta a la visita correcta i retorn a l'agenda sense canvis pendents.

El menú d'una visita del mode simple inclou «Fitxa» per obrir directament la fitxa clínica a aquella visita, sense sortir del mode simple. Es reutilitzen el formulari i els avisos de canvis pendents existents; després de guardar es refresca l'agenda i en tancar es manté el dia i el filtre seleccionats. Disponible per a administradors i professionals amb accés clínic, també en pacients sense telèfon; els permisos del servidor continuen aplicant-se.

Idiomes limitats a català i castellà: retirats els recursos dels altres 40 idiomes i restringits els codis i selectors. Català predeterminat a configuració, instal·lació, Docker, notificacions, formularis i nous registres. La migració 098 passa tots els idiomes guardats al català i fixa el valor predeterminat de la columna users.language; el castellà continua disponible quan es tria expressament. Els valors antics o no admesos es resolen a català perquè una còpia antiga no intenti carregar fitxers retirats. Còpia prèvia: easyappointments-backup-2026-11-16-024113-before-language-restriction.sql.gz.

Verificat després de la migració: els 12.426 registres d'usuari tenen idioma català; tots els altres camps dels usuaris, els accessos/configuració del personal i tots els camps dels 363.686 marcadors coincideixen amb els hashes esperats. Idioma predeterminat de configuració i columna: català. La pantalla d'accés respon amb `lang="ca"` per defecte i quan es demana un idioma retirat, i amb `lang="es"` en seleccionar castellà. Proves: 6 PHP i 13 JavaScript correctes.

Eliminada completament la pàgina «About»: controlador, vista, consulta RSS i textos exclusius de la pàgina. `/about` i `/about/index` deixen d'estar disponibles. Els botons de tornar de la pantalla d'actualització apunten al calendari i Integracions conserva la seva pròpia URL de retorn després d'iniciar sessió.

La versió simple substitueix el nom «versió mòbil» a l'URL (`/simple`), controlador (`Simple`), pàgina, JS/CSS, selectors i claus de traducció (`simple_*`). Els dos accessos conserven `fa-solid fa-puzzle-piece`. `/mobile` i `/mobile/index` redirigeixen permanentment a `/simple`; es conserva la preferència de calendari complet i es recupera el filtre de professional ja guardat amb la clau antiga. La detecció de telèfons, els permisos, el calendari i les accions de contacte mantenen el seu funcionament. Les metadades estàndard del navegador que inclouen «mobile» mantenen el nom tècnic correcte.

Verificació de la versió simple: 6 proves JavaScript de preferències, selectors i traduccions; 3 proves de detecció de dispositius. Renderització real comprovada per administrador i professional, amb el filtre de professionals restringit correctament. Les dues URL antigues responen amb 301 a `/simple`, la ruta nova requereix iniciar sessió i els fitxers JS/CSS nous responen amb 200. Compilació i sintaxi PHP de totes les traduccions verificades.

Totes les llistes de patologies comparteixen ordre de més a menys usos: cada marcador guardat en un dibuix de visita compta com un ús, inclosos els importats. En cas d'empat s'ordenen per nom; les no utilitzades queden al final. El catàleg i la fitxa clínica consulten el mateix mètode, sense modificar marcadors, coordenades ni dades clíniques.

Selector de tractaments de la cita: cada opció mostra el color del tractament barrejat amb un 80% de blanc, amb text negre per mantenir un contrast alt. Colors absents o invàlids fan servir fons blanc. Es mantenen les categories, els serveis inactius i el selector natiu.

Raigs X: transició d'opacitat de 220 ms entre la imatge normal i la radiografia, en tots dos sentits. Es carrega la radiografia abans d'esvair la imatge normal i s'ignoren càrregues antigues si es canvia de vista o es torna a prémer el botó. Marcadors i coordenades no es modifiquen; es respecta la preferència de moviment reduït.

Horari de cita agrupat en una fila: «Inici», «Fi» i «Minuts». Les dates conserven data i hora completes, amb amplades proporcionals i durada compacta; els noms accessibles mantenen les descripcions completes. En mòbils estrets la durada passa a sota per evitar tallar les dates. Es manté la sincronització existent entre inici, final i durada.

WhatsApp al peu de la cita: casella de selecció de 20 × 20 px amb l'estil de Bootstrap, alineada amb l'etiqueta i amb una superfície clicable de 44 px d'alçada. Substitueix el commutador en forma de píndola i conserva el mateix funcionament.

El commutador WhatsApp de donar cita queda a l'esquerra del peu del formulari, amb les accions de tancar i guardar a la dreta. S'ajusten mida, alineació i superfície clicable del control; l'etiqueta curta i l'explicació accessible del recordatori es mantenen. La durada torna a ocupar l'amplada del seu camp.

Fitxa clínica: antecedents i al·lèrgies passen a l'alçada normal de tres línies, sense estirar-se per ocupar tota la finestra. A sota es mostren les «Observacions pacient», compartides amb la reserva de cita mitjançant el mateix camp users.notes. Es carreguen i es guarden des de la fitxa, amb el control de canvis pendents existent. La pàgina de pacients també actualitza aquestes notes després de guardar la fitxa per conservar-ne el contingut en edicions posteriors.

Formulari de cita: intercanviades les posicions de «Durada (minuts)» i «Data/Hora de fi». El commutador de recordatori queda a la dreta del valor de durada en una sola línia, amb l'etiqueta curta «WhatsApp»; l'explicació completa es conserva a l'ajuda en passar-hi el cursor i al nom accessible.

Per petició de la clínica, les etiquetes dels dos camps passen a «Observacions pacient» i «Observacions cita» (en castellà, «Observaciones paciente» i «Observaciones cita»). Es mantenen els mateixos camps i continguts.

Restaurats els dos camps originals del formulari de donar cita: «Comentari» de la cita (`appointments.notes`) i «Observacions administratives» de la persona (`users.notes`), amb els mateixos noms i àrees de text que abans. Les notes de la persona es tornen a carregar en seleccionar-la i a guardar en crear o editar les seves dades; continuen opcionals. Reutilitzar un pacient protegit conserva les notes existents. Verificades 7 proves del formulari, inclosa la separació dels dos comentaris i la conservació de les notes guardades.

Restaurat el nom original «Comentari» al camp de nota de la cita del formulari de donar cita, que s'havia reanomenat «Observacions». Continua visible i opcional amb la configuració actual, i es guarda a la nota de la cita, independentment de les dades del pacient i de les observacions clíniques.

Navegació de pacients: clicar una persona a la llista obre directament la seva fitxa clínica. A l'esquerra de la X de la fitxa hi ha un petit botó SVG amb el títol «Dades del pacient», que obre la pàgina original del mateix pacient amb el seu historial administratiu, també des del calendari i per a pacients fora dels primers resultats. Abans de sortir es manté l'avís per guardar, descartar o cancel·lar els canvis pendents de pacient o visita. El botó apareix quan el pacient ja està guardat. Els usuaris sense accés clínic continuen obrint les dades de pacient. Compilació de JavaScript i estils verificada.

Correcció de codificació aplicada a 11.910 registres (12.219 camps de text): notes de pacients, antecedents, al·lèrgies i textos de visites. Es recuperen accents, Ñ/ñ, ordinals (1º, 2ª), dièresis, ce trencada, punt volat, cometes i altres signes reconeixibles, incloses conversions incorrectes repetides, conservant majúscules i espais. Verificats tots els altres camps de les taules revisades, incloses totes les coordenades i configuracions dels dibuixos; l'arxiu original no es modifica. Còpia prèvia: easyappointments-backup-2026-11-15-225858-before-text-encoding-repair.sql.gz. La importació reconeix text binari UTF-8 i Windows-1252 i aplica la mateixa correcció. `application/scripts/repair_podologo_encoding.php` mostra el pla; `--apply` crea còpia prèvia i aplica els canvis en una transacció amb verificació completa. Proves: 34 tests, 188 assercions.

Procedències recuperades d'EMAIL2: 8.500 fitxes actualitzades i 12 que ja contenien el valor; 8.512 valors originals comprovats en total. En 1.083 fitxes s'ha conservat també la procedència diferent que ja hi havia. Comprovació de tots els camps restants de les històries i dels pacients sense canvis, inclosos els correus. Còpia prèvia: easyappointments-backup-2026-11-15-225053-before-provenance-repair.sql.gz. Proves: 12 tests i 108 assercions.

EMAIL2 → Procedència: la clínica feia servir el segon correu de l'antic programa per guardar la procedència del pacient. Es converteix al camp ja existent patient_records.referral_source, no al correu electrònic. Si ja hi ha una procedència diferent, es conserven les dues sense etiquetes afegides ni duplicats. `application/scripts/repair_podologo_referrals.php` mostra el pla i amb `--apply` crea còpia prèvia i repara les fitxes existents, comprovant tots els altres camps de les històries i dels pacients (inclòs el correu) abans de confirmar els canvis. La importació també incorpora aquesta correspondència.

Calendari recuperat després de completar professionals: corregides 36.084 assignacions segons les convencions de la clínica i els valors ja guardats (15.498 antigues sense hora → Sara; 9.500 sense marca ni professional → Sara; 11.086 amb S separada → Sebas). Les marques T des de 2024 continuen assignades a Toñi. Resultat: zero visites sense professional, i tots els altres camps de les 84.290 visites i dels 363.686 marcadors verificats sense canvis. Proves de les dues peticions reals del calendari: 13 visites el 13/11/2012, 31 el 03/06/2024 i 22 el 12/11/2026, totes carregades correctament. Còpia prèvia: easyappointments-backup-2026-11-15-224352-before-provider-repair.sql.gz.

Assignació de professionals recuperada segons les convencions confirmades per la clínica: una S majúscula separada al comentari (QUIRO S, CURA S...) identifica Sebas; una T majúscula separada identifica Toñi exclusivament des de l'01/01/2024. Quan no hi ha professional guardat ni cap marca aplicable, la visita és de Sara, incloses les antigues sense hora. Les marques explícites prevalen sobre la categoria general. La revisió de VISITA confirma que els quatre identificadors originals són buits en les visites que havien quedat sense professional; per això cal convertir les anotacions, no afegir una columna o un professional fictici. La reparació crea còpia prèvia i compara totes les visites i tots els marcadors abans de confirmar els canvis. Es proven les dues respostes reals del calendari amb dates antigues i recents.

Correcció d'horaris aplicada: revisades 84.290 visites importades i ajustades 65.246. Verificació final: zero inicis fora del quart d'hora i zero durades diferents de la predeterminada del servei assignat. Tots els altres camps de les visites i tots els marcadors s'han comparat abans i després per SHA-256; no presenten canvis. Còpia prèvia: easyappointments-backup-2026-11-15-223334-before-visit-time-repair.sql.gz. Proves de conversió d'hores i importació: 11 tests i 26 assercions.

Horaris de visites importades: l'inici s'arrodoneix cap avall al quart d'hora (10:29:43 → 10:15:00) i el final es calcula amb la durada predeterminada del servei assignat, no amb 30 minuts fixos. Es conserven el dia i l'hora locals, sense convertir fusos horaris; un inici ja situat al quart exacte no es mou. La mateixa conversió s'aplica a noves importacions. `application/scripts/repair_podologo_schedule.php` mostra el pla i amb `--apply` crea una còpia prèvia, aplica una transacció i verifica tots els camps de les visites contra el canvi esperat, més tots els marcadors. No es modifiquen pacients, professionals, tractaments, textos o coordenades.

Reserva amb pacient existent: el modal elimina el selector d'idioma i els camps secundaris de la reserva (adreça, ciutat, codi postal i notes del pacient). Del pacient només demana nom, cognoms i telèfon; les observacions de la visita continuen opcionals. Quan el pacient està seleccionat i bloquejat, només s'envia la seva referència a la cita i no es validen ni es reescriuen les dades del pacient. En editar explícitament nom/telèfon, es conserven les altres dades ja guardades. Això evita el bloqueig «Els camps amb un * són obligatoris» causat pel valor importat english, que no apareix entre els idiomes disponibles del formulari. La importació usa l'idioma predeterminat de la clínica en crear pacients. Proves del component: reproducció del bloqueig, enviament només de l'ID del pacient, validació dels camps de cita i de pacient nou, conservació de dades secundàries i absència de camps secundaris al formulari.

Recuperació de documents aplicada: 8.279 documents originals restaurats als camps existents de la fitxa (7.584 DNI vàlids, 268 NIE vàlids i 427 valors conservats literalment com a altre document). Els 4.141 pacients sense document a l'origen continuen sense número inventat. Cada document s'ha comprovat després d'escriure'l; no hi havia conflictes amb documents posteriors. Còpia prèvia: easyappointments-backup-2026-11-15-222155-before-document-repair.sql.gz. Proves: 13 tests i 49 assercions.

Documents del pacient: el camp NIF de PACIENTE es converteix als camps existents document_type i document_number de fiscal_parties (relationship=patient), que ja consumeixen la fitxa clínica i les dades fiscals. Es reutilitza la validació existent: DNI i NIE vàlids normalitzats, zeros inicials conservats; valors no reconeguts es mantenen literalment com a altre document. No s'inventen lletres ni dígits ni es canvien les validacions o les pantalles. `application/scripts/repair_podologo_documents.php` mostra el pla i amb `--apply` crea còpia prèvia, recupera els documents absents, conserva els documents ja editats i verifica cada resultat i la resta de dades fiscals. La importació incorpora també aquesta conversió.

PIESIGUALESBIO: l'indicador antic de «peus iguals» de l'exploració biomecànica es conserva a l'arxiu original però s'exclou dels comentaris visibles. La neteja només retira el paràgraf generat «Piesigualesbio: 1» (o 0), amb còpia prèvia i verificació de totes les altres columnes de cada registre. No es regeneren les històries ni es modifiquen els professionals, tractaments o dibuixos.

Unificació aplicada i verificada: 6 patologies duplicades fusionades, amb 262.167 marcadors reassignats a l'entrada canònica. Els 2 professionals importats SARA i SEBASTIÁN s'han fusionat amb Sara i Sebas existents. S'han assignat 1.021 visites a Toñi per una T majúscula separada al comentari, exclusivament des de l'01/01/2024. S'ha verificat el resultat esperat de tots els camps de totes les visites i marcadors mitjançant SHA-256 abans de confirmar la transacció; la resta del contingut i les coordenades no canvien. Còpia prèvia: easyappointments-backup-2026-11-15-220821-before-catalog-unification.sql.gz.

Unificació de dades: els duplicats de patologies es reconeixen pel nom normalitzat i equivalències explícites català/castellà, sense barrejar subtipus. Es conserven els noms i les icones del catàleg existent i es reassignen els marcadors abans de retirar el duplicat. Els professionals importats SARA i SEBASTIÁN s'unifiquen amb Sara i Sebas existents, conservant-ne els accessos i relacionant-hi les visites. Des de l'01/01/2024, una T majúscula com a paraula separada dins del comentari (p. ex. QUIRO T) assigna la visita a Toñi. La regla no s'aplica abans del 2024 ni a t minúscula ni a lletres dins d'una paraula. `application/scripts/unify_podologo_catalogs.php` mostra un pla i amb `--apply` crea còpia prèvia, aplica una transacció i verifica tots els camps de cada visita i marcador contra el resultat esperat. La importació també reutilitza aquests catàlegs i professionals.

Validació de la neteja aplicada a totes les històries: 84.290 visites i 12.420 pacients processats, amb 97.712 camps de text original comprovats. Els 46.374 registres que contenien identificadors d'especialista als comentaris queden nets; la cerca final de prefixos generats retorna zero registres. Tres visites mostren literalment «OC CT PI. PAUTO MUPIROCINA» i dues més conserven la frase juntament amb altre contingut. Es mantenen els 363.686 marcadors. Proves: 38 tests i 157 assercions. Còpia prèvia: easyappointments-backup-2026-11-15-215820-before-clinical-repair.sql.gz.

Neteja de textos importats: es retiren dels comentaris els identificadors interns d'especialista i les capçaleres afegides per la conversió. Els textos originals s'uneixen sense prefixos com «Tractament: Quiropòdia:» i sense duplicats. Exemple comprovat: «OC CT PI. PAUTO MUPIROCINA» es conserva literalment com a únic contingut quan no hi ha més text clínic. Les mesures numèriques mantenen el nom necessari per entendre-les. Les plantilles i els camps del programa no es modifiquen. Es reconeix el text generat anteriorment per substituir-lo sense esborrar les notes posteriors.

Conversión clínica Podologo: usa exclusivamente las plantillas y los campos originales del programa. La información se distribuye entre motivo, diagnóstico, tratamiento y observaciones; cuando la plantilla ofrece menos campos, se agrupa en uno de sus campos visibles, sin perder textos. Los tratamientos se asignan al catálogo existente según lo prescrito en el original, con prioridad de Cura PV frente a Cura y del tratamiento específico frente a la categoría general de quiropodia. No se añade una plantilla de importación ni se cambia la disposición de la ficha.

La página de copias permite borrar pacientes, citas, dibujos, datos fiscales y clínicos, recordatorios y datos importados mediante «Borrar base de datos actual». Solo administradores, POST con CSRF y confirmación escrita DELETE. Se crea una copia before-delete antes de borrar y la eliminación usa una transacción; se conservan personal, ajustes, catálogos, auditoría y copias. No se ejecuta el borrado al añadir el botón. Tras vaciar, la importación reutiliza las patologías existentes del mismo nombre para permitir subir de nuevo la base Podologo.

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
- **Cirugia** : `[DIBUJO PIES]`, Motivo, Diagnóstico, Tratamiento, Precio de la cirugía (pasa a ser el precio de la visita) y Observaciones.
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

Cambio posterior: la etiqueta «Migdia» queda centrada en la franja plegada del mediodía (antes el margen la empujaba abajo y se cortaba); lo mismo para las etiquetas del resto de franjas fuera de horario.

Cambio posterior: las visitas cortas (10-15 minutos) muestran todo en una sola línea («Paciente · Tratamiento (precio) · hora · duración · estado»), cortada con puntos suspensivos si no cabe, en lugar de dos líneas que se salían de la tarjeta; al pasar el ratón se sigue viendo todo.

Cambio posterior: en las visitas cortas la línea usa letra de 10 px con interlineado de 12 px, centrada en vertical y sin el margen interior de FullCalendar, para que en las de 10 minutos (14 px útiles) quepa entera sin tocar el borde.

Cambio posterior (simplificación de la base de datos): la fila fiscal del propio paciente guarda solo lo que difiere del paciente. Nombre, dirección, código postal, ciudad y provincia vacíos significan los del paciente (`Fiscal_parties_model::PATIENT_FIELDS`), y el país vacío, España. Al guardar se vacía lo que repite los datos del paciente; la migración `090_keep_only_differing_fiscal_data.php` lo aplicó a los 9.827 registros (ahora solo guardan el documento). En Pacients los campos vacíos muestran en gris el valor del paciente. Así, cambiar la dirección en la ficha cambia también la de las facturas.

Descartado tras medirlo: quitar el índice `id_users_provider` de las citas (lo cubre en teoría el índice por profesional y periodo). Sin él, MySQL leía toda la tabla (7.453 filas) para la agenda de un día en lugar de las 2.466 del profesional, porque «empieza antes del día X» no filtra casi nada. Se mantiene.

Cambio posterior (un solo camino para «no disponible»): las ausencias y cierres se crean solo como bloqueos (botón «Bloquejos», tipo ausencia personal, vacaciones… y profesionales). El menú «+» ya no ofrece «Indisponibilitat» (se oculta desde `calendar_table_view.js` y `calendar_default_view.js`; las que ya existen se siguen viendo y editando), y seleccionar una franja en la agenda abre directamente una cita nueva en lugar de preguntar «¿cita o indisponibilidad?». Sin cambios en la base de datos: el motor de disponibilidad sigue entendiendo las indisponibilidades.

## Limpieza de lo que no se usa (13/11/2026)

Decidido con la clínica: se elimina por completo lo que está desactivado y vacío y no se va a usar. Migración `091_remove_unused_features.php`.

| Qué | Base de datos | Código |
| --- | --- | --- |
| Videollamada (Jitsi y Google Meet) | `appointments.meeting_link` | Ajustes de Jitsi, `Jitsi_client`, la parte de Meet de `Google_sync` (la sincronización con Google Calendar se mantiene), campo del formulario de cita, popover, correos, `.ics`, API |
| LDAP | `users.ldap_dn`, 8 ajustes `ldap_*` | Ajustes e importación LDAP, `Ldap_client`, el inicio de sesión por LDAP, campo en las fichas del personal, extensión PHP `ldap` del Dockerfile. Se mantienen las constantes `LDAP_DEFAULT_*` porque la migración 057 las usa en instalaciones nuevas |
| Campos personalizados | `users.custom_field_1..5`, 15 ajustes | Componente `custom_fields`, ajustes de reserva, formularios, correos, API |
| Analíticas web | 3 ajustes | Ajustes de Google Analytics y Matomo y sus scripts. La política de seguridad (CSP) ya solo permite scripts y peticiones de la propia aplicación |
| Móvil | `users.mobile_number` | Campo en cuenta, administradores, profesionales y secretaría, búsquedas, API |
| Zona horaria de los pacientes | `users.timezone` vacío en pacientes | Los pacientes usan siempre la de la clínica (`default_timezone`); la columna sigue para el personal |
| Reserva online | `disable_booking = 1` | La dirección de la aplicación lleva al inicio de sesión |

Resultado: `ea_users` pasa de 27 a 20 columnas y `ea_settings` de 82 a 56 filas; 24 textos menos en cada idioma. Las notas del paciente se mantienen: salen en el formulario de cita como «Notes administratives».

Cambio posterior: la barra de la agenda deja el botón «Nova cita» aparte; el desplegable «+» pasa a llamarse «Nova cita», y «Cercar forat» queda solo con la lupa. La etiqueta del mediodía muestra el horario: «12:30 - 15:00 - Migdia».

## Diagramas de los pies (fase 1: base de datos y maquetación)

Migración `092_create_foot_diagrams.php` (copia previa en `storage/backups/easyappointments-backup-2026-11-13-225050.gz`):

| Tabla | Campos | Notas |
| --- | --- | --- |
| `podology_pathologies` | `id`, `name` (único), `image_filename`, `description`, fechas | Catálogo compartido. Se deja vacío: no se inventan patologías. Los iconos van en `assets/img/podology_pathologies/` |
| `podology_diagram_types` | `id` (0-3, con CHECK), `name`, `background_image` | Los cuatro diagramas globales: 0 → `fotos_pies/1.png`, 1 → `2.png`, 2 → `3.png`, 3 → `4.png`. Sin nombre de vista anatómica |
| `appointment_foot_markers` | `id`, `id_appointments`, `id_podology_diagram_types`, `id_podology_pathologies`, `x1`, `y1`, `x2`, `y2` (INT con signo), `is_healed`, `annotation_title`, `annotation_text`, `original_external_id` (único si se informa), fechas | Cada patología colocada en una visita; coordenadas exactas, sin escalar. Se permite repetir la misma patología en el mismo diagrama |

Relaciones: paciente → citas (`ea_appointments`) → marcas. Borrar una cita borra sus marcas (cascade); una patología o un diagrama en uso no se pueden borrar (restrict). Índices: (cita, diagrama), diagrama, patología. Comprobado: coordenadas negativas guardadas tal cual, repeticiones permitidas, diagrama 4 rechazado (clave foránea y CHECK).

Ficha de la visita (plantillas «Peus i observacions» y «Cirurgia»): el recuadro `[DIBUJO PIES]` pasa a ser la foto de la vista elegida con las cuatro vistas en miniatura debajo; a la derecha, la lista desplazable de patologías del catálogo (se puede elegir una) y, debajo, Observaciones. `Foot_diagrams_model` lee diagramas, catálogo y marcas; `Clinical_records::appointment()` los envía. Colocar los iconos sobre las fotos queda para la fase siguiente.

Catálogo de prueba: 5 patologías en `podology_pathologies` de la base de datos local (Onicomicosi, Onicocriptosi, Hiperqueratosi, Heloma, Papil·loma), con iconos provisionales generados (forma y color distintos, 64×64) en `assets/img/podology_pathologies/`. No van en una migración: el catálogo real lo definirá la clínica.

Cambio posterior (fase 2: colocar patologías): en la ficha de la visita se elige una patología de la lista (el cursor pasa a cruz) y se hace clic en la foto: la marca se guarda al momento en `appointment_foot_markers` con el diagrama (0-3) y las coordenadas `x1 y1 x2 y2` en píxeles de la foto original (688×535), un cuadrado de 24 px centrado en el clic, sea cual sea el tamaño en pantalla. La patología sigue elegida para poner varias. Las marcas se dibujan en % de la foto original, cada miniatura muestra cuántas tiene su vista y al hacer clic en una marca se pide confirmación para quitarla. Endpoints `clinical_records/add_marker` y `delete_marker` (mismo control de acceso que la ficha, con registro de auditoría); `Foot_diagrams_model::add_marker()` valida diagrama, patología y coordenadas enteras sin modificarlas.

Cambio posterior: la ficha clínica ocupa toda la altura de la pantalla (margen de 0,75rem) y gana espacio para trabajar: la cabecera es una sola línea baja con el nombre del paciente y, si tiene, una etiqueta roja con las alergias (se quitan el icono de persona —era un icono genérico, no había foto en la base de datos— y la línea de edad, documento y teléfono); se quita el pie (la ficha se cierra con la X) y el botón «Desa» de los datos pasa a la fila de pestañas, a la derecha, solo en «Dades». A 1366×768 el área de trabajo pasa a 652 px de alto.

Cambio posterior: en la pestaña «Visites» la visita elegida es una columna a la derecha que empieza justo debajo de la cabecera, al lado de las pestañas y de la lista de visitas (clase `ea-visits-mode`: rejilla de dos columnas en `.modal-content`), y ocupa toda la altura. Su cabecera es una sola línea con el tratamiento, fecha y precio y el botón «Desa la visita» a la derecha. La foto de los pies se adapta a la altura de la ventana (`max-height: calc(100vh - 300px)`) y el marco toma el tamaño de la foto para que las marcas sigan en su sitio. Comprobado sin desplazamiento en 1920×950, 1536×730, 1440×900, 1366×657 y 1280×600; en el móvil la visita va debajo de la lista. «Dades» sigue a todo el ancho.

Cambio posterior: los botones «Desa» (datos) y «Desa la visita» están apagados mientras no hay cambios y se encienden al modificar algo (se vuelven a apagar si se deshace). Las patologías colocadas ya no se guardan al hacer clic: son cambios de la visita y se guardan con «Desa la visita» (el servidor sincroniza la lista entera: mantiene las que siguen, añade las nuevas y quita las demás, en la misma transacción; `Foot_diagrams_model::sync_markers()`), y quitar una ya no pide confirmación. Al cambiar de visita o cerrar la ficha con cambios pendientes (de la visita o de los datos, estés en la pestaña que estés) se pregunta «Hi ha canvis sense desar. Els vols desar?» con Cancel·la / No desis / Desa. Se quitan los endpoints `add_marker` y `delete_marker`.

Cambio posterior (el dibujo pasa a la visita siguiente): una visita cuyos diagramas nunca se han guardado empieza con una copia exacta del dibujo de la visita anterior del paciente que sí lo tenga guardado (mismas patologías, vistas, coordenadas, estado curado y anotaciones), con el aviso «Dibuix copiat de la visita del dd/mm/aaaa. Es desa amb aquesta visita.». La copia no se guarda hasta que se guarda la visita, y entonces son marcas propias de la visita (ids nuevos; nunca se mezclan con las de la anterior). Para distinguir «sin guardar» de «guardado vacío» (todas las patologías quitadas porque se curaron), migración `093_add_foot_diagrams_saved.php`: `appointment_records.foot_diagrams_saved`, marcado en las visitas que ya tenían marcas y al guardar las marcas de una visita. `Foot_diagrams_model::get_drawing()`.

Cambio posterior: el popover de una cita en la agenda ya no muestra correo, paciente (ya es el título), estado ni zona horaria; inicio y fin van en una sola línea «Hora 12/11/2026 11:15–11:45» (con la fecha completa del final solo si acaba otro día).

Cambio posterior: en el popover de una cita, «Tanca» y «Elimina» pasan al título (papelera roja a la izquierda que pide confirmación para eliminar, X a la derecha para cerrar); debajo, «Cancel·lar la cita» y «Falta d'assistència» en una línea, «Edita» en la siguiente y «Fitxa» abajo, más alto; todos del ancho de la tarjeta. `createAppointmentActions()` y `moveHeaderActions()` en `calendar_event_popover.js`.

Cambio posterior: en el popover de la cita se intercambian los colores de «Edita» (ahora contorno azul) y «Fitxa» (ahora azul lleno); cada uno mantiene su tamaño, «Fitxa» sigue siendo el más alto.

Cambio posterior: la visita elegida en la lista de la ficha se resalta en azul claro (`#d0e7ff`).

Cambio posterior: al hacer clic en una patología colocada se pide confirmación («Vols treure «Heloma» d'aquesta vista?», Cancel·la / Treu); quitarla sigue siendo un cambio de la visita que se guarda con «Desa la visita».

Cambio posterior: al hacer clic en una patología colocada se abre un popup («Què vols fer amb «Heloma»?») con Cancel·la, Treu y «Curada» (carita sonriente; «No curada» si ya lo estaba). Una patología curada se ve atenuada, en gris y con una carita verde en la esquina; el estado se guarda con «Desa la visita» en `appointment_foot_markers.is_healed`, que ahora también se actualiza en los marcadores existentes.

Cambio posterior: se quitan todas las preguntas de aviso al crear, editar, mover, redimensionar, cancelar o eliminar una cita («Voleu enviar una notificació…?», «Vols avisar el pacient…?»); nunca se envía aviso (`notify_users` = 0, también por defecto en `calendar_http_client.js`). Cancelar pide solo confirmación (Tanca / Cancel·lar la cita) y eliminar usa `delete_record_prompt` (Tanca / Elimina), sin motivo de eliminación. Se eliminan las claves de idioma que quedaban sin uso.

Cambio posterior: en el formulario de la cita se quitan el campo de correo electrónico (queda un `#email` oculto para conservar el del paciente) y el botón «Més dades»; los campos `.ea-more-field` (color, ubicación, zona horaria, idioma, dirección, ciudad, código postal) quedan siempre ocultos.

Cambio posterior: al mover o redimensionar una cita en el calendario (vista normal y de tabla) se pide confirmación («Vols desar el nou horari de la cita?», con el paciente y el nuevo horario; Cancel·la / Desa); al cancelar se devuelve la cita a su sitio y se restauran sus horas en los datos del evento. Cancel·lar y eliminar ya pedían confirmación.

Cambio posterior: la dirección se separa en Adreça, Població y Codi postal. En la ficha (pestaña de datos) quedan Adreça (6) + Població (4) + Codi postal (2), y debajo Com ens ha conegut / Professió / Activitat física pasan a 6 columnas; `Clinical_records::CUSTOMER_FIELDS` incluye `city` y `zip_code`. En la página de pacientes, Població y Codi postal van con la dirección (solo se ven sin la ficha clínica; con ella se conservan del paciente, `IDENTITY_FIELDS`). En la base de datos ya eran columnas separadas (`ea_users.address`, `city`, `zip_code`), así que no hay migración ni se vacían datos. La etiqueta `city` pasa a «Població» / «Población».

Cambio posterior: icono de ojo en la cabecera (`#toggle-money`) que oculta todos los importes: ojo abierto mientras se ven, tachado mientras están ocultos. Pone la clase `ea-money-hidden` en `<html>` (guardada por navegador en `localStorage`, aplicada en el `<head>` para que no parpadee) y oculta (`display: none`) todo lo marcado con `.ea-money`; en los formularios se oculta el campo entero: precio de las tarjetas del calendario (y se quita del tooltip), totales por profesional, precio de la cita, precios de las visitas de la ficha, precio de cirugía, precio y lista de servicios. Las opciones de ortesis llevan `data-money-label` y se muestran sin precio. No afecta a la página pública de reservas.

Cambio posterior: separador entre días en las vistas de más de un día: una línea gris oscura de 3px (`#6c757d`). En la vista de semana es el borde izquierdo de cada columna de día (cabecera y rejilla); en la vista de tabla se dibuja con un `::before` sobre cada `.date-column` a partir de la segunda, para no ensanchar las columnas, que reparten el ancho de la pantalla exacto.

Cambio posterior: en la vista de tabla, cuando las columnas de profesional no caben (se reparten el ancho de la pantalla hasta un mínimo de 200px, p. ej. una semana de todos los profesionales), aparece una barra de scroll horizontal encima del calendario (`.ea-calendar-top-scrollbar`), sincronizada en los dos sentidos con la vista; antes no había ninguna barra visible. La altura de la vista descuenta la de la barra.

Cambio posterior: página de **Patologies** (Tractaments › Patologies, `Pathologies` + `Pathologies_model` + `pathologies.js`) para añadir, cambiar y eliminar las patologías del catálogo de los pies, con nombre, descripción e icono (PNG, JPG, WebP o GIF de hasta 1 MB; el tipo se comprueba por el contenido). Los iconos subidos se guardan en `storage/uploads/podology_pathologies` (volumen persistente) con `image_filename` empezando por `uploads/` y se sirven por `pathologies/image/{id}` solo a usuarios con sesión; los que venían con la aplicación siguen en `assets/img/podology_pathologies`. No se puede eliminar una patología puesta en alguna visita (se indica cuántas veces). Usa los permisos de Tractaments. Test: `tests/Unit/Model/PathologiesModelTest.php`.

Revisión de los bloqueos (14/11/2026): comprobado contra la aplicación que un cierre de todo el día impide las citas y quita el día de la búsqueda de huecos; que un bloqueo de un profesional solo le afecta a él y respeta los bordes (la cita que acaba al empezar el bloqueo o empieza al acabar no choca); que la repetición semanal acaba en su fecha; que avisa de las citas afectadas antes de guardar, y que rechaza fin antes del inicio y semanales de más de una semana. Sin errores encontrados.

Cambio posterior: al hacer clic en una hora de la columna de horas del calendario se abre una cita nueva a esa hora (en la vista de tabla, con el profesional y el día de la columna; en la vista normal, solo en modo día, porque en la semana la columna de horas es de todos los días). Las horas muestran el cursor de mano y se resaltan al pasar por encima.

Cambio posterior: las citas canceladas (y cualquier estado que libera el hueco, `.fc-appointment-freed`) llevan además las rayas diagonales de las horas fuera de horario (`unavailability.jpg` en `multiply` sobre su color), en las tarjetas del calendario y en las filas de la vista de lista, como las citas ya terminadas de la lista. Siguen atenuadas y tachadas.

Cambio posterior: **recordatorios de WhatsApp** (especificación 14). Cada cita tiene el interruptor «Envia un recordatori per WhatsApp el dia abans (10:00)», activado por defecto (`appointments.send_whatsapp_reminder`, migración 094; también en la API como `sendWhatsappReminder`). El cron (`docker/cron.sh`) ejecuta cada 15 minutos `php index.php console whatsapp` (librería `Whatsapp_reminders`): de 10:00 a 20:00 en Europe/Madrid (con horario de verano/invierno, sea cual sea la zona del servidor) crea un recordatorio por cita del día siguiente que lo quiera, no esté cancelada y tenga paciente, en `whatsapp_reminders` con clave única por cita y día; lo toma con un bloqueo optimista (pending → processing) antes de enviarlo por la WhatsApp Cloud API de Meta con una plantilla aprobada de 4 parámetros (nombre, clínica, fecha, hora; nada clínico), y si la cita se ha cancelado, movido de día o desactivado, lo anula. Los fallos se reintentan hasta 3 veces dentro de la ventana; a las 20:00 lo no enviado pasa a «Per revisar», igual que los que se quedan en «processing» más de 15 minutos (no se reenvían para no duplicar) y los pacientes sin móvil válido. El webhook público `whatsapp_webhook` (sin CSRF, con la firma `X-Hub-Signature-256` del secreto de la app; GET responde a la verificación con el token) actualiza los estados enviado/entregado/leído/fallido, solo hacia adelante. Configuración en Integracions › Recordatoris de WhatsApp (`Whatsapp_settings`): Phone number ID, token, plantilla, idioma, URL y token del webhook, secreto de la app (los secretos no se devuelven a la página), envío de prueba y panel con los recordatorios. `php index.php console whatsapp 2026-11-16T10:05` lo ejecuta como si fuera ese momento (recuperación manual o pruebas); el ajuste oculto `whatsapp_api_url` apunta a otra API para probar en local. Pendiente de la especificación: consentimiento/oposición por paciente, envío manual desde la cita, mensaje de modificación y respuestas del paciente. Tests: `tests/Unit/Library/WhatsappRemindersTest.php`.

Cambio posterior: se quita el campo «Preu per missatge» de la página de WhatsApp (el precio lo fija Meta); el coste del mes se estima con un precio fijo de 0,016 € por mensaje (`Whatsapp_reminders::MESSAGE_PRICE`).

Cambio posterior: en la columna de horas del calendario (vista de tabla y vista normal en modo día) también se puede arrastrar: se pulsa una hora, se arrastra arriba o abajo hasta otra (con el rango resaltado) y al soltar se abre la cita nueva con ese inicio y fin (la última franja incluida). Un clic sin arrastrar sigue tomando solo esa hora con la duración del tratamiento. Código común en `assets/js/utils/calendar_time_labels.js`; nunca cruza la pausa del mediodía.

Cambio posterior: la plantilla de WhatsApp por defecto es la de la clínica en catalán (idioma `ca`), con tres variables: `{{1}}` nombre, `{{2}}` fecha de la cita como «dilluns 16 de gener» o «dijous 3 d'abril» (el texto dice «Tens visita demà, *{{2}}*, a les *{{3}} h*») y `{{3}}` hora («09:00»). La plantilla lleva un botón de llamada, fijo en Meta. El texto está en `Whatsapp_reminders::TEMPLATE_BODY` y se muestra en «Avançat» de la configuración para copiarlo en Meta.

Cambio posterior: botón de notificaciones (campana) en la cabecera, que solo aparece mientras hay algún mensaje sin enviar, con el número de mensajes de WhatsApp que no se han podido enviar (fallidos o por revisar). Lleva a la página «Missatges de WhatsApp» (`whatsapp_messages`), que lista todos los mensajes, también las pruebas enviadas desde la configuración, y marca en rojo los no enviados. Cada mensaje tiene «Reintenta» y eliminar; si al reintentar no se puede enviar, se muestra el motivo con «Cancel·la el missatge», «Marca com a enviat» y «Torna a provar». Un recordatorio solo se reenvía a mano el día antes de la cita, porque el texto dice «demà». Eliminar lo quita de la lista (y lo cancela si aún tenía que enviarse) sin borrar la fila, para que la tarea programada no lo vuelva a crear. Migración 095 (`is_test`, `is_deleted`).

Cambio posterior (16/11/2026), protocolo de envío de la clínica: cada visita recibe un único WhatsApp, a la hora = máximo(día anterior a las 10:00, creación de la visita + 30 min), y no se envía si faltan menos de 2 h para la visita. Los cuatro valores (hora de envío, días antes, espera desde la reserva y antelación mínima) se cambian en la configuración de WhatsApp, sección «Quan s'envia», con un resumen en palabras; también se envía en fines de semana, festivos y fuera de horario. Los 30 min cuentan solo desde la creación: modificar la visita no los reinicia. La tarea programada calcula el momento de cada visita con su día y hora actuales y solo crea la fila al enviar, con clave única por visita (`reminder:<id>`), así que una visita movida antes del envío se envía el día anterior a la nueva fecha, una cancelada no se envía y una ya avisada nunca recibe un segundo mensaje. Un recordatorio fallido se reintenta hasta la antelación mínima y después pasa a «Per revisar». La tarea de WhatsApp corre cada 5 minutos (la sincronización y la limpieza siguen cada 15). La plantilla ya no dice «demà»: `{{2}}` lleva el día entero, «demà, dimecres 18 de novembre», «avui, dimarts 17 de novembre» o, reenviado a mano otro día, «el dijous 19 de novembre»; mientras Meta no apruebe la plantilla nueva queda marcado «La plantilla aprovada diu «demà»», que envía solo la fecha y solo el día antes. Si una visita se mueve de día u hora después de enviar el recordatorio, al guardar se avisa de que el paciente lo recibió con la fecha anterior y hay que avisarlo por teléfono; el mensaje queda marcado en «Missatges de WhatsApp» (también la campana) hasta pulsar «Pacient avisat». El formulario de la cita muestra bajo la casilla de WhatsApp cuándo se enviará o cómo fue. Migración 099 (reglas y `change_to_datetime`, `change_datetime`, `change_handled_datetime`).

Cambio posterior: en la lista de visitas de la ficha, las visitas cuya cita tiene comentario muestran una icona de bocadillo al lado de la fecha; al clicarla se abre el comentario en un globo, sin cambiar de visita. Se cierra al clicar fuera.

Cambio posterior: página **Còpies de seguretat** (Configuració › Còpies de seguretat, `Backups` + librería `Database_backup` + `backups.js`), solo para administradores: «Fes una còpia ara i descarrega-la», la lista de las copias de `storage/backups` (las diarias incluidas) con Descarrega y Restaura, y la subida de un archivo `.sql.gz` o `.sql` para restaurarlo (hasta el límite de subida de PHP, 100 MB en Docker). La copia es un SQL comprimido con todas las tablas en una instantánea consistente (`DROP`/`CREATE` + `INSERT` por lotes, binarios en hexadecimal, `foreign_key_checks = 0`). Restaurar pregunta antes, comprueba que el archivo crea `ea_migrations`, guarda una copia «before-restore» de los datos actuales, borra todas las tablas, ejecuta el archivo y las migraciones pendientes; si el archivo falla a medias vuelve a poner la copia anterior. Acepta también volcados de mysqldump y las copias antiguas de `console backup`. Si el usuario que restaura no es administrador en la copia restaurada, se cierra su sesión. Las descargas y restauraciones quedan en el registro de auditoría. `Instance::backup()` (la copia diaria del cron) usa ahora la misma librería y crea `.sql.gz`. Consola: `php index.php console restore <archivo>`. Los archivos subidos (`storage/uploads`, iconos de patologías) no van en la copia. Test: `tests/Unit/Library/DatabaseBackupTest.php`.

Validación aplicada a la base actual: recuperados 12.420 pacientes y 84.290 visitas; verificados 97.712 campos de texto. Las seis visitas del ejemplo incluyen cuatro exploraciones visibles con plantilla completa. Los 363.686 marcadores se conservan con cero diferencias de coordenadas respecto a los datos originales. Se guardó la copia previa easyappointments-backup-2026-11-15-212114-before-clinical-repair.sql.gz. Pruebas: 32 tests, 80 aserciones; aplicación reconstruida y login HTTP 200.

Corrección de criterio: se retira completamente la plantilla podologo y su disposición especial. La conversión usa únicamente los tratamientos y plantillas existentes. Cura PV 1a y Cura PV se convierten a Cura PV, Cura se convierte a Cura, y el resto se asigna según el tratamiento prescrito y el tipo original. Se unifican los textos en campos ya existentes; el contenido sin campo propio se integra en observaciones, tratamiento o diagnóstico según los campos disponibles. No se muestran IDs ni tablas de importación en los comentarios. Se sustituye el texto generado por la conversión anterior conservando notas posteriores.

Verificación de la conversión corregida aplicada: 1.533 visitas asignadas a Cura y 3.082 a Cura PV. Comprobación de los tratamientos originales Cura PV: cero discrepancias. Cero campos clínicos ocultos en las plantillas originales y cero diferencias en las coordenadas de los 363.686 marcadores. Se verificaron 97.712 textos originales en el conjunto de campos visibles. Pruebas: 35 tests, 111 aserciones. Copia previa: easyappointments-backup-2026-11-15-213710-before-clinical-repair.sql.gz.
