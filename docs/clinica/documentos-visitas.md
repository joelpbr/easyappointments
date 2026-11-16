# Documentos de las visitas

El botón **Documents / Documentos** de cada visita es directamente un desplegable, con secciones separadas de menor a mayor complejidad: justificantes, facturas, recetas, pautas e informes. Al elegir un tipo se abre una **tarjeta independiente**, sobre la visita, con su propio foco de teclado, desplazamiento y cierre con Escape. «Recepta / Receta» es una única entrada; después se elige el medicamento o una receta personalizada en el formulario. Los 39 archivos originales de `Downloads/words` se clasifican en 35 tipos. **Generar** guarda el PDF terminado y lo abre en el visor, también si la tarjeta se cierra mientras se prepara. Pedir de nuevo un documento que imprimiría exactamente lo mismo (misma visita, mismos datos, mismo firmante) abre el ya guardado en lugar de añadir una copia. Cada entrada del historial tiene una única acción: **Ver PDF**.

## Datos y profesionales

Generar un documento queda en el registro de actividad (paciente, título, profesional y fechas). La papelera de cada documento pide confirmación antes de eliminarlo. La eliminación del PDF y su entrada en el registro de actividad se realizan en una misma transacción: se conserva quién lo eliminó, el paciente, el título, el profesional y las fechas, sin copiar el contenido del PDF al log. El servidor comprueba el acceso al paciente y que el documento le pertenezca.

Cada PDF generado se guarda en el historial del paciente antes de abrirse en el visor. El botón de historial de la cabecera permite verlo o imprimir de nuevo el mismo archivo. La lista muestra fecha de generación, visita, profesional y usuario creador; carga 25 documentos cada vez. Los PDF y sus datos se guardan en `patient_documents` y entran en las copias de seguridad de la base de datos. El acceso requiere los mismos permisos clínicos sobre el paciente. Eliminar una visita conserva el PDF y la fecha original; eliminar el paciente elimina sus documentos. El historial recoge las generaciones realizadas a partir de esta función: los PDF anteriores que no se guardaron no se pueden recuperar automáticamente.

- Se completan nombre del paciente, documento de identidad, nacimiento, fecha y hora de visita, tratamiento y precio guardado, así como motivo, diagnóstico y tratamiento clínico cuando existen. Los hallazgos de exploración no se deducen del diagnóstico.
- El panel pregunta por los campos necesarios que faltan. Los datos personales y fechas ya completados se pueden revisar desplegando «Revisar los datos ya rellenados». Las correcciones solo afectan al PDF; no guardan ni modifican silenciosamente la ficha. Los cambios actuales de nombre, documento, nacimiento y texto clínico también se recogen del formulario abierto.
- Facturas y recetas se emiten exclusivamente con los datos de un profesional colegiado: los proveedores activos que tienen número de colegiado en su ficha (sección «Firma de documentos»: número de colegiado, nombre en los documentos y NIF). No hay nombres ni datos personales en el código: se escriben en la ficha de cada profesional. Un profesional colegiado usa automáticamente su propia identidad. Cualquier otro usuario con acceso a la visita debe elegir uno. El servidor verifica esta regla aunque se manipule el formulario, y no emite una factura si a quien firma le falta el NIF. Hay una única plantilla de factura (`invoice.docx`): el membrete toma el nombre, el número de colegiado y el NIF de la ficha.
- Los otros documentos usan al profesional de la visita. Se conserva el membrete original de la clínica, que menciona a sus dos titulares.
- La dirección que imprimen las recetas es la de **Configuración general → Dirección de la clínica**. Si está vacía, la receta no se emite y se pide completarla.
- Los números de factura/recibo y el estado de cobro se introducen explícitamente: no existe una secuencia de facturación ni se presume que la visita esté cobrada. Generar el PDF no registra un cobro.
- Las recetas preseleccionan únicamente el medicamento y su presentación; envases, duración, dosis, unidades e intervalo se revisan y completan expresamente. Las recetas y pautas requieren confirmar su revisión y muestran las alergias disponibles.
- Los archivos se generan temporalmente con permisos privados y se eliminan tras la respuesta, que lleva `Cache-Control: no-store`. El registro de auditoría recoge tipo de documento y profesional, sin copiar los textos clínicos. Se mantienen los permisos de acceso de la ficha.

