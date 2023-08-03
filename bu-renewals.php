<?php

/*
Plugin Name: BU Site Renewal Service
Version: 1.2
Description: Provides mechanism for having site owners renew their sites on a periodic basis.
Author: Professional Web Presence
Network: true
*/

require_once( __DIR__ . DIRECTORY_SEPARATOR . 'bu-renewals-settings-page.php' );
require_once( __DIR__ . DIRECTORY_SEPARATOR . 'bu-renewals-dashboard.php' );

/**
 * bu_renewals_add_dashboard_widget()
 *
 * Current, site-specific dashboard.
 *
 * @return void
 */
function bu_renewals_add_dashboard_widget() { // for each blog in blogs; USED IN bu_renewals_init()
	$blogID = get_current_blog_id();
	bu_renewals_traffic_cop($blogID, "widget");
}

/**
 * bu_renewals_content()
 *
 * The meat of any renewal request.
 *
 * @return void
 */
function bu_renewals_content( $blogID ) { // SITE DASHBOARD RENEWAL CONFIRM/DENY VIEW FOR ADMINS/DETERMINED USERS; SC#3

	$option = 'bu_' . $blogID . '_site_renewed';
	$renewed = get_site_option( $option, '' );
	$currentDate = strtotime( date('F d, Y') );
	
	if ( $renewed ) {
		bu_renewals_content_confirmed($blogID);

	}  
	else {
		$options = get_network_option( 1, 'bu_renewals_config', array('enabled' => 'N', 'end_date' => '') );
		$selectedDate = date_create( $options['end_date'] );
		$end_date = date_format( $selectedDate,"F d, Y" );
		$renewal = strtotime( $end_date );

		if ( $renewal < $currentDate ){ //Checks if the renewal date has passed
			print "<p>The renewal deadline of <strong>" . $end_date. "</strong> has passed. Your site will be archived for 90 days and then deleted. Please contact the Butler IT Help Desk for retrieval or any other inquires.</p>\n";
		}
		else {
			if ( $options['bu_renewal_text'] ){
				print "<p>" . $options['bu_renewal_text'] . "</p>";
			}

			print "<p>You have until <strong>" . $end_date. "</strong> to renew the website <i>'" . get_bloginfo('name') . "'</i>. Non-renewed websites will be archived for 90 days and then deleted.</p>\n";
			print "<p style=\"text-align: center;\">\n";
			print "  <a href=\"?bu-renew-site=Y\" class=\"bu-button-green\">Renew " . get_bloginfo('name') . "</a>\n";
			print "  <a href=\"ms-delete-site.php\" class=\"bu-button-red\">Delete " . get_bloginfo('name') . "</a>\n";
		print "</p>\n";
		}
	}
}


/**
 * bu_renewals_content_nonadmin()
 *
 * Renewal requests for non-admins.
 *
 * @return void
 */
function bu_renewals_content_nonadmin( $blogID ) { // SITE DASHBOARD RENEWAL CONFIRM/DENY VIEW FOR NON-ADMINS

	$option = 'bu_' . $blogID . '_site_renewed';
	$renewed = get_site_option( $option, '' );

	if ( $renewed ) {
		bu_renewals_content_confirmed( $blogID );
	}

	else {
		$options = get_network_option( 1, 'bu_renewals_config', array('enabled' => 'N', 'end_date' => '') );

		if ( $options['bu_renewal_text'] ){
			print "<p>" . $options['bu_renewal_text'] . "</p>";
		}

		$selectedDate = date_create( $options['end_date'] );
		$end_date = date_format( $selectedDate,"F d, Y" );

		print "<p>Renewal of this website, <i>'" . get_bloginfo( 'name' ) . "'</i>, is due on <strong>" . $options['end_date'] . "</strong>. Non-renewed websites will be archived for 90 days and deleted.</p>\n";
		print "<h4>Site Administators</h4>\n";
		print "<p>Please contact one of the website administrators listed below to complete the renewal process.</p>\n";

		$blogID = get_current_blog_id();
		$userAdmin = get_users( array('blog_id'=> $blogID, 'role' => 'administrator') );
		if ( $userAdmin ){
			foreach( $userAdmin as $user ){
				print "<p><a href=mailto:'" . $user->user_email . "'>" . $user->display_name . "</a></p>\n";
				// $to = $user->user_email;
				// $subject = "It is time to renew this WP website! ";
				// $message = "You have until " . $end_date. " to renew the website " . get_bloginfo('name') . ". Non-renewed websites will be archived for 90 days and deleted.";
				// $headers = get_bloginfo('name');
				// wp_mail( $to, $subject, $message, $headers );
			}
		}
	  print "\n";
	}
}

/**
 * bu_renewals_content_confirmed()
 *
 * Confirmed messsage.
 *
 * @return void
 */
function bu_renewals_content_confirmed( $blogID ) { // USED IN bu_renewals_content; POST-RENEWAL, CONFIRMATION DASHBOARD WIDGET; SC#4

	if ( $blogID == "" ){
		// why do i have to do this? WordPress widgets are a mysterious beast.
		$blogID = get_current_blog_id();
	}

	global $wpdb;

	$option = 'bu_' . $blogID . '_site_renewed';
	$renewed = get_site_option( $option, '' );

	if ( $renewal = $wpdb->get_results( "SELECT meta_value FROM wp_sitemeta WHERE meta_key LIKE 'bu_". $blogID . "_site_renewed'" ) ) {
		$details = explode( '|', $renewal[0] -> meta_value );

		if ( $user = $wpdb->get_results( "SELECT display_name FROM wp_users WHERE user_login LIKE '" . $details[2] . "'" ) ) {
      $userName = $user[0] -> display_name;
		}

		$rawDate = strtotime( $details[1] );

		print "<p style='font-size: 120%; font-weight: 800;'>Thank you for renewing your website!</p>\n";
    print "<p style='font-size: 100%;'>  Your website was renewed on <strong>" .  date( 'M j, Y', $rawDate ) . "</strong> by <strong>" . ( isset($userName) ? $userName : $details[2] ) . "</strong>.</p>";

	}
	else {
		print "<p class='notice notice-error'>Error: Unable to find website renewal details!</p>\n";
	}
}

function bu_renewals_site_archive( $blogID ) { // USED IN bu_renewals_content; POST-RENEWAL, CONFIRMATION DASHBOARD WIDGET; SC#4
	$options = get_network_option( 1, 'bu_renewals_config', array('enabled' => 'N', 'end_date' => '') );
	$selectedDate = date_create( $options['end_date'] );
	$end_date = date_format( $selectedDate,"F d, Y" );

	if ( $blogID == "" ){
		// why do i have to do this? WordPress widgets are a mysterious beast.
		$blogID = get_current_blog_id();
	}

	global $wpdb;

	$option = 'bu_' . $blogID . '_archived';
	$renewed = get_site_option( $option, '' );

	if ( $renewal = $wpdb->get_results( "SELECT meta_value FROM wp_sitemeta WHERE meta_key LIKE 'bu_". $blogID . "_archived'" ) ) {
		$details = explode( '|', $renewal[0] -> meta_value );

		if ( $user = $wpdb->get_results( "SELECT display_name FROM wp_users WHERE user_login LIKE '" . $details[2] . "'" ) ) {
      $userName = $user[0] -> display_name;
		}

		$rawDate = strtotime( $details[1] );
	}

	print "<p>The renewal deadline of <strong>" . $end_date. "</strong> has passed. Your site will be archived for 90 days and then deleted. Please contact the Butler IT Help Desk for retrieval or any other inquires.</p>\n";
}


/**
 * bu_renewals_traffic_cop
 *
 * Redirects renewal traffic to admin, non-admin views.
 *
 * @param [type] $blogID
 * @return void
 */
