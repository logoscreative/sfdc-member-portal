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

	$connectionData = connectWPtoSFandGetUserInfo();
	$response_user_info = $connectionData->response_user_info;
	$mySforceConnection = $connectionData->SforceConnectionToken;
	$contactid = $connectionData->currentContactId;
	$accountid = $connectionData->currentAccountId;

	$siteURL = get_site_url();

	$query_programs_signedup = "select Contact.Name, Contact.Id, Campaign.name, Campaign.StartDate, Campaign.Type from campaignmember where contactid in (select Contact.id from Contact where Contact.accountid = '".$accountid."') and Campaign.isActive=true and Campaign.StartDate > TODAY";
	//$response_programs_signedup = $mySforceConnection->query($query_programs_signedup);

	$query_programs_scheduled = "select Id, Name, StartDate, Registration_Fee__c, isActive, Type, RecordTypeId from Campaign where isActive=true and Featured__c=true";
	//$response_programs_scheduled = $mySforceConnection->query($query_programs_scheduled);

	$querySuccess = true;
	$content = '';

	try {
		$response_programs_signedup = $mySforceConnection->query($query_programs_signedup);
		$response_programs_scheduled = $mySforceConnection->query($query_programs_scheduled);
	} catch( Exception $e ) {
		echo 'Something went wrong :'.$e->getMessage();
		$querySuccess = false;
	}

	if( $querySuccess ) {		

		$content .=	'<h2>My Upcoming Programs</h2>';
		if( count( $response_programs_signedup->records ) == 0 ) {
			$content .=	'<p>No programs found</p>';
		} else {
			$content .=	'<table>
					<tr>
						<th></th>
						<th>Name</th>
						<th>Program</th>
						<th>Date</th>
					</tr>';

				foreach ($response_programs_signedup->records as $record_signedup) {
					$record_signedup = new SObject( $record_signedup );
					$content .=
						'<tr>
							<td><span class="dashicons dashicons-calendar-alt"></span></td>
							<td>'.$record_signedup->fields->Contact->fields->Name.'</td>
							<td>'.$record_signedup->fields->Campaign->fields->Name.'</td>
							<td>'.$record_signedup->fields->Campaign->fields->StartDate.'</td>
						</tr>';
				}

			$content .= '</table>';
		}

		$content .=	'<h2>Featured Programs</h2>';

		if( count( $response_programs_scheduled->records ) == 0 ) {

			$content .=	'<p>No programs found</p>';

		} else {

			$content .=	'<table>
				<tr>
					<th></th>
					<th>Program</th>
					<th>Date</th>
					<th>Cost</th>
					<th>Sign Up</th>
				</tr>';

			foreach ($response_programs_scheduled->records as $record_scheduled) {
				$record_scheduled = new SObject( $record_scheduled );
				$addtolist = true;
				foreach ($response_programs_signedup->records as $record_signedup) {
					if ($record_signedup->fields->Campaign->fields->Name == $record_scheduled->fields->Name) {
						$addtolist = false;
					}
				}

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
		}
	}
	echo $content;
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

	$connectionData = connectWPtoSFandGetUserInfo();
	$response_user_info = $connectionData->response_user_info;
	$mySforceConnection = $connectionData->SforceConnectionToken;
	$contactid = $connectionData->currentContactId;
	$accountid = $connectionData->currentAccountId;

	if ( ( isset($_GET['formid']) && $_GET['formid'] ) && shortcode_exists('formassembly') ) {

		$formId = $_GET['formid'];
		echo do_shortcode( '[formassembly formid=' . $formId . ']' );

	} elseif( isset( $_GET['cmpid'] ) && $_GET['cmpid'] ) {

		$query_currentaccount_contacts    = "select Id, Name from Contact where AccountId = '" . $accountid . "'";		

		$query_campaigndetails    = "select Id, Name, Description, StartDate, EndDate, Registration_Fee__c, isActive, Type from Campaign where ID='" . $_GET['cmpid'] . "'";		

		//Fetch mapping of form and campaign type from SF custom object "Program Forms"
		$query_form_campaign_mapping    = "select Name, Form_Number__c, Individual_Request__c from Program_Forms__c";

		$querySuccess = true;

		try {
			$response_currentaccount_contacts = $mySforceConnection->query( $query_currentaccount_contacts );
			$response_campaigndetails = $mySforceConnection->query( $query_campaigndetails );
			$response_form_campaign_mapping = $mySforceConnection->query( $query_form_campaign_mapping );
		} catch( Exception $e ) {
			echo 'Something went wrong :'.$e->getMessage();
			$querySuccess = false;			
		}
		if( $querySuccess ) {

			$formCampaignMapping            = array();

			foreach ( $response_form_campaign_mapping->records as $record_mapping ) {

				$record_mapping = new SObject( $record_mapping );
				$programFormRecord = (object) [
					"formNumber"          => $record_mapping->fields->Form_Number__c,
					"isIndividualRequest" => $record_mapping->fields->Individual_Request__c
				];
				//$formCampaignMapping[ $record_mapping->fields->Name ] = $record_mapping->fields->Form_Number__c;
				$formCampaignMapping[ $record_mapping->fields->Name ] = $programFormRecord;

			}

			$siteURL = get_site_url();

			if ( count( $response_campaigndetails->records ) > 0 ) {

				$campaigndetails = new SObject( $response_campaigndetails->records[0] );

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

							$record_contact = new SObject( $record_contact );

							$content .= '<li><a href="' . $siteURL . '/campaign/?cntid=' . $record_contact->Id . '&cmpid=' . $campaigndetails->Id . '&formid=' . $formCampaignMapping[ $campaigndetails->fields->Type ]->formNumber . '">' . $record_contact->fields->Name . '</a></li>';
						}

						$content .= '</ul>';

					} else {

						$content .= '<p><a class="button" href="' . $siteURL . '/campaign?cmpid=' . $campaigndetails->Id . '&cntid=' . $contactid . '&formid=' . $formCampaignMapping[ $campaigndetails->fields->Type ]->formNumber . '">Sign Up</a></p>';

					}

				}

				echo $content;

			} else {
				echo '<p>Campaign not found.</p>';
			}
		} 
	} else {

		echo '<p>No campaign selected.</p>';

	}
} 


