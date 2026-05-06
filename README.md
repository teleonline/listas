# Teleonline - Curated TV Lists

Teleonline maintains curated lists of publicly available television channels from around the world. These lists aggregate free-to-air (FTA) channels that are legally available to the general public.

## Contents

This repository contains:

- **tv.json** - Television channels organized by country and scope
- **tv.m3u8** - M3U8 playlist format for media players

All channels listed are:
- ✓ Publicly broadcast channels
- ✓ Free-to-air (FTA) without subscription requirements
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
| `logo` | string | Logo/image URL |
| `web` | string | Official website |
| `epg_id` | string | Electronic Program Guide identifier |
| `options` | array | Available streaming options |
| `format` | string | Stream format (hls, dash, youtube, web) |
| `url` | string | Stream URL |

## M3U8 Playlist

The `tv.m3u8` file is automatically generated from `tv.json` and contains:
- Channel metadata (name, logo, EPG ID)
- Stream URLs
- Group categorization by country and scope

Compatible with:
- VLC Media Player
- Kodi
- OBS Studio
- IPTV Player apps
- Other IPTV-compatible clients

## Legal Compliance

### What We Include

Only channels that meet these criteria:

1. **Legally Distributed** - Officially broadcast and publicly available
2. **No DRM Bypass** - No circumvention of copyright protection mechanisms
3. **Regional Respect** - Geographic restrictions are properly applied
4. **Proper Attribution** - Credit given to original broadcasters

### What We Don't Include

- Unauthorized or pirated streams
- Subscription-protected channels
- DRM-circumvented content
- Illegally redistributed broadcasts

### External Use

If you use these lists externally, you agree to:

1. Respect all geographic and licensing restrictions
2. Comply with local broadcast regulations
3. Use streams only in permitted regions
4. Follow broadcaster terms of service

## Updates & Maintenance

Lists are maintained through:
- Community contributions
- Broadcaster API monitoring
- Regular stream availability validation
- Removal of broken or obsolete streams

## Contributing

To contribute updates or corrections:

1. Verify the channel is legally available FTA in its broadcast region
2. Include working stream URLs and accurate metadata
3. Test streams function correctly
4. Submit updates with clear documentation

## Disclaimer

Teleonline provides lists "as-is" for informational and legal use only. Users are responsible for:

- Complying with local broadcast laws
- Respecting broadcaster terms of service
- Understanding regional content restrictions
- Using streams only in permitted geographic regions

Teleonline does NOT:
- Host or distribute any streams directly
- Circumvent DRM or copyright protections
- Facilitate unauthorized access to paid content
- Guarantee availability or reliability of streams

## License

These lists are provided solely for legal informational purposes. By using these lists, you acknowledge you will use them in compliance with all applicable laws and broadcaster terms of service.

---

**Last Updated:** Automatically maintained via WordPress admin panel
