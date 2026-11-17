"""Convert a generated Word document to PDF in a private, isolated job directory."""
import os
import pathlib
import signal
import subprocess
import sys

directory = pathlib.Path(sys.argv[1]).resolve(strict=True)
# A Word document of the visits, or the HTML of a signed consent.
name = sys.argv[2] if len(sys.argv) > 2 else 'document.docx'
if name not in ('document.docx', 'document.html'):
    sys.exit(2)
source = directory / name
export = 'pdf:writer_web_pdf_Export' if name.endswith('.html') else 'pdf:writer_pdf_Export'
profile = directory / 'office-profile'
process = subprocess.Popen(
    ['soffice', '-env:UserInstallation=' + profile.as_uri(), '--headless', '--nologo',
     '--nodefault', '--norestore', '--convert-to', export, '--outdir', str(directory), str(source)],
    stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True,
    env={**os.environ, 'HOME': str(directory), 'SAL_USE_VCLPLUGIN': 'svp'},
)
try:
    result = process.wait(timeout=45)
except subprocess.TimeoutExpired:
    os.killpg(process.pid, signal.SIGKILL)
    process.wait()
    sys.exit(2)
pdf = directory / 'document.pdf'
sys.exit(0 if result == 0 and pdf.is_file() and pdf.read_bytes().startswith(b'%PDF-') else 1)