// Add Volunteer Jobs [focus_volunteers]
add_shortcode( 'focus_volunteers', 'render_focus_volunteer_landing_page' );

function render_focus_volunteer_landing_page() {

	// Do not render shortcode in the admin area
	if ( is_admin() ) {
		return;
	}

	$connectionData = connectWPtoSFandGetUserInfo();
	$response_user_info = $connectionData->response_user_info;
	$mySforceConnection = $connectionData->SforceConnectionToken;
	$contactid = $connectionData->currentContactId;
	$accountid = $connectionData->currentAccountId;

	$siteURL = get_site_url();

	if ( ( isset($_GET['formid']) && $_GET['formid'] ) && shortcode_exists('formassembly') ) { //display form

		$formId = $_GET['formid'];
		echo do_shortcode( '[formassembly formid=' . $formId . ']' );

	} else { //display list of volunteer jobs
			
		$query_my_jobs = "select Id, Name, GW_Volunteers__Contact__r.Name, GW_Volunteers__Volunteer_Job__c, GW_Volunteers__Volunteer_Job__r.Name, GW_Volunteers__Start_Date__c, GW_Volunteers__Hours_Worked__c, GW_Volunteers__Volunteer_Job__r.GW_Volunteers__Campaign__c, GW_Volunteers__Volunteer_Job__r.GW_Volunteers__Campaign__r.Name from GW_Volunteers__Volunteer_Hours__c where GW_Volunteers__Contact__c='".$contactid."'";			


		$query_jobs = "select GW_Volunteers__Volunteer_Job__r.Id, GW_Volunteers__Volunteer_Job__r.GW_Volunteers__Campaign__r.Name, GW_Volunteers__Volunteer_Job__r.Name, GW_Volunteers__Volunteer_Job__r.GW_Volunteers__Description__c, GW_Volunteers__Start_Date_Time__c , GW_Volunteers__Duration__c from GW_Volunteers__Volunteer_Shift__c where GW_Volunteers__Volunteer_Job__r.GW_Volunteers__Display_on_Website__c = true AND GW_Volunteers__Start_Date_Time__c > TODAY and GW_Volunteers__Start_Date_Time__c = NEXT_N_DAYS:90";

		$querySuccess = true;

		try {			
			$response_myjobs = $mySforceConnection->query( $query_my_jobs ); 
			$response_jobs = $mySforceConnection->query( $query_jobs );
		} catch( Exception $e ) {
			echo 'Something went wrong :'.$e->getMessage();
			$querySuccess = false;
		}

		if( $querySuccess ) {
		?>

			<h2>My Jobs</h2>

			<?php
			if( count( $response_myjobs->records ) > 0 ) { ?>
				<table>
					<thead>
						<th></th>
						<th>Campaign Name</th>
						<th>Job Title</th>
						<th>Start Date</th>
						<th>Number of Hours</th>
					</thead>
					<tbody>
						<?php foreach ( $response_myjobs->records as $record_myjob ) { 
								$record_myjob = new SObject( $record_myjob );
							?>
							<tr>
								<td><span class="dashicons dashicons-calendar-alt"></td>
								<td>
									
									<?php echo $record_myjob->fields->GW_Volunteers__Volunteer_Job__r->fields->GW_Volunteers__Campaign__r->fields->Name ;?>
								</td>
								<td>
									<?php echo $record_myjob->fields->GW_Volunteers__Volunteer_Job__r->fields->Name ;?>				
								</td>
								<td><?php echo $record_myjob->fields->GW_Volunteers__Start_Date__c;?></td>
								<td><?php echo $record_myjob->fields->GW_Volunteers__Hours_Worked__c;?></td>								
							</tr>
						<?php
							} ?>
					</tbody>
				</table>
								
			<?php 
			} else {
				echo '<p>No jobs found</p>';
			} ?>

			<h2>Featured Jobs</h2>

			<?php
			if( count( $response_jobs->records ) > 0 ) { ?>
				<table>
					<thead>
						<th></th>
						<th>Campaign Name</th>
						<th>Job Title</th>
						<th>Start Date</th>
						<th>Duration</th>
						<th>Sign Up</th>
					</thead>
					<tbody>
						<?php foreach ( $response_jobs->records as $record_job ) { 
								$record_job = new SObject( $record_job );
							?>
							<tr>
								<td><span class="dashicons dashicons-calendar-alt"></td>
								<td>
									
									<?php echo $record_job->fields->GW_Volunteers__Volunteer_Job__r->fields->GW_Volunteers__Campaign__r->fields->Name;?>
								</td>
								<td>
									<?php echo $record_job->fields->GW_Volunteers__Volunteer_Job__r->fields->Name;?>				
								</td>
								<td><?php echo $record_job->fields->GW_Volunteers__Start_Date_Time__c;?></td>
								<td><?php echo $record_job->fields->GW_Volunteers__Duration__c;?></td>
								<td>
									<a class="button" href="<?php echo $siteURL . '/volunteer-jobs?jobid=' . $record_job->fields->GW_Volunteers__Volunteer_Job__r->Id . '&cntid=' . $contactid . '&formid=4719240'; ?>">Sign Up</a>
								</td>
							</tr>
						<?php
							} ?>
					</tbody>
				</table>
								
			<?php 
			} else {
				echo '<p>No jobs found</p>';
			} 
		} 
	} 
}