## Revisión de los originales

Se han inspeccionado los XML de documento, cuadros de texto, controles y opciones de los 39 archivos, además del fondo gráfico de la receta. Las plantillas preparadas conservan geometría, membretes, fondo de receta y opciones de informes; eliminan los ejemplos de pacientes, metadatos de autor, fechas fijas y valores de campos anteriores.

Decisiones de clasificación y limpieza:

- Las dos facturas se unifican en una opción y se elige la plantilla según el profesional autorizado.
- `IBUPROFENO 400.docx` e `IBUPROFENO400.docx` son variantes del mismo medicamento con distintos pacientes de ejemplo: una sola opción.
- `PARACETAMOL 650.docx` contiene **paracetamol de 1 g**, pese a su nombre. Se clasifica por su contenido junto a `PARACETAMOL 1G.docx`. La opción de 650 mg procede de `PARACETAMOL 650 MG.docx`.
- `JUSTIFICANTE IQ1.docx` es una variante bilateral de `JUSTIFICANTE IQ.dotx` con paciente y reposo concretos. Se unifican y se preguntan zona intervenida, reposo y días sin deporte.
- `DIAZEPAM 5 MG.docx` tiene pautas distintas en sus dos copias; `VOLTAREN RETARD 75.docx` mezcla duraciones de siete y diez días; `ITRAGERM 50MG.docx` mezcla intervalos y duraciones. No se importan pautas de estos ejemplos a las recetas.
- `MEPIVACAINA.docx` conserva una mención ajena a paracetamol. Se mantiene únicamente la presentación de mepivacaína y su código nacional.
- Algunos ejemplos contienen varios nombres/documentos de pacientes, incluso en un mismo archivo (`IBUPROFENO600 SOBRES.docx`, `METAMIZOL575.docx`). Ninguno se incorpora a las plantillas distribuidas. Todas las recetas se generan sobre la receta base vacía, no modificando esos ejemplos.
- Las dos pautas prequirúrgicas y el tratamiento homeopático se conservan como instrucciones editables del original, no como una prescripción deducida por el programa. El usuario debe revisarlas expresamente.
- Los informes conservan todas las opciones originales de exploración, permiten texto distinto y preguntan por hallazgos ausentes. No se inventan resultados «normales».

## Adaptar los documentos a otra clínica

Nada de lo que distingue a una clínica está en el código. Está en tres sitios:

| Qué | Dónde |
| --- | --- |
| Nombre, dirección, teléfono, correo y web de la clínica | Configuración general (campos `clinic_name`, `clinic_address`, `clinic_phone`, `clinic_email` y `clinic_link` de las plantillas) |
| Quién firma facturas y recetas, con su número de colegiado y NIF | Ficha de cada profesional, sección «Firma de documentos» (campos `professional_name`, `professional_college` y `professional_tax_id`) |
| Qué documentos hay, cómo se agrupan, qué preguntan y cómo se imprimen | El **paquete de documentos**: un `catalog.json` y sus plantillas Word |

El paquete incluido es el de esta clínica (`application/resources/visit_documents`). Otra clínica deja el suyo en `storage/visit_documents` (con su `catalog.json` y sus `.docx`) y la aplicación lo usa en lugar del incluido, sin tocar código ni reconstruir la imagen: `storage` es el volumen de datos. La carpeta lleva un `.htaccess` que impide descargarla desde fuera; hay que conservarlo. En una instalación anterior a esta función el volumen ya existía y la carpeta no está: se crea a mano copiando también ese `.htaccess` (está en `storage/visit_documents` del repositorio).

`catalog.json` tiene dos listas:

- `groups`: las secciones del menú, en su orden. Cada una tiene `id`, `label` (un texto por idioma) y, si procede, `requires_review` (hay que confirmar la revisión del tratamiento antes de generar, y se muestran las alergias) y `single_entry` (el menú muestra una sola entrada, con su `label`, y el tipo concreto se elige en la tarjeta bajo `choice_label`; así funcionan las recetas).
- `templates`: los tipos de documento. Cada uno tiene `id`, `label`, `group`, `template` (el `.docx`), `restricted` (solo lo firma un profesional colegiado), `defaults` (valores iniciales) y `fields`.

Cada campo tiene `key`, `label`, `type` (`text`, `textarea`, `date`, `time`, `integer` o `money`) y `required`; opcionalmente `options` (desplegable con «Otro»), `max_length`, `min` y `max` (enteros), `not_future` (fechas) y `source` (otro dato del que toma su valor inicial, p. ej. `visit_date`). Los datos que la ficha ya conoce se rellenan por su `key`: `patient_name`, `document_number`, `birth_date`, `date`, `dispensing_date`, `visit_date`, `visit_time`, `description` (tratamiento), `amount` (precio), `reason`, `diagnosis` y `treatment`.

En las plantillas Word, cada dato se escribe como `${clave}`: la `key` de un campo, o uno de los campos `clinic_*` y `professional_*` de la tabla. Un documento no se emite si su plantilla nombra un dato que el catálogo no declara, ni con un dato de la clínica o del firmante en blanco.

Las etiquetas pueden estar en cualquier idioma: se muestra el del usuario y, si el paquete no lo tiene, el primero que haya. Los límites de cada campo y las reglas de revisión se leen solo en el servidor (`Visit_documents`), que se los entrega a la página ya resueltos; no hay que repetirlos en el JavaScript.

## Mantenimiento y comprobaciones

`application/resources/visit_documents/catalog.json` define los grupos del menú, los tipos, campos, opciones, archivos de origen y valores iniciales (formato en «Adaptar los documentos a otra clínica»). Los `.docx` de esa carpeta son las plantillas internas limpias; no se entregan al usuario ni dependen de la carpeta Downloads. LibreOffice Writer las convierte a PDF conservando los membretes y el fondo de las recetas. La imagen Docker instala Writer y fuentes compatibles con Arial/Calibri/Cambria.

Para reconstruirlas con los originales disponibles:

```console
python3 application/scripts/prepare_visit_documents.py /ruta/words application/resources/visit_documents
```

No se deben versionar los originales con pacientes de ejemplo. El preparador comprueba que todos los archivos de entrada estén clasificados. La preparación interna usa `PharData`, DOM y ZIP. La conversión ejecuta Python/LibreOffice mediante argumentos estructurados, con un perfil privado e independiente por solicitud y un límite de 45 segundos; elimina tanto los documentos temporales como el perfil al acabar, también si falla. Las pruebas de `VisitDocumentsTest` comprueban todos los tipos, XML, caracteres especiales, campos obligatorios, fechas, importes y restricciones de profesional. `php tests/integration_visit_documents_pdf.php` verifica los 10 diseños PDF, el texto resultante y la limpieza de archivos temporales; requiere LibreOffice y `pdftotext` (paquete de pruebas `poppler-utils`).

## Inventario completo

Revisión de controles: los 39 originales contienen 44 desplegables (19 en pie diabético, 21 en biomecánica y dos en cada factura), con 210 opciones no vacías contando ambas facturas. Se muestran como desplegables explícitos con «Altre / Otro» para valores personalizados. Se conservan todas las opciones; los importes se unifican, ordenan y normalizan como cantidades. Las fechas de ambos informes se completan con la fecha de la visita. Los originales no tienen hallazgos clínicos seleccionados: «Elija un elemento» no es un valor predeterminado. Las dosis o datos personales de los ejemplos no se usan como valores clínicos automáticos.

La comparación reproducible de todas las opciones con los originales se ejecuta así:

```console
python3 application/scripts/audit_visit_document_options.py /ruta/words application/resources/visit_documents/catalog.json
```

| Tipo del menú | Grupo | Archivos originales |
| --- | --- | --- |
| Receta personalizada | prescriptions | RECEPTA.dotx |
| Amoxicilina 500 mg · 30 comprimits EFG | prescriptions | AMOXICILINA 500.docx |
| Amoxicilina / clavulánico 250 / 62,5 mg · sobres EFG | prescriptions | AMOXICILINACAVULÁNICO 250-62.5.docx |
| Amoxicilina / clavulánico 500 / 125 mg · comprimidos EFG | prescriptions | AMOXICILINACAVULÁNICO500125.docx |
| Arcoxia 60 mg · 28 comprimidos | prescriptions | ARCOXIA 60MG.docx |
| Celestone Cronodose · inyectable | prescriptions | CELESTONE CRONODOSE INJ.docx |
| Ciprofloxacino 500 mg EFG | prescriptions | CIPROFLOXACINO500.docx |
| Diazepam 5 mg | prescriptions | DIAZEPAM 5 MG.docx |
| Enantyum 25 mg · solución oral | prescriptions | ENANTYUAM 25MG.docx |
| Espidifen 600 mg · 40 sobres EFG | prescriptions | ESPIDIFEN 600 MG. 40 SOBRES.docx |
| Fosfomicina EFG · 2 sobres | prescriptions | FOSFOMICINA EFG GRANULADO.docx |
| Ibuprofeno 400 mg EFG | prescriptions | IBUPROFENO 400.docx; IBUPROFENO400.docx |
| Ibuprofeno 600 mg EFG | prescriptions | IBUPROFENO600.docx |
| Ibuprofeno + arginina 600 mg · sobres EFG | prescriptions | IBUPROFENO600 SOBRES.docx |
| Itragerm 50 mg · cápsulas duras, 14 unidades | prescriptions | ITRAGERM 50MG.docx |
| Mepivacaína Normon 2% · solución inyectable · 100 unidades · CN 602914.5 | prescriptions | MEPIVACAINA.docx |
| Metamizol 575 mg EFG | prescriptions | METAMIZOL575.docx |
| Nolotil 575 mg EFG | prescriptions | NOLOTIL 575.docx |
| Nolotil 0,4 g/ml · solución inyectable y para perfusión | prescriptions | NOLOTIL INYECTABLE.docx |
| Paracetamol 1 g EFG | prescriptions | PARACETAMOL 1G.docx; PARACETAMOL 650.docx |
| Paracetamol 650 mg EFG | prescriptions | PARACETAMOL 650 MG.docx |
| Voltaren Retard 100 mg · comprimidos | prescriptions | VOLTAREN RETARD 100.docx |
| Voltaren Retard 50 mg · comprimidos | prescriptions | VOLTAREN RETARD 50.docx |
| Voltaren Retard 75 mg · comprimidos | prescriptions | VOLTAREN RETARD 75.docx |
| Zaldiar 37,5 mg / 325 mg · comprimidos | prescriptions | ZALDIAR 37,5 MG-325MG.docx |
| Zinnat 250 mg EFG | prescriptions | ZINNAT250.docx |
| Factura / recibo | invoices | FACTURAS SARA NUEVO.dotx; FACTURAS SEBAS NUEVO .dotx |
| Justificante de visita | certificates | JUSTIFICANTES.dotx |
| Justificante quirúrgico | certificates | JUSTIFICANTE IQ.dotx; JUSTIFICANTE IQ1.docx |
| Certificado de calzado laboral | certificates | CALZADO LABORAL.dotx |
| Informe biomecánico | reports | INFORME BIOMECÀNIC.dotx |
| Informe de pie diabético | reports | INFORME PEU DIABÈTIC NOU.dotx |
| Pauta con amoxicilina y urgencias | instructions | PAUTA AMOX. Y TELF. URGENCIAS.docx |
| Pauta con ciprofloxacino y urgencias | instructions | PAUTA CIPRO Y TELF. URGENCIAS.docx |
| Tratamiento homeopático | instructions | HOMEOPATIA TTO..docx |
