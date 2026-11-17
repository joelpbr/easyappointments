# Registre de jornada dels administratius

Revisió de fonts: 17 de novembre de 2026. Funció limitada al rol intern `secretary` (Administratiu); el rol `admin` supervisa. No s'estén automàticament als podòlegs: si són treballadors per compte d'altri, la seva obligació de registre també s'ha de cobrir. Els permisos compartits de l'agenda no donen accés als fitxatges d'altres persones.

## Fonament i ús dels registres

- [Estatut dels treballadors, art. 34.9](https://www.boe.es/buscar/act.php?id=BOE-A-2015-11430#a34): registre diari dels inicis i finals, conservació de quatre anys, disponibilitat per a treballadors, representants i Inspecció. Per això es guarden hores exactes, registres propis, exports i historial de correccions.
- [Art. 12.4.c](https://www.boe.es/buscar/act.php?id=BOE-A-2015-11430#a12): per a temps parcial, resum mensual i còpia amb la nòmina, amb ordinàries i complementàries diferenciades. Admin pot emetre snapshots mensuals, imprimir-los i anotar-ne el lliurament real. El botó no envia correus ni acredita per si sol la recepció.
- [Art. 35.5](https://www.boe.es/buscar/act.php?id=BOE-A-2015-11430#a35): resum del còmput extraordinari en el període retributiu. No es qualifiquen automàticament com a extres les hores per damunt d'una jornada teòrica.
- [Guia del Ministeri de Treball sobre registre de jornada](https://www.mites.gob.es/ficheros/ministerio/GuiaRegistroJornada.pdf): cal que el registre permeti identificar el temps real. Les pauses depenen del conveni i els pactes; el programari separa treball, pausa computable, no computable i pendent.
- [AEPD: base jurídica i informació al personal](https://www.aepd.es/preguntas-frecuentes/3-proteccion-de-datos-en-el-ambito-laboral/FAQ-0311-es-necesario-el-consentimiento-del-trabajador-para-implantar-un-sistema-de-control-horario): obligació legal, sense necessitat de consentiment general, amb deure d'informació. La interfície informa sobre finalitat, accés i conservació. La clínica ha de completar el seu avís de privacitat i els canals per exercir drets.

La implementació es basa en el text consolidat consultat; no tracta projectes de reglament com a normativa ja vigent. No és una certificació de compliment legal: la clínica ha de documentar el procediment, consultar la representació laboral quan correspongui, aplicar el conveni i lliurar els resums. La classificació d'hores i els casos excepcionals requereixen revisió laboral, especialment extraordinàries per força major a temps parcial o períodes retributius no mensuals.

## Operativa

**Administratiu:** Fitxatge, Entrar → Fer pausa → Reprendre → Sortir. Horari del servidor, mai del navegador. Es poden fer múltiples torns al dia. La sortida durant una pausa la tanca. Sortir del compte no fitxa la sortida. Una jornada oberta més de 16 hores es marca per revisar, però no es completa artificialment. Si falta un registre, es comunica una incidència amb data i motiu. La persona pot consultar i exportar les seves dades i veure totes les correccions.

**Admin:** selector de persona i mes, estat de l'equip, totals diaris, jornades obertes, incidències, correccions i informes. Les correccions conserven abans/després, actor, moment i motiu. Anul·lar treu el registre del còmput, però no de l'historial. Resoldre una incidència no modifica hores automàticament. Cal corregir-les, si correspon, i respondre la petició.

Els contractes, minuts setmanals i convenis comencen sense definir; no impedeixen fitxar. Les pauses sense criteri fan provisional el resum: el temps computat exclou les pauses pendents i així s'indica. Canviar la política només afecta jornades noves; corregir pauses històriques requereix motiu. L'informe mensual classificat exigeix contracte, hores i conveni, mes finalitzat, pauses resoltes, jornades tancades i incidències respostes. El repartiment HH:MM:SS ordinàries/complementàries/extraordinàries l'introdueix admin i ha de quadrar amb el total. No es calcula una nòmina, ni absències, ni es garanteixen automàticament límits de descans o d'hores per conveni.

Els informes emesos són snapshots: una correcció posterior no els reescriu. Cal emetre i lliurar una nova versió. PDF s'obté amb Imprimir / desar com a PDF del navegador. CSV conté trams i offsets; JSON inclou historial complet i informes de la persona per donar resposta a requeriments. L'accés dels representants/Inspecció es facilita per exportació autoritzada, sense donar-los comptes clínics.

Hi ha avisos orientatius de jornades obertes de més de 16 hores, trams de treball de més de 6 hores sense pausa, dies amb més de 9 hores computades i menys de 12 hores entre registres de dies diferents. Només examinen els registres retornats en el mes consultat; no substitueixen una comprovació completa del conveni, excepcions, descans setmanal o límits anuals. Els torns partits dins del mateix dia no es marquen com a descans nocturn insuficient.

## Dades, seguretat i conservació

- Taules `attendance_profiles`, `attendance_entries`, `attendance_journal`, `attendance_requests`, `attendance_reports`. Incloses en les còpies globals de la BBDD. No contenen dades de pacients, biometria ni ubicació.
- UTC al desat; Europe/Madrid en consulta i separació de dies. Els informes mensuals retallen els totals al mes, però mostren íntegres els trams que en travessen el límit. Canvis d'hora amb offset explícit a les correccions.
- Les operacions s'agrupen en transaccions i bloqueig per treballador, amb revisió optimista i clau idempotent als fitxatges. Doble clic no crea dues entrades. Desconnexió: no es presumeix que el fitxatge hagi quedat desat; es consulta l'estat del servidor abans de reintentar.
- No hi ha esborrat de registres ni neteja automàtica. Es conserven també si s'elimina el compte. El rollback de la migració es nega a esborrar les taules. Després del termini legal cal revisar supressió/bloqueig segons obligacions i reclamacions; no conservar indefinidament per inèrcia.
- La clínica ha de mantenir còpies externes provades i el rellotge del servidor sincronitzat. Una restauració antiga pot perdre fitxatges posteriors: exportar-los abans i reconciliar-los. La traçabilitat d'aplicació no és una signatura electrònica ni una prova criptogràfica contra manipulació directa de la BBDD.
- Comptes personals, no compartits, i contrasenyes robustes. Els comptes de demostració amb contrasenya igual al nom no ofereixen garanties suficients d'atribució per a un ús laboral real; no s'han canviat silenciosament. Proporcionar un dispositiu de la clínica per no exigir un mòbil personal.

## Verificació

`AttendanceRulesTest` comprova límits de mes, mitjanit, DST, pauses, anul·lacions, seqüències i hores futures. `tests/attendance_integration.php` crea taules temporals amb prefix propi, prova fitxatges/revisions/informes i les elimina; no toca fitxatges reals. Executar dins del contenidor PHP amb la configuració de l'aplicació.