// Add Volunteer Jobs [focus_volunteers]
add_shortcode( 'focus_donations', 'render_focus_donation_landing_page' );

function render_focus_donation_landing_page() {

	// Do not render shortcode in the admin area
	if ( is_admin() ) {
		return;
	}

	$connectionData = connectWPtoSFandGetUserInfo();
	$response_user_info = $connectionData->response_user_info;
	$mySforceConnection = $connectionData->SforceConnectionToken;
	$contactid = $connectionData->currentContactId;
	$accountid = $connectionData->currentAccountId;

	$query_opportunity_recordtype = "select Id from RecordType where Name ='Donation' ";

	$querySuccess = true;
	try {
		$response_opportunity_recordtype = $mySforceConnection->query( $query_opportunity_recordtype );
	} catch( Exception $e ) {
		echo 'Something went wrong :'.$e->getMessage();
		$querySuccess = false;
	}
	if( $querySuccess ) {

		if( count( $response_opportunity_recordtype->records ) > 0 ) {
			$recordTypeRec = new SObject( $response_opportunity_recordtype->records[0] );
			$recordTypeId = $recordTypeRec->Id;

			$query_opportunites = "select Id, Name, Amount, Campaign.Name, npsp__Primary_Contact__r.Name, CloseDate from Opportunity where RecordTypeId = '".$recordTypeId."' 
			and AccountId = '".$accountid."'";
			$querySuccessInner = true;
			try {
				$response_opportunities = $mySforceConnection->query( $query_opportunites ); 
			} catch( Exception $e ) {
				echo 'Something went wrong :'.$e->getMessage();
				$querySuccessInner = false;
			}
			if( $querySuccessInner ) {
				if( count( $response_opportunities->records ) > 0 ) { ?>

					<table>
						<thead>
							<tr>
								<th>Contact Name</th>
								<th>Date</th>
								<th>Campaign</th>
								<th>Amount</th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $response_opportunities->records as $record_opportunity ) { 
								$record_opportunity = new SObject( $record_opportunity );
								?>
								<tr>
									<td> <?php echo $record_opportunity->fields->npsp__Primary_Contact__r->fields->Name; ?> </td>
									<td> <?php echo $record_opportunity->fields->CloseDate; ?> </td>
									<td> <?php echo ( $record_opportunity->fields->Campaign->fields->Name ) ? $record_opportunity->fields->Campaign->fields->Name : 'General' ; ?> </td>
									<td> <?php echo $record_opportunity->fields->Amount; ?> </td>
								</tr>
							<?php
							} ?>						
						</tbody>
					</table>
				<?php
				} else {
					echo '<p>No donations found</p>';
				}	
			} 			
		}
	} 
}


// Shortcode to show total amount of donations current year or last year [focus_totaldonation]
add_shortcode( 'focus_totaldonation', 'render_focus_total_donation_amount' );

function render_focus_total_donation_amount( $atts ) {

	// Do not render shortcode in the admin area
	if ( is_admin() ) {
		return;
	}

	$connectionData = connectWPtoSFandGetUserInfo();
	$response_user_info = $connectionData->response_user_info;
	$mySforceConnection = $connectionData->SforceConnectionToken;
	$contactid = $connectionData->currentContactId;
	$accountid = $connectionData->currentAccountId;

	$query_opportunity_recordtype = "select Id from RecordType where Name ='Donation' ";
	$response_opportunity_recordtype = $mySforceConnection->query( $query_opportunity_recordtype );

	if( count( $response_opportunity_recordtype->records ) > 0 ) {

		$recordTypeRec = new SObject( $response_opportunity_recordtype->records[0] );
		$recordTypeId = $recordTypeRec->Id;

		$attrs = shortcode_atts( array(
			'year' => 'current'
		), $atts );

		if( $attrs['year'] == 'current' ) {
			$query_totaldonation = "select SUM(Amount) from Opportunity where RecordTypeId = '".$recordTypeId."' and AccountId = '".$accountid."' AND Amount != null AND CreatedDate = THIS_YEAR";
		} elseif( $attrs['year'] == 'last' ) {
			$query_totaldonation = "select SUM(Amount) from Opportunity where RecordTypeId = '".$recordTypeId."' and AccountId = '".$accountid."' AND Amount != null AND CreatedDate = LAST_YEAR";
		}

		$querySuccess = true;
		try {
			$response_totaldonation = $mySforceConnection->query( $query_totaldonation );
		} catch( Exception $e ) {
			echo 'Something went wrong :'.$e->getMessage();
			$querySuccess = false;
		}
		if( $querySuccess ) {
			echo '<p>';
			if( $attrs['year'] == 'current' ) {
				echo 'Total Donation This Year : ';
			} elseif( $attrs['year'] == 'last' ) {
				echo 'Total Donation Last Year : ';
			}
			if( count( $response_totaldonation->records ) > 0 ) {

				$totaldonationrecord = new SObject( $response_totaldonation->records[0] );

				if( $totaldonationrecord->fields->expr0 != '' && $totaldonationrecord->fields->expr0 != null ) {
					echo '$'.$totaldonationrecord->fields->expr0;	
				} else {
					echo '$0';
				}				
			} else {
				echo '$0';
			}
			echo '</p>';
		}
	} else {
		echo '<p>Donation record type not found</p>';
	}
}

function connectWPtoSFandGetUserInfo() {
	
	if( !is_user_logged_in() ) {
		$login_url = wp_login_url( get_permalink() );	
		wp_redirect( $login_url );
		exit;
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

	$sf_connect = false;
	try {
		$mySforceConnection = new SforcePartnerClient();
		$mySforceConnection->createConnection($pluginsUrl . "PartnerWSDL.xml");
		$mySforceConnection->login($storedUsername, $storedPassword.$storedSecurityToken);
		$sf_connect = true;
	} catch (Exception $e) {
	    $sf_connect = false;
	}

	if( !$sf_connect ) {
		echo '<p>Error while connecting to Salesforce</p>';
		exit;
	}

	$query_user_info = "SELECT Id, Name, AccountId FROM Contact WHERE Email = '".$userEmail."'";
	$response_user_info = $mySforceConnection->query($query_user_info);
	//if respective contact found at SF then only show programs
	$contactid = '';
	$accountid = '';
	if ( count( $response_user_info->records ) > 0 ) {
		$contactRec = new SObject( $response_user_info->records[0] );
		$contactid = $contactRec->Id;
		$accountid = $contactRec->fields->AccountId;
	} else {
		echo '<p>We can not find your record at FOCUS. Please call administrator for more details</p>';
		exit;
	}
	return (object) [
		"response_user_info"  => $response_user_info,
		"SforceConnectionToken" => $mySforceConnection,
		"currentContactId" => $contactid,
		"currentAccountId" => $accountid
	];
}

// Shortcode to display total number of volunteer working hours for current year or last year [focus_totaldonation]
add_shortcode( 'focus_totalvolunteerhours', 'render_focus_total_volunteer_hours' );

function render_focus_total_volunteer_hours( $atts ) {
	// Do not render shortcode in the admin area
	if ( is_admin() ) {
		return;
	}

	$connectionData = connectWPtoSFandGetUserInfo();
	$response_user_info = $connectionData->response_user_info;
	$mySforceConnection = $connectionData->SforceConnectionToken;
	$contactid = $connectionData->currentContactId;
	$accountid = $connectionData->currentAccountId;

	$attrs = shortcode_atts( array(
		'year' => 'current'
	), $atts );

	if( $attrs['year'] == 'current' ) {

		$query_totalvolunteerhours = "select SUM(GW_Volunteers__Hours_Worked__c) from GW_Volunteers__Volunteer_Hours__c where GW_Volunteers__Contact__c='".$contactid."' AND GW_Volunteers__Hours_Worked__c != null AND CreatedDate = THIS_YEAR";

	} elseif( $attrs['year'] == 'last' ) {

		$query_totalvolunteerhours = "select SUM(GW_Volunteers__Hours_Worked__c) from GW_Volunteers__Volunteer_Hours__c where GW_Volunteers__Contact__c='".$contactid."' AND GW_Volunteers__Hours_Worked__c != null AND CreatedDate = LAST_YEAR";

	}

	$querySuccess = true;
	try {
		$response_totalvolunteerhours = $mySforceConnection->query( $query_totalvolunteerhours );
	} catch( Exception $e ) {
		echo 'Something went wrong :'.$e->getMessage();
		$querySuccess = false;
	}
	if( $querySuccess ) {
		echo '<p>';
		if( $attrs['year'] == 'current' ) {
			echo 'Total Volunteer Hours This Year : ';
		} elseif( $attrs['year'] == 'last' ) {
			echo 'Total Volunteer Hours Last Year : ';
		}
		if( count( $response_totalvolunteerhours->records ) > 0 ) {
			$totalvolunteerhoursrecord = new SObject( $response_totalvolunteerhours->records[0] );				
			if( $totalvolunteerhoursrecord->fields->expr0 != '' && $totalvolunteerhoursrecord->fields->expr0 != null ) {
				echo $totalvolunteerhoursrecord->fields->expr0;	
			} else {
				echo '0';
			}				
		} else {
			echo '0';
		}
		echo '</p>';
	}
}

// Add Volunteer Jobs [focus_volunteercalendar]
add_shortcode( 'focus_volunteercalendar', 'render_focus_volunteer_calendar' );

function render_focus_volunteer_calendar( $atts ) {

	// Do not render shortcode in the admin area
	if ( is_admin() ) {
		return;
	}

	$attrs = shortcode_atts( array(
		'pagelink' => 'https://partial-focustest.cs42.force.com/FS_UpcomingVolunteerJobs',
		'iframewidth' => '700',
		'iframeheight' => '600'
	), $atts );

	if( shortcode_exists('advanced_iframe') ) {
		$connectionData = connectWPtoSFandGetUserInfo();
		$response_user_info = $connectionData->response_user_info;
		$mySforceConnection = $connectionData->SforceConnectionToken;
		$contactid = $connectionData->currentContactId;
		$accountid = $connectionData->currentAccountId;
		echo do_shortcode( '[advanced_iframe src="'.$attrs['pagelink'].'?id='.$contactid.'" width="'.$attrs['iframewidth'].'" height="'.$attrs['iframeheight'].'"]' );
	}
}