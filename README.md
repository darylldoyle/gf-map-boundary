# GF Map Boundary

Adds a Gravity Forms field that lets users draw a polygon on a Google Map when submitting a form. Upon submission, the
plugin generates and stores a static image of the drawn area and saves helpful metadata (map center, zoom, and a link to
open the view in Google Maps) with the entry.

## Features

- New "Map Drawing" field in Gravity Forms (Advanced Fields).
- Draw polygon boundaries on an interactive Google Map.
- Saves a static map image (PNG) of the polygon to the WordPress uploads folder.
- Stores map center, zoom level, and an "Open in Google Maps" link as entry meta.
- Displays the saved image in entries and supports merge tag output in notifications.

## Requirements

- WordPress 5.8+
- Gravity Forms 2.5+
- Google Maps JavaScript API key (with access to the Maps JavaScript API and Static Maps API)

## Installation

1. Copy the plugin folder `gf-map-boundary` into `wp-content/plugins/`.
2. Activate "GF Map Boundary" from the WordPress Plugins screen.
3. Make sure you have Gravity Forms active.

## Configuration (Google Maps API Key)

There are two ways to provide the API key:

1) Via the plugin settings page (recommended)

- Go to Forms → Settings → Map Boundary.
- Enter your Google Maps JavaScript API key and save.

2) Via wp_option (fallback)

- Option key: `gfmb_google_api_key`
- This can be set programmatically or via a database tool. The Add-On settings override this value if provided.

## Usage

1. Edit a Gravity Form and add the "Map Drawing" field (found under Advanced Fields).
2. On the front end, visitors can:
    - Search by postcode and navigate the map.
    - Click the map to add polygon points, undo the last point, or clear the shape.
3. Upon submission:
    - The plugin generates a static map image of the polygon using Google Static Maps.
    - The image is stored in `wp-content/uploads/gf-map-boundary/` and the image URL is saved as the field value.
    - The center latitude/longitude and zoom are saved as entry meta.
    - A Google Maps link is stored to quickly open the same view in an interactive map.

## Output and Admin

- The saved image appears on the entry detail screen. Center/zoom and a link to Google Maps are shown beneath the value.
- In notifications/merge tags, the image is output in HTML emails with a small details block. In plain text formats, the
  details are appended inline.

## Assets

- Frontend JS: `assets/js/gfmb-frontend.js`
- Frontend CSS: `assets/css/gfmb.css`

## Uninstall / Cleanup

This plugin stores generated images in `wp-content/uploads/gf-map-boundary/`. Removing the plugin will not delete
previously generated images or entry meta. If desired, you can safely remove that directory after confirming it is no
longer needed.

## License

GPL-2.0-or-later. See License URI in the plugin header.
