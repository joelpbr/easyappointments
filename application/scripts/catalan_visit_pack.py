"""Apply the clinic's Catalan wording to its imported Word layouts and catalog."""
import json
import re
from xml.dom import minidom
from zipfile import ZipFile


CERTIFICATES = {
    'attendance': 'Certifico que ${patient_name}, amb document d’identitat ${document_number}, ha assistit a una visita en aquesta clínica el dia ${visit_date} a les ${visit_time} h.',
    'surgery': 'Certifico que ${patient_name}, amb document d’identitat ${document_number}, s’ha sotmès a una intervenció quirúrgica en aquesta clínica el dia ${visit_date}.\nZona intervinguda: ${surgical_site}\nDies de repòs: ${rest_days}\nDies sense activitat esportiva: ${sport_days}',
    'footwear': 'Certifico que ${patient_name}, amb document d’identitat ${document_number}, necessita tractament ortopodològic per la morfologia del peu.\nRecomanació de calçat laboral: ${footwear_recommendation}',
}

HEADINGS = {
    'DATA NAIXEMENT:': 'DATA DE NAIXEMENT:', 'REBUT Nº:': 'NÚM. DE REBUT:',
    '    NIF: ${document_number}': '    DOCUMENT: ${document_number}',
    'FORMULA DIGITAL': 'FÓRMULA DIGITAL', 'FÒRMULA METATARSAL': 'FÓRMULA METATARSAL',
    'EXPLORACIÓ BIPEDESTACIÓ': 'EXPLORACIÓ EN BIPEDESTACIÓ', 'TIBIA:': 'TÍBIA:',
    'TALONS LÍNIA HELBING': 'TALONS (LÍNIA DE HELBING)', 'EEII:': 'EXTREMITATS INFERIORS:',
    'XOC TALÓ': 'CONTACTE INICIAL DEL TALÓ', 'RECOLZAMENT MIGPEU': 'SUPORT DEL MIGPEU',
    'RECOLZAMENT AVANTPEU': 'SUPORT DE L’AVANTPEU', 'ENLAIRAMENT DITS': 'ENLAIRAMENT DELS DITS',
    'ANGLE FICK': 'ANGLE DE FICK', 'INFORME PEU DIABÈTIC': 'INFORME DEL PEU DIABÈTIC',
    'TIPUS DIABETIS:': 'TIPUS DE DIABETIS: ', 'DATA VISITA': 'DATA DE LA VISITA',
    'MOTIU CONSULTA': 'MOTIU DE CONSULTA', 'AVALUACIÓ SENSIBILITAT': 'AVALUACIÓ DE LA SENSIBILITAT',
    'SUPERCIAL': 'SUPERFICIAL', '( PINPRICK)': '(PUNXADA)', 'VIBRATÒRIA(': 'VIBRATÒRIA (',
    'POLS ARTÈRIA PÈDIA': 'POLS DE L’ARTÈRIA PÈDIA', 'POLS ARTÈRIA TIBIAL POSTERIOR': 'POLS TIBIAL POSTERIOR',
    'PAL.LIDESA, PÈRDUA PIL.LOSITAT': 'PAL·LIDESA, PÈRDUA DE PILOSITAT',
    'HIPERQUERATOSIS': 'HIPERQUERATOSI', 'PELL SECA-CLIVELLES TALONS': 'PELL SECA O CLIVELLES ALS TALONS',
    'UTILITZA CALÇAT ADEQUAT:': 'ÚS DE CALÇAT ADEQUAT: ',
    '*** UN COP A L´ANY US FAREM ARRIBAR UN INFORME PER SEGUIMENT DE LES VISITES DE CATSALUT.':
        'Un cop l’any us farem arribar un informe de seguiment de les visites del CatSalut.',
    '08207 sabadell': '08207 Sabadell',
}

MEDICINES = {
    'Amoxicilina / clavulánico': 'Amoxicil·lina / àcid clavulànic', 'Amoxicilina': 'Amoxicil·lina',
    'Ciprofloxacino': 'Ciprofloxacina', 'Ibuprofeno': 'Ibuprofèn', 'Mepivacaína': 'Mepivacaïna',
    'solución inyectable y para perfusión': 'solució injectable i per a perfusió',
    'solución inyectable': 'solució injectable', 'solución oral': 'solució oral',
    'inyectable': 'injectable', 'comprimidos': 'comprimits', 'cápsulas duras': 'càpsules dures',
    'unidades': 'unitats',
}

OPTIONS = {
    'CIRUGIA UNGUEAL': 'CIRURGIA UNGUIAL', 'AUMENTAD': 'AUGMENTAD', 'AUMENTAT': 'AUGMENTAT',
    'GENU VARUS': 'GENU VARUM', 'GENUS RECURVATUM': 'GENU RECURVATUM', 'HIPERMOVILITAT': 'HIPERMOBILITAT',
    'CAIGUDA RADIS CENTRALS': 'CAIGUDA DELS RADIS CENTRALS', 'DITS URPA': 'DITS EN URPA',
    'HIERRECOLZAMENT 1ER DIT': 'SUPORT EXCESSIU DEL PRIMER DIT',
    'HIPEREXTENSIÓ 1ER DIT': 'HIPEREXTENSIÓ DEL PRIMER DIT', 'ABDUCCIÓ 1ER DIT': 'ABDUCCIÓ DEL PRIMER DIT',
    'INSUFICIÈNCIA PRIMER RADI': 'INSUFICIÈNCIA DEL PRIMER RADI', 'INSUFICIÈNCIA 5É RADI': 'INSUFICIÈNCIA DEL CINQUÈ RADI',
    'INSUFICIÈNCIA 1º RADI': 'PER INSUFICIÈNCIA DEL PRIMER RADI',
    'VALGOS': 'VALGS', 'VAROS': 'VARS', 'VALGO': 'VALG', 'VARO': 'VAR',
    'PEUS CAVOS': 'PEUS CAVUS', 'NEUROMA MORTON': 'NEUROMA DE MORTON',
    'SOPORTS PLANTARS PERSONALIZATS': 'SUPORTS PLANTARS PERSONALITZATS',
    'REVISIÓ 6 MESOS': 'REVISIÓ AL CAP DE 6 MESOS', 'REVISIÓ 1 ANY': 'REVISIÓ AL CAP D’UN ANY',
    'HIPERGLUCEMIA': 'HIPERGLUCÈMIA', 'PEU RISC': 'PEU DE RISC', 'FLEBLE': 'FEBLE',
    ' PEU ESQUERRE': ' AL PEU ESQUERRE', ' PEU DRET': ' AL PEU DRET',
    'DORS DITS': 'DORS DELS DITS', 'PULPELL DITS': 'PULPELL DELS DITS',
    'NO PRESENTA': 'ABSENT', 'NO PRESENT': 'ABSENT', 'EXCÉS TALÓ': 'EXCÉS DE TALÓ',
    'NO.MANCA D´AMORTIGUACIÓ': 'NO. MANCA D’AMORTIMENT', 'Tall i fresat ungueal.': 'Tall i fresatge unguial.',
    'Recomanem realitzar les tres consultes anuals per presència peu de risc.':
        'Recomanem fer les tres visites anuals per la presència d’un peu de risc.',
    'QUIROPÒDIA.NO PRESENTA CAP PATOLOGIA VASCULAR NI NEUROPÀTICA CRÒNICA.':
        'Quiropòdia. No presenta cap patologia vascular ni neuropàtica crònica.',
}


def translate(value, terms):
    pattern = '|'.join(map(re.escape, sorted(terms, key=len, reverse=True)))
    return re.sub(pattern, lambda match: terms[match[0]], value)


def catalan_option(key, original):
    value = translate(original, OPTIONS)
    if key in ('thermal', 'tactile', 'pain', 'vibration'):
        return value.replace('POSITIU', 'POSITIVA').replace('NEGATIU', 'NEGATIVA')
    if key == 'joints':
        return {'NORMAL': 'NORMALS', 'REDUÏT': 'REDUÏTS'}.get(value, value)
    if key == 'patella' and value == 'NORMAL':
        return 'NORMALS'
    return value


def localize_word(path):
    with ZipFile(path) as archive:
        parts = [(info, archive.read(info)) for info in archive.infolist()]
    xml = minidom.parseString(dict((info.filename, data) for info, data in parts)['word/document.xml'])
    for p in xml.getElementsByTagName('w:p'):
        if p.getElementsByTagName('w:p'):
            continue
        nodes = [t.firstChild for t in p.getElementsByTagName('w:t') if t.firstChild]
        original = ''.join(n.data for n in nodes)
        if not original.strip():
            continue
        terms = dict(HEADINGS)
        if original.strip().startswith('Certifico que') and path.stem in CERTIFICATES:
            terms[original.strip()] = CERTIFICATES[path.stem]
            for alignment in p.getElementsByTagName('w:jc'):
                alignment.setAttribute('w:val', 'left')
        if path.stem == 'diabetic_foot' and p.parentNode.tagName == 'w:body':
            if re.fullmatch(r'-+', original.strip()):
                p.parentNode.removeChild(p)
                continue
            if not p.getElementsByTagName('w:pict') and not p.getElementsByTagName('w:drawing'):
                props = next(iter(p.getElementsByTagName('w:pPr')), None)
                if props is None:
                    props = xml.createElement('w:pPr')
                    p.insertBefore(props, p.firstChild)
                spacing = next(iter(props.getElementsByTagName('w:spacing')), None)
                if spacing is None:
                    spacing = xml.createElement('w:spacing')
                    props.appendChild(spacing)
                spacing.setAttribute('w:after', '45')
        if 'ATENTAMENTE' in original:
            # Footwear and instruction sheets already have a dated heading.
            terms[original.strip()] = ('${professional_name}\nData d’emissió: ${date}' if path.stem in
                                       ('surgery', 'attendance') else '${professional_name}')
        phone = '${?emergency_phone}' if path.stem == 'homeopathy' else '${emergency_phone}'
        terms['Teléfono urgencias: ${emergency_phone}'] = 'Telèfon d’urgències: ' + phone
        # Word can split a printed word across runs. Preserve formatting outside each replaced span.
        offsets = []
        for index, node in enumerate(nodes):
            offsets.extend((index, i) for i in range(len(node.data)))
        for match in reversed(list(re.finditer('|'.join(map(re.escape, sorted(terms, key=len, reverse=True))), original))):
            first, start = offsets[match.start()]
            last, end = offsets[match.end() - 1]
            tail = nodes[last].data[end + 1:]
            for node in nodes[first + 1:last + 1]:
                node.data = ''
            nodes[first].data = nodes[first].data[:start] + terms[match[0]] + tail
    with ZipFile(path, 'w') as archive:
        for info, data in parts:
            archive.writestr(info, xml.toxml(encoding='utf-8') if info.filename == 'word/document.xml' else data)


def localize_pack(directory):
    catalog = directory / 'catalog.json'
    data = json.loads(catalog.read_text(encoding='utf-8'))
    for item in data['templates']:
        key, defaults = item['id'], item['defaults']
        if key.startswith('rx_'):
            name = translate(defaults['medication'], MEDICINES)
            item['label']['catalan'] = defaults['medication'] = name
        if key == 'footwear':
            defaults['footwear_recommendation'] = 'Calçat de tipus esportiu, prou ample i amb un bon amortiment.'
        if key in ('amoxicillin_instructions', 'cipro_instructions'):
            antibiotic, ibu, para = (
                ('amoxicil·lina / àcid clavulànic de 250/62,5 mg en sobres', '400 mg', '650 mg')
                if key == 'amoxicillin_instructions' else ('ciprofloxacina de 500 mg', '600 mg', '1 g'))
            defaults['instructions'] = (
                f'Dos dies abans de la intervenció, comenceu a prendre {antibiotic} (antibiòtic), dues vegades al dia durant 10 dies.\n'
                f'Dues hores abans de la intervenció, preneu un ibuprofèn de {ibu}. Quatre hores després, preneu un paracetamol de {para}. Seguiu aquesta pauta durant les primeres 72 hores.')
        if key == 'homeopathy':
            defaults['instructions'] = 'Antimonium crudum 7 CH\n3 grànuls abans de dinar i 3 grànuls abans de sopar.\nThuya occidentalis\n10 grànuls un cop per setmana.'
        for field in item['fields']:
            if 'options' in field:
                options = [catalan_option(field['key'], v) for v in field['options']]
                field['options'] = list(dict.fromkeys(options))
    catalog.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    for path in directory.glob('*.docx'):
        if path.stem != 'prescription':  # The college's two-copy prescription artwork is already in Catalan.
            localize_word(path)
