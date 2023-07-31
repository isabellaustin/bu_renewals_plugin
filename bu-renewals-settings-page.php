<?php

class bu_renewals_optionspage {
  var $group;
  var $page;
  var $options;

  var $title;
  var $buNetworkSaveError = '';

  /**
	 * bu_renewals_optionspage(0)
	 *
	 * Rendering option page.
	 *
	 * @param [type] $group
	 * @param [type] $page
	 * @param [type] $options
	 * @return void
	 */
  function __construct( $group, $page, $options ) {
    $this -> group = $group;
    $this -> page = $page;
    $this -> options = $options;
    $this -> title = "Site Renewals";

    if ( $_POST && isset($_POST[$group]) ) {
      $this -> saveNetworkOptions($group);
    }

    if ( isset($_REQUEST['bu-renewal-reset']) && ($_REQUEST['bu-renewal-reset'] == 'YES') ) {
      global $wpdb;
      $wpdb->get_results( "DELETE FROM $wpdb->sitemeta WHERE meta_key LIKE 'bu_%_renewed'" );
      $this -> buNetworkSaveError = "<div id='message' class='updated fade'><p>All renewal data has been deleted.</p></div>\n";
    }

    $this -> options = get_network_option( 1, $group, array() );

    add_action( 'admin_init', array(&$this, 'register_options') );
    add_action( 'network_admin_menu', array(&$this, 'add_networksettings_page') );

  }


	/**
	 * saveNetworkOptions()
	 *
	 * Group will normally be bu_renewals_options_ms, but could possibly change in the future
	 *
	 * @param [type] $group
	 * @return void
	 */
  function saveNetworkOptions($group) {

    $settings = array();

    foreach ( array('enabled','end_date','bu_renewal_text') as $field ) {
      $settings[$field] = isset( $_POST[$group][$field] ) ? $_POST[$group][$field] : '';
    }
    update_network_option( 1, $group, $settings );

    $this -> buNetworkSaveError = "<div id='message' class='updated fade'><p>Settings updated.</p></div>\n";

  }

	/**
	 * register_options()
	 *
	 * Register the options for this plugin so they can be displayed and updated below.
	 *
	 * @return void
	 */
  function register_options() {

    register_setting( $this -> group, $this -> group );

    #-- Create the 'bu_renewals_main' section and add fields to it
    $section = 'bu_renewals_main';
    add_settings_section( $section, 'General Settings', array(&$this, '_display_options_section'), $this -> page );
		add_settings_field( 'bu_renewals_enabled', 'Renewals Enabled?', array(&$this, '_display_option_enabled'), $this -> page, $section );
		add_settings_field( 'bu_renewals_renwal_text', '<label>Renewal Text Prompt:</label>', array(&$this, '_display_option_renewaltext'), $this -> page, $section );
    add_settings_field( 'bu_renewals_end_date', '<label>Renewal End Date:</label>', array(&$this, '_display_option_end_date'), $this -> page, $section );
		add_settings_field( 'bu_renewals_reset_button', 'Reset Renewal Data:', array(&$this, '_display_option_reset_button'), $this -> page, $section );

  }

/*
  function _display_date_picker($label) {
  	print "<label for=$label>End Date:</label>"
  	print "<input type="date">"
  }
 */
	/**
	 * _display_options_section
	 *
	 * Unused - Display explanatory text for the main options section.
	 *
	 * @return void
	 */
  function _display_options_section() {
  }

	/**
	 * _display_radio_buttons
	 *
	 * Display radio buttons
	 *
	 * @param [type] $groupID
	 * @param [type] $value
	 * @param [type] $radio_options
	 * @param integer $noNewlines
	 * @return void
	 */
  function _display_radio_buttons( $groupID, $value, $radio_options, $noNewlines = 0 ) {

    print "\n<p>\n";
    foreach( $radio_options as $option => $name ) {
      $fid = htmlspecialchars( $this -> group ) . '_' . htmlspecialchars( $groupID ) . '_' . $option;
      $fname = htmlspecialchars( $this -> group ) . '[' . htmlspecialchars( $groupID ) . ']';

      print '<input type="radio" name="' . $fname . '" id="' . $fid . '"' . ( $value === $option ? ' checked="checked"' : '' ) . ' value="' . $option . '"/><label for="' . $fid . '">' . $name  . '</label>' . ( !$noNewlines ? "<br />\n" : '  ' );
    }
    print "</p>\n";

  }

	/**
	 * _display_checkbox
	 *
	 * Display checkbox
	 *
	 * @param [type] $groupID
	 * @param [type] $value
	 * @param [type] $label
	 * @return void
	 */
  function _display_checkbox( $groupID, $value, $label ) {

    $fid = htmlspecialchars( $this -> group ) . '_' . htmlspecialchars( $groupID );
    $fname = htmlspecialchars( $this -> group ) . '[' . htmlspecialchars( $groupID ) . ']';

    print "\n<p><input type='checkbox' name='" . $fname . "' id='" . $fid . "'" . ( $value ? " checked='checked'" : '' ) . "/><label for='" . $fid . "'>" . $label  . "</label></p>\n";

  }

