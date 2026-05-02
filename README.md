# Listas de TV de Teleonline

## Descripción General

Teleonline mantiene listas curadas de canales de televisión públicamente disponibles de todo el mundo. Estas listas agregan canales de transmisión libre (TDT) que están legalmente disponibles para el público general.

## Alcance

Este repositorio contiene:

- **tv.json** - Lista de canales de televisión organizados por país y tipo de contenido
- **epg.json** - Datos de Guía Electrónica de Programación (cuando está disponible)

Todos los canales listados son:
- ✓ Canales de transmisión pública disponibles
- ✓ Televisión Digital Terrestre (TDT) sin requerimientos de suscripción
- ✓ Distribuidos legalmente por sus respectivos radiodifusores
- ✓ Accesibles dentro de sus regiones de transmisión designadas

## Estructura de Datos

### tv.json

```json
{
  "countries": [
    {
      "name": "Spain",
      "ambits": [
        {
          "name": "Nacional",
          "channels": [
            {
              "name": "TVE 1",
              "logo": "https://ejemplo.com/logo.png",
              "web": "https://www.rtve.es/",
              "epg_id": "tv1.es",
              "options": [
                {
                  "format": "hls",
                  "url": "https://stream.ejemplo.com/master.m3u8",
                  "res": "720p",
                  "geo2": "ES"
                }
              ],
              "category": "publica",
              "catchup": {
                "provider": "rtve",
                "channelId": "tv1",
                "days": 7
              }
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
|-------|------|-------------|
| `name` | string | Nombre del canal |
| `logo` | string | URL del logo/imagen del canal |
| `web` | string | Sitio web oficial del canal |
| `epg_id` | string | Identificador de Guía Electrónica de Programación |
| `options` | array | Opciones de transmisión disponibles |
| `format` | string | Formato de transmisión (hls, dash, youtube, web) |
| `url` | string | URL de transmisión |
| `res` | string | Resolución preferida |
| `geo2` | string | Código de país ISO 3166-1 alpha-2 para restricciones geográficas |
| `category` | string | Categoría de contenido (ej: publica, deportes, noticias) |
| `catchup` | object | Configuración de reproductor de contenido (opcional) |

## Cumplimiento Legal

### Qué Incluimos

Solo canales que cumplen estos criterios:

1. **Distribuidos Legalmente** - Canales transmitidos oficialmente y públicamente disponibles
2. **Sin Elusión de DRM** - No facilitamos el bypass de mecanismos de protección de derechos de autor
3. **Respeto de Restricciones Regionales** - Se aplica geo-codificación donde los radiodifusores imponen limitaciones regionales
4. **Atribución** - Se da crédito a las fuentes de transmisión oficiales

### Qué NO Incluimos

- Transmisiones pirateadas o no autorizadas
- Canales protegidos por servicios de suscripción (excepto si están explícitamente disponibles en TDT)
- Contenido con DRM eludido
- Contenido redistribuido ilegalmente

### Uso Externo

Si utilizas estas listas externamente, aceptas:

1. Respetar todas las restricciones geográficas y de licencia
2. No eludir protecciones de derechos digitales
3. Cumplir con regulaciones de transmisión locales
4. Atribuir contenido a los radiodifusores originales

## Actualizaciones de Datos

Las listas son mantenidas por:
- Contribuciones de la comunidad
- Monitoreo de APIs de radiodifusores
- Validación regular de disponibilidad de transmisiones

Las transmisiones obsoletas o rotas se eliminan regularmente.

## Contribuir

Para contribuir actualizaciones o correcciones:

1. Verifica que el canal esté legalmente disponible en TDT en su región de transmisión
2. Incluye URLs de transmisión funcionales y metadatos
3. Prueba que las transmisiones funcionan correctamente
4. Envía actualizaciones con documentación clara a: info@teleonline.org

## Aviso Legal

Teleonline proporciona herramientas y listas "tal como están". Los usuarios son responsables de:

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

Estas listas se proporcionan únicamente para uso informativo y legal. Al usar estas listas, reconoces que las usarás en cumplimiento con todas las leyes aplicables y términos de servicio del radiodifusor.

---

**Última Actualización:** 2026-05-02

Para cuestiones, reclamaciones o preguntas sobre cumplimiento legal, contáctanos en: info@teleonline.org.
