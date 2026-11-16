"""Compare every original Word dropdown with the generated catalog. No patient text is exported."""
import json
import pathlib
import sys
import xml.etree.ElementTree as ET
import zipfile

source = pathlib.Path(sys.argv[1])
catalog = json.loads(pathlib.Path(sys.argv[2]).read_text(encoding='utf-8'))['templates']
ns = {'w': 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'}
w = '{' + ns['w'] + '}'
total_controls = total_options = files = 0
for path in sorted(source.iterdir()):
    if not path.is_file():
        continue
    matches = [item for item in catalog if path.name in item['sources']]
    assert len(matches) == 1, f'Unclassified or ambiguous source: {path.name}'
    template = matches[0]
    with zipfile.ZipFile(path) as archive:
        controls = []
        for name in archive.namelist():
            if name.startswith('word/') and name.endswith('.xml'):
                root = ET.fromstring(archive.read(name))
                assert not root.findall('.//w:ffData', ns), f'Unmapped legacy controls: {path.name}'
                controls += [node for node in root.findall('.//w:sdt', ns) if node.findall('.//w:listItem', ns)]
    fields = [field for field in template['fields'] if field.get('options') and field['key'] != 'paid']
    if controls:
        assert len(controls) == len(fields), (path.name, len(controls), len(fields))
        for control, field in zip(controls, fields):
            original = [node.get(w + 'displayText', '').strip() for node in control.findall('.//w:listItem', ns)]
            original = [value for value in original if value]
            if field['type'] == 'money':
                original = [f"{float(value.replace('€', '').strip().replace(',', '.')):.2f}" for value in original]
            assert all(value in field['options'] for value in original), (path.name, field['key'], original)
            assert len(set(field['options'])) == len(field['options']), (path.name, field['key'], 'duplicates')
            total_options += len(original)
        print(f'{path.name}: {len(controls)} dropdowns, all options preserved')
    files += 1
    total_controls += len(controls)
assert files == 39 and total_controls == 44, (files, total_controls)
print(f'PASS: {files} originals, {total_controls} dropdowns, {total_options} non-empty source options.')
