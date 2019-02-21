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
	wp_enqueue_style( 'dashCss', plugins_url('/dashCss.css', __FILE__) );
	wp_enqueue_style( 'fontFamily', 'https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700' );	
}

// Add Programs shortcode [focus_programs]
add_shortcode( 'focus_programs', 'wp_focus_program' );

function wp_focus_program( $atts ) {

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
	
	$featuredLimitClause = '';
	if( $atts && $atts['featuredlimit'] ) {
		$featuredLimitClause = ' LIMIT '.$atts['featuredlimit'];
	}
	
	$upcomingLimitClause = '';
	if( $atts && $atts['upcominglimit'] ) {
		$upcomingLimitClause = ' LIMIT '.$atts['upcominglimit'];
	}
	
 	$query_programs_signedup = "select Contact.Name, Contact.Id, Campaign.Id, Campaign.name, Campaign.StartDate, Campaign.Type, Campaign.Parent.Id, Campaign.Parent.Name  from campaignmember where contactid in (select Contact.id from Contact where Contact.accountid = '".$accountid."') and Campaign.isActive=true and Campaign.StartDate > TODAY".$upcomingLimitClause;

	$query_programs_scheduled = "select Id, Name, StartDate, Registration_Fee__c, isActive, Type, RecordTypeId from Campaign where isActive=true and Featured__c=true".$featuredLimitClause;

	$querySuccess = true;
	$content = '';

	try {
		$response_programs_signedup = $mySforceConnection->query($query_programs_signedup);
		$response_programs_scheduled = $mySforceConnection->query($query_programs_scheduled);
	} catch( Exception $e ) {
		echo 'Something went wrong :'.$e->getMessage();
		$querySuccess = false;
	}

	if( $querySuccess ) { 	?>
		<div class="cardStyle">
			<div class="cardTitle">
				<div class="cardTitleTxt">Featured Events</div>
			</div>
			<div class="cardBody">
				<?php 
				if( count( $response_programs_scheduled->records ) == 0 ) { ?>
					<p>No programs found</p>
				<?php } else{ ?>
					<div class="eventList">
				  <?php foreach ($response_programs_scheduled->records as $record_scheduled) {
						$record_scheduled = new SObject( $record_scheduled );
						$addtolist = true;
						foreach ($response_programs_signedup->records as $record_signedup) {
							$record_signedup = new SObject( $record_signedup );
							$campRec = new SObject( $record_signedup->fields->Campaign );
							if ( $campRec->fields && $campRec->fields->Name == $record_scheduled->fields->Name) {
								$addtolist = false;
							}
						}
						if ($addtolist) { ?>
						<div class="eventItem">
							<div class="eventName"><?php echo $record_scheduled->fields->Name; ?></div>
							<div class="eventDate">
								<?php 
									$date = date_create( $record_scheduled->fields->StartDate );
									echo date_format($date,"F d, Y");
								?>
							</div>
							<a href="<?php echo $siteURL.'/campaign?cmpid='.$record_scheduled->Id; ?>" class="btnStyle btnBlue viewBtn">View</a>
						</div>
						<?php } ?>
					<?php } ?>
					</div>
				<?php }?>
			</div>
		</div>
		<div class="cardStyle">
			<div class="cardTitle hasLink">
				<div class="cardTitleTxt">Your Upcoming Events</div>
				<?php if( $atts && $atts['alleventslink'] ) { ?>
					<a href="<?php echo $siteURL.'/member-programs';?>" class="cardTitleLink">More Programs</a>
				<?php } ?>
			</div>
			<div class="cardBody">
				<?php if( count( $response_programs_signedup->records ) == 0 ) { ?>
					<p>No programs found</p>
				<?php } else { ?>
					<div class="eventList">
				<?php foreach ($response_programs_signedup->records as $record_signedup) {
					$record_signedup = new SObject( $record_signedup ); 
					$campRec = new SObject( $record_signedup->fields->Campaign ); ?>
						<div class="eventItem">
							<div class="eventName"><?php if( $campRec->fields ) { echo $campRec->fields->Name;
								/*if( $campRec->fields->Parent ) { echo ' parent: '.$campRec->fields->Parent->Name; }*/ } ?></div>
							<div class="eventDate">
								<?php if( $record_signedup ) { echo $record_signedup->fields->Contact->Name; } ?>
							</div>
			 				<div class="eventDate">
								<?php 
								if( $campRec->fields ) {
									$date = date_create( $campRec->fields->StartDate );
									echo date_format($date,"F d, Y");
								}
								?>
							</div>
							<a href="<?php echo $siteURL.'/campaign?cmpid='.$campRec->Id; ?>" class="btnStyle btnBlue viewBtn">View</a>
						</div>
				<?php }	?>
					</div>
				<?php }	?>	
			</div>
		</div>
	<?php } 
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

		$query_campaigndetails    = "select Id, Name, Description, Location__c, StartDate, EndDate, Registration_Fee__c, isActive, Type, Registration_Date__c, Registration_End_Date__c from Campaign where ID='" . $_GET['cmpid'] . "'";		

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
				$content .= '<p>Location : ' . $campaigndetails->fields->Location__c . '</p>';
				$content .= '<p>' . $campaigndetails->fields->Description . '</p>';

				$content .= '
					<table>
						<tr>
							<th>Start Date</th>
							<th>End Date</th>
							<th>Registration Fee</th>
						</tr>';

				$content .=
					'<tr>
						<td>' . $campaigndetails->fields->StartDate . '</td>
						<td>' . $campaigndetails->fields->EndDate . '</td>
						<td>$ ' . $campaigndetails->fields->Registration_Fee__c . '</td>
					</tr>';

				$content .= '</table>';

				
				$todayDate = date('Y-m-d');
				$todayDate = date('Y-m-d', strtotime($todayDate));
				$campRegStartDate = '';
				$campRegEndDate = '';

				if( $campaigndetails->fields->Registration_Date__c != '' && $campaigndetails->fields->Registration_Date__c != null ) {
					$campRegStartDate = date( 'Y-m-d', strtotime( $campaigndetails->fields->Registration_Date__c ) );
				}
				if( $campaigndetails->fields->Registration_End_Date__c != '' && $campaigndetails->fields->Registration_End_Date__c != null ) {
					$campRegEndDate = date( 'Y-m-d', strtotime( $campaigndetails->fields->Registration_End_Date__c ) );
				}

				if ( $formCampaignMapping[ $campaigndetails->fields->Type ] ) {

					if ( $formCampaignMapping[ $campaigndetails->fields->Type ]->isIndividualRequest == 'true' ) {
						
						//check if today's date is between start and end date. If in between then only show sign up button
						if( ( $campRegStartDate != '' && $campRegEndDate != '' && ( $todayDate > $campRegStartDate ) && ( $todayDate < $campRegEndDate ) ) || 
							( $campRegStartDate != '' && $campRegEndDate == '' && ( $todayDate > $campRegStartDate ) ) ||
							( $campRegStartDate == '' && $campRegEndDate != '' && ( $todayDate < $campRegEndDate )  ) ||
							( $campRegStartDate == '' && $campRegEndDate == '' )
						) {
							$content .= '<p><a class="button" href="#ex1" rel="modal:open">Sign Up</a></p>';
						} else {
							$content .= '<p>Program is not available for registration.</p>'; 
						}
						//show modal
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
						//check if today's date is between start and end date. If in between then only show sign up button
						if( ( $todayDate > $campRegStartDate ) && ( $todayDate < $campRegEndDate ) ) {
						    $content .= '<p><a class="button" href="' . $siteURL . '/campaign?cmpid=' . $campaigndetails->Id . '&cntid=' . $contactid . '&formid=' . $formCampaignMapping[ $campaigndetails->fields->Type ]->formNumber . '">Sign Up</a></p>';
						} else {
							$content .= '<p>Program is not available for registration.</p>'; 
						}
						

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

function render_focus_volunteer_landing_page( $atts ) {

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

	$attrs = shortcode_atts( array(
		'showmyjobs' => 'true',
		'alloppslink' => 'false'
	), $atts );

	if ( ( isset($_GET['formid']) && $_GET['formid'] ) && shortcode_exists('formassembly') ) { //display form

		$formId = $_GET['formid'];
		echo do_shortcode( '[formassembly formid=' . $formId . ']' );

	} else { //display list of volunteer jobs
		
		if( $attrs[ 'showmyjobs' ] == 'true' ) {
			$query_my_jobs = "select Id, Name, GW_Volunteers__Contact__r.Name, GW_Volunteers__Volunteer_Job__c, GW_Volunteers__Volunteer_Job__r.Name, GW_Volunteers__Start_Date__c, GW_Volunteers__Hours_Worked__c, GW_Volunteers__Volunteer_Job__r.GW_Volunteers__Campaign__c, GW_Volunteers__Volunteer_Job__r.GW_Volunteers__Campaign__r.Name from GW_Volunteers__Volunteer_Hours__c where GW_Volunteers__Contact__c='".$contactid."'";			
		}

		$query_jobs = "select GW_Volunteers__Volunteer_Job__r.Id, GW_Volunteers__Volunteer_Job__r.GW_Volunteers__Campaign__r.Name, GW_Volunteers__Volunteer_Job__r.Name, GW_Volunteers__Volunteer_Job__r.GW_Volunteers__Description__c, GW_Volunteers__Start_Date_Time__c , GW_Volunteers__Duration__c from GW_Volunteers__Volunteer_Shift__c where GW_Volunteers__Volunteer_Job__r.GW_Volunteers__Display_on_Website__c = true AND GW_Volunteers__Start_Date_Time__c > TODAY and GW_Volunteers__Start_Date_Time__c = NEXT_N_DAYS:90";

		$querySuccess = true;

		try {	
			if( $attrs[ 'showmyjobs' ] == 'true'  ) {	
				$response_myjobs = $mySforceConnection->query( $query_my_jobs );
			} 
			$response_jobs = $mySforceConnection->query( $query_jobs );
		} catch( Exception $e ) {
			echo 'Something went wrong :'.$e->getMessage();
			$querySuccess = false;
		}

		if( $querySuccess ) {
			if( $attrs[ 'showmyjobs' ] == 'true' ) {	//show my jobs only when it is set in shortcode
			?>
				<div class="cardStyle">
					<div class="cardTitle">
						<div class="cardTitleTxt">My Jobs</div>
					</div>
					<div class="cardBody">
						<?php
						if( count( $response_myjobs->records ) > 0 ) { ?>
							<div class="eventList">
								<?php foreach ( $response_myjobs->records as $record_myjob ) { 
										$record_myjob = new SObject( $record_myjob );
										$jobRec = new SObject( $record_myjob->fields->GW_Volunteers__Volunteer_Job__r );
										if( $jobRec->fields ) {
											$campaignRec = new SObject( $jobRec->fields->GW_Volunteers__Campaign__r );	
										}										
								?>
								<div class="eventItem">
									<div class="eventName"><?php if( $jobRec->fields ) { echo $jobRec->fields->Name ; }?>	</div>
									<div class="eventDate"><?php if( $campaignRec && $campaignRec->fields ) { echo $campaignRec->fields->Name ; }?><span style="margin-left:7px;">
									<?php 
										$date = date_create( $record_myjob->fields->GW_Volunteers__Start_Date__c );
										echo date_format($date,"F d, Y");?>
									</span></div>
								</div>
								<?php
								} ?>
							</div>
						<?php 
						} else {
							echo '<p>No opportunities found</p>';
						} 
						?>
					</div>
				</div>
				<?php } ?>
				<div class="cardStyle">
					<div class="cardTitle hasLink">
						<div class="cardTitleTxt">Your Upcoming Volunteer Activities</div>
						<?php if( $attrs['alloppslink'] == 'true' ) { ?>
							<a href="<?php echo $siteURL.'/volunteer-jobs';?>" class="cardTitleLink">More Opps</a>
						<?php } ?>
					</div>
					<div class="cardBody">
						<?php
						if( count( $response_jobs->records ) > 0 ) { ?>
						<div class="eventList">
							<?php foreach ( $response_jobs->records as $record_job ) { 
								$record_job = new SObject( $record_job );
								$jobRec = new SObject( $record_job->fields->GW_Volunteers__Volunteer_Job__r );
								if( $jobRec->fields ) {
									$campaignRec = new SObject( $jobRec->fields->GW_Volunteers__Campaign__r );
								}
							?>
							<div class="eventItem">
								<div class="eventName"><?php if( $jobRec->fields ) { echo $jobRec->fields->Name; }?></div>
								<div class="eventDate"><?php if( $campaignRec && $campaignRec->fields ) { echo $campaignRec->fields->Name;} ?><span style="margin-left:7px;">
								<?php 
								$date = date_create( $record_job->fields->GW_Volunteers__Start_Date_Time__c );
								echo date_format($date,"F d, Y");?>
								</span></div>
								<a href="<?php echo $siteURL . '/volunteer-jobs?jobid=' . $jobRec->Id . '&cntid=' . $contactid . '&formid=4713591'; ?>" class="btnStyle btnBlue viewBtn">View</a>
							</div>
							<?php
							} ?>
						</div>
						<?php 
						} else {
							echo '<p>No opportunities found</p>';
						} ?>
					</div>
				</div>
			<?php 
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
								$tempContactRec = new SObject( $record_opportunity->fields->npsp__Primary_Contact__r );
								?>
								<tr>
									<td> <?php if(  $tempContactRec->fields ) { echo $tempContactRec->fields->Name; } ?> </td>
									<td> <?php echo $record_opportunity->fields->CloseDate; ?> </td>
									<td> <?php $camp = new SObject( $record_opportunity->fields->Campaign ); 
									echo ( $camp->fields && $camp->fields->Name ) ? $camp->fields->Name : 'General' ; ?> </td>
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
		$connection = $mySforceConnection->createConnection($pluginsUrl . "PartnerWSDL.xml");		
		$mySforceConnection->login($storedUsername, $storedPassword.$storedSecurityToken);
		$sf_connect = true;
	} catch (Exception $e) {
		echo $e->getMessage();
	    $sf_connect = false;
	}

	if( !$sf_connect ) {
		echo '<p>Error while connecting to Salesforce</p>';
		exit;
	}

	$query_user_info = "SELECT Id, Name, AccountId, Account.Total_Due__c, Account.npo02__OppAmountThisYear__c, Account.npo02__Informal_Greeting__c, Account.Name, Account.Primary_Email__c, Account.Phone, Account.CreatedDate, Account.BillingStreet, Account.BillingCity, Account.BillingState, Account.BillingPostalCode, Account.BillingCountry, Account.npo02__TotalOppAmount__c, Account.Level__r.Name, GW_Volunteers__Volunteer_Hours__c FROM Contact WHERE Email = '".$userEmail."'";
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
		"currentAccountId" => $accountid,
		"contactRecord" => $contactRec
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

// Display focus account information [focus_accountinfo]
add_shortcode( 'focus_accountinfo', 'render_focus_account_information' );

function render_focus_account_information() {

	// Do not render sortcode in the admin area
	if ( is_admin() ) {
		return;
	}

	$connectionData = connectWPtoSFandGetUserInfo();
	$response_user_info = $connectionData->response_user_info;
	$mySforceConnection = $connectionData->SforceConnectionToken;
	$contactid = $connectionData->currentContactId;
	$accountid = $connectionData->currentAccountId;
	$contactRec = $connectionData->contactRecord; 

	$BillingAddress = '';
	if( $contactRec->fields->Account->BillingStreet ) {
		$BillingAddress .= $contactRec->fields->Account->BillingStreet.' <br/>';
	}
	if( $contactRec->fields->Account->BillingCity ) {
		$BillingAddress .= $contactRec->fields->Account->BillingCity.', ';
	}
	if( $contactRec->fields->Account->BillingState ) {
		$BillingAddress .= $contactRec->fields->Account->BillingState.' ';
	}
	if( $contactRec->fields->Account->BillingPostalCode ) {
		$BillingAddress .= $contactRec->fields->Account->BillingPostalCode.' ';
	}
	$currentUser = wp_get_current_user();
	?>
	<div class="household">
		<!--<div class="householdPic-wrap">
			<?php echo get_avatar( $currentUser->ID, 227 ); ?>
		</div>-->
		<img src="https://d3r03scjf2irwr.cloudfront.net/content/uploads/2019/02/16142132/ff.png" alt="" class="householdPic"/>
		<div class="householdName">
			<div class="householdNameTxt"><?php echo $contactRec->fields->Account->npo02__Informal_Greeting__c;?></div>
			
		</div>
		<ul class="householdNameInfo">
			<?php if( $BillingAddress != '' ) {?>
				<li>
					<img src="https://image.flaticon.com/icons/svg/252/252106.svg" alt="" class="infoIcon"/>
					<p><?php echo $BillingAddress;?></p>
				</li>
			<?php
			} if( $contactRec->fields->Account->Phone != '' ) {?>			
				<li>
					<img src="https://image.flaticon.com/icons/svg/252/252050.svg" alt="" class="infoIcon"/>
					<p><?php echo $contactRec->fields->Account->Phone;?></p>
				</li>
			<?php } if( $contactRec->fields->Account->Primary_Email__c != '' ) { ?>
				<li>
					<img src="https://image.flaticon.com/icons/svg/252/252049.svg" alt="" class="infoIcon"/>
					<p><a href="mailto:<?php echo $contactRec->fields->Account->Primary_Email__c;?>" class="emailLink"><?php echo $contactRec->fields->Account->Primary_Email__c;?></a></p>
				</li>
			<?php } if( $contactRec->fields->Account->CreatedDate != '' ) { ?>		
				<li>
					<img src="https://image.flaticon.com/icons/svg/252/252091.svg" alt="" class="infoIcon"/>
					<p>
						<?php 
						$date=date_create( $contactRec->fields->Account->CreatedDate );
						echo 'Family Since '.date_format($date,"Y");?>
					</p>
				</li>
			<?php } ?>
		</ul>
		<div class="householdName">
			<div class="householdNameTxt">Thanks you!</div>
		</div>
		<div class="divider"></div>
		<div class="donations">
			<div class="donationAmt">$<?php echo $contactRec->fields->Account->npo02__TotalOppAmount__c; ?> <a href="<?php echo $siteURL.'/donations';?>" class="viewLinkSm">View All</a></div>
			
			<div class="donationYr">Donations <?php echo date("Y"); ?><br/>
				<?php if( $contactRec->fields->Account->Level__r ) { ?>
					<b><?php echo $contactRec->fields->Account->Level__r->Name; ?> Level Donor</b>
				<?php
				}?>				
			</div>	
		</div>
		<div class="divider"></div>
		<div class="donations">
			<div class="donationAmt"><?php echo $contactRec->fields->GW_Volunteers__Volunteer_Hours__c; ?> <a href="<?php echo $siteURL.'/volunteers';?>" class="viewLinkSm">View All</a></div>
			<div class="donationYr">Hours Volunteered</div>	
		</div>	
		<div class="divider"></div>
		<div class="donations">
			<div class="donationAmt">$<?php echo $contactRec->fields->Account->Total_Due__c; ?></div>
			<div class="donationYr">Total Due for Programs</div>	
		</div>
	</div>
	<?php
}

// Display focus family dashboard [focus_familydashboard]
add_shortcode( 'focus_donation_volunteer_details', 'render_donation_volunteer_details' );

function render_donation_volunteer_details() {
	// Do not render shortcode in the admin area
	if ( is_admin() ) {
		return;
	}

	$connectionData = connectWPtoSFandGetUserInfo();
	$response_user_info = $connectionData->response_user_info;
	$mySforceConnection = $connectionData->SforceConnectionToken;
	$contactid = $connectionData->currentContactId;
	$accountid = $connectionData->currentAccountId;
	$contactRec = $connectionData->contactRecord; 
	$siteURL = get_site_url();
	?>
	<div class="cardStyle">
		<div class="cardTitle">
			<div class="cardTitleTxt">Thank you!</div>
		</div>
		<div class="cardBody">
			<div class="donations">
				<div class="donationAmt">$<?php echo $contactRec->fields->Account->npo02__TotalOppAmount__c; ?> <a href="<?php echo $siteURL.'/donations';?>" class="viewLinkSm">View All</a></div>
				
				<div class="donationYr">Donations <?php echo date("Y"); ?><br/>
					<?php if( $contactRec->fields->Account->Level__r ) { ?>
						<b><?php echo $contactRec->fields->Account->Level__r->Name; ?> Level Donor</b>
					<?php
					}?>				
				</div>	
			</div>
			<div class="divider"></div>
			<div class="donations">
				<div class="donationAmt"><?php echo $contactRec->fields->GW_Volunteers__Volunteer_Hours__c; ?> <a href="<?php echo $siteURL.'/volunteers';?>" class="viewLinkSm">View All</a></div>
				<div class="donationYr">Hours Volunteered</div>	
			</div>	
		</div>
	</div>		
	<?php
}

// Display focus family dashboard [focus_familydashboard]
add_shortcode( 'focus_familydashboard', 'render_focus_family_dashboard' );

function render_focus_family_dashboard() { ?>
    
<div class="dashboardLayoutContainer">
	<div class="dashboardLayout">
		<div class="dashboardLayoutCol householdCol">
			<?php echo do_shortcode( '[focus_accountinfo]' ) //shortcode to display account information ( left sidebar )
			?>	
		</div>	
		<div class="dashboardLayoutCol">
			<div class="vCol">
				<?php echo do_shortcode( '[focus_programs featuredlimit="3" upcominglimit="5" alleventslink="true"]' ); //shortcode to display events ( my and featured )?
				 echo do_shortcode( '[focus_volunteers showmyjobs="false" alloppslink="true"]' ); //volunteer opportunities ?>
			</div>	
		</div>
	</div>
</div>

<?php 		
}