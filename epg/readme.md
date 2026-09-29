# EPG - Guía Electrónica de Programación

Este directorio contiene la configuración para generar automáticamente la guía de programación (epg.xml y epg.json) de los canales listados en el repositorio.

La idea original de este sistema proviene del proyecto miEPG de davidmuma (https://github.com/davidmuma/miEPG), adaptada a las necesidades de este repositorio.

---

## ¿Cómo funciona?

El sistema no extrae la programación de las cadenas directamente. En su lugar, descarga guías EPG públicas ya existentes, las fusiona y se queda solo con los canales que nos interesan.

En resumen:

1. Se descargan varias fuentes EPG (definidas en sources.txt).
2. Se buscan en ellas los canales que aparecen en nuestro tv.json.
3. Se genera un único archivo con solo esos canales y su programación:
   - epg.xml  -> formato XMLTV (estándar, compatible con VLC, Kodi, Jellyfin...).
   - epg.json -> mismo contenido en JSON, más fácil de usar en aplicaciones web.

Ambos archivos se generan en la raíz del repositorio y se actualizan automáticamente mediante GitHub Actions.

---

## Archivos de configuración

| Archivo         | Para qué sirve                                                                                  |
|-----------------|-------------------------------------------------------------------------------------------------|
| sources.txt     | Lista de URLs de las EPGs de origen. Una por línea.                                             |
| settings.txt    | Ajustes generales: días de programación pasada y futura a incluir.                              |
| mapping.txt     | Mapeos manuales para canales que no se emparejan automáticamente.                               |
| unmatched.txt   | (Generado) Lista de canales que no se han encontrado en ninguna fuente. Útil para rellenar mapping.txt. |

---

## Cómo añadir un canal nuevo

1. Añádelo a tv.json con su campo epg_id.
2. Ejecuta el proceso (o espera al siguiente ciclo automático).
3. Si aparece en unmatched.txt, significa que no se encontró en las fuentes. En ese caso:
   - Busca el canal en alguna de las fuentes EPG.
   - Añade una línea en mapping.txt con el formato:

     id_en_tv.json = id_en_la_fuente

   - Ejemplo:

     La 1.TV = La1.es

---

## Cómo añadir una fuente EPG nueva

Solo hay que añadir su URL a sources.txt. El orden importa: las fuentes de arriba tienen prioridad si hay canales duplicados.

Se recomienda añadir fuentes solo de países de los que tengamos canales, para no sobrecargar el proceso.

---

## Generación automática

Un workflow de GitHub Actions ejecuta el script scripts/build-epg-guide.php una vez al día. Este script:

1. Descarga las fuentes.
2. Empareja los canales.
3. Genera epg.xml y epg.json en la raíz.
4. Hace commit automático de los cambios.

También se puede lanzar manualmente desde la pestaña Actions del repositorio.

---

## Créditos

Idea original y sistema base: davidmuma (https://github.com/davidmuma/miEPG).

Adaptación y mantenimiento: teleonline (https://github.com/teleonline).
