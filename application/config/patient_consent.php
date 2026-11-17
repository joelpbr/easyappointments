<?php defined('BASEPATH') or exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Patient consent signed on the tablet
|--------------------------------------------------------------------------
|
| The text the patient (or their legal representative) signs with a finger: the consent to the usual podiatric care
| (Ley 41/2002 and, in Catalonia, Llei 21/2000), the information on the processing of their data (art. 13 RGPD) and
| the optional consents, each one separate and unticked (art. 7 RGPD). It lives here, not in the code, so that a
| clinic can adapt it; change "version" whenever the text changes, so that every signature says which text it was.
|
| Placeholders, filled from the settings: {clinic}, {address}, {email}, {phone}, {dpo}.
|
| options  Optional consents: key => [language => text]. Each key is stored with the signature (1 accepted, 0 not).
|
*/

$config['patient_consent_version'] = '2026-11-17.2';

$config['patient_consent_title'] = [
    'ca' => 'Consentiment informat i informació sobre protecció de dades',
    'es' => 'Consentimiento informado e información sobre protección de datos',
];

$config['patient_consent_sections'] = [
    'ca' => [
        [
            'title' => '1. Consentiment per a l’atenció podològica',
            'paragraphs' => [
                'Autoritzo els professionals de {clinic} a fer-me l’exploració i els tractaments podològics habituals: quiropòdia, cures, estudi biomecànic i de la marxa, suports plantars i ortesis, i tractaments conservadors.',
                'M’han explicat en què consisteixen, els seus beneficis, les alternatives i els riscos més freqüents, que solen ser lleus (molèsties, petites ferides o sagnat, irritació de la pell i, rarament, infecció), i he pogut fer les preguntes que he volgut.',
                'Abans de qualsevol intervenció quirúrgica, infiltració o altre tractament amb un risc rellevant se’m demanarà un consentiment específic per escrit. Puc revocar aquest consentiment en qualsevol moment.',
                'Llei 41/2002, de 14 de novembre, bàsica reguladora de l’autonomia del pacient, i Llei 21/2000, de 29 de desembre, sobre els drets d’informació concernent la salut i l’autonomia del pacient.',
            ],
        ],
        [
            'title' => '2. Informació sobre protecció de dades',
            'paragraphs' => [
                'Responsable: {clinic}, {address}. Contacte: {email} · {phone}. Delegat de protecció de dades: {dpo}.',
                'Finalitat: prestar-vos l’assistència sanitària i portar la vostra història clínica, gestionar les cites i facturar els serveis.',
                'Base jurídica: la prestació de l’assistència sanitària (art. 6.1.b i 9.2.h del Reglament (UE) 2016/679, RGPD) i les obligacions legals de la història clínica i de la facturació (art. 6.1.c RGPD). Les opcions de l’apartat 3 es basen en el vostre consentiment (art. 6.1.a RGPD).',
                'Destinataris: no es comuniquen dades a tercers, llevat d’obligació legal (Administració tributària, autoritats sanitàries o judicials) o de la vostra mútua o asseguradora quan ho demaneu. Els proveïdors que ens presten serveis (programari i allotjament) hi accedeixen com a encarregats del tractament amb contracte.',
                'Conservació: la història clínica es conserva com a mínim 15 anys des de l’alta de cada procés assistencial (art. 12 de la Llei 21/2000); les dades de facturació, el termini que fixen les lleis fiscals i mercantils.',
                'Drets: podeu exercir els drets d’accés, rectificació, supressió, oposició, limitació del tractament i portabilitat, i retirar els consentiments en qualsevol moment, adreçant-vos a {email} o a la clínica. Si considereu que no s’han respectat, podeu reclamar davant l’Agència Espanyola de Protecció de Dades (www.aepd.es).',
                'Les dades són necessàries per atendre-us: sense elles no us podem prestar l’assistència.',
            ],
        ],
        [
            'title' => '3. Consentiments opcionals',
            'paragraphs' => [
                'Podeu acceptar-los o no; no afecten l’atenció que rebeu i els podeu retirar quan vulgueu.',
            ],
        ],
    ],
    'es' => [
        [
            'title' => '1. Consentimiento para la atención podológica',
            'paragraphs' => [
                'Autorizo a los profesionales de {clinic} a realizarme la exploración y los tratamientos podológicos habituales: quiropodia, curas, estudio biomecánico y de la marcha, soportes plantares y ortesis, y tratamientos conservadores.',
                'Me han explicado en qué consisten, sus beneficios, las alternativas y los riesgos más frecuentes, que suelen ser leves (molestias, pequeñas heridas o sangrado, irritación de la piel y, raramente, infección), y he podido hacer las preguntas que he querido.',
                'Antes de cualquier intervención quirúrgica, infiltración u otro tratamiento con un riesgo relevante se me pedirá un consentimiento específico por escrito. Puedo revocar este consentimiento en cualquier momento.',
                'Ley 41/2002, de 14 de noviembre, básica reguladora de la autonomía del paciente, y Ley catalana 21/2000, de 29 de diciembre, sobre los derechos de información concernientes a la salud y la autonomía del paciente.',
            ],
        ],
        [
            'title' => '2. Información sobre protección de datos',
            'paragraphs' => [
                'Responsable: {clinic}, {address}. Contacto: {email} · {phone}. Delegado de protección de datos: {dpo}.',
                'Finalidad: prestarle la asistencia sanitaria y llevar su historia clínica, gestionar las citas y facturar los servicios.',
                'Base jurídica: la prestación de la asistencia sanitaria (art. 6.1.b y 9.2.h del Reglamento (UE) 2016/679, RGPD) y las obligaciones legales de la historia clínica y de la facturación (art. 6.1.c RGPD). Las opciones del apartado 3 se basan en su consentimiento (art. 6.1.a RGPD).',
                'Destinatarios: no se comunican datos a terceros, salvo obligación legal (Administración tributaria, autoridades sanitarias o judiciales) o a su mutua o aseguradora cuando lo solicite. Los proveedores que nos prestan servicios (software y alojamiento) acceden a ellos como encargados del tratamiento con contrato.',
                'Conservación: la historia clínica se conserva como mínimo 15 años desde el alta de cada proceso asistencial (art. 12 de la Ley catalana 21/2000); los datos de facturación, el plazo que fijan las leyes fiscales y mercantiles.',
                'Derechos: puede ejercer los derechos de acceso, rectificación, supresión, oposición, limitación del tratamiento y portabilidad, y retirar los consentimientos en cualquier momento, dirigiéndose a {email} o a la clínica. Si considera que no se han respetado, puede reclamar ante la Agencia Española de Protección de Datos (www.aepd.es).',
                'Los datos son necesarios para atenderle: sin ellos no podemos prestarle la asistencia.',
            ],
        ],
        [
            'title' => '3. Consentimientos opcionales',
            'paragraphs' => [
                'Puede aceptarlos o no; no afectan a la atención que recibe y puede retirarlos cuando quiera.',
            ],
        ],
    ],
];

$config['patient_consent_options'] = [
    'images' => [
        'ca' =>
            'Autoritzo que les fotografies clíniques dels meus peus es puguin fer servir de manera anonimitzada, sense que se’m pugui identificar, amb finalitats docents o de divulgació professional.',
        'es' =>
            'Autorizo que las fotografías clínicas de mis pies puedan usarse de forma anonimizada, sin que se me pueda identificar, con fines docentes o de divulgación profesional.',
    ],
];

$config['patient_consent_declaration'] = [
    'ca' => 'Declaro que he llegit i entès aquest document i que les dades que hi consten són certes.',
    'es' => 'Declaro que he leído y entendido este documento y que los datos que constan en él son ciertos.',
];