function bu_renewals_traffic_cop( $blogID, $type ){ // USED IN bu_renewals_add_dashboard_widget()
	// if blogID=1, get out.
	if ( $blogID == 1 ){
		return;
	}

	$flag = 0;
	$currentUser = wp_get_current_user();

	// method derived from https://wordpress.stackexchange.com/a/131816
	$userClean = get_users( array('blog_id'=> $blogID, 'include' => array($currentUser->ID)) );
	$user = $userClean[0];
	$allowed_roles = array( 'administrator' );

	$option = 'bu_' . $blogID . '_site_renewed';
	$renewed = get_site_option( $option, '' );

	// if admin exists
	if ( array_intersect($allowed_roles, $user->roles) ) {
		if ( $type == "widget" ){
			$flag = 1;

			$options = get_network_option( 1, 'bu_renewals_config', array('enabled' => 'N', 'end_date' => '') );
			$selectedDate = date_create( $options['end_date'] );
			$end_date = date_format( $selectedDate,"F d, Y" );
			$renewal = strtotime( $end_date );
			$currentDate = strtotime( date('F d, Y') );

			$archived = get_blog_status( $blogID, 'archived');

			if ( $renewal < $currentDate ) {
				// If renewal date has passed

				// $user = wp_get_current_user();
				// update_site_option( $option, $blogID . '|' . date('Y-m-d H:i:s') . "|" . $user -> user_login );
				wp_add_dashboard_widget( 'bu_renewals', "Renewal date for '" . get_bloginfo('name') . "' has passed.", 'bu_renewals_site_archive', '', $blogID );
				
				//updates every refresh
				if ( get_blog_status( $blogID, 'archived' ) !== 1 ) {
					update_blog_status( $blogID, 'archived', 1 ); //last updated
					$archival_date = shell_exec("wp site list --field=last_updated --site__in=$blogID");
				}
				// echo "<pre> -------------------------".$archival_date."</pre>";
			}

			else if ( (!$renewed && !isset($_REQUEST['bu-renew-site'])) && ($archived == 0) ) { //if it needs renewed and isn't already archived
				// Renewal widget.
				wp_add_dashboard_widget( 'bu_renewals', "Please Renew '" . get_bloginfo('name') . "'", 'bu_renewals_content', '', $blogID );
			}

			else if ( ($renewed == '') && isset($_REQUEST['bu-renew-site']) && ($_REQUEST['bu-renew-site'] == 'Y') && ($archived == 0) ) {
				// For when renewal is first submitted - save and display a thank you message
				$user = wp_get_current_user();
				update_site_option( $option, $blogID . '|' . date('Y-m-d H:i:s') . "|" . $user -> user_login );
				wp_add_dashboard_widget( 'bu_renewals', 'Website Renewal Confirmed', 'bu_renewals_content_confirmed', '', $blogID );
			}

			else if ( $renewed ) {
				// If renewal was previously submitted, show that it's already in the system
				wp_add_dashboard_widget( 'bu_renewals', 'Website Renewal Confirmed', 'bu_renewals_content_confirmed' );
			}
			else {

			}
		}
		else if ( $type == "sites" ) {

			if ( $renewed ) {
				// If renewal was previously submitted, show that it's already in the system
				bu_renewals_confirm_my_sites();
			}
			else {
				bu_renewals_deny_my_sites();
			}
		}

		else {

		}
	}

	// non-admin operation
	else {

		if ( $type == "widget" ){
			wp_add_dashboard_widget( 'bu_renewals', "Renewal Required for '" . get_bloginfo('name') . "'", 'bu_renewals_content_nonadmin', '', $blogID );
			$flag = 1;
		}
		elseif ( $type == "sites" ) {
			if ( $renewed ) {
				// If renewal was previously submitted, show that it's already in the system
				bu_renewals_confirm_my_sites();
			}
			else {
				bu_renewals_deny_my_sites();
			}
		}
		$flag = 1;
	}

	if( $type == "widget") {
		if ( $flag ) {

			// Globalize the metaboxes array, this holds all the widgets for wp-admin
			global $wp_meta_boxes;

			// Get the regular dashboard widgets array
			// (which has our new widget already but at the end)

			$normal_dashboard = $wp_meta_boxes['dashboard']['normal']['core'];

			// Backup and delete our new dashboard widget from the end of the array

			$example_widget_backup = array( 'bu_renewals' => $normal_dashboard['bu_renewals'] );
			unset( $normal_dashboard['bu_renewals'] );

			// Merge the two arrays together so our widget is at the beginning

			$sorted_dashboard = array_merge( $example_widget_backup, $normal_dashboard );

			// Save the sorted array back into the original metaboxes

			$wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;
		}
	}
}

