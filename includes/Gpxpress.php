<?php

/*
 * Copyright 2012 David Keen <david@davidkeen.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

class Gpxpress
{
    // The name of the div containing the map.
    const MAP_DIV = 'gpxpressMap';

    // MapQuest tile layer
    const OMQ_TILE_LAYER = 'http://{s}.mqcdn.com/tiles/1.0.0/osm/{z}/{x}/{y}.png';
    const OMQ_ATTRIBUTION = 'Data, imagery and map information provided by <a href=\"http://open.mapquest.co.uk\" target=\"_blank\">MapQuest</a>';
    const OMQ_SUBDOMAINS = '["otile1","otile2","otile3","otile4"]';

    // String containing a JS array of latlong pairs, parsed from the GPX file in the shortcode handler.
    // Format: [[12.34,98.76],[56.78,54.32],...]
    private $latlong = '';

    // The track start latlong ('[12.34,98.76]')
    private $start = '';

    // The track finish latlong
    private $finish = '';

    // Default values for all plugin options.
    // To add a new option just add it to this array.
    private $defaultOptions = array(
        'path_colour' => 'magenta',
        'width' => 600,
        'height' => 400,
        'showStart' => false,
        'showFinish' => false);
    private $options;

    public function __construct() {

        // Set up the options array
        $this->options = get_option('gpxpress_options');
        if (!is_array($this->options)) {

            // We don't have any options set yet.
            $this->options = $this->defaultOptions;

            // Save them to the DB.
            update_option('gpxpress_options', $this->options);
        } else if (count(array_diff_key($this->defaultOptions, $this->options)) > 0) {

            // The option was set but we don't have all the option values.
            foreach ($this->defaultOptions as $key => $val) {
                if (!isset($this->options[$key]) ) {
                    $this->options[$key] = $this->defaultOptions[$key];
                }
            }

            // Save them to the DB.
            update_option('gpxpress_options', $this->options);
        }
    }

    /**
     * The wp_enqueue_scripts action callback.
     * This is the hook to use when enqueuing items that are meant to appear on the front end.
     * Despite the name, it is used for enqueuing both scripts and styles.
     */
    public function wp_enqueue_scripts() {

        // Styles
        wp_register_style('leaflet-css', 'http://cdn.leafletjs.com/leaflet-0.4/leaflet.css');
        wp_enqueue_style('leaflet-css');

        // Scripts
        wp_register_script('leaflet-js', 'http://cdn.leafletjs.com/leaflet-0.4/leaflet.js');
        wp_enqueue_script('leaflet-js');

        wp_register_script('icons', plugins_url('js/icons.js', dirname(__FILE__)));
        wp_enqueue_script('icons');
        wp_localize_script('icons', 'data', array(
                'iconPath' => plugins_url('icons', dirname(__FILE__))
            )
        );
    }

    /**
     * The wp_footer action callback.
     *
     * Outputs the javascript to show the map.
     */
    public function wp_footer() {

        // TODO: Extract this into separate file and parameterise.

        echo '
            <script type="text/javascript">
            //<![CDATA[
            var map = L.map("' . self::MAP_DIV . '");
            L.tileLayer("' . self::OMQ_TILE_LAYER . '", {
                attribution: "' . self::OMQ_ATTRIBUTION . '",
                maxZoom: 18,
                subdomains: ' . self::OMQ_SUBDOMAINS . '
            }).addTo(map);
            var polyline = L.polyline(' . $this->latlong . ', {color: "' . $this->options['path_colour'] . '"}).addTo(map);

            // zoom the map to the polyline
            map.fitBounds(polyline.getBounds());

            // Add markers
            ' .
            (!empty($this->start) ? 'L.marker(' . $this->start . ', {icon: startIcon}).addTo(map);' : '') .
            (!empty($this->finish) ? 'L.marker(' . $this->finish . ', {icon: finishIcon}).addTo(map);' : '') . '
            //]]>
            </script>';
    }

    /**
     * Filter callback to allow .gpx file uploads.
     *
     * @param array $existing_mimes the existing mime types.
     * @return array the allowed mime types.
     */
    public function add_gpx_mime($existing_mimes = array()) {

        // Add file extension 'extension' with mime type 'mime/type'
        $existing_mimes['gpx'] = 'application/gpx+xml';

        // and return the new full result
        return $existing_mimes;
    }

    /**
     * The [gpxpress] shortcode handler.
     *
     * This shortcode inserts a map of the GPX track.
     * The 'src' parameter should be used to give the url containing the GPX data.
     * The 'width' and 'height' parameters set the width and height of the map in pixels. (Default 600x400)
     * The 'showStart' and 'showFinish' parameters toggle the start/finish markers.
     * Eg: [gpxpress src=http://www.example.com/my_file.gpx width=600 height=400 showStart=true showFinish=false]
     *
     * @param string $atts an associative array of attributes.
     * @return string the shortcode output to be inserted into the post body in place of the shortcode itself.
     */
    public function gpxpress_shortcode($atts) {

        // Extract the shortcode arguments into local variables named for the attribute keys (setting defaults as required)
        $defaults = array(
            'src' => GPXPRESS_PLUGIN_DIR . '/demo.gpx',
            'width' => $this->options['width'],
            'height' => $this->options['height'],
            'showStart' => $this->options['showStart'],
            'showFinish' => $this->options['showFinish']);
        extract(shortcode_atts($defaults, $atts));

        // Create a div to show the map.
        $ret = '<div id="' . self::MAP_DIV .'" style="width: ' . $width . 'px; height: ' . $height .'px">&#160;</div>';

        // Parse the latlongs from the GPX and save them to a global variable to be used in the JS later.
        // String format: [[12.34,98.76],[56.78,54.32]]
        $pairs = array();
        $xml = simplexml_load_file($src);
        foreach ($xml->trk->trkseg->trkpt as $trkpt) {
            $pairs[] = '[' . $trkpt['lat'] . ',' . $trkpt['lon'] . ']';
        }
        $this->latlong = '[' . implode(',', $pairs) . ']';

        // Set the start and finish latlongs to be used in the JS later.
        if ($showStart) {
            $this->start = $pairs[0];
        }
        if ($showFinish) {
            $this->finish = end(array_values($pairs));
        }

        return $ret;
    }

    // TODO: Move admin stuff into separate class.

    /**
     * admin_init action callback.
     */
    public function admin_init() {

        // Register a setting and its sanitization callback.
        // Parameters are:
        // $option_group - A settings group name. Must exist prior to the register_setting call. (settings_fields() call)
        // $option_name - The name of an option to sanitize and save.
        // $sanitize_callback - A callback function that sanitizes the option's value.
        register_setting('gpxpress-options', 'gpxpress_options', array($this, 'validate_options'));

        // Add the 'General Settings' section to the options page.
        // Parameters are:
        // $id - String for use in the 'id' attribute of tags.
        // $title - Title of the section.
        // $callback - Function that fills the section with the desired content. The function should echo its output.
        // $page - The type of settings page on which to show the section (general, reading, writing, media etc.)
        add_settings_section('general', 'General Settings', array($this, 'general_section_content'), 'gpxpress');


        // Register the options
        // Parameters are:
        // $id - String for use in the 'id' attribute of tags.
        // $title - Title of the field.
        // $callback - Function that fills the field with the desired inputs as part of the larger form.
        //             Name and id of the input should match the $id given to this function. The function should echo its output.
        // $page - The type of settings page on which to show the field (general, reading, writing, ...).
        // $section - The section of the settings page in which to show the box (default or a section you added with add_settings_section,
        //            look at the page in the source to see what the existing ones are.)
        // $args - Additional arguments
        add_settings_field('path_colour', 'Path colour', array($this, 'path_colour_input'), 'gpxpress', 'general');
        add_settings_field('width', 'Map width', array($this, 'width_input'), 'gpxpress', 'general');
        add_settings_field('height', 'Map height', array($this, 'height_input'), 'gpxpress', 'general');
        add_settings_field('showStart', 'Show start marker', array($this, 'showStart_input'), 'gpxpress', 'general');
        add_settings_field('showFinish', 'Show finish marker', array($this, 'showFinish_input'), 'gpxpress', 'general');
    }

    /**
     * Filter callback to add a link to the plugin's settings.
     *
     * @param $links
     * @return array
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=gpxpress">' . __("Settings", "GPXpress") . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * admin_menu action callback.
     */
    public function admin_menu() {
        add_options_page('GPXpress Options', 'GPXpress', 'manage_options', 'gpxpress', array($this, 'options_page'));
    }

    /**
     * Creates the plugin options page.
     * See: http://ottopress.com/2009/wordpress-settings-api-tutorial/
     * And: http://codex.wordpress.org/Settings_API
     */
    public function options_page() {

        // Authorised?
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Start the settings form.
        echo '
            <div class="wrap">
            <h2>GPXpress Settings</h2>
            <form method="post" action="options.php">';

        // Display the hidden fields and handle security.
        settings_fields('gpxpress-options');

        // Print out all settings sections.
        do_settings_sections('gpxpress');

        // Finish the settings form.
        echo '
            <input class="button-primary" name="Submit" type="submit" value="Save Changes" />
            </form>
            </div>';
    }

    /**
     * Fills the section with the desired content. The function should echo its output.
     */
    public function general_section_content() {
        // Nothing to see here.
    }

    /**
     * Fills the field with the desired inputs as part of the larger form.
     * Name and id of the input should match the $id given to this function. The function should echo its output.
     *
     * Name value must start with the same as the id used in register_setting.
     */
    public function path_colour_input() {
    	echo "<input id='path_colour' name='gpxpress_options[path_colour]' size='40' type='text' value='{$this->options['path_colour']}' />";
    }

    public function width_input() {
        echo "<input id='width' name='gpxpress_options[width]' size='40' type='text' value='{$this->options['width']}' />";
    }

    public function height_input() {
        echo "<input id='height' name='gpxpress_options[height]' size='40' type='text' value='{$this->options['height']}' />";
    }

    public function showStart_input() {
        echo "<input id='showStart' name='gpxpress_options[showStart]' type='checkbox' value='true' " . checked(true, $this->options['showStart'], false) . "/>";
    }

    public function showFinish_input() {
        echo "<input id='showFinish' name='gpxpress_options[showFinish]' type='checkbox' value='true' " . checked(true, $this->options['showFinish'], false) . "/>";
    }

    public function validate_options($input) {

        // TODO: Do we need to list all options here or only those that we want to validate?

        // Validate path colour
        $this->options['path_colour'] = $input['path_colour'];

        // Validate width and height
        if ($input['width'] < 0) {
            $this->options['width'] = 0;
        } else {
            $this->options['width'] = $input['width'];
        }

        if ($input['height'] < 0) {
            $this->options['height'] = 0;
        } else {
            $this->options['height'] = $input['height'];
        }

        // If the checkbox has not been checked, we void it
        if (!isset($input['showStart'])) {
            $input['showStart'] = null;
        }
        // We verify if the input is a boolean value
        $this->options['showStart'] = ($input['showStart'] == true ? true : false);

        if (!isset($input['showFinish'])) {
            $input['showFinish'] = null;
        }
        $this->options['showFinish'] = ($input['showFinish'] == true ? true : false);

        return $this->options;
    }
}


