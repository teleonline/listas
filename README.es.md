[Read in English](README.md)

# Teleonline - Listas Curadas de TV

Teleonline mantiene listas curadas de canales de televisión públicamente disponibles de todo el mundo. Estas listas reúnen canales de televisión digital terrestre (TDT) que están legalmente disponibles para el público general.

## Índice de Contenidos

- [Contenido](#contenido)
- [Estructura de Datos](#estructura-de-datos)
- [Definición de Campos](#definición-de-campos)
- [Lista M3U8](#lista-m3u8)
- [Cómo Usar](#cómo-usar)
- [Cumplimiento Legal](#cumplimiento-legal)
- [Actualizaciones y Mantenimiento](#actualizaciones-y-mantenimiento)
- [Contribuir](#contribuir)
- [Aviso Legal](#aviso-legal)
- [Licencia](#licencia)

## Contenido

Este repositorio contiene:

- **tv.json** - Canales de televisión organizados por país y ámbito
- **tv.m3u8** - Formato de lista M3U8 para reproductores multimedia

Todos los canales listados son:
- ✓ Canales de transmisión pública
- ✓ Televisión Digital Terrestre (TDT) sin requisitos de suscripción
- ✓ Distribuidos legalmente por sus respectivos radiodifusores
- ✓ Accesibles dentro de sus regiones de transmisión designadas

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
|-------|------|-------------|
| `name` | string | Nombre del canal |
| `logo` | string | URL del logo o imagen |
| `web` | string | Sitio web oficial |
| `epg_id` | string | Identificador de Guía Electrónica de Programación |
| `options` | array | Opciones de transmisión disponibles |
| `format` | string | Formato de transmisión (hls, dash, youtube, web) |
| `url` | string | URL de transmisión |

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

## Cómo Usar

### Acceso Directo

Accede a los archivos en bruto directamente para integración:

**TV JSON:**
```
https://raw.githubusercontent.com/teleonline/listas/main/tv.json
```

**TV M3U8:**
```
https://raw.githubusercontent.com/teleonline/listas/main/tv.m3u8
```

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

## Cumplimiento Legal

### Qué Incluimos

Solo canales que cumplen estos criterios:

1. **Distribuidos Legalmente** - Transmitidos oficialmente y públicamente disponibles
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
4. Envía actualizaciones con documentación clara a: info@teleonline.org

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

---

**Última Actualización:** Mantenida automáticamente
