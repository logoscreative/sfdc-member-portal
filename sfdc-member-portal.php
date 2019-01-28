<?php
/**
 * Plugin Name: SFDC Web portal
 * Plugin URI: http://www.focus-ga.org/
 * Description: Cloudland Technologies - FOCUS Member Portal
 * Version: 1.2
 * Author: Paul Cannon
 * Author URI: http://www.cloudlandtechnologies.com
 */

// Enqueue Dashicons for calendar icon
add_action( 'wp_enqueue_scripts', 'load_dashicons_front_end' );

function load_dashicons_front_end() {
	wp_enqueue_style( 'dashicons' );
}

// Add Programs shortcode [focus_programs]
add_shortcode( 'focus_programs', 'wp_focus_program' );

function wp_focus_program() {

	// Do not render shortcode in the admin area
	if ( is_admin() ) {
		return;
	}

	$pluginsUrl = plugin_dir_path( __FILE__ );

	$currentUser = wp_get_current_user();
	$userEmail = $currentUser->user_email;

	// Allow debugging
	if ( isset($_GET['sfdc_user_email']) && $_GET['sfdc_user_email'] ) {
		$userEmail = $_GET['sfdc_user_email'];
	}

	$storedUsername = '';
	if ( defined('SFDC_MEMBER_PORTAL_USERNAME')) {
		$storedUsername = SFDC_MEMBER_PORTAL_USERNAME;
	}

	$storedPassword = '';
	if ( defined('SFDC_MEMBER_PORTAL_PASSWORD')) {
		$storedPassword = SFDC_MEMBER_PORTAL_PASSWORD;
	}

	$storedSecurityToken = '';
	if ( defined('SFDC_MEMBER_PORTAL_SECURITY_TOKEN')) {
		$storedSecurityToken = SFDC_MEMBER_PORTAL_SECURITY_TOKEN;
	}

	require_once ($pluginsUrl . 'soapclient/SforcePartnerClient.php');

	$mySforceConnection = new SforcePartnerClient();
	$mySforceConnection->createConnection($pluginsUrl . "PartnerWSDL.xml");
	$mySforceConnection->login($storedUsername, $storedPassword.$storedSecurityToken);

	$query_user_info = "select id, Name, accountid from contact where Contact.email = '" . $userEmail . "'";
	$response_user_info = $mySforceConnection->query($query_user_info);
	$siteURL = get_site_url();

	//if respective contact found at SF then only show programs
	 if ( count( $response_user_info->records ) > 0 ) {
		$contactid = $response_user_info->records[0]->Id;
		$accountid = $response_user_info->records[0]->fields->AccountId;
		$currentContactName = $response_user_info->records[0]->fields->Name;

		$query_programs_signedup = "select Contact.Name, Contact.Id, Campaign.name, Campaign.StartDate, Campaign.Type from campaignmember where contactid in (select Contact.id from Contact where Contact.accountid = '".$accountid."') and Campaign.isactive=true and Campaign.StartDate > TODAY";
		$response_programs_signedup = $mySforceConnection->query($query_programs_signedup);


		$query_programs_scheduled = "select Id, Name, StartDate, Registration_Fee__c, isactive, Type, RecordTypeId, ID__c from Campaign where isactive=true and startdate > TODAY and startdate = NEXT_N_DAYS:90";
		$response_programs_scheduled = $mySforceConnection->query($query_programs_scheduled);

		$content = '';

		$content .=	'<h2>My Upcoming Programs</h2>
			<table>
				<tr>
					<th></th>
					<th>Name</th>
					<th>Program</th>
					<th>Date</th>
				</tr>';

			foreach ($response_programs_signedup->records as $record_signedup) {
				$content .=
					'<tr>
						<td><span class="dashicons dashicons-calendar-alt"></span></td>
						<td>'.$record_signedup->fields->Contact->fields->Name.'</td>
						<td>'.$record_signedup->fields->Campaign->fields->Name.'</td>
						<td>'.$record_signedup->fields->Campaign->fields->StartDate.'</td>
					</tr>';
			}

		$content .= '</table>';

		$content .=	'<h2>Featured Programs</h2>
			<table>
				<tr>
					<th></th>
					<th>Program</th>
					<th>Date</th>
					<th>Cost</th>
					<th>Sign Up</th>
				</tr>';

			foreach ($response_programs_scheduled->records as $record_scheduled) {

				$addtolist = true;
				// This section is to remove the existing events users has signed up for.   This likely needs to change to an marker instead.
				/*foreach ($response_programs_signedup->records as $record_signedup) {
					if ($record_signedup->fields->Campaign->fields->Name == $record_scheduled->fields->Name) {
						$addtolist = false;
					}
				} */

				if ($addtolist) {
					$content .=
						'<tr>
							<td><span class="dashicons dashicons-calendar-alt"></span></td>
							<td>'.$record_scheduled->fields->Name.'</td>
							<td>'.$record_scheduled->fields->StartDate.'</td>
							<td>'.$record_scheduled->fields->Registration_Fee__c.'</td>
							<td><a href="'.$siteURL.'/campaign?cmpid='.$record_scheduled->Id.'">More Details</a></td>
					</tr>';
				}
			}

		$content .= '</table>';
		echo $content;

	} else {
		//if not respective contact as WP user found at SF then display message
		echo '<p>We can not find your record at FOCUS. Please call administrator for more details</p>';
	} 
}

// Enqueue modal scripts and styles
add_action( 'wp_enqueue_scripts', 'load_jquery_modal' );


function load_jquery_modal() {
	if ( is_page('campaign') ) {

		wp_enqueue_script(
			'jQuery-modal',
			'https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.js',
			array('jquery'),
			false,
			true
		);

		wp_enqueue_style(
			'jQuery-modal',
			'https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.css'
		);

	}
} 

// Add Campaigns shortcode [focus_campaign]
 add_shortcode( 'focus_campaign', 'render_focus_campaign_landing_page' );

function render_focus_campaign_landing_page() {

	// Do not render shortcode in the admin area
	if ( is_admin() ) {
		return;
	}

	if ( ( isset($_GET['formid']) && $_GET['formid'] ) && shortcode_exists('formassembly') ) {

		$formId = $_GET['formid'];
		echo do_shortcode( '[formassembly formid=' . $formId . ']' );

	} elseif( isset( $_GET['cmpid'] ) && $_GET['cntid'] ) {

		$pluginsUrl = plugin_dir_path( __FILE__ );

		$currentUser = wp_get_current_user();
		$userEmail = $currentUser->user_email;

		// Allow debugging
		if ( isset($_GET['sfdc_user_email']) && $_GET['sfdc_user_email'] ) {
			$userEmail = $_GET['sfdc_user_email'];
		}

		$storedUsername = '';
		if ( defined('SFDC_MEMBER_PORTAL_USERNAME')) {
			$storedUsername = SFDC_MEMBER_PORTAL_USERNAME;
		}

		$storedPassword = '';
		if ( defined('SFDC_MEMBER_PORTAL_PASSWORD')) {
			$storedPassword = SFDC_MEMBER_PORTAL_PASSWORD;
		}

		$storedSecurityToken = '';
		if ( defined('SFDC_MEMBER_PORTAL_SECURITY_TOKEN')) {
			$storedSecurityToken = SFDC_MEMBER_PORTAL_SECURITY_TOKEN;
		}

		require_once ($pluginsUrl . 'soapclient/SforcePartnerClient.php');

		$mySforceConnection = new SforcePartnerClient();
		$mySforceConnection->createConnection($pluginsUrl . "PartnerWSDL.xml");
		$mySforceConnection->login($storedUsername, $storedPassword.$storedSecurityToken);

		$query_user_info = "select id, Name, accountid from contact where Contact.email = '".$userEmail."'";
		$response_user_info = $mySforceConnection->query($query_user_info);

		if( count( $response_user_info->records ) > 0 ) {

			$contactid          = $response_user_info->records[0]->Id;
			$accountid          = $response_user_info->records[0]->fields->AccountId;
			$currentContactName = $response_user_info->records[0]->fields->Name;

			$query_currentaccount_contacts    = "select Id, ID__c, Name from Contact where AccountId = '" . $accountid . "'";
			$response_currentaccount_contacts = $mySforceConnection->query( $query_currentaccount_contacts );

			$query_campaigndetails    = "select Id, ID__c, Name, Description, StartDate, EndDate, Registration_Fee__c, isactive, Type from Campaign where ID='" . $_GET['cmpid'] . "'";
			$response_campaigndetails = $mySforceConnection->query( $query_campaigndetails );

			//Fetch mapping of form and campaign type from SF custom object "Program Forms"
			$query_form_campaign_mapping    = "select Name, Form_Number__c, Individual_Request__c from Program_Forms__c";
			$response_form_campaign_mapping = $mySforceConnection->query( $query_form_campaign_mapping );
			$formCampaignMapping            = array();
			foreach ( $response_form_campaign_mapping->records as $record_mapping ) {
				$programFormRecord = (object) [
					"formNumber"          => $record_mapping->fields->Form_Number__c,
					"isIndividualRequest" => $record_mapping->fields->Individual_Request__c
				];
				//$formCampaignMapping[ $record_mapping->fields->Name ] = $record_mapping->fields->Form_Number__c;
				$formCampaignMapping[ $record_mapping->fields->Name ] = $programFormRecord;
			}

			$siteURL = get_site_url();

			if ( count( $response_campaigndetails->records ) > 0 ) {

				$campaigndetails = $response_campaigndetails->records[0];

				$content = '';

				$content .= '<h2>' . $campaigndetails->fields->Name . '</h2>';
				$content .= '<p>' . $campaigndetails->fields->Description . '</p>';

				$content .= '
					<table>
						<tr>
							<th>Start Date</th>
							<th>End Date</th>
							<th>Campaign Type</th>
							<th>Registration Fee</th>
						</tr>';

				$content .=
					'<tr>
						<td>' . $campaigndetails->fields->StartDate . '</td>
						<td>' . $campaigndetails->fields->EndDate . '</td>
						<td>' . $campaigndetails->fields->Type . '</td>
						<td>' . $campaigndetails->fields->Registration_Fee__c . '</td>
					</tr>';

				$content .= '</table>';

				if ( $formCampaignMapping[ $campaigndetails->fields->Type ] ) {

					if ( $formCampaignMapping[ $campaigndetails->fields->Type ]->isIndividualRequest == 'true' ) {

						//show modal
						$content .= '<p><a class="button" href="#ex1" rel="modal:open">Sign Up</a></p>';

						$content .= '
						<div id="ex1" class="modal">
							<h4>Select Member</h4>
							<ul>';

						foreach ($response_currentaccount_contacts->records as $record_contact) {

							$content .= '<li><a href="' . $siteURL . '/campaign/?cntid=' . $record_contact->fields->ID__c . '&cmpid=' . $campaigndetails->fields->ID__c . '&formid=' . $formCampaignMapping[ $campaigndetails->fields->Type ]->formNumber . '">' . $record_contact->fields->Name . '</a></li>';
						}

						$content .= '</ul>';

					} else {

						$content .= '<p><a class="button" href="' . $siteURL . '/campaign?cmpid=' . $campaigndetails->fields->ID__c . '&cntid=' . $contactid . '&formid=' . $formCampaignMapping[ $campaigndetails->fields->Type ]->formNumber . '">Sign Up</a></p>';

					}

				}

				echo $content;

			}

		}

	} else {

		echo '<p>No campaign selected.</p>';

	}
} 


// Add Volunteer Jobs [focus_volunteers]
add_shortcode( 'focus_volunteers', 'render_focus_volunteer_landing_page' );

function render_focus_volunteer_landing_page() {

	$siteURL = get_site_url();
	// Do not render shortcode in the admin area
	if ( is_admin() ) {
		return;
	}

	$pluginsUrl = plugin_dir_path( __FILE__ );

	$currentUser = wp_get_current_user();
	$userEmail = $currentUser->user_email;

	// Allow debugging
	if ( isset($_GET['sfdc_user_email']) && $_GET['sfdc_user_email'] ) {
		$userEmail = $_GET['sfdc_user_email'];
	}

	$storedUsername = '';
	if ( defined('SFDC_MEMBER_PORTAL_USERNAME')) {
		$storedUsername = SFDC_MEMBER_PORTAL_USERNAME;
	}

	$storedPassword = '';
	if ( defined('SFDC_MEMBER_PORTAL_PASSWORD')) {
		$storedPassword = SFDC_MEMBER_PORTAL_PASSWORD;
	}

	$storedSecurityToken = '';
	if ( defined('SFDC_MEMBER_PORTAL_SECURITY_TOKEN')) {
		$storedSecurityToken = SFDC_MEMBER_PORTAL_SECURITY_TOKEN;
	}

	require_once ($pluginsUrl . 'soapclient/SforcePartnerClient.php');

	$mySforceConnection = new SforcePartnerClient();
	$mySforceConnection->createConnection($pluginsUrl . "PartnerWSDL.xml");
	$mySforceConnection->login($storedUsername, $storedPassword.$storedSecurityToken);

	$query_user_info = "select id, Name, accountid from contact where Contact.email = '".$userEmail."'";
	$response_user_info = $mySforceConnection->query($query_user_info);

	if( count( $response_user_info->records ) > 0 ) {
		$contactid          = $response_user_info->records[0]->Id;
		$accountid          = $response_user_info->records[0]->fields->AccountId;
		$currentContactName = $response_user_info->records[0]->fields->Name;

		if ( isset($_GET['jobid']) && $_GET['jobid'] ) { //detail page

			$query_opportunity = "select Id, Name, Amount, CloseDate from Opportunity where Id ='".$_GET['jobid']."'";
			$response_opportunity = $mySforceConnection->query( $query_opportunity );
			
			if( count( $response_opportunity->records ) > 0 ) { 
				$opportunity_record = $response_opportunity->records[0];
				?>
				<p><b>Name : </b> <?php echo $opportunity_record->fields->Name; ?></p>
				<p><b>Amount : </b> <?php echo $opportunity_record->fields->Amount; ?></p>
				<p><b>Close Date : </b> <?php $opportunity_record->fields->CloseDate; ?></p>
			<?php
			} else { ?>
				<p>No Job Found</p>
			<?php
			}
		} else { //display list of volunteer jobs

			$query_opportunity_recordtype = "select Id from RecordType where Name ='Membership'";
			$response_opportunity_recordtype = $mySforceConnection->query( $query_opportunity_recordtype );

			if( count( $response_opportunity_recordtype->records ) > 0 ) {

				$recordTypeId = $response_opportunity_recordtype->records[0]->Id;

				$query_opportunites = "select Id, Name from Opportunity where RecordTypeId = '".$recordTypeId."'";
				$response_opportunities = $mySforceConnection->query( $query_opportunites );
				if( count( $response_opportunities->records ) > 0 ) {
					echo '<ul>';
					foreach ( $response_opportunities->records as $record_opportunity ) {
						echo '<li><a href="'.$siteURL.'/volunteer-jobs/?jobid='.$record_opportunity->Id.'">'.$record_opportunity->fields->Name.'</a></li>';
					}
					echo '</ul>';
				}				
			}
			
		}

	} else {
		echo '<p>We can not find your record at FOCUS. Please call administrator for more details</p>';
	}

	 
}
