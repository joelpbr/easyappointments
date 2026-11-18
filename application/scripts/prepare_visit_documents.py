"""Build clean Word templates and a catalog from the clinic's original words folder.

Usage: python3 prepare_visit_documents.py SOURCE OUTPUT
Only generated templates belong in version control, never the original patient examples.
"""
import copy
import io
import json
import pathlib
import re
import sys
import zipfile
import xml.etree.ElementTree as ET

W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
NS = {'w': W}
ET.register_namespace('w', W)
SOURCE, OUTPUT = map(pathlib.Path, sys.argv[1:3])
OUTPUT.mkdir(parents=True, exist_ok=True)
catalog = []
# Sections of the menu, in its order. requires_review: the user confirms having reviewed the treatment before the
# PDF is generated. single_entry: the menu shows one entry and the document type is chosen on the card.
groups = [
    dict(id='certificates', label={'catalan': 'Justificants i certificats', 'spanish': 'Justificantes y certificados'}),
    dict(id='invoices', label={'catalan': 'Factures i rebuts', 'spanish': 'Facturas y recibos'}),
    dict(id='prescriptions', label={'catalan': 'Receptes', 'spanish': 'Recetas'}, requires_review=True,
         single_entry=dict(label={'catalan': 'Recepta', 'spanish': 'Receta'},
                           choice_label={'catalan': 'Medicament', 'spanish': 'Medicamento'})),
    dict(id='instructions', label={'catalan': 'Pautes i tractaments', 'spanish': 'Pautas y tratamientos'},
         requires_review=True),
    dict(id='reports', label={'catalan': 'Informes', 'spanish': 'Informes'}),
]


def field(key, ca, es=None, kind='text', required=True, options=None, **rules):
    # rules: not_future (a date up to today) and min (the lowest integer), checked by the server and the form.
    result = dict(key=key, label={'catalan': ca, 'spanish': es or ca}, type=kind, required=required, **rules)
    if options:
        result['options'] = options
    return result


common = [field('patient_name', 'Nom del pacient', 'Nombre del paciente'),
          field('date', 'Data del document', 'Fecha del documento', 'date')]
identity = [field('document_number', 'DNI / NIE / passaport', 'DNI / NIE / pasaporte'),
            field('birth_date', 'Data de naixement', 'Fecha de nacimiento', 'date', not_future=True)]


def text(node):
    return ''.join(t.text or '' for t in node.findall('.//w:t', NS))


def leaf_paragraphs(root):
    return [p for p in root.findall('.//w:p', NS) if not p.findall('.//w:p', NS)]


def set_text(node, value):
    # Keep the paragraph and first run's formatting, discard stale fields and text.
    props = copy.deepcopy(node.find('w:pPr', NS))
    run_props = node.find('.//w:rPr', NS)
    run_props = copy.deepcopy(run_props) if run_props is not None else None
    node.clear()
    if props is not None:
        node.append(props)
    run = ET.SubElement(node, f'{{{W}}}r')
    if run_props is not None:
        run.append(run_props)
    ET.SubElement(run, f'{{{W}}}t').text = value


def token(key):
    return '${' + key + '}'


def write_template(original, filename, mutate):
    with zipfile.ZipFile(SOURCE / original) as archive:
        source_xml = archive.read('word/document.xml')
        namespaces = dict(value for event, value in ET.iterparse(io.BytesIO(source_xml), events=['start-ns']))
        for prefix, uri in namespaces.items():
            if prefix != 'xml' and not re.fullmatch(r'ns\d+', prefix):
                ET.register_namespace(prefix, uri)
        root = ET.fromstring(source_xml)
        mutate(root)
        # Preserve prefix declarations referenced by mc:Ignorable / Requires after ElementTree serialization.
        for node in root.iter():
            for attr in list(node.attrib):
                if attr.endswith('}Ignorable'):
                    del node.attrib[attr]
        entries = {}
        for name in archive.namelist():
            if name.startswith('word/media/') or name.startswith('word/theme/') or name in (
                'word/styles.xml', 'word/fontTable.xml', 'word/numbering.xml'):
                entries[name] = archive.read(name)
        document_xml = ET.tostring(root, encoding='utf-8', xml_declaration=True)
        # mc:Choice/@Requires contains namespace prefixes as values, invisible to ElementTree's serializer.
        missing = [f' xmlns:{prefix}="{uri}"' for prefix, uri in namespaces.items() if prefix and prefix != 'xml' and f'xmlns:{prefix}='.encode() not in document_xml]
        document_xml = re.sub(rb'(<[\w:]+document\b)', lambda m: m[1] + ''.join(missing).encode(), document_xml, count=1)
        entries['word/document.xml'] = document_xml
        entries['word/settings.xml'] = f'<w:settings xmlns:w="{W}"/>'.encode()
        rel = ET.fromstring(archive.read('word/_rels/document.xml.rels'))
        for child in list(rel):
            target = child.get('Target', '')
            if child.get('TargetMode') == 'External' or 'word/' + target not in entries:
                rel.remove(child)
        ET.register_namespace('', 'http://schemas.openxmlformats.org/package/2006/relationships')
        entries['word/_rels/document.xml.rels'] = ET.tostring(rel, encoding='utf-8', xml_declaration=True)
        entries['_rels/.rels'] = b'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>'
        types = ET.fromstring(archive.read('[Content_Types].xml'))
        for child in list(types):
            if child.tag.endswith('Override') and child.get('PartName', '').lstrip('/') not in entries:
                types.remove(child)
            elif child.get('PartName') == '/word/document.xml':
                child.set('ContentType', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml')
        ET.register_namespace('', 'http://schemas.openxmlformats.org/package/2006/content-types')
        entries['[Content_Types].xml'] = ET.tostring(types, encoding='utf-8', xml_declaration=True)
        with zipfile.ZipFile(OUTPUT / filename, 'w', zipfile.ZIP_DEFLATED) as result:
            for name, content in entries.items():
                result.writestr(name, content)


def add(key, ca, es, group, template, fields, originals, restricted=False, defaults=None):
    catalog.append(dict(id=key, label={'catalan': ca, 'spanish': es}, group=group, template=template,
                        fields=copy.deepcopy(fields), sources=originals, restricted=restricted, defaults=defaults or {}))


def prescription(root):
    parents = {child: parent for parent in root.iter() for child in parent}
    for box in root.findall('.//w:txbxContent', NS):
        shape = parents[box]
        while not shape.tag.endswith('}shape'):
            shape = parents[shape]
        style = shape.get('style', '')
        x = float(re.search(r'margin-left:([\d.]+)pt', style)[1])
        y = float(re.search(r'margin-top:([\d.]+)pt', style)[1])
        key = None
        if x > 370 and (120 < y < 200 or 530 < y < 590):
            value = '${patient_name}\n${document_number}\n${birth_date}'
        elif x > 370 and (205 < y < 280 or 600 < y < 665):
            value = '${professional_name}\nCol. ${professional_college}\n${clinic_address}'
        elif x > 370 and (280 < y < 310 or 665 < y < 690):
            value = '${date}'
        elif x < 60 and (175 < y < 310 or 580 < y < 690):
            value = '${medication}'
        elif 110 < x < 140:
            value = '${packages}'
        elif 250 < x < 370 and (115 < y < 140 or 520 < y < 545):
            value = '${duration}'
        elif 250 < x < 370 and (140 < y < 165 or 545 < y < 570):
            value = '${dosage}'
        elif 250 < x < 310 and (175 < y < 195 or 575 < y < 595):
            value = '${units}'
        elif x >= 310 and (175 < y < 195 or 575 < y < 595):
            value = '${interval}'
        elif 250 < x < 370 and (200 < y < 240 or 610 < y < 680):
            value = '${dispensing_date}' if 215 < y < 240 or y > 665 else ''
        elif x < 60 and 690 < y < 720:
            value = '${diagnosis}'
        elif x < 60 and y > 720:
            value = '${instructions}'
        else:
            value = ''
        first = box.find('w:p', NS)
        for child in list(box):
            if child is not first:
                box.remove(child)
        set_text(first, value)
        # The blank form contains tiny/justified runs and default textbox insets. Normalize them for finished PDFs.
        textbox = parents[box]
        textbox.set('inset', '1pt,7.2pt,1pt,0pt')
        shape.set('filled', 'f')
        shape.set('style', re.sub(r'height:([\d.]+)pt', lambda match: 'height:' + str(max(28, float(match[1]))) + 'pt', shape.get('style', '')))
        props = first.find('w:pPr', NS)
        if props is None:
            props = ET.SubElement(first, f'{{{W}}}pPr')
        for child in list(props):
            props.remove(child)
        ET.SubElement(props, f'{{{W}}}jc', {f'{{{W}}}val': 'left'})
        ET.SubElement(props, f'{{{W}}}spacing', {f'{{{W}}}before':'0', f'{{{W}}}after':'0', f'{{{W}}}line':'200', f'{{{W}}}lineRule':'auto'})
        for run in first.findall('w:r', NS):
            previous = run.find('w:rPr', NS)
            if previous is not None:
                run.remove(previous)
            rprops = ET.Element(f'{{{W}}}rPr')
            run.insert(0, rprops)
            size = '22' if value == '${medication}' else '16' if value in ('${units}', '${interval}') else '18'
            ET.SubElement(rprops, f'{{{W}}}rFonts', {f'{{{W}}}ascii':'Arial', f'{{{W}}}hAnsi':'Arial'})
            ET.SubElement(rprops, f'{{{W}}}sz', {f'{{{W}}}val':size})


write_template('RECEPTA.dotx', 'prescription.docx', prescription)
rx_fields = common + identity + [
    field('medication', 'Medicament i presentació', 'Medicamento y presentación', 'textarea'),
    field('packages', 'Nombre d’envasos', 'Número de envases', 'integer', min=1),
    field('duration', 'Durada del tractament', 'Duración del tratamiento'),
    field('dosage', 'Posologia', 'Posología'), field('units', 'Unitats per presa', 'Unidades por toma'),
    field('interval', 'Pauta / interval', 'Pauta / intervalo'),
    field('dispensing_date', 'Data prevista de dispensació', 'Fecha prevista de dispensación', 'date'),
    field('diagnosis', 'Diagnòstic', 'Diagnóstico', 'textarea', False),
    field('instructions', 'Instruccions', 'Instrucciones', 'textarea', False)]
for item in rx_fields:
    item['max_length'] = {'medication':500, 'diagnosis':200, 'instructions':200, 'duration':35,
                          'dosage':35, 'units':12, 'interval':12, 'patient_name':160}.get(item['key'], 500)
add('prescription', 'Recepta personalitzada', 'Receta personalizada', 'prescriptions', 'prescription.docx', rx_fields, ['RECEPTA.dotx'], True)
medicines = [
 ('amoxicillin', 'Amoxicilina 500 mg · 30 comprimits EFG', ['AMOXICILINA 500.docx']),
 ('amoxiclav_250', 'Amoxicilina / clavulánico 250 / 62,5 mg · sobres EFG', ['AMOXICILINACAVULÁNICO 250-62.5.docx']),
 ('amoxiclav_500', 'Amoxicilina / clavulánico 500 / 125 mg · comprimidos EFG', ['AMOXICILINACAVULÁNICO500125.docx']),
 ('arcoxia', 'Arcoxia 60 mg · 28 comprimidos', ['ARCOXIA 60MG.docx']),
 ('celestone', 'Celestone Cronodose · inyectable', ['CELESTONE CRONODOSE INJ.docx']),
 ('ciprofloxacin', 'Ciprofloxacino 500 mg EFG', ['CIPROFLOXACINO500.docx']),
 ('diazepam', 'Diazepam 5 mg', ['DIAZEPAM 5 MG.docx']),
 ('enantyum', 'Enantyum 25 mg · solución oral', ['ENANTYUAM 25MG.docx']),
 ('espidifen', 'Espidifen 600 mg · 40 sobres EFG', ['ESPIDIFEN 600 MG. 40 SOBRES.docx']),
 ('fosfomycin', 'Fosfomicina EFG · 2 sobres', ['FOSFOMICINA EFG GRANULADO.docx']),
 ('ibuprofen_400', 'Ibuprofeno 400 mg EFG', ['IBUPROFENO 400.docx', 'IBUPROFENO400.docx']),
 ('ibuprofen_600', 'Ibuprofeno 600 mg EFG', ['IBUPROFENO600.docx']),
 ('ibuprofen_sachets', 'Ibuprofeno + arginina 600 mg · sobres EFG', ['IBUPROFENO600 SOBRES.docx']),
 ('itragerm', 'Itragerm 50 mg · cápsulas duras, 14 unidades', ['ITRAGERM 50MG.docx']),
 ('mepivacaine', 'Mepivacaína Normon 2% · solución inyectable · 100 unidades · CN 602914.5', ['MEPIVACAINA.docx']),
 ('metamizole', 'Metamizol 575 mg EFG', ['METAMIZOL575.docx']),
 ('nolotil', 'Nolotil 575 mg EFG', ['NOLOTIL 575.docx']),
 ('nolotil_injection', 'Nolotil 0,4 g/ml · solución inyectable y para perfusión', ['NOLOTIL INYECTABLE.docx']),
 ('paracetamol_1g', 'Paracetamol 1 g EFG', ['PARACETAMOL 1G.docx', 'PARACETAMOL 650.docx']),
 ('paracetamol_650', 'Paracetamol 650 mg EFG', ['PARACETAMOL 650 MG.docx']),
 ('voltaren_100', 'Voltaren Retard 100 mg · comprimidos', ['VOLTAREN RETARD 100.docx']),
 ('voltaren_50', 'Voltaren Retard 50 mg · comprimidos', ['VOLTAREN RETARD 50.docx']),
 ('voltaren_75', 'Voltaren Retard 75 mg · comprimidos', ['VOLTAREN RETARD 75.docx']),
 ('zaldiar', 'Zaldiar 37,5 mg / 325 mg · comprimidos', ['ZALDIAR 37,5 MG-325MG.docx']),
 ('zinnat', 'Zinnat 250 mg EFG', ['ZINNAT250.docx']),
]
for key, name, originals in medicines:
    add('rx_' + key, name, name, 'prescriptions', 'prescription.docx', rx_fields, originals, True, {'medication': name})


def replace_controls(root, keys, fields):
    controls = root.findall('.//w:sdt', NS)
    assert len(controls) == len(keys), (len(controls), keys)
    for control, key in zip(controls, keys):
        options = [n.get(f'{{{W}}}displayText', '').strip() for n in control.findall('.//w:listItem', NS)]
        options = [s for s in options if s]
        for f in fields:
            if f['key'] == key and options:
                if f['type'] == 'money':
                    options = [f"{float(s.replace('€', '').strip().replace(',', '.')):.2f}" for s in options]
                f['options'] = list(dict.fromkeys(f.get('options', []) + options))
                if f['type'] == 'money':
                    f['options'].sort(key=float)
        content = control.find('w:sdtContent', NS)
        texts = content.findall('.//w:t', NS)
        assert texts
        texts[0].text = token(key)
        for t in texts[1:]:
            t.text = ''
        props = control.find('w:sdtPr', NS)
        control.remove(props)


invoice_fields = common + identity + [field('invoice_number', 'Número de factura / rebut', 'Número de factura / recibo'),
    field('description', 'Concepte', 'Concepto'), field('amount', 'Import total (€)', 'Importe total (€)', 'money'),
    field('paid', 'Estat del cobrament', 'Estado del cobro', options=['COBRAT', 'PENDENT'])]
# One layout for every professional: the name, the membership number and the NIF of the letterhead come from the
# record of whoever signs. Both originals only differ in those three lines.
def invoice(root):
    replace_controls(root, ['description', 'amount', 'date'], invoice_fields)
    letterhead = True
    for p in leaf_paragraphs(root):
        s = text(p).strip()
        if letterhead and s == 'SARA BURGUEÑO MELGAREJO':
            set_text(p, '${professional_name}')
        elif letterhead and s.startswith('COL.'):
            set_text(p, 'COL. ${professional_college}')
        elif letterhead and s.startswith('NIF.'):
            set_text(p, 'NIF. ${professional_tax_id}')
            letterhead = False
        elif s.startswith('REBUT Nº:'):
            set_text(p, 'REBUT Nº: ${invoice_number}')
        elif s.startswith('PACIENT:'):
            set_text(p, 'PACIENT: ${patient_name}    NIF: ${document_number}')
        elif s.startswith('DATA NAIXEMENT:'):
            set_text(p, 'DATA NAIXEMENT: ${birth_date}')
        elif s == 'TOTAL':
            set_text(p, 'TOTAL: ${amount} €')
        elif s == 'COBRAT':
            set_text(p, '${paid}')


write_template('FACTURAS SARA NUEVO.dotx', 'invoice.docx', invoice)
add('invoice', 'Factura / rebut', 'Factura / recibo', 'invoices', 'invoice.docx', invoice_fields,
    ['FACTURAS SARA NUEVO.dotx', 'FACTURAS SEBAS NUEVO .dotx'], True)


def certificate(kind):
    def mutate(root):
        paragraphs = leaf_paragraphs(root)
        started = False
        for p in paragraphs:
            s = text(p).strip()
            if s.startswith('Sabadell,'):
                set_text(p, 'Sabadell, ${date}')
            elif s.startswith('Certifico que'):
                started = True
                content = {
                    'attendance': 'Certifico que el/la paciente ${patient_name}, con documento ${document_number}, ha sido visitado/a el día ${visit_date} a las ${visit_time} horas en la presente clínica.',
                    'surgery': 'Certifico que el/la paciente ${patient_name}, con documento ${document_number}, ha sido intervenido/a quirúrgicamente el día ${visit_date} en ${surgical_site} en la presente clínica. Deberá guardar reposo durante ${rest_days} días y no podrá realizar actividad deportiva durante ${sport_days} días.',
                    'footwear': 'Certifico que el/la paciente ${patient_name}, con documento ${document_number}, por su morfología de pie precisa tratamiento ortopodológico, por lo que necesita ${footwear_recommendation}.',
                }[kind]
                set_text(p, content)
            elif started and (s.startswith('se ha sido') or s.startswith('por su morfología')):
                set_text(p, '')
            elif s == 'ATENTAMENTE':
                set_text(p, 'ATENTAMENTE\n${professional_name}\n${date}')
    return mutate


for key, original, ca, es in [
    ('attendance', 'JUSTIFICANTES.dotx', 'Justificant de visita', 'Justificante de visita'),
    ('surgery', 'JUSTIFICANTE IQ.dotx', 'Justificant quirúrgic', 'Justificante quirúrgico'),
    ('footwear', 'CALZADO LABORAL.dotx', 'Certificat de calçat laboral', 'Certificado de calzado laboral')]:
    fields = common + [identity[0]]
    if key in ('attendance', 'surgery'):
        fields += [field('visit_date', 'Data de la visita / intervenció', 'Fecha de la visita / intervención', 'date')]
    if key == 'attendance':
        fields += [field('visit_time', 'Hora de la visita', 'Hora de la visita', 'time')]
    if key == 'surgery':
        fields += [field('surgical_site', 'Zona intervinguda', 'Zona intervenida'),
                   field('rest_days', 'Dies de repòs', 'Días de reposo', 'integer'),
                   field('sport_days', 'Dies sense esport', 'Días sin deporte', 'integer')]
    if key == 'footwear':
        fields += [field('footwear_recommendation', 'Recomanació de calçat', 'Recomendación de calzado', 'textarea')]
    write_template(original, key + '.docx', certificate(key))
    originals = [original] + (['JUSTIFICANTE IQ1.docx'] if key == 'surgery' else [])
    defaults = {'footwear_recommendation': 'calzado laboral suficientemente ancho y con buena amortiguación, tipo deportivo'} if key == 'footwear' else {}
    add(key, ca, es, 'certificates', key + '.docx', fields, originals, defaults=defaults)

bio_keys = ['reason', 'hip', 'knee', 'metatarsal', 'digital', 'joints', 'muscle', 'patella', 'tibia', 'medial', 'lateral', 'forefoot', 'toes', 'limbs', 'heel', 'midfoot', 'forefoot_support', 'toe_off', 'fick', 'diagnosis', 'treatment', 'date']
bio_labels = [('Motiu de consulta','Motivo de consulta'),('Maluc · exploració física','Cadera · exploración física'),('Genoll','Rodilla'),('Fórmula metatarsal',),('Fórmula digital',),('Moviments articulars','Movimientos articulares'),('Balanç muscular','Balance muscular'),('Ròtules','Rótulas'),('Tíbia','Tibia'),('Columna lateral interna',),('Columna lateral externa',),('Avantpeu','Antepié'),('Dits','Dedos'),('EEII',),('Xoc taló','Choque del talón'),('Recolzament migpeu','Apoyo del mediopié'),('Recolzament avantpeu','Apoyo del antepié'),('Enlairament dits','Despegue de los dedos'),('Angle Fick','Ángulo Fick'),('Diagnòstic','Diagnóstico'),('Tractament','Tratamiento'),('Data','Fecha')]
diab_keys = ['diabetes_type','date','reason','thermal','tactile','pain','monofilament','vibration','pedal_pulse','tibial_pulse','cold_feet','claudication','rest_pain','hyperkeratosis','dry_skin','nails','osteoarticular','footwear','hygiene','treatment']
diab_labels = [('Tipus de diabetis','Tipo de diabetes'),('Data de visita','Fecha de visita'),('Motiu de consulta','Motivo de consulta'),('Sensibilitat tèrmica','Sensibilidad térmica'),('Sensibilitat tàctil','Sensibilidad táctil'),('Sensibilitat àlgica','Sensibilidad álgica'),('Monofilament',),('Diapasó','Diapasón'),('Pols artèria pèdia','Pulso arteria pedia'),('Pols tibial posterior','Pulso tibial posterior'),('Peus freds / pal·lidesa / pilositat','Pies fríos / palidez / pilosidad'),('Claudicació intermitent','Claudicación intermitente'),('Dolor en repòs','Dolor en reposo'),('Hiperqueratosi','Hiperqueratosis'),('Pell seca / clivelles','Piel seca / grietas'),('Onicopaties','Onicopatías'),('Alteracions osteoarticulars','Alteraciones osteoarticulares'),('Calçat adequat','Calzado adecuado'),('Higiene',),('Tractament','Tratamiento')]
for key, original, ca, es, keys, labels in [
    ('biomechanical', 'INFORME BIOMECÀNIC.dotx', 'Informe biomecànic', 'Informe biomecánico', bio_keys, bio_labels),
    ('diabetic_foot', 'INFORME PEU DIABÈTIC NOU.dotx', 'Informe de peu diabètic', 'Informe de pie diabético', diab_keys, diab_labels)]:
    fields = [common[0], identity[1]] if key == 'biomechanical' else [common[0]]
    fields += [field(k, *label, kind='date' if k == 'date' else 'textarea') for k, label in zip(keys, labels)]
    for item in fields:
        if item['key'] == 'date':
            item['source'] = 'visit_date'
    extras = ['physical_observations','asymmetries','standing_hip','helbing','standing_observations','dynamic_observations'] if key == 'biomechanical' else []
    for k, label in zip(extras, ['Observacions · exploració física', 'Asimetries', 'Maluc · bipedestació', 'Talons · línia Helbing', 'Observacions · bipedestació', 'Observacions · dinàmica']):
        fields.append(field(k, label, kind='textarea', required=False))
    def report(root):
        replace_controls(root, keys, fields)
        observations = iter(['physical_observations','standing_observations','dynamic_observations'])
        for p in leaf_paragraphs(root):
            s = text(p).strip()
            if s == 'NOM I COGNOMS:':
                set_text(p, 'NOM I COGNOMS: ${patient_name}')
            elif s == 'DATA DE NAIXEMENT:':
                set_text(p, 'DATA DE NAIXEMENT: ${birth_date}')
            elif key == 'biomechanical' and s in ('OBSERVACIONS:', 'ASIMETRIES:', 'MALUC:', 'TALONS LÍNIA HELBING:'):
                k = next(observations) if s == 'OBSERVACIONS:' else {'ASIMETRIES:':'asymmetries','MALUC:':'standing_hip','TALONS LÍNIA HELBING:':'helbing'}[s]
                set_text(p, s + ' ' + token(k))
        body = root.find('w:body', NS)
        signature = ET.Element(f'{{{W}}}p')
        set_text(signature, '${professional_name}')
        section = body.find('w:sectPr', NS)
        body.insert(list(body).index(section) if section is not None else len(body), signature)
    write_template(original, key + '.docx', report)
    add(key, ca, es, 'reports', key + '.docx', fields, [original])

for key, original, ca, es in [
    ('amoxicillin_instructions','PAUTA AMOX. Y TELF. URGENCIAS.docx','Pauta amb amoxicil·lina i urgències','Pauta con amoxicilina y urgencias'),
    ('cipro_instructions','PAUTA CIPRO Y TELF. URGENCIAS.docx','Pauta amb ciprofloxacina i urgències','Pauta con ciprofloxacino y urgencias'),
    ('homeopathy','HOMEOPATIA TTO..docx','Tractament homeopàtic','Tratamiento homeopático')]:
    with zipfile.ZipFile(SOURCE / original) as a:
        original_root = ET.fromstring(a.read('word/document.xml'))
    pars = leaf_paragraphs(original_root)
    start = next(i for i,p in enumerate(pars) if 'TRACTAMENT HOMEOPÀTIC' in text(p)) if key == 'homeopathy' else next(i for i,p in enumerate(pars) if 'DOS DÍAS ANTES' in text(p))
    if key == 'homeopathy':
        body = []
        for p in pars[start + 1:]:
            if 'Sabadell,' in text(p):
                break
            if text(p).strip():
                body.append(text(p).strip())
        default_body = '\n'.join(body)
    else:
        default_body = '\n'.join(text(p).strip() for p in pars[start:] if text(p).strip())
    # Use the original clinic letterhead, with one explicit, editable treatment instead of stale duplicated text.
    def instructions(root):
        certificate('footwear')(root)
        for p in leaf_paragraphs(root):
            if 'Certifico que' in text(p):
                set_text(p, '${patient_name}\n${instructions}\nTeléfono urgencias: ${emergency_phone}')
                props = p.find('w:pPr', NS)
                if props is not None:
                    for child in list(props):
                        if child.tag == f'{{{W}}}jc':
                            props.remove(child)
                    ET.SubElement(props, f'{{{W}}}jc', {f'{{{W}}}val':'left'})
                for size in p.findall('.//w:sz', NS) + p.findall('.//w:szCs', NS):
                    size.set(f'{{{W}}}val', '24')
    write_template('CALZADO LABORAL.dotx', key + '.docx', instructions)
    fields = common + [field('instructions','Pauta / instruccions','Pauta / instrucciones','textarea'),
                       field('emergency_phone','Telèfon d’urgències','Teléfono de urgencias', required=key != 'homeopathy')]
    add(key, ca, es, 'instructions', key + '.docx', fields, [original], defaults={'instructions':default_body,'emergency_phone':'681344344' if key != 'homeopathy' else ''})

covered = {name for item in catalog for name in item['sources']}
assert covered == {p.name for p in SOURCE.iterdir() if p.is_file()}, covered.symmetric_difference(p.name for p in SOURCE.iterdir() if p.is_file())
assert {item['group'] for item in catalog} <= {group['id'] for group in groups}
(OUTPUT / 'catalog.json').write_text(
    json.dumps({'groups': groups, 'templates': catalog}, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
from catalan_visit_pack import localize_pack
localize_pack(OUTPUT)
print(f'{len(covered)} original files classified into {len(catalog)} document types.')
