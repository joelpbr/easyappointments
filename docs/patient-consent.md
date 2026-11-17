# Consentiment del pacient signat a la tauleta

Revisió de fonts: 17 de novembre de 2026.

## Què signa el pacient

Un sol document, en català o castellà, amb tres parts:

1. **Consentiment per a l'atenció podològica habitual** (exploració, quiropòdia, cures, estudi biomecànic, ortesis, tractaments conservadors), amb els riscos freqüents i el dret a revocar-lo. [Llei 41/2002](https://www.boe.es/buscar/act.php?id=BOE-A-2002-22188), arts. 4, 8 i 10, i [Llei catalana 21/2000](https://portaljuridic.gencat.cat/eli/es-ct/l/2000/12/29/21), art. 6. Les intervencions amb risc rellevant (cirurgia, infiltracions…) necessiten el seu consentiment específic per escrit, que segueix sent un document de la visita.
2. **Informació sobre protecció de dades** (art. 13 [RGPD](https://eur-lex.europa.eu/eli/reg/2016/679/oj)): responsable, delegat de protecció de dades, finalitat, base jurídica, destinataris, conservació, drets i reclamació a l'[AEPD](https://www.aepd.es). La història clínica es tracta per l'assistència sanitària (art. 9.2.h RGPD), no per consentiment: per això aquesta part informa i no demana permís.
3. **Consentiments opcionals**, separats i sense marcar (art. 7 RGPD): ús docent o divulgatiu de fotografies anonimitzades.

Signa el pacient, o el seu representant legal (menors de 16 anys, art. 9.3.c Llei 41/2002; persones amb mesures de suport), amb nom, document d'identitat i relació.

## Com es fa

Pacients → fitxa del pacient → **Consentiment**. El botó diu si està signat (i quan) o pendent. S'obre a pantalla completa: el pacient llegeix, marca el que vulgui, signa amb el dit i es prem **Signar i desar**. Ho pot fer qui pot editar pacients (també recepció).

Abans del primer, cal posar el contacte del **delegat de protecció de dades** a Configuració → Continguts legals: és obligatori per als centres sanitaris que porten històries clíniques ([art. 34.1.l LOPDGDD](https://www.boe.es/buscar/act.php?id=BOE-A-2018-16673#a3-4)) i ha de constar a la informació (art. 13.1.b RGPD). Sense aquest contacte no es pot signar.

Per canviar d'opció (per exemple, retirar l’autorització d’ús d’imatges), el pacient signa un consentiment nou: el darrer és el vigent i els anteriors es conserven.

## Què es guarda i on

- **PDF signat** a l'historial de documents del pacient (`patient_documents`, tipus `patient_consent`): forma part de la història clínica, no es pot esborrar des de l'aplicació i es conserva amb ella (com a mínim 15 anys des de l'alta de cada procés assistencial, art. 12 Llei 21/2000).
- **Prova de la signatura** a `patient_consents`: text i versió, idioma, opcions, qui signa i en quina qualitat, dibuix de la signatura, data i hora, qui la recull, IP i navegador, empremta SHA-256 de tot el que se signa i del PDF.
- Al registre de moviments: la signatura i cada consulta del PDF.

Una signatura dibuixada a la pantalla és una signatura electrònica simple: té efectes jurídics i no se li pot negar valor per ser electrònica ([art. 25 eIDAS](https://eur-lex.europa.eu/eli/reg/2014/910/oj), [Llei 6/2020](https://www.boe.es/buscar/act.php?id=BOE-A-2020-14046)); les dades anteriors serveixen per provar-la si cal.

## WhatsApp

Des de la versió `2026-11-17.2`, el consentiment no inclou textos ni opcions de WhatsApp. Signar-lo no canvia les preferències de recordatoris: si una versió anterior recollia aquesta opció, es respecta la darrera elecció explícita. Els documents ja signats es conserven intactes.

## Adaptar-lo

El text és a `application/config/patient_consent.php` (títol, apartats, opcions i declaració en cada idioma, amb `{clinic}`, `{address}`, `{email}`, `{phone}` i `{dpo}`, que surten de la configuració). Cal canviar `patient_consent_version` sempre que es canviï el text: cada signatura guarda la versió que es va signar. Una opció nova s'afegeix a `patient_consent_options`.

No és una certificació de compliment: la clínica ha de tenir el registre d'activitats de tractament, els contractes d'encarregat (programari, allotjament, Meta), el procediment per atendre drets i, per a les intervencions amb risc, els seus consentiments específics.

## Verificació

`PatientConsentTest`: text amb les dades de la clínica, opcions mai presumides, qui signa, signatura buida o falsa refusada, empremta i document. Provat de punta a punta amb LibreOffice: PDF amb la signatura a escala, prova desada amb l'empremta del PDF, esborrat refusat i absència de WhatsApp als nous documents.
