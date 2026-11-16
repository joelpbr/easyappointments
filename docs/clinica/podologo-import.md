# Importing the original Podologo database

Read [MIGRATION_RULES.md](../../MIGRATION_RULES.md) first for the consolidated clinic rules, final mappings, preservation requirements and verification procedure. That document records the final decisions where older change-history entries differ.

Use **Settings → Backups → Upload** and select `podologo.db` directly. The application detects the Firebird ODS 11 header from the file contents; it does not treat the binary database as a SQL or text file. SQL backup restoration keeps its existing behavior.

The `.db` import adds patients, clinical visits, pathology catalog entries and foot markers. It preserves the current application accounts and data, creates a safety backup first, and rejects a second legacy import to prevent duplicates. Historical staff reuse matching clinic providers or receive inactive accounts with random credentials; original login credentials are never activated. Historical visits reuse the existing treatment catalog. Their start times are rounded down to the preceding quarter-hour and their end times use the assigned service's default duration. The original timestamps remain unchanged in the source archive. Prescribed Cura PV and Cura override the general chiropody flag. Other legacy tables and fields, including the original dates with fractions of seconds, binary values and identifiers, are preserved in `podologo_source_rows`; they are not all exposed as native application screens.

## Drawings and icons

The converter uses only the existing `feet`, `surgery`, `exploration`, `silicone` and `notes` templates. No imported-only template or new visible fields are added. Motive, diagnosis and treatment go into the existing matching fields. Information that does not have its own field in that template is combined as readable prose in an existing visible field: observations when available, treatment for exploration, or diagnosis for silicone. Do not prepend technical IDs or generated treatment headings; label measurements only when needed to preserve their meaning. Saving the form therefore retains the complete narrative even when the form has fewer fields.

Original linked treatments identify Cura PV (including Cura PV 1a), Cura, Wallet, Ortonixia, infiltration and orthoses. Existing catalog entries are reused; the general QUIROPODIA flag does not erase a more specific prescribed care treatment. Internal IDs and default unselected form flags are excluded from clinical prose. The complete unchanged source stays archived for traceability.

Run `php -d memory_limit=512M index.php console repair_podologo_clinical` to correct an earlier import. It creates a safety backup, replaces only the converter's previous generated text, preserves later notes, and checks every original nonempty visit text across the existing visible fields. Earlier converter formatting is retained internally only to recognize and remove that generated content accurately. It does not change the application templates or form layout.

Every marker retains its signed integer `X_1`, `Y_1`, `X_2`, `Y_2` values, diagram number, visit and pathology links, healed state, and annotations. The importer compares a SHA-256 digest of these fields (including the destination links and canvas metadata) against a fresh database read before committing. Any mismatch rolls back the entire import. Empty historical drawings are explicitly saved as empty, preventing accidental inheritance from another visit.

The database does **not** contain its external icon images or the old drawing canvas dimensions. As requested for this database, the current foot backgrounds are reused. Existing sample icons are matched by pathology name; unmatched entries show a neutral `?` icon. All imported catalog entries can be renamed and their icons replaced on **Pathologies**. The original image filenames remain in the source archive.

**360 × 300 is a provisional canvas, not a measured original size.** The upload exposes editable width and height fields. Display positions use percentages of this canvas on the current higher-resolution backgrounds; raw coordinates are never rewritten. Pixel-perfect anatomical placement requires later calibration against an old drawing, especially if a new image has different cropping or proportions. The canvas is stored with each visit and marker and is retained when drawings are copied to later visits.

## Runtime

Rebuild the application image after updating (`docker compose build app` and restart the app service), then run the normal migrations. Migration 096 adds the source archive and drawing layout columns. The Docker PHP upload limit is 256 MB, with 260 MB allowed for the multipart request.

The image installs Python, `fdb==2.0.4` and the compatible Firebird 2.5 embedded reader using checksum-verified archives. The supplied reader installation targets Linux x86-64. For a non-Docker Linux x86-64 installation, install Python 3 with venv support, curl and ca-certificates, then run `docker/install-podologo-reader.sh` as an administrator. The PHP worker needs these environment variables:

```
FIREBIRD=/opt/podologo/firebird
FIREBIRD_LOCK=/opt/podologo/locks
LD_LIBRARY_PATH=/opt/podologo/firebird/lib:/opt/podologo/deps/lib/x86_64-linux-gnu
```

The executable defaults to `/opt/podologo/venv/bin/python`; `PODOLOGO_PYTHON` and `PODOLOGO_FB_LIBRARY` can override the reader paths. PHP must allow `proc_open`. Allow sufficient local disk space for the private working copy, streamed intermediate export, safety backup and database transaction (several GB for this database). Configure any reverse proxy upload size and request timeout accordingly. Processing can take several minutes.

The installer grants `www-data` access to the embedded reader’s security database and shared lock directory. If PHP uses another OS account, adjust those two owners accordingly. The reader has also been checked under the unprivileged `www-data` account.

The reader opens only a private copy, disables Firebird connection triggers, uses a read-only transaction and executes fixed metadata/data queries. Temporary files are removed after success or failure. Uploaded data is never used as executable SQL. The original `.db` remains unchanged.

## Validation

Text conversion accepts original Windows-1252 or UTF-8 BLOBs and repairs recognizable UTF-8 sequences previously misread as Windows-1252/Latin-1 (for example, `Ã‘` → `Ñ`, `Ã±` → `ñ`, `1Âº` → `1º`). Correct text, case and whitespace are retained. The source archive remains unchanged. To preview corrections in existing human-readable fields, run `php application/scripts/repair_podologo_encoding.php`; add `--apply` to create a safety backup, update atomically and verify all reviewed table fields, including unchanged drawing coordinates. Credentials, image paths, JSON layouts and raw source rows are excluded from conversion.

The supplied database was read and imported into an isolated MariaDB test database: 12,420 patients, 84,290 clinical visits, 144 pathology definitions, 363,686 markers and 691,292 original source rows. The importer verified all marker fields before committing. Tests also cover binary content detection, negative coordinates, rejection of fractional coordinates and unknown views, and icon fallback behavior.

An authenticated multipart upload of the actual 151,134,208-byte `.db` through `backups/restore` returned success in 209 seconds. The clinical-record endpoint returned the original negative coordinates and four canvas layouts. A second upload was rejected with HTTP 400 and no duplicate data. The focused tests passed (21 tests, 39 assertions); the full suite had one unrelated timezone alias failure (`Asia/Calcutta`) in this environment. Frontend asset compilation passed; the distribution ZIP task could not finish because its Docker/Composer fallback is unavailable on this host.
