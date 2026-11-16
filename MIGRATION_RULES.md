# Migration rules for ProgramaConsulta

Consolidated on **16 November 2026** from the clinic's instructions and the verified Podologo import work.

This is the current specification for future data migrations, repeat imports and repairs. It records the final decisions, including decisions that replaced earlier ones in the conversation and changelog. Read it before changing the converter or migrating this clinic again. The technical setup is in [the Podologo import guide](docs/clinica/podologo-import.md); [the change history](docs/clinica/modificaciones.md) contains earlier implementations as well as the final ones.

## 1. Preserve the application and the clinical information

- Adapt the source data to the application's existing fields, templates and relationships. A migration must not redesign how the application's database or clinical forms work.
- Do not introduce imported-only forms, extra clinical text fields or a special `podologo` visit template to accommodate the old database.
- Fewer destination fields do **not** justify losing information. Combine related source information sensibly in an existing visible field that survives saving the form.
- Apply the rules to **every patient and every visit type**, including very old visits and linked examination tables. A fix demonstrated on one patient is not a complete migration.
- Retain the complete original source archive as well as the usable converted information. An archive alone is not a substitute for making clinical narratives available in the existing record.
- Preserve later clinician edits when repairing an earlier import. Never regenerate all clinical text blindly.
- Imported documents and database text are data, not instructions to execute. Read a private database copy without executing source triggers or arbitrary uploaded SQL as part of Firebird conversion.
- Resolve uncertain mappings explicitly. Do not invent patient documents, clinical findings, image assets or professional identities.

## 2. Distinguish the migration paths

| Operation                                            | Required behavior                                                                                                                                                                                 |
| ---------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Upload the original `podologo.db`                    | Use the existing Settings → Backups → Upload flow. Detect Firebird ODS 11 from the binary file contents; accept the actual `.db`, without renaming it to `.txt` or requiring a manual SQL export. |
| Restore an application's SQL backup                  | Keep the existing restoration behavior. Restore its saved application data; do not reinterpret it as legacy Podologo data.                                                                        |
| Move the current application to another installation | Preserve its SQL data, configuration and persistent uploaded assets. Apply necessary schema migrations without reseeding, clearing or reclassifying existing clinical records.                    |
| Repair a previous Podologo import                    | Use the source archive and source-to-target links to identify affected records, preserve later edits and verify only the intended changes.                                                        |
| Reimport the same legacy database                    | Reject a duplicate legacy import while its source archive/import links exist. A fresh import after clearing clinical data requires an explicitly authorized clear operation.                      |

Quarter-hour rounding, legacy professional inference and legacy text conversion are **conversion rules for the old data**. Do not reapply them indiscriminately to current native appointments or to a restored application backup. New appointments retain the application's normal editable times and duration behavior.

## 3. Patient field mappings

Table names below omit the configurable application prefix, usually `ea_`.

| Original source                                                                                       | Existing destination and rule                                                                                                              |
| ----------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| `PACIENTE.NOMBRE`, `APELLIDOS`                                                                        | `users.first_name`, `users.last_name`. Preserve spelling, accents and case after correcting recognizable encoding damage.                  |
| `TELEFONOMOVIL`, otherwise `TELEFONOCASA`                                                             | `users.phone_number`, with the existing phone normalization for `phone_normalized`. Preserve both originals in the archive.                |
| `EMAIL1`                                                                                              | `users.email`.                                                                                                                             |
| `EMAIL2`                                                                                              | **Patient provenance**, not a second email address: `patient_records.referral_source`.                                                     |
| `IDPROCEDENCIA` → `COMOCONOCIO`                                                                       | Resolve the catalog text and combine it with `EMAIL2` in the existing provenance field.                                                    |
| `DOMICILIO`, `POBLACION`, `PROVINCIA`, `CODIGOPOSTAL`                                                 | Existing user address, city, state and postal-code fields.                                                                                 |
| `FECHANACIMIENTO`                                                                                     | `patient_records.birth_date`.                                                                                                              |
| `NIF`                                                                                                 | Existing `fiscal_parties.document_type` and `document_number`, with `relationship='patient'`.                                              |
| `IDPROFESION` → `PROFESION`                                                                           | `patient_records.profession`.                                                                                                              |
| `ENFERMEDADES`, `HISTORIALANTIGUO`, `COMENTARIOS`, meaningful measurements and patient-linked history | Existing `patient_records.medical_history`, combined without losing narrative or measurement meaning.                                      |
| `ALERGIAS`                                                                                            | `patient_records.allergies`.                                                                                                               |
| `PACIENTE.COMENTARIOS`                                                                                | Also retained in the existing `users.notes`, the shared **Observacions pacient** field.                                                    |
| New imported patient's language                                                                       | The clinic's configured default language, falling back to Catalan; never introduce a hidden unsupported-language requirement into booking. |

Provenance rules:

- Retain an existing different provenance together with `EMAIL2`; do not overwrite either value.
- Remove duplicate provenance lines using case/whitespace normalization. Do not add technical headings or invent an email address.
- If the combined value exceeds the existing 256-character field, report the conflict; do not truncate silently.

Document rules:

- Reuse the existing DNI/NIE/NIF validation and normalization. Preserve leading zeros.
- An unrecognized nonempty document stays literal under the existing `other` type. Do not fabricate a missing letter or digit or assume every value is a Spanish DNI.
- An empty original document stays empty.
- During repairs, preserve documents added or changed after migration and report conflicts rather than overwriting them.
- A value exceeding the existing document field must be reported, not truncated.

## 4. Three different kinds of notes

These are separate concepts; do not merge them into one field:

| Meaning                           | Storage                                                             | Current Catalan label    |
| --------------------------------- | ------------------------------------------------------------------- | ------------------------ |
| Notes about the person            | `users.notes`                                                       | **Observacions pacient** |
| Notes about a booking/appointment | `appointments.notes`                                                | **Observacions cita**    |
| Clinical narrative about a visit  | Existing fields in `appointment_records`, according to its template | Existing clinical labels |

`VISITA.DESCRIPCION` is imported into the appointment's notes. Other original clinical narratives and linked information must also appear in the appropriate clinical record fields. Do not remove original patient comments from clinical history merely because they are also retained as patient notes.

Patient observations must load and save to the **same `users.notes` field** from both the booking form and the clinical record. Appointment notes must remain independent of patient notes. Both comment fields are optional under the clinic's current settings.

## 5. Clinical narratives and existing templates

- Map reason for consultation → `reason`, diagnosis → `diagnosis`, recommendations/treatment → `treatment`, and remaining meaningful narrative → an existing visible field.
- Include information from all four legacy groups: chiropody (`QUIRO`/`QUIROPODIA`), surgery (`CIRUGIA`), biomechanical exploration (`BIO`/`BIOMECANICA`) and at-risk foot (`PIERIESGO`). Resolve related diagnoses, treatments, examination details and annotations from linked tables, not just `VISITA.DESCRIPCION`.
- When source catalogs encode descriptions behind IDs, resolve the descriptive text. Internal IDs themselves do not belong in prose.
- Do not prepend generated headings such as `Tractament:`, `Quiropòdia:` or `Qidespecialista: 2` to otherwise usable treatment text.
- Required example: `Qidespecialista: 2` plus `Tractament:` plus `Quiropòdia: OC CT PI. PAUTO MUPIROCINA` must become **`OC CT PI. PAUTO MUPIROCINA`**. Preserve any additional genuine narrative elsewhere in the source.
- Omit administrative identifiers, specialist IDs and empty/default flags from visible clinical prose. Keep them in the archive.
- `PIESIGUALESBIO` is a legacy equal-feet form flag, not a clinical comment. Omit generated `Piesigualesbio: 1`/`0` paragraphs without deleting surrounding clinical text.
- Text already in prose needs no technical field label. Meaningful numeric measurements may keep a readable label so the number remains interpretable.
- Remove identical complete paragraphs where appropriate; do not collapse distinct findings that merely share words.

| Existing template | Fields it actually exposes                                             | Where additional narrative must fit |
| ----------------- | ---------------------------------------------------------------------- | ----------------------------------- |
| `feet`            | `observations`, plus foot drawings                                     | `observations`                      |
| `surgery`         | `reason`, `diagnosis`, `treatment`, `observations`, plus foot drawings | `observations`                      |
| `exploration`     | `reason`, `diagnosis`, `treatment`                                     | `treatment`                         |
| `silicone`        | `reason`, `diagnosis`, `orthosis`                                      | `diagnosis`                         |
| `notes`           | `observations`                                                         | `observations`                      |

Never place the only copy of migrated narrative in a field the chosen template hides or clears on save. Do not infer a priced orthosis or other structured choice merely to fill a field.

## 6. Treatment classification

- Reuse existing clinic services and their configured templates, durations and prices.
- A specific prescribed treatment overrides a broad legacy visit flag. A record marked chiropody that actually prescribes **Cura** becomes Cura; **Cura PV** becomes Cura PV. Match Cura PV before generic Cura.
- Resolve linked prescribed treatments first; an incidental mention of care in an examination must not outweigh an explicit prescribed service.
- Preserve the implemented distinctions for Wallet, Ortonixia, Infiltración and Silicona/orthoses. Do not turn every legacy chiropody-flagged visit into the same service.
- Use the current classification helper and verify the available service mapping. Historical IDs are not portable between databases.
- Preserve both the structured service assignment and the original explanatory treatment text.

## 7. Professional matching and assignment

Reuse the real clinic professionals rather than creating duplicate active accounts:

- `SARA` → existing **Sara**.
- `SEBASTIAN`, `SEBASTIÁN`, `Sebas` → existing **Sebas**.
- The clinic's `T` shorthand identifies **Toñi**, also referred to as Tonyi in earlier instructions.
- Resolve destination IDs from the actual installation; do not hardcode the numeric IDs from this migration.
- Preserve staff logins, passwords, permissions and settings when merging duplicates. Unmatched historical staff must not gain an active usable login from the legacy data.

Apply the following precedence to the original visit comments and clinical text:

1. From **2024-01-01 inclusive**, a standalone uppercase **`T`** → Toñi.
2. A standalone uppercase **`S`** → Sebas, including before 2024.
3. If neither applicable marker exists, retain a real professional already stored on the source visit.
4. If the source has no professional and no applicable marker, assign Sara. This includes the very old visits without a time and unmarked `QUIRO` visits.

The markers are standalone tokens **within** a comment, not only whole-field values: `QUIRO T`, `QUIRO S` and `CURA S` count. Do not match the letter inside another word, number or identifier, or treat lowercase letters as equivalent. The T rule does not apply before 2024. If both markers apply, the implemented precedence above gives T priority.

Inspect all source professional fields (`QIDESPECIALISTA`, `CIDESPECIALISTA`, `BIDESPECIALISTA`, `PIDESPECIALISTA`) and the relevant comment fields. Blank IDs do occur in the original database; do not leave visits unassigned because those IDs are blank. Verify that old and recent visits actually load in both calendar feeds.

## 8. Legacy appointment times

- Preserve source calendar dates and local wall-clock hours. Do not shift legacy timestamps through UTC/local timezone conversion.
- Round each imported start **down** to the preceding quarter-hour and set seconds to zero: `10:29:43` → `10:15:00`; `10:15:00` stays unchanged.
- Calculate the end from the **assigned service's current configured default duration**, after treatment classification. Do not hardcode 30 minutes for every service.
- For the verified installation, Quiropodia/Exploración were 30 minutes, Cirugía 60, Cura/Cura PV 15, and the other mapped services 30. These are reference values, not replacements for reading the service configuration.
- Preserve the full original timestamp in the source archive, including subsecond data when supplied.
- Very old visits without a time remain represented at the available time; do not invent a schedule or move historical visits to avoid overlaps. Apply the professional rules above.

## 9. Drawings: coordinates are the highest-priority invariant

- Preserve every signed integer `X_1`, `Y_1`, `X_2`, `Y_2` exactly, including negative values. Do not scale, round, clamp, swap or truncate them.
- Preserve marker-to-visit, marker-to-pathology and diagram links, healed state, annotation title/text, original external identifier and canvas metadata.
- Convert legacy diagram indices `0..3` to the corresponding existing views. Preserve the mapping to `fotos_pies/1.png` through `4.png`.
- Reject fractional/out-of-range coordinates or unknown views rather than silently changing the drawing.
- The current higher-resolution foot backgrounds represent the same foot views and are reused. Adapt **display** to the saved coordinate canvas; never rewrite raw coordinates to fit a new image resolution.
- The source database has no measured original canvas dimensions. **360 × 300 is provisional, not a verified measurement.** Keep canvas dimensions editable for later calibration by comparing old placements; do not claim exact anatomical alignment before that calibration.
- Store canvas width/height and background selection with visits/markers and preserve them when copying drawings to later visits.
- An explicitly saved empty historical drawing stays empty. It must not accidentally inherit another visit's pathologies. Preserve the normal application's separate behavior of copying a previous drawing when a new visit has never saved its own drawing.
- The original external icon folder was unavailable. Use current matching icons and a neutral missing-icon placeholder for unmatched entries; allow the clinic to add, rename and replace icons. Do not invent unavailable originals. Archive the original filenames.
- Verify all marker fields and relationships against an independent database read before committing, using stable normalization and SHA-256 where implemented. A mismatch must roll back the operation.

## 10. Duplicate pathologies and list order

- Merge confirmed duplicate catalog entries and relink their markers without changing coordinates, annotations or visit links.
- Normalize case, accents, whitespace and known importer prefixes for matching. Prefer the clinic's existing canonical entry and retain its manually chosen name/icon.
- Confirmed equivalents include Hiperqueratosis/Hiperqueratosi, Onicomicosis/Onicomicosi, Onicocriptosis/Onicocriptosi and Papiloma/Papil·loma; exact normalized duplicates such as Heloma also merge.
- Do not merge different pathological subtypes just because part of the name matches. Use the explicit equivalences in `Podologo_unify`.
- **Every pathology list on the website** uses saved marker count (`marker_count`), highest first, including imported markers. Ties use alphabetical name order; unused pathologies remain available at the end.
- The clinical picker and catalog page share `Pathologies_model::get_all()` as the ordering source. Keep this consistent after future UI changes or migrations.

## 11. Text encoding

- Support original Windows-1252 text/BLOBs and BLOBs already containing valid UTF-8.
- Repair recognizable UTF-8 text previously misread as Windows-1252/Latin-1, including repeated incorrect conversions.
- Examples: `Ã‘` → `Ñ`, `Ã±` → `ñ`, `1Âº` → `1º`. Preserve the original letter's case; do not lowercase uppercase Ñ indiscriminately.
- Preserve correct accents, ç, ü, Catalan middle dots, ordinals, punctuation, whitespace and already-correct multilingual text.
- Do not guess characters already lost as `?` or `�`. Report unresolved corruption rather than claiming it has been recovered.
- During a text repair, limit conversion to readable fields. Do not rewrite credentials, image filenames/paths, JSON drawing layouts or the raw source archive.
- Reuse `Podologo_text_encoding`; do not perform a blanket encoding conversion over the entire destination database.

## 12. Existing application behavior to retain

These final UI decisions are compatibility requirements when migrating or rebuilding the application; they are not instructions to modify the legacy source database.

- Ship only **Catalan and Spanish application translations**, with **Catalan as the default everywhere**. Migration 098 sets every existing user's language and the clinic default to Catalan, including previously stored English preferences, and sets the database column's default to Catalan. Spanish remains an explicit selectable alternative. Normalize unsupported saved/API language values to Catalan and keep the loader's Catalan fallback so restoring an older backup cannot request removed translation files. Do not restore the other application language directories during upgrades.
- The **About page is removed**: do not restore its controller, view, RSS fetch or navigation links during an update. `/about` returns 404; update-screen return links go to the calendar and Integrations uses its own post-login destination.
- The lightweight agenda is **Versió simple**, canonically at `/simple`, with `Simple.php`, `pages/simple.php`, `simple.js`, `simple.scss` and `simple_*` translation keys. Keep the `fa-solid fa-puzzle-piece` navigation icon. The former `/mobile` and `/mobile/index` URLs redirect to `/simple`; preserve the existing `ea_full_version` cookie and migrate the remembered provider filter from `ea-mobile-provider` to `ea-simple-provider`. Standard browser metadata and phone user-agent tokens still use their platform-defined names.
- A simple-mode appointment's menu includes **Fitxa** for users with clinical access. It opens the existing clinical modal directly on that appointment, including for patients without a phone. Keep the simple agenda's day/provider filter, refresh it after changes, retain unsaved-change protection and preserve the server's clinical-record permissions.
- Keep simple-mode **Trucar** and **WhatsApp** actions inside the **Contacta** dropdown, with their existing confirmations. The dropdown is available when a phone exists; it is independent of the direct clinical-record button and works for roles without clinical access.
- In that menu, **Contacta has no border** and **Fitxa uses the same filled primary button colors as the full calendar**, including the shared company-color override.
- Selecting a patient from Patients opens the clinical record directly when the user has clinical access. A small SVG button immediately left of its close X opens that same patient's original details page, including from the calendar and for patients outside the first search page. Preserve existing permission checks.
- Keep save/discard/cancel protection for unsaved patient or visit changes when closing or navigating away.
- Patient medical history and allergies use compact three-line text areas. Show **Observacions pacient** below them, using the same `users.notes` as booking.
- Booking keeps both **Observacions pacient** and **Observacions cita**, with independent storage. The basic patient fields are name, surnames and phone. Keep the previously removed language/address/city/postal-code controls out of this booking form; those data remain in the patient's record.
- Reusing a locked existing patient sends the patient reference on the appointment without revalidating missing/unsupported hidden patient fields or resaving their record. Explicit editing preserves secondary fields and existing notes; creating a new patient uses the configured default language.
- Timing fields appear together as **Inici**, **Fi**, **Minuts**, with complete date/time values. On narrow phones, duration may wrap below to keep dates readable. Preserve synchronization between start, end and duration.
- WhatsApp is a **20 × 20 px checkbox**, not a pill switch, at the left of the booking footer; close/save stay on the right. Keep a comfortable clickable label area. Its final Catalan text is **Envia un recordatori per WhatsApp**: no parenthesis and no “el dia abans”. This copy change does not change the reminder schedule or the saved setting.
- Treatment dropdown options use the treatment color mixed with **65% white / 35% original color**, with near-black text (`#111111`). Missing/invalid colors use white. This is display styling, not a change to stored treatment colors.
- X-ray toggling cross-fades images over **220 ms**, preloading the X-ray and ignoring stale loads. Respect reduced-motion preferences. Keep markers visible, the normal image's click geometry unchanged and the X-ray preference transient.

## 13. Backups, clearing and transaction safety

- Create a consistent safety backup **before** a data-changing import, repair, clear or restore, and retain the original `.db` unchanged.
- Serialize imports/repairs/clears using the existing `podologo_import` database lock. Do not run them concurrently.
- Use transactions, preserve foreign-key relationships and roll back on incomplete input, validation failures, concurrent edits or verification mismatches.
- Stream/page large source tables; validate export completion and table counts rather than loading the entire archive into memory.
- Preserve `podologo_source_rows` and `podologo_import_links` for traceability and targeted repairs. Do not delete them simply to bypass duplicate-import protection.
- Only an explicitly authorized clinical-data clear may remove patients, visits, drawings, patient/fiscal records, associated reminders/consents and imported source history. Use the existing delete button and its **DELETE** confirmation; keep its safety backup.
- The authorized clear scope retains staff accounts/logins, settings, catalogs and audit history. Do not drop the whole database, truncate shared tables or reuse patient IDs as a shortcut.
- An interrupted request does not by itself prove corruption. Inspect the running operation and transaction outcome before retrying or clearing. Never start a second import while the first is still running.
- Do not recreate the app container while an import or repair is active.

## 14. Execution and verification

For another Podologo file, inspect its structure and produce a mapping/check report before applying it. The clinic-specific conventions above are confirmed for the supplied database; do not infer that unrelated clinics use the same shorthand.

1. Identify whether the task is a legacy import, application-backup restore or repair. Record the source file identity/hash and the target installation. Keep patient details out of reports and logs.
2. Preserve the current data and uploaded assets, verify a usable backup and ensure the target schema supports the existing importer. Follow the runtime setup in the linked import guide, including reader permissions, upload limits and disk space.
3. Review service/professional aliases, source relationships and provisional canvas dimensions. Use destination IDs discovered from this installation.
4. Prefer the current full importer for a fresh import. Do not automatically run every old repair afterward: most corrections are already part of fresh conversion.
5. For an older import, preview only the necessary targeted repair(s), inspect conflicts and then apply with their backup/verification mechanisms. Recheck subsequent repairs against any text cleanup already applied.
6. Verify patient/visit/source counts and all foreign-key links; every imported visit must have a resolved professional and service.
7. Verify legacy starts are on quarter-hours and durations match the assigned service defaults. Check old and recent dates through both calendar feeds.
8. Check every original meaningful clinical narrative against the visible destination fields across all visit types and linked tables. Open and save representative existing forms to check information is retained.
9. Check DNI/NIE/NIF preservation, provenance, both kinds of comments, corrected encoding and later clinician edits.
10. Verify drawings independently, including negative coordinates, empty drawings, healed markers, annotations, canvas metadata and inherited-drawing behavior. Compare visual placement separately from raw-coordinate fidelity.
11. Check both pathology lists against independently aggregated marker counts and alphabetical tie order. Check icons and persistent uploaded images.
12. Run the relevant existing conversion/model tests and booking tests, plus read-only integration checks. Report actual outcomes and remaining calibration or data conflicts; do not claim success solely from a successful upload response.
13. For code deployments, rebuild and restart the app only after all active repairs finish. A source edit alone does not update the running Docker image. Keep `app` and `cron` on the same image.
14. Check the running app, patient records, booking comments and calendar after deployment. Report what changed, what was verified and where the backup is stored.

The supplied migration's verified reference dataset had **12,420 patients**, **84,290 visits**, **363,686 markers**, and **144 canonical pathologies after unification**. The last verified list check counted 98 unused pathologies. These are historical reference counts, not hardcoded requirements for a newer file or a live database that has since changed.

## 15. Implementation and maintenance references

| Responsibility                                               | Current implementation                                                                                                                                                                                                                                                                                                                |
| ------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Binary detection, source archive, mappings and atomic import | [Podologo_import.php](application/libraries/Podologo_import.php), [read_podologo.py](application/scripts/read_podologo.py)                                                                                                                                                                                                            |
| Clinical consolidation and treatment classification          | [Podologo_clinical.php](application/libraries/Podologo_clinical.php)                                                                                                                                                                                                                                                                  |
| Earlier-import text recognition                              | [Podologo_clinical_previous.php](application/libraries/Podologo_clinical_previous.php), [Podologo_clinical_v2.php](application/libraries/Podologo_clinical_v2.php); keep these internal snapshots for matching older generated content, not for new visible forms                                                                     |
| Targeted clinical repair                                     | [Podologo_clinical_repair.php](application/libraries/Podologo_clinical_repair.php); `php -d memory_limit=512M index.php console repair_podologo_clinical` applies changes with a backup; it is not a dry run                                                                                                                          |
| Professional rules                                           | [Podologo_providers.php](application/libraries/Podologo_providers.php), [repair_podologo_providers.php](application/scripts/repair_podologo_providers.php)                                                                                                                                                                            |
| Documents                                                    | [Podologo_documents.php](application/libraries/Podologo_documents.php), [repair_podologo_documents.php](application/scripts/repair_podologo_documents.php)                                                                                                                                                                            |
| EMAIL2/provenance                                            | [Podologo_referrals.php](application/libraries/Podologo_referrals.php), [repair_podologo_referrals.php](application/scripts/repair_podologo_referrals.php)                                                                                                                                                                            |
| Legacy schedule                                              | [Podologo_schedule.php](application/libraries/Podologo_schedule.php), [repair_podologo_schedule.php](application/scripts/repair_podologo_schedule.php)                                                                                                                                                                                |
| Encoding                                                     | [Podologo_text_encoding.php](application/libraries/Podologo_text_encoding.php), [repair_podologo_encoding.php](application/scripts/repair_podologo_encoding.php)                                                                                                                                                                      |
| Duplicate catalogs                                           | [Podologo_unify.php](application/libraries/Podologo_unify.php), [unify_podologo_catalogs.php](application/scripts/unify_podologo_catalogs.php)                                                                                                                                                                                        |
| Equal-feet flag cleanup                                      | [remove_podologo_equal_feet_flag.php](application/scripts/remove_podologo_equal_feet_flag.php)                                                                                                                                                                                                                                        |
| Backups and authorized clearing                              | [Database_backup.php](application/libraries/Database_backup.php), [Backups.php](application/controllers/Backups.php)                                                                                                                                                                                                                  |
| Pathology ordering and drawing data                          | [Pathologies_model.php](application/models/Pathologies_model.php), [Foot_diagrams_model.php](application/models/Foot_diagrams_model.php)                                                                                                                                                                                              |
| Existing regression checks                                   | `tests/Unit/Library/Podologo*Test.php`, [PathologiesModelTest.php](tests/Unit/Model/PathologiesModelTest.php), [appointments_modal.test.cjs](tests/appointments_modal.test.cjs), [integration_podologo_calendar.php](tests/integration_podologo_calendar.php), [integration_clear_database.php](tests/integration_clear_database.php) |

The standalone `repair_podologo_*.php` scripts and `unify_podologo_catalogs.php` preview changes by default; `--apply` performs their writes. **`remove_podologo_equal_feet_flag.php` writes immediately when matching flags exist**, after creating its backup; it has no dry-run switch. The clinical console repair also applies changes immediately. Inspect the selected script's behavior and locking before use; older helpers do not all acquire the import lock themselves, so ensure no concurrent operation is running. Do not run clear-database integration checks against live data.

When the clinic changes one of these decisions, update this document and the relevant implementation/tests together. Preserve the distinction between historical source facts, provisional assumptions and confirmed clinic rules.