	/**
	 * _display_textarea
	 *
	 * Display textarea
	 *
	 * @param [type] $groupID
	 * @param [type] $value
	 * @param integer $mode
	 * @return void
	 */
  function _display_textarea( $groupID, $value, $mode = 0 ) {

    $fid = htmlspecialchars( $this -> group ) . '_' . htmlspecialchars( $groupID );
    $fname = htmlspecialchars( $this -> group ) . '[' . htmlspecialchars( $groupID ) . ']';

    print "\n<p><textarea type='textarea' name='" . $fname . "' id='" . $fid . "rows='7' cols='50'>" . htmlspecialchars( $value ) . "</textarea></p>\n";
  }

	/**
	 * _display_datepicker
	 *
	 *  Display textfield
	 *
	 * @param [type] $groupID
	 * @param [type] $value
	 * @param [type] $size
	 * @param integer $mode
	 * @return void
	 */
  function _display_datepicker( $groupID, $value, $size, $mode = 0 ) {

    $fid = htmlspecialchars( $this -> group ) . '_' . htmlspecialchars( $groupID );
    $fname = htmlspecialchars( $this -> group ) . '[' . htmlspecialchars( $groupID ) . ']';

    print "\n<p><input type='date' name='" . $fname . "' id='" . $fid . "' size='" . $size . "' maxlenbuh='" . $size . "' value='" . htmlspecialchars( $value ) . "'/></p>\n";
  }

	/**
	 * _display_option_enabled
	 *
	 * Display the privacy radio buttons
	 *
	 * @return void
	 */
  function _display_option_enabled() {

    $currentValue = $this -> options['enabled'];
    $this -> _display_radio_buttons( 'enabled', $currentValue, array('N' => 'Renewals are disabled', 'Y' => 'Renewals are enabled') );

    print "<p class='description'>Disable the system to hide all messages from all users.  When enabling, for a new round of renewals, don't forget to reset the renewal data.</p>\n";

  }

	/**
	 * _display_option_renewaltext
	 *
	 * Display the visible renewal text
	 *
	 * @return void
	 */
  function _display_option_renewaltext() {

    $currentValue = $this -> options['bu_renewal_text'];
    $this -> _display_textarea( 'bu_renewal_text', $currentValue );
		print "<p class='description'>Provide boilerplate text for the renewal prompts.</p>";
  }

	/**
	 * _display_option_end_date
	 *
	 * Display the visible end date for renewals Field
	 *
	 * @return void
	 */
  function _display_option_end_date() {
    $end_date = $this -> options['end_date'];
    $this -> _display_datepicker( 'end_date', $end_date, 60 );
	

    /* INCREMENTING THE RENEWAL DATE
    $annual = strtotime($end_date . ' + 1 Year');
    // echo date('m/d/Y', $annual);

    if ( strtotime("now") == $annual )
    {
      options['end_date'] = $annual;
      $end_date = $this -> options['end_date'];
      $annual = strtotime($end_date . ' + 1 Year');
    }
    */
  }

	/**
	 * _display_option_reset_button
	 *
	 * Throw caution into the wind, and destroy all things.
	 *
	 * @return void
	 */
  function _display_option_reset_button() {
        print "<p><input class='button-primary' type='button' value='Reset Renewal Data' onClick=\"\n";
        print "if (confirm('Are you sure you want to reset all renewal data? (This operation cannot be undone!)')) {\n";
        print "document.location='?page=bu-renewals-config&amp;bu-renewal-reset=YES';\n";
        print "}\"></p>\n";
  }

	#---------- END OPTIONS DEFINITIONS ----------#

  ## Add a network options page for this plugin.
  function add_networksettings_page() {
    add_submenu_page( 'settings.php', $this -> title, $this -> title, 'manage_options', $this -> page, array(&$this, '_display_networksettings_page') );
  }

  ## Display the options for this plugin.
  function _display_networksettings_page() {

    $this -> _display_settings_page();

  }

  function _display_settings_page() {

    if ( !current_user_can('manage_options') ) {
      wp_die( __('You do not have sufficient permissions to access this page.') );
    }

    if ( $this -> buNetworkSaveError != '' ) {
      print $this -> buNetworkSaveError;
    }

    print "<div class='wrap'>\n";
    print "<h1>Site Renewal Settings</h1>\n";
    print "<form action='' method='post'>\n";

    #-- Internal WP Fields
    settings_fields( $this -> group );
    print "\n";

    #-- The actual options settings that users can see and change
    do_settings_sections( $this -> page );
    print "\n";

    print "<p class='submit'>\n";
    print "<input type='submit' name='Submit' class='button-primary' value=\"";
    esc_attr_e( 'Save Changes' );
    print "\" />\n";
    print "</p>\n";
    print "</form>\n";
    print "</div>\n";
  }
}
?>