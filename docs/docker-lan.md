# Clon local per al mòbil

Instància Docker: `programaconsulta-lan` (web i base de dades pròpies, amb una passarel·la local).

Adreça inicial: **https://192.168.1.154:8081**. El mòbil ha d'estar connectat al mateix Wi-Fi i l'ordinador ha d'estar encès.

És una còpia independent de les dades i els documents de la instància `easyappointments`, feta el 17/11/2026. Utilitza els mateixos comptes d'usuari que existien en fer la còpia. Els canvis posteriors no se sincronitzen entre les dues instàncies.

La imatge `programaconsulta-lan:snapshot` conserva el codi que estava executant el contenidor original, inclosos els canvis locals. La web i la base de dades estan en una xarxa Docker interna, sense sortida a Internet. Una passarel·la Node publica únicament la web a la xarxa local; només reenvia peticions al servidor intern. No s'executa cap contenidor cron en el clon. Els serveis que requereixen Internet no funcionaran en aquesta instància.

La web només es publica a la IP Wi-Fi indicada, al port 8081. La base de dades no publica cap port. No s'ha configurat cap túnel ni cap redirecció de ports al router. La regla `ProgramaConsulta-LAN-8081` permet l'accés des de `192.168.1.0/24` a la interfície WiFi.

Les credencials Docker del clon són a `.local-lan/clone.env`, exclòs de Git. Les dades persistents són als volums `programaconsulta-lan_db-data` i `programaconsulta-lan_storage`. No es copien les sessions obertes, les memòries cau, els logs ni les còpies de seguretat antigues.

Abans de recrear la passarel·la, indica a `.local-lan/clone.env` les rutes absolutes dels fitxers PEM amb `LAN_TLS_CERT` i `LAN_TLS_KEY`. El certificat ha d’incloure la IP de la Wi-Fi al camp SAN i estar signat per una CA de confiança als dispositius. Instal·la el certificat de la CA als iPads i activa la confiança per a TLS. Mantén la clau privada fora del repositori. Construeix la imatge actual amb `docker build -f docker/Dockerfile -t programaconsulta-lan:secure .` i indica `LAN_IMAGE=programaconsulta-lan:secure` al mateix fitxer. Després de recrear el servei, executa `docker compose --env-file .local-lan/clone.env -f docker-compose.lan.yml exec app php index.php console migrate`.

Des de l'arrel del projecte:

```powershell
# Consultar estat
 docker compose --env-file .local-lan/clone.env -f docker-compose.lan.yml ps

# Aturar sense esborrar dades
 docker compose --env-file .local-lan/clone.env -f docker-compose.lan.yml stop

# Tornar a engegar
 docker compose --env-file .local-lan/clone.env -f docker-compose.lan.yml up -d
```

Si el router assigna una IP diferent a l'ordinador, cal actualitzar `LAN_IP` a `.local-lan/clone.env`, actualitzar la regla del tallafoc i recrear la web amb `docker compose --env-file .local-lan/clone.env -f docker-compose.lan.yml up -d`. Es pot reservar la IP actual al DHCP del router per mantenir l'adreça estable.

El script `docker/enable-lan-firewall.ps1` configura l'excepció inicial del tallafoc i requereix permisos d'administrador.

## Obrir com una app a l’iPhone

1. Obre `http://192.168.1.154:8081` amb Safari, connectat al mateix Wi-Fi.
2. Prem Compartir i «Afegir a la pantalla d’inici». Si apareix «Obrir com a app web», activa-ho.
3. Obre la consulta des de la nova icona.

El manifest declara `display: standalone` i totes les pantalles principals inclouen les metadades d’iOS. En aquest mode desapareixen les barres de navegació de Safari; la barra d’estat del sistema (hora i bateria) continua visible. Una pestanya normal de Safari manté les seves barres. Si ja hi havia una icona anterior, elimina-la i torna-la a afegir per actualitzar les metadades.

Aquesta configuració no afegeix funcionament fora de línia: l’ordinador i Docker han d’estar encesos. Cal verificar el comportament final al dispositiu iOS.
