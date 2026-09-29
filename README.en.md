[Read in English](README.md)

# Teleonline - Curated TV Lists

Teleonline maintains curated lists of publicly available television channels from around the world (only from official and authorized sources). These lists bring together local, national, independent, and international television channels that are legally available to the general public, as well as videos/live streams from video platforms with authorization to embed their player on third-party sites.

## Table of Contents

- [Content](#content)
- [Data Structure](#data-structure)
- [Field Definitions](#field-definitions)
- [M3U8 List](#m3u8-list)
- [How to Use](#how-to-use)
- [Legal Compliance](#legal-compliance)
- [Updates and Maintenance](#updates-and-maintenance)
- [Contributing](#contributing)
- [Legal Notice](#legal-notice)
- [License](#license)

## Content

This repository contains:

- **tv.json** - TV channels organized by country and theme
- **tv.m3u8** - M3U8 playlist format for media players

All listed channels are:
- ✓ Public broadcast channels
- ✓ Local, national, independent, and international television services without subscription requirements
- ✓ Legally distributed by their respective broadcasters
- ✓ Accessible within their designated broadcast regions

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
|-------|------|-------------|
| `name` | string | Channel name |
| `logo` | string | Logo or image URL |
| `web` | string | Official website |
| `epg_id` | string | Electronic Program Guide identifier |
| `options` | array | Available streaming options |
| `format` | string | Streaming format (hls, dash, youtube, web) |
| `url` | string | Streaming URL |

## M3U8 List

The `tv.m3u8` file is automatically generated from `tv.json` and contains:
- Channel metadata (name, logo, EPG ID)
- Streaming URLs
- Categorization by country and scope

Compatible with:
- VLC Media Player
- Kodi
- OBS Studio
- IPTV apps
- Other IPTV-compatible players

## How to Use

### Direct Access

Access the raw files directly for integration:

**TV JSON:**
```
https://teleonline.github.io/listas/tv.json
```

**TV M3U8:**
```
https://teleonline.github.io/listas/tv.m3u8
```

### Use Cases

#### 1. Media Players
Add the M3U8 URL to your player:
- **VLC:** Media → Open Network Stream → Paste M3U8 URL
- **Kodi:** Add-ons → Install from repository → Enter M3U8 URL
- **OBS:** Scene → Add source → Media Source → Enter M3U8 URL

#### 2. Custom Applications
Use `tv.json` to build custom apps:
- Create IPTV applications
- Develop channel recommendation systems
- Integrate with electronic program guides
- Create Smart TV apps

#### 3. Playlist Management
Import M3U8 into playlist managers:
- IPTV Smarters
- Perfect Player
- GSE Smart IPTV
- Televizo

#### 4. Web Integration
Integrate streams into web applications using JSON data with libraries such as HLS.js

#### 5. Backup and Archive
Keep local copies of channels and metadata for offline access

## Legal Compliance

### What We Include

Only channels that meet these criteria:

1. **Legally Distributed** - Officially broadcast and publicly available with authorization
2. **No DRM Circumvention** - No copyright protection mechanisms are circumvented
3. **Regional Respect** - Geographic restrictions apply where applicable
4. **Proper Attribution** - Credit is given to the original broadcasters

### What We Do NOT Include

- Unauthorized or pirated streams
- Channels protected by pay services
- Content with circumvented DRM
- Illegally distributed streams

### External Use

If you use these lists externally, you agree to:

1. Respect all geographic and licensing restrictions
2. Comply with local broadcasting regulations
3. Use streams only in permitted regions
4. Follow the broadcaster's terms of service

## Updates and Maintenance

Lists are maintained through:
- Community contributions
- Monitoring broadcaster APIs
- Regular validation of stream availability
- Removal of obsolete or broken streams

## Contributing

To contribute updates or corrections:

1. Verify that the channel is legally available in its region
2. Include working stream URLs and accurate metadata
3. Test that the streams work correctly
4. Submit updates with clear documentation to: soporte@teleonline.org

## Legal Notice

Teleonline provides these lists "as is" for informational and legal use only. Users are responsible for:

- Complying with local broadcasting laws
- Respecting the broadcaster's terms of service
- Understanding regional content restrictions
- Using streams only in permitted geographic regions

Teleonline does NOT:
- Provide, host, or distribute any streams directly
- Circumvent DRM or copyright protections
- Facilitate unauthorized access to paid content
- Guarantee stream availability or reliability

## License

These lists are provided for informational and legal use only. By using these lists, you acknowledge that you will use them in compliance with all applicable laws and the broadcasters' terms of service.

---

**Last Updated:** Automatically maintained
