# EPG - Guía de Programación

Este directorio contiene la configuración para generar automáticamente la guía de programación (`epg.xml` / `epg.json`) a partir de fuentes EPG públicas. Idea original basada en [miEPG de davidmuma](https://github.com/davidmuma/miEPG).

## Cómo funciona

El sistema descarga varias guías EPG públicas, las fusiona y genera un único archivo con todos los canales.

1. Se descargan las fuentes definidas en `sources.txt`.
2. Se filtran los programas por ventana temporal.
3. Se excluyen los canales indicados en `exclusions.txt`.
4. Se generan los archivos de salida:
   - `epg.xml.gz` y `epg.json.gz` — comprimidos, en el repositorio.
   - `epg.xml` y `epg.json` — sin comprimir, en el Release `epg-latest`.

## URLs

Comprimidos (repo):
```
https://raw.githubusercontent.com/teleonline/listas/main/epg.xml.gz
https://raw.githubusercontent.com/teleonline/listas/main/epg.json.gz
```

Sin comprimir (Release):
```
https://github.com/teleonline/listas/releases/download/epg-latest/epg.xml
https://github.com/teleonline/listas/releases/download/epg-latest/epg.json
```

Las URLs son permanentes: cada día se sobrescribe el contenido pero se mantiene la dirección.

## Cómo usar

En cualquier cliente IPTV (VLC, Kodi, Jellyfin, TVHeadend, IPTV Smarters, Tivimate), añade la URL del XML como fuente EPG remota.

En VLC, por ejemplo: Medio > Abrir ubicación de red > pegar la URL del XML.

Los IDs del EPG son los originales de las fuentes. Para cruzarlos con tu propia lista de canales, empareja por nombre.

## Configuración

- `sources.txt` — URLs de las fuentes EPG, una por línea.
- `settings.txt` — días de programación pasada y futura a incluir.
- `exclusions.txt` — patrones de IDs a excluir.

## Generación automática

Un workflow de GitHub Actions ejecuta el script una vez al día. 

## Créditos

Idea original: [davidmuma](https://github.com/davidmuma/miEPG). 
Mantenimiento: [teleonline](https://github.com/teleonline).
