# Registro de decisiones técnicas

Cada decisión indica la fecha, el contexto, lo decidido y sus consecuencias. Las decisiones no se borran: si cambian,
se añade una nueva que sustituye a la anterior.

## D-001 · Base: Easy!Appointments 1.6.1 con las correcciones de `upstream/develop`

- **Fecha**: 12/11/2026
- **Decisión**: partir de 1.6.1 e integrar las 4 correcciones de seguridad, las traducciones y el arreglo del
  instalador publicados después en `upstream/develop`. `upstream/master` se descarta por obsoleto.
- **Consecuencias**: `upstream` se mantiene como remoto para fusionar versiones futuras.

## D-002 · Gitflow

- **Fecha**: 12/11/2026
- **Decisión**: `main` contiene solo versiones publicadas; el trabajo se integra en `develop` mediante ramas
  `feature/*` fusionadas con `--no-ff`.

## D-003 · Despliegue en una VPS en la nube

- **Fecha**: 12/11/2026
- **Contexto**: la especificación asumía un servidor en la clínica (LAN, VPN, SAI y agenda sin internet).
- **Decisión**: la aplicación se despliega en una VPS y se accede a ella por internet.
- **Consecuencias**:
    - HTTPS obligatorio mediante un proxy inverso delante del contenedor `app`.
    - Los requisitos de LAN, VPN y funcionamiento sin internet (18.4, escenarios N y O) dejan de ser prioritarios.
    - Los webhooks de WhatsApp llegan directamente a la VPS, sin relay.
    - Las copias deben salir de la VPS (destino pendiente).

## D-004 · Facturación y VERI\*FACTU con motor propio

- **Fecha**: 12/11/2026
- **Decisión**: desarrollar la facturación dentro de la aplicación (emisores, series, facturas, cobros, PDF) y generar
  los registros VERI\*FACTU con una librería mantenida (`josemmo/verifactu-php`, a evaluar en la fase 10), en lugar
  de usar un proveedor externo.
- **Consecuencias**:
    - El software necesitará la **declaración responsable** del productor (RD 1007/2023) antes de emitir facturas
      reales.
    - El mantenimiento normativo pasa a ser responsabilidad del proyecto.
    - Antes de producción: revisión por asesoría fiscal y pruebas en el entorno oficial de la AEAT.

## D-005 · Recepción gestiona varios profesionales con calendarios partidos

- **Fecha**: 12/11/2026
- **Decisión**: la cuenta de recepción usa el rol `secretary` de Easy!Appointments, mostrado como «Recepción», con
  los profesionales que gestiona asignados. Su pantalla principal es la vista en tabla, con una columna por
  profesional.
- **Consecuencias**: los profesionales pueden seguir teniendo su propia cuenta con el rol `provider`.
