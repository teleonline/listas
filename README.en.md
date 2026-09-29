[Leer en español](README.md)

# Teleonline - Curated TV/Streaming List + EPG


Teleonline maintains a curated list of publicly available television channels from around the world, <strong>only from official and authorized sources</strong> that broadcast over the Internet. These lists bring together local, national, independent and international television channels that are legally available to the general public, and also list videos/live streams from video platforms that allow their player to be embedded (embed) on third-party sites.

## Table of Contents

- [How to Use](#how-to-use)
- [Program Guide (EPG)](#program-guide-epg)
- [Contents](#contents)
- [M3U8 List](#m3u8-list)
- [Data Structure](#data-structure)
- [Field Definitions](#field-definitions)
- [Updates and Maintenance](#updates-and-maintenance)
- [Contributing](#contributing)
- [Legal Compliance](#legal-compliance)
- [Legal Notice](#legal-notice)
- [License](#license)

## How to Use

Access the raw files directly for integration:

---

**TV (M3U8):**

```text
https://teleonline.github.io/listas/tv.m3u8
```

**TV (JSON):**

```text
https://teleonline.github.io/listas/tv.json
```

**PROGRAM GUIDE EPG (XML):**

```text
https://teleonline.github.io/listas/epg.xml.gz
```
---

### Use Cases

#### 1. Media Players

Add the M3U8 URL to your player:

- **VLC:** Media → Open Network Stream → Paste M3U8 URL
- **Kodi:** Add-ons → Install from repository → Enter M3U8 URL
- **OBS:** Scene → Add source → Media source → Enter M3U8 URL

#### 2. Custom Applications

Use `tv.json` to build custom apps:

- Create IPTV applications
- Develop channel recommendation systems
- Integration with electronic program guides
- Create apps for Smart TV

#### 3. Playlist Management

Import M3U8 into playlist managers:

- IPTV Smarters
- Perfect Player
- GSE Smart IPTV
- Televizo

#### 4. Web Integration

Embed streams in web applications using JSON data with libraries such as HLS.js

#### 5. Backup and Archive

Keep local copies of channels and metadata for offline access

## Program Guide (EPG)

Automatically generated from multiple public sources. It covers thousands of international channels in **XMLTV** and **JSON** format.

| Format | Type | URL |
|---|---|---|
| XML | Compressed | `https://raw.githubusercontent.com/teleonline/listas/main/epg.xml.gz` |
| JSON | Compressed | `https://raw.githubusercontent.com/teleonline/listas/main/epg.json.gz` |
| XML | Uncompressed | `https://github.com/teleonline/listas/releases/download/epg-latest/epg.xml` |
| JSON | Uncompressed | `https://github.com/teleonline/listas/releases/download/epg-latest/epg.json` |

The URLs are permanent: the content is updated every day, but the address does not change.

### How to use it

In any IPTV client compatible with XMLTV, add the URL as a remote EPG source:

| Client | Path |
|---|---|
| **VLC** | Media → Open Network Stream → paste the XML URL |
| **Kodi** | PVR IPTV Simple Client addon → EPG Settings → paste the XML URL |
| **Jellyfin** | Live TV → add XMLTV provider → paste the XML URL |
| **TVHeadend** | Configuration → EPG Grabber → XMLTV → paste the XML URL |
| **IPTV Smarters / Tivimate / Perfect Player** | EPG section → add remote XMLTV source |

More information at [epg/readme.md](https://github.com/teleonline/listas/blob/main/epg/readme.md).

## Contents

This repository contains:

| File | Description |
|---|---|
| `tv.json` | Television channels organized by country and theme |
| `tv.m3u8` | M3U8 playlist format for media players |
| `varios/canales.txt` | Simplified list of channels grouped by category |
| `epg.xml.gz` · `epg.json.gz` | Compressed program guide |
| `epg.xml` · `epg.json` | Uncompressed guide (in *Releases*) |

All listed channels are:

- Public broadcast channels
- Local, national, independent and international television with no subscription requirements
- Legally distributed by their respective broadcasters
- Accessible within their designated broadcast regions

[View channel list](https://github.com/teleonline/listas/blob/main/varios/canales.txt)

## M3U8 List

The `tv.m3u8` file is automatically generated from `tv.json` and contains:

- Channel metadata (name, logo, EPG ID)
- Stream URLs
- Categorization by country and scope

Compatible with:

- VLC Media Player
- Kodi
- OBS Studio
- IPTV applications
- Other IPTV-compatible players

## Data Structure

### tv.json

```json
{
  "countries": [
    {
      "name": "Country Name",
      "ambits": [
        {
          "name": "Category",
          "channels": [
            {
              "name": "Channel Name",
              "logo": "https://example.com/logo.png",
              "web": "https://example.com/",
              "epg_id": "channel.id",
              "options": [
                {
                  "format": "hls",
                  "url": "https://stream.example.com/master.m3u8"
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

### Field Definitions

| Field | Type | Description |
|---|---|---|
| `name` | string | Channel name |
| `logo` | string | Logo or image URL |
| `web` | string | Official website |
| `epg_id` | string | Electronic Program Guide identifier |
| `options` | array | Available streaming options |
| `format` | string | Streaming format (hls, dash, youtube, web) |
| `url` | string | Stream URL |

## Updates and Maintenance

The lists are maintained through:

- Community contributions
- Monitoring of broadcaster APIs
- Regular validation of stream availability
- Removal of outdated or broken streams

## Contributing

To contribute updates or corrections:

1. Verify that the channel is legally available in its region
2. Include working stream URLs and accurate metadata
3. Test that the streams work correctly
4. Send updates with clear documentation to: soporte@teleonline.org

## Legal Compliance

### What We Include

Only channels that meet these criteria:

1. **Legally Distributed** - Officially broadcast and publicly available with authorization
2. **No DRM Circumvention** - Copyright protection mechanisms are not circumvented
3. **Regional Respect** - Geographic restrictions are applied where appropriate
4. **Proper Attribution** - Credit is given to the original broadcasters

### What We DO NOT Include

- Unauthorized or pirated streams
- Channels protected by paid services
- Content with circumvented DRM
- Illegally distributed streams

### External Use

If you use these lists externally, you agree to:

1. Respect all geographic and license restrictions
2. Comply with local broadcasting regulations
3. Use the streams only in permitted regions
4. Follow the broadcaster's terms of service

## Legal Notice

Teleonline provides these lists "as is" for informational and legal use only. Users are responsible for:

- Complying with local broadcasting laws
- Respecting the broadcaster's terms of service
- Understanding regional content restrictions
- Using streams only in permitted geographic regions

Teleonline DOES NOT:

- Provide, host, or distribute any stream directly
- Circumvent DRM or copyright protections
- Facilitate unauthorized access to paid content
- Guarantee availability or reliability of streams

## License

These lists are provided for informational and legal use only. By using these lists, you acknowledge that you will use them in compliance with all applicable laws and broadcasters' terms of service.

**Last Update:** Maintained automatically
