# Akeeba Engage — fork Joomla 6 + importador de CComment

Fork **no oficial** de [Akeeba Engage](https://github.com/akeeba/engage) (componente de comentarios para artículos de Joomla). Linaje:

1. **Akeeba Ltd / Nicholas K. Dionysopoulos** — proyecto original (archivado en agosto de 2025, última versión 3.4.3).
2. **[whynotindeed/engage-1](https://github.com/whynotindeed/engage-1)** — parche de compatibilidad con Joomla 6 (namespaces de `Filesystem`).
3. **Este build (kumori)** — añade preparación para J7, un `make-zip.sh` autónomo y un **importador de comentarios desde CComment**.

## Qué incluye este build

### Compatibilidad Joomla 6 (heredado del fork upstream)
Las clases `Joomla\CMS\Filesystem\{Path,File,Folder}` se movieron a `Joomla\Filesystem\…` en Joomla 6. El fork upstream actualizó las 4 referencias en 3 ficheros; sin ese cambio el panel de Engage lanza una excepción en J6.

### Preparación Joomla 7 (añadido en este build)
Se sustituye el deprecado `Factory::getUser()` por `Factory::getApplication()->getIdentity()` en 3 ficheros del componente (`View/Comments/HtmlView.php`, `Service/Html/Engage.php`, `Helper/HtmlFilter.php`). Funciona en J5/J6 y sobrevive a J7.

### `make-zip.sh` — constructor de paquete autónomo
Akeeba usa un build con phing (`build.xml`) que depende de los *buildfiles* de Akeeba, no incluidos aquí. `make-zip.sh` reproduce el paquete instalable sin esa cadena de herramientas:

```bash
./make-zip.sh
# -> dist/pkg_engage-<version>.zip
```

Requiere **Composer** (para las dependencias del backend: htmlpurifier). Si falta `component/backend/vendor`, el script ejecuta `composer install` automáticamente. Ensambla el componente + módulo + 10 plugins y los envuelve con el manifiesto de paquete (`pkg_engage.xml`), el script de instalación y los idiomas. El zip resultante se instala por **Extensiones → Instalar → Subir**.

### Importador CComment → Engage (`import/ccomment-to-engage.php`)
Herramienta **autónoma y bajo demanda** (no se instala ni se ejecuta sola) para migrar comentarios del abandonado **CComment** (Compojoom) a Engage. No requiere arrancar Joomla: lee las credenciales de un `configuration.php`, toma los comentarios de `<prefix>comment`, resuelve el `asset_id` de cada artículo desde `<prefix>content`, e inserta en `<prefix>engage_comments`.

```bash
# 1) Prueba en seco (no cambia nada; muestra qué haría):
php import/ccomment-to-engage.php --config=/ruta/a/configuration.php

# 2) Tras revisar y hacer copia de la BD, ejecuta de verdad:
php import/ccomment-to-engage.php --config=/ruta/a/configuration.php --commit
```

Características: **dry-run por defecto**, re-ejecutable sin duplicar (salta los ya importados por `asset_id`+fecha+email), salta comentarios de artículos borrados, mapea estado publicado/no publicado, y convierte texto plano a HTML seguro. Opciones: `--source-table`, `--user-agent`, `--include-spam`, `--no-skip-existing`, `--limit`, `--help`.

Requisitos: PHP CLI con `pdo_mysql`. Engage debe estar instalado (para que exista `<prefix>engage_comments`).

## Aviso y licencia

- Fork **no mantenido activamente** y **no afiliado** a Akeeba Ltd. Software original de Nicholas K. Dionysopoulos / Akeeba Ltd.
- El parche de J6 fue obra de [TheAIDirector.win](https://theaidirector.win); las adiciones de este build (importador, `make-zip.sh`, preparación J7) se hicieron sobre ese fork.
- **Úsalo bajo tu responsabilidad**: prueba en un entorno de staging y haz copia de la base de datos antes de importar.
- Licencia: **GNU General Public License v3 o posterior** (ver [LICENSE](LICENSE)).

## Proyecto original
- Repositorio: https://github.com/akeeba/engage
- Autor: Akeeba Ltd / Nicholas K. Dionysopoulos — última versión 3.4.3 (archivado agosto 2025)
