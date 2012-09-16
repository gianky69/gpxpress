=== GPXpress ===
Contributors: davidkeen
Tags: geo, gpx, navigation, maps
Requires at least: 3.0
Tested up to: 3.4.2
Stable tag: 0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WordPress plugin to display GPX tracks.

== Description ==

This plugin uses the [Leaflet](http://leaflet.cloudmade.com) JavaScript library and tiles from the [Open MapQuest](http://open.mapquest.co.uk) project to display beautiful maps of GPX tracks.

== Installation ==

1.  Extract the zip file and drop the contents in the wp-content/plugins/ directory of your WordPress installation.
2.  Activate the plugin from Plugins page.
3.  Go to the plugin settings page and choose the colour of your tracks. This may be any valid HTML colour code (default is 'red').

To add a map to a post:

1.  Insert the [gpxpress] shortcode into your post where you want to display the map. Use the 'src' parameter to specify the URL of the GPX track you want to display.
Use the 'width' and 'height' parameters to give the width and height of the map in pixels (default is 600x400). Eg, [gpxpress src=http://www.example.com/my_file.gpx width=600 height=400].

== Screenshots ==

1. Example.

== Changelog ==

= 0.1 =
* Initial release.