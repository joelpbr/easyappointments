"""Read a private working copy of an ODS 11 Firebird database into JSON lines.

Never executes SQL supplied by the upload. Called by Podologo_import, not an HTTP endpoint.
The original database is retained separately; all source tables are exported without truncation.
"""
import base64
import datetime
import decimal
import json
import os
import sys

import fdb


def encode(value):
    if isinstance(value, (datetime.datetime, datetime.date, datetime.time)):
        return value.isoformat(sep=" ") if isinstance(value, datetime.datetime) else value.isoformat()
    if isinstance(value, decimal.Decimal):
        return str(value)
    if isinstance(value, bytes):
        return {"base64": base64.b64encode(value).decode("ascii")}
    raise TypeError(type(value).__name__)


def export(source, destination):
    # Workers share engine locks (including security2.fdb), but never a working database or temp files.
    os.environ.setdefault("FIREBIRD_LOCK", "/opt/podologo/locks")
    os.environ["FIREBIRD_TMP"] = os.path.dirname(os.path.abspath(source))
    fdb.load_api(os.environ.get("PODOLOGO_FB_LIBRARY", "/opt/podologo/firebird/lib/libfbembed.so"))
    connection = fdb.connect(dsn=os.path.abspath(source), user="SYSDBA", password="masterkey",
                             charset="WIN1252", no_db_triggers=1)
    connection.begin(tpb=fdb.ISOLATION_LEVEL_READ_COMMITED_RO)
    cursor = connection.cursor()
    try:
        cursor.execute("SELECT TRIM(RDB$RELATION_NAME) FROM RDB$RELATIONS "
                       "WHERE COALESCE(RDB$SYSTEM_FLAG, 0) = 0 AND RDB$VIEW_BLR IS NULL ORDER BY 1")
        tables = [row[0] for row in cursor]
        required = {"PACIENTE", "PERSONAL", "VISITA", "QUIROPODIAPATOLOGIA", "QUIROPODIAPATOLOGIACOLOCADA"}
        if not required.issubset(tables):
            raise ValueError("This is not a supported Podologo database.")
        counts = {}
        with open(destination, "w", encoding="utf-8") as output:
            for table in tables:
                cursor.execute('SELECT * FROM "' + table.replace('"', '""') + '"')
                columns = [column[0] for column in cursor.description]
                count = 0
                for row in cursor:
                    output.write(json.dumps([table, dict(zip(columns, row))], default=encode,
                                            ensure_ascii=False, separators=(",", ":")) + "\n")
                    count += 1
                counts[table] = count
            output.write(json.dumps(["__complete__", counts]) + "\n")
    finally:
        connection.rollback()
        connection.close()


if __name__ == "__main__":
    try:
        export(sys.argv[1], sys.argv[2])
    except Exception as error:
        # Do not print patient data or database query values to HTTP responses/logs.
        print("Podologo reader failed: " + type(error).__name__, file=sys.stderr)
        sys.exit(1)
