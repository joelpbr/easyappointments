# ProgramaConsulta

Aplicació de gestió de consulta: agenda, pacients, professionals, visites i documents clínics.

## Desenvolupament

El projecte utilitza PHP, MySQL i JavaScript. Inicia l'entorn amb:

```sh
docker compose -f docker-compose.dev.yml up
```

L'aplicació s'obre a http://localhost. El codi es munta al contenidor i el servei node recompila els recursos.

## Instal·lació

1. Copia .env.example a .env i configura l'URL i les credencials.
2. Executa `docker compose up -d`.
3. Obre l'aplicació i completa la instal·lació.

Sense Docker, configura config.php a partir de config-sample.php i consulta la [guia d'instal·lació](docs/installation-guide.md).

## Actualitzacions i dades existents

Fes una còpia de seguretat abans d'actualitzar. Aplica les migracions amb `php index.php console migrate`.

En instal·lacions existents, conserva a .env els identificadors originals de DB_NAME, DB_USERNAME,
PROD_COMPOSE_PROJECT_NAME i DEV_COMPOSE_PROJECT_NAME perquè Docker reutilitzi les bases de dades i els volums.
Els noms nous per defecte són per a instal·lacions noves; canviar un identificador no trasllada les dades.

Les còpies de seguretat antigues continuen sent compatibles. Les preferències del navegador passen als noms nous quan s'utilitzen.

## Comprovacions

```sh
npm test
php vendor/bin/phpunit
npx gulp scripts styles
```

## Documentació

- [Índex](docs/readme.md)
- [API](docs/rest-api.md) i [especificació OpenAPI](openapi.yml)
- [Documents de visites](docs/clinica/documentos-visitas.md)

## Llicència

Consulta [LICENSE](LICENSE) i l'[atribució del codi original](NOTICE.md).
