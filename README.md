[Read in English](README.en.md)

# Teleonline - Lista curada de TV/Streaming + EPG


Teleonline mantiene una lista curada de canales de televisión públicamente disponibles de todo el mundo, <strong>únicamente de fuentes oficiales y autorizadas</strong> que emiten por Internet. Estas listas reúnen canales de televisión locales, nacionales, independientes e internacionales que están legalmente disponibles para el público general, además de listar vídeos/directos de plataformas de vídeo con autorización de inserción de su reproductor (embed) en sitios de terceros.

## Índice de Contenidos

- [Cómo Usar](#cómo-usar)
- [Guía de programación (EPG)](#guía-de-programación-epg)
- [Contenido](#contenido)
- [Lista M3U8](#lista-m3u8)
- [Estructura de Datos](#estructura-de-datos)
- [Definición de Campos](#definición-de-campos)
- [Actualizaciones y Mantenimiento](#actualizaciones-y-mantenimiento)
- [Contribuir](#contribuir)
- [Cumplimiento Legal](#cumplimiento-legal)
- [Aviso Legal](#aviso-legal)
- [Licencia](#licencia)

## Cómo Usar

Accede a los archivos en bruto directamente para integración:

---

**TV (M3U8):**

```text
https://teleonline.github.io/listas/tv.m3u8
```

**TV (JSON):**

```text
https://teleonline.github.io/listas/tv.json
```

**GUÍA PROGRAMACIÓN EPG (XML):**

```text
https://teleonline.github.io/listas/epg.xml.gz
```
---

### Casos de Uso

#### 1. Reproductores Multimedia

Añade la URL M3U8 a tu reproductor:

- **VLC:** Multimedia → Abrir ubicación de red → Pega URL M3U8
- **Kodi:** Add-ons → Instalar desde repositorio → Ingresa URL M3U8
- **OBS:** Escena → Añadir fuente → Fuente multimedia → Ingresa URL M3U8

#### 2. Aplicaciones Personalizadas

Usa `tv.json` para construir apps personalizadas:

- Crear aplicaciones IPTV
- Desarrollar sistemas de recomendación de canales
- Integración con guías electrónicas de programación
- Crear apps para Smart TV

#### 3. Gestión de Listas de Reproducción

Importa M3U8 en gestores de listas:

- IPTV Smarters
- Perfect Player
- GSE Smart IPTV
- Televizo

#### 4. Integración Web

Integra transmisiones en aplicaciones web usando datos JSON con librerías como HLS.js

#### 5. Respaldo y Archivo

Mantén copias locales de canales y metadatos para acceso sin conexión

## Guía de programación (EPG)

Generada automáticamente a partir de múltiples fuentes públicas. Cubre miles de canales internacionales en formato **XMLTV** y **JSON**.

| Formato | Tipo | URL |
|---|---|---|
| XML | Comprimido | `https://raw.githubusercontent.com/teleonline/listas/main/epg.xml.gz` |
| JSON | Comprimido | `https://raw.githubusercontent.com/teleonline/listas/main/epg.json.gz` |
| XML | Sin comprimir | `https://github.com/teleonline/listas/releases/download/epg-latest/epg.xml` |
| JSON | Sin comprimir | `https://github.com/teleonline/listas/releases/download/epg-latest/epg.json` |

Las URLs son permanentes: el contenido se actualiza cada día, pero la dirección no cambia.

### Cómo usarla

En cualquier cliente IPTV compatible con XMLTV, añade la URL como fuente EPG remota:

| Cliente | Ruta |
|---|---|
| **VLC** | Medio → Abrir ubicación de red → pegar la URL del XML |
| **Kodi** | Addon PVR IPTV Simple Client → EPG Settings → pegar la URL del XML |
| **Jellyfin** | Live TV → añadir proveedor XMLTV → pegar la URL del XML |
| **TVHeadend** | Configuration → EPG Grabber → XMLTV → pegar la URL del XML |
| **IPTV Smarters / Tivimate / Perfect Player** | Sección EPG → añadir fuente XMLTV remota |

Más información en [epg/readme.md](https://github.com/teleonline/listas/blob/main/epg/readme.md).

## Contenido

Este repositorio contiene:

| Archivo | Descripción |
|---|---|
| `tv.json` | Canales de televisión organizados por país y temática |
| `tv.m3u8` | Formato de lista M3U8 para reproductores multimedia |
| `varios/canales.txt` | Lista simplificada de canales agrupados por categorías |
| `epg.xml.gz` · `epg.json.gz` | Guía de programación comprimida |
| `epg.xml` · `epg.json` | Guía sin comprimir (en *Releases*) |

Todos los canales listados son:

- Canales de transmisión pública
- Televisiones locales, nacionales, independientes e internacionales sin requisitos de suscripción
- Distribuidos legalmente por sus respectivos radiodifusores
- Accesibles dentro de sus regiones de transmisión designadas

[Ver lista de Canales](https://github.com/teleonline/listas/blob/main/varios/canales.txt)

## Lista M3U8

El archivo `tv.m3u8` se genera automáticamente desde `tv.json` y contiene:

- Metadatos del canal (nombre, logo, EPG ID)
- URLs de transmisión
- Categorización por país y ámbito

Compatible con:

- VLC Media Player
- Kodi
- OBS Studio
- Aplicaciones IPTV
- Otros reproductores compatibles con IPTV

## Estructura de Datos

### tv.json

```json
{
  "countries": [
    {
      "name": "Nombre del País",
      "ambits": [
        {
          "name": "Categoría",
          "channels": [
            {
              "name": "Nombre del Canal",
              "logo": "https://ejemplo.com/logo.png",
              "web": "https://ejemplo.com/",
              "epg_id": "canal.id",
              "options": [
                {
                  "format": "hls",
                  "url": "https://stream.ejemplo.com/master.m3u8"
                }
              ]
            }
          ]
        }
      ]
    }
  ]
}
```

### Definición de Campos

| Campo | Tipo | Descripción |
|---|---|---|
| `name` | string | Nombre del canal |
| `logo` | string | URL del logo o imagen |
| `web` | string | Sitio web oficial |
| `epg_id` | string | Identificador de Guía Electrónica de Programación |
| `options` | array | Opciones de transmisión disponibles |
| `format` | string | Formato de transmisión (hls, dash, youtube, web) |
| `url` | string | URL de transmisión |

## Actualizaciones y Mantenimiento

Las listas se mantienen mediante:

- Contribuciones de la comunidad
- Monitoreo de APIs de radiodifusores
- Validación regular de disponibilidad de transmisiones
- Eliminación de transmisiones obsoletas o rotas

## Contribuir

Para contribuir actualizaciones o correcciones:

1. Verifica que el canal esté legalmente disponible en su región
2. Incluye URLs de transmisión funcionales y metadatos precisos
3. Prueba que las transmisiones funcionan correctamente
4. Envía actualizaciones con documentación clara a: soporte@teleonline.org

## Cumplimiento Legal

### Qué Incluimos

Solo canales que cumplen estos criterios:

1. **Distribuidos Legalmente** - Transmitidos oficialmente y públicamente disponibles con autorización
2. **Sin Elusión de DRM** - No se eluden mecanismos de protección de derechos de autor
3. **Respeto Regional** - Se aplican restricciones geográficas cuando corresponde
4. **Atribución Adecuada** - Se da crédito a los radiodifusores originales

### Qué NO Incluimos

- Transmisiones no autorizadas o pirateadas
- Canales protegidos por servicios de pago
- Contenido con DRM eludido
- Transmisiones distribuidas ilegalmente

### Uso Externo

Si utilizas estas listas externamente, aceptas:

1. Respetar todas las restricciones geográficas y de licencia
2. Cumplir con las regulaciones locales de transmisión
3. Usar las transmisiones solo en regiones permitidas
4. Seguir los términos de servicio del radiodifusor

## Aviso Legal

Teleonline proporciona estas listas "tal como están" solo para uso informativo y legal. Los usuarios son responsables de:

- Cumplir con las leyes de transmisión locales
- Respetar los términos de servicio del radiodifusor
- Entender las restricciones de contenido regional
- Usar transmisiones solo en regiones geográficas permitidas

Teleonline NO:

- Proporciona, aloja, o distribuye ninguna transmisión directamente
- Elude DRM o protecciones de derechos de autor
- Facilita acceso no autorizado a contenido de pago
- Garantiza disponibilidad o confiabilidad de transmisiones

## Licencia

Estas listas se proporcionan únicamente para uso informativo y legal. Al usar estas listas, reconoces que las usarás en cumplimiento con todas las leyes aplicables y términos de servicio de los radiodifusores.

**Última Actualización:** Mantenida automáticamente