function bu_renewals_confirm_my_sites(){
	print "<p class='notice notice-success'>Website renewal confirmed!</p>\n";

}

function bu_renewals_deny_my_sites(){
	print "<p class='notice notice-error'>Website has not been renewed.</p>\n";
	
}

/**
 * function bu_renewals_widget_my_sites
 *
 * Processes a renewal form on My Sites
 *
 * @param [type] $actions
 * @param [type] $user_blog
 * @return void
 */
function bu_renewals_widget_my_sites( $actions, $user_blog ) { // USED IN bu_renewals_init()
	$blogID = $user_blog->userblog_id;
	bu_renewals_traffic_cop( $blogID, "sites" );
	return $actions;
}

/**
 * function bu_renewals_new_site
 *
 * Processes the renewal for a site created while active.
 *
 * @param [type] $blog_id
 * @param [type] $user_id
 * @param [type] $domain
 * @param [type] $path
 * @param [type] $site_id
 * @param [type] $meta
 * @return void
 */
function bu_renewals_new_site ( $blog_id, $user_id, $domain, $path, $site_id, $meta ) { // USED IN bu_renewals_init()

	// getting blog details from arguments + calls
	$blogID = $blog_id;
	$option = 'bu_' . $blogID . '_site_renewed';
	$user = wp_get_current_user();

	//renewal function //
	update_site_option( $option, $blogID . '|' . date('Y-m-d H:i:s') . "|" . $user->user_login );

}

/**
 * bu_renewals_css_enqueue
 *
 * Queue in the CSS, Batman!
 *
 * @return void
 */
function bu_renewals_css_enqueue() { // USED IN bu_renewals_init()
  wp_enqueue_style( 'bu_renewals_css', plugins_url('bu-renewals.css', __FILE__) );
}

/**
 * bu_renewals_init()
 *
 * Init function.
 *
 * @return void
 */
function bu_renewals_init() {
  $options = get_network_option( 1, 'bu_renewals_config', array('enabled' => 'N', 'end_date' => '') );

  new bu_renewals_optionspage( 'bu_renewals_config', 'bu-renewals-config', $options );

  add_action( 'admin_enqueue_scripts', 'bu_renewals_css_enqueue' );

  if ( $options['enabled'] == 'Y' ) {
		add_action( 'wp_dashboard_setup', 'bu_renewals_add_dashboard_widget' );
		add_filter( 'myblogs_blog_actions', 'bu_renewals_widget_my_sites', 10, 2 );
  }

	add_action( 'wp_network_dashboard_setup', 'bu_renewals_add_network_dashboard_widget' );

	// conditional on whether new site config is called.
	$mu_options = get_network_option( 1, 'bu_renewals_config', array('enabled' => 'N', 'end_date' => '') );

	if ( $mu_options['enabled'] == 'Y' && date("Y-m-d H:i:s",strtotime($mu_options['end_date'])) >= date("Y-m-d H:i:s") ) {
		add_action( 'wpmu_new_blog', 'bu_renewals_new_site' );
	}
}

bu_renewals_init();

?>
