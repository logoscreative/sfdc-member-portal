<?php
/**
 * Plugin Name: SFDC Web portal
 * Plugin URI: http://www.focus-ga.org/
 * Description: Cloudland Technologies - FOCUS Member Portal
 * Version: 1.2
 * Author: Paul Cannon
 * Author URI: http://www.cloudlandtechnologies.com
 */

// Utility function to look for applicable shortcodes in content
function check_for_shortcode_in_content( $post_id = null ) {

	if ( !$post_id ) {
		$post_id = get_the_ID();
	}

	if ( $post_id ) {
		$post_content = apply_filters('the_content', get_post_field('post_content', $post_id));
		if (
		has_shortcode( $post_content, 'focus_programs' ) ||
		has_shortcode( $post_content, 'focus_campaign' ) ||
		has_shortcode( $post_content, 'focus_volunteers' ) ||
		has_shortcode( $post_content, 'focus_donations' ) ||
		has_shortcode( $post_content, 'focus_totaldonation' ) ||
		has_shortcode( $post_content, 'focus_totalvolunteerhours' ) ||
		has_shortcode( $post_content, 'focus_volunteercalendar' ) ||
		has_shortcode( $post_content, 'focus_accountinfo' ) ||
		has_shortcode( $post_content, 'focus_donation_volunteer_details' ) ||
		has_shortcode( $post_content, 'focus_familydashboard' )
		) {
			return true;
		} else {
			return false;
		}
	}
	return false;
}

// Enqueue Dashicons for calendar icon
add_action( 'wp_enqueue_scripts', 'load_dashicons_front_end' );

function load_dashicons_front_end() {
	if ( check_for_shortcode_in_content() === true ) {
		wp_enqueue_style( 'dashicons' );
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

function connectWPtoSFandGetUserInfo() {

	if( !is_user_logged_in() ) {
		return '<p>You must be logged in.</p>';
	}

	$pluginsUrl = plugin_dir_path( __FILE__ );

	$currentUser = wp_get_current_user();
	$userEmail = $currentUser->user_email;

	// Allow debugging
	if ( isset($_GET['sfdc_user_email']) && $_GET['sfdc_user_email'] ) {
		$userEmail = sanitize_email($_GET['sfdc_user_email']);
	}

	$storedUsername = '';
	if ( defined('SFDC_MEMBER_PORTAL_USERNAME')) {
		$storedUsername = SFDC_MEMBER_PORTAL_USERNAME;
		echo $storedUsername;
	}

	$storedPassword = '';
	if ( defined('SFDC_MEMBER_PORTAL_PASSWORD')) {
		$storedPassword = SFDC_MEMBER_PORTAL_PASSWORD;
		echo $storedPassword;
	}

	$storedSecurityToken = '';
	if ( defined('SFDC_MEMBER_PORTAL_SECURITY_TOKEN')) {
		$storedSecurityToken = SFDC_MEMBER_PORTAL_SECURITY_TOKEN;
		echo $storedSecurityToken;
	}

	require_once ($pluginsUrl . 'soapclient/SforcePartnerClient.php');


	$sf_connect = false;
	try {
		$mySforceConnection = new SforcePartnerClient();
		$connection = $mySforceConnection->createConnection($pluginsUrl . "PartnerWSDL.xml");
		$mySforceConnection->login($storedUsername, $storedPassword.$storedSecurityToken);
		$sf_connect = true;
	} catch (Exception $e) {
		return $e->getMessage();
		$sf_connect = false;
	}

	if( !$sf_connect ) {
		return '<p>Error while connecting to Salesforce</p>';
	}

	$query_user_info = "SELECT Id, Name, AccountId, Account.Total_Due__c, Account.npo02__OppAmountThisYear__c, Account.npo02__Informal_Greeting__c, Account.Name, Account.Primary_Email__c, Account.Phone, Account.CreatedDate, Account.BillingStreet, Account.BillingCity, Account.BillingState, Account.BillingPostalCode, Account.BillingCountry, Account.npo02__TotalOppAmount__c, Account.Level__r.Name, GW_Volunteers__Volunteer_Hours__c, TYA_Camp_Invite__c, TYA_Monthly_Invite__c FROM Contact WHERE Email = '".$userEmail."'";
	$response_user_info = $mySforceConnection->query($query_user_info);
	//if respective contact found at SF then only show programs
	$contactid = '';
	$accountid = '';
	if ( $response_user_info && count( $response_user_info->records ) > 0 ) {
		$contactRec = new SObject( $response_user_info->records[0] );
		$contactid = $contactRec->Id;
		$accountid = $contactRec->fields->AccountId;
	} else {
		return '<p>We can not find your record at FOCUS. Please call administrator for more details</p>';
	}
	return (object) [
		"response_user_info"  => $response_user_info,
		"SforceConnectionToken" => $mySforceConnection,
		"currentContactId" => $contactid,
		"currentAccountId" => $accountid,
		"contactRecord" => $contactRec
	];
}

// Add Programs shortcode [focus_programs]
add_shortcode( 'focus_programs', 'wp_focus_program' );

function wp_focus_program( $atts ) {

	// Do not render shortcode in the admin area
	if ( is_admin() ) {
		return;
	}

	$connectionData = connectWPtoSFandGetUserInfo();

	// If $connectionData is a string, it means an error/exception occurred. Print the message
	if ( is_string($connectionData) ) {
		return $connectionData;
	}

	$response_user_info = $connectionData->response_user_info;
	$mySforceConnection = $connectionData->SforceConnectionToken;
	$contactid = $connectionData->currentContactId;
	$accountid = $connectionData->currentAccountId;
	$contactRec = $connectionData->contactRecord;

	$attrs = shortcode_atts( array(
		'featuredlimit' => '',
		'upcominglimit' => '',
		'alleventslink' => ''
	), $atts );

	$featuredLimitClause = '';
	if( $attrs && $attrs['featuredlimit'] != '' ) {
		$featuredLimitClause = ' LIMIT '.$attrs['featuredlimit'];
	}

	$upcomingLimitClause = '';
	if( $attrs && $attrs['upcominglimit'] != '' ) {
		$upcomingLimitClause = ' LIMIT '.$attrs['upcominglimit'];
	}

	$query_programs_signedup = "select Contact.Name, Contact.Id, Campaign.Id, Campaign.name, Campaign.StartDate, Campaign.Type, Campaign.Parent.Id, Campaign.Parent.Name, Campaign.Parent.StartDate, Campaign.TYA_monthly_invite__c, Campaign.TYA_camp_invite__c, Campaign.Parent.TYA_monthly_invite__c, Campaign.Parent.TYA_camp_invite__c, Campaign.Parent.Type from campaignmember where contactid in (select Contact.id from Contact where Contact.accountid = '".$accountid."') and Campaign.isActive=true and Campaign.StartDate > TODAY".$upcomingLimitClause;

	$query_programs_scheduled = "select Id, Name, StartDate, Registration_Fee__c, isActive, Type, RecordTypeId, TYA_monthly_invite__c, TYA_camp_invite__c, Parent.Id, Parent.Name, Parent.StartDate, Parent.TYA_monthly_invite__c, Parent.TYA_camp_invite__c, Parent.Type from Campaign where isActive=true and Featured__c=true".$featuredLimitClause;

	$query_contactsWithMonthlyInviteCheck = "select Id, Name from Contact where AccountId = '" . $accountid . "' AND TYA_Monthly_Invite__c = true";

	$query_contactsWithCampInviteCheck = "select Id, Name from Contact where AccountId = '" . $accountid . "' AND TYA_Camp_Invite__c = true";

	$query_accountOpportunities = "SELECT Id, npsp__Primary_Contact__c, npsp__Primary_Contact__r.AccountId, CampaignId, Name, Amount FROM Opportunity WHERE npsp__Primary_Contact__r.AccountId = '" . $accountid . "' AND CampaignId != '' ORDER BY CreatedDate DESC";

	$querySuccess = true;

	try {
		$response_programs_signedup = $mySforceConnection->query($query_programs_signedup);
		$response_programs_scheduled = $mySforceConnection->query($query_programs_scheduled);
		$response_contactsWithMonthlyInviteCheck = $mySforceConnection->query($query_contactsWithMonthlyInviteCheck);
		$response_contactsWithCampInviteCheck = $mySforceConnection->query($query_contactsWithCampInviteCheck);
		$response_accountOpportunities = $mySforceConnection->query($query_accountOpportunities);
	} catch( Exception $e ) {
		return 'Something went wrong :'.$e->getMessage();
		$querySuccess = false;
	}

	$content = '';

	if( $querySuccess ) {
		$opportunityEvents = array();
		//create a map for current acount's contact opportunities and events
		if( count( $response_accountOpportunities->records ) > 0 ) {
			foreach ($response_accountOpportunities->records as $record_opp ) {
				$record_opp = new SObject( $record_opp );
				$arrKey = $record_opp->fields->npsp__Primary_Contact__c.'_'.$record_opp->fields->CampaignId;
				if( !array_key_exists( $arrKey, $opportunityEvents ) ) {
					$opportunityEvents[ $arrKey ] = $record_opp->fields->Amount;
				}
			}
		}

		$content .= '<table width="100%">';
		$content .= '<tr>
				<td style="border-top:none;"> 
					<h4><b>Featured Events</b></h4>
				</td>
				<td>';
		if( $attrs && $attrs['alleventslink'] != '' ) {
			$content .= '<a href="' . esc_url( home_url('/programs') ) . '" class="alignright">More</a>';
		}
		$content .= '</td>
			</tr>';

		if( count( $response_programs_scheduled->records ) == 0 ) {
			$content .= '
			<tr>
                <td>
                    <p>No programs found</p>
                </td>
			</tr>';
		} else {
			$displayedParentCampaigns = [];
			foreach ($response_programs_scheduled->records as $record_scheduled) {
				$record_scheduled = new SObject( $record_scheduled );
				$addtolist = true;
				foreach ($response_programs_signedup->records as $record_signedup) {
					$record_signedup = new SObject( $record_signedup );
					$campRec = new SObject( $record_signedup->fields->Campaign );
					if ( $campRec->fields && $campRec->fields->Name == $record_scheduled->fields->Name) {
						$addtolist = false;
					}
				}
				//if parent campaign is present for any campaign then only display parent campaign and skip child campaigns
				//At campaign detail page if it is a parent campaign then display all it's child campaigns
				$parentCampaign = new SObject( $record_scheduled->fields->Parent );
				if( $parentCampaign->Id != ''
				    && in_array( $parentCampaign->Id, $displayedParentCampaigns) ) {
					$addtolist = false;
				}
				if( $parentCampaign->Id != '' ) {
					$rec = $parentCampaign;
					if( !in_array( $parentCampaign->Id, $displayedParentCampaigns) ) {
						array_push( $displayedParentCampaigns, $parentCampaign->Id );
					}
				} else {
					$rec = $record_scheduled;
				}
				//if monthly invite checkbox for campaign is set and any family member don't have same checkbox checked (at contact) then don't display campaign
				if( $rec->fields->TYA_monthly_invite__c == 'true' && count( $response_contactsWithMonthlyInviteCheck->records ) == 0 ) {
					$addtolist = false;
				}
				//if camp invite checkbox for campaign is set and any family member don't have same checkbox checked (at contact) then don't display campaign

				if( $rec->fields->TYA_camp_invite__c == 'true' && count( $response_contactsWithCampInviteCheck->records ) == 0 ) {
					$addtolist = false;
				}
				if ($addtolist) {
					$date = date_create( $rec->fields->StartDate );
					$content .= '
					<tr>
                        <td width="70%">
                            <div><b>' .  $rec->fields->Name . '</b></div>
                            <div>' . date_format($date,"F d, Y") . '</div>
                        </td>
                        <td>
                            <div align="right">
                                <a class="button small" href="' . esc_url( home_url('/campaign?cmpid=' . $rec->Id . '&showParent=true') ) . '">View</a>
                            </div>
                        </td>
                    </tr>';
				}
			}
		}

		$content .= '</table>';

		$content .= '
		<div>
			<table width="100%">
				<tr>
				<td style="border-top:none;">
				<h4><b>Your Upcoming Events</b></h4>
				</td>
				</tr>';

		if( count( $response_programs_signedup->records ) == 0 ) {
			$content .= '<tr><td>No programs found</td></tr>';
		} else {
			$shownParentCampaigns = [];
			foreach ($response_programs_signedup->records as $record_signedup) {
				$record_signedup = new SObject( $record_signedup );
				$campRec = new SObject( $record_signedup->fields->Campaign );
				$addtolist = true;
				/*if( $campRec->fields && $campRec->fields->Parent )  {
					$parentCampaign = new SObject( $campRec->fields->Parent );
					if( in_array( $parentCampaign->Id, $shownParentCampaigns) ) {
						$addtolist = false;
					} else {
						array_push( $shownParentCampaigns, $parentCampaign->Id );
						$campRec = $parentCampaign;
					}
				}*/

				//if monthly invite checkbox for campaign is set and any family member don't have same checkbox checked (at contact) then don't display campaign
				/*if( $campRec->fields->TYA_monthly_invite__c == 'true' && count( $response_contactsWithMonthlyInviteCheck->records ) == 0 ) {
					$addtolist = false;
				}

				//if camp invite checkbox for campaign is set and any family member don't have same checkbox checked (at contact)  then don't display campaign
				if( $campRec->fields->TYA_camp_invite__c == 'true' && count( $response_contactsWithCampInviteCheck->records ) == 0 ) {
					$addtolist = false;
				}*/

				if( $addtolist ) {
					$content .= '<tr><td><div><b>';
					if( $campRec->fields ) {
						$content .= $campRec->fields->Name;
						/*if( $campRec->fields->Parent ) { echo ' parent: '.$campRec->fields->Parent->Name; }*/
					}
					$content .= '</b></div>';

					$content .= '<div>';
					if( $record_signedup ) {
						$content .= $record_signedup->fields->Contact->Name;
					}
					$content .= '</div>';

					$content .= '<div>';
					if( $campRec->fields ) {
						$date = date_create( $campRec->fields->StartDate );
						$content .= date_format($date,"F d, Y");
					}
					//display respective opportunity price
					if( $record_signedup ) {
						$reqArrKey = $record_signedup->fields->Contact->Id.'_'.$campRec->Id;
						if( array_key_exists( $reqArrKey, $opportunityEvents ) ) {
							if( $campRec->fields && $record_signedup && $opportunityEvents[ $reqArrKey ] != 0 ) {
								$content .= '<br /> Total Due: $'.$opportunityEvents[ $reqArrKey ];
								$content .= '&nbsp;&nbsp;<a href="https://www.tfaforms.com/4726609?tfa_2='.$campRec->fields->Name.'&tfa_1='.$record_signedup->fields->Contact->Name.'&tfa_3='.$opportunityEvents[ $reqArrKey ].'">Pay Now</a>';
							}
						}
					}
					$content .= '</div></td>';

					$content .= '<td width="90">
	                                <div align="right">
                                        <a href="' . esc_url( home_url('/campaign?cmpid='.$campRec->Id.'&showParent=false') ) . '" class="button small">View</a>
                                    </div>
                                </td>
                            </tr>';
				}
			}
		}

		$content .= '</table></div>';
	}

	return $content;

}

// Add Campaigns shortcode [focus_campaign]
add_shortcode( 'focus_campaign', 'render_focus_campaign_landing_page' );

function render_focus_campaign_landing_page() {

	// Do not render shortcode in the admin area
	if ( is_admin() ) {
		return;
	}

	$connectionData = connectWPtoSFandGetUserInfo();

	// If $connectionData is a string, it means an error/exception occurred. Print the message
	if ( is_string($connectionData) ) {
		return $connectionData;
	}

	$response_user_info = $connectionData->response_user_info;
	$mySforceConnection = $connectionData->SforceConnectionToken;
	$contactid = $connectionData->currentContactId;
	$accountid = $connectionData->currentAccountId;

	if ( ( isset($_GET['formid']) && $_GET['formid'] ) && shortcode_exists('formassembly') ) {

		$formId = $_GET['formid'];
		return do_shortcode( '[formassembly formid=' . $formId . ']' );

	} elseif( isset( $_GET['cmpid'] ) && $_GET['cmpid'] ) {

		$query_currentaccount_contacts    = "select Id, Name from Contact where AccountId = '" . $accountid . "'";

		$query_campaigndetails    = "select Id, Name, Description, Location__c, StartDate, EndDate, Registration_Fee__c, isActive, Type, Registration_Date__c, Registration_End_Date__c, Parent.Id  from Campaign where ID='" . sanitize_text_field($_GET['cmpid']) . "'";

		$query_childCampaigns    = "select Id, Name, Description, Location__c, StartDate, EndDate, Registration_Fee__c, isActive, Type, Registration_Date__c, Registration_End_Date__c, Parent.Id  from Campaign where ParentId='" . sanitize_text_field($_GET['cmpid']) . "'";

		//Fetch mapping of form and campaign type from SF custom object "Program Forms"
		$query_form_campaign_mapping    = "select Name, Form_Number__c, Individual_Request__c from Program_Forms__c";

		$querySuccess = true;

		try {
			$response_currentaccount_contacts = $mySforceConnection->query( $query_currentaccount_contacts );
			$response_campaigndetails = $mySforceConnection->query( $query_campaigndetails );
			$response_form_campaign_mapping = $mySforceConnection->query( $query_form_campaign_mapping );
			$response_childCampaigns = $mySforceConnection->query( $query_childCampaigns );
		} catch( Exception $e ) {
			return 'Something went wrong :'.$e->getMessage();
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

			if ( count( $response_campaigndetails->records ) > 0 ) {

				$campaignRecords = [];
				if( count( $response_childCampaigns->records ) > 0 && isset( $_GET['showParent'] ) && $_GET['showParent'] == 'true' ) {
					$campaignRecords = $response_childCampaigns->records;
				} else {
					array_push($campaignRecords, $response_campaigndetails->records[0]);
				}
				$content = '';
				for( $i = 0; $i < count( $campaignRecords ); $i++ ) {
					$campRecord = new SObject( $campaignRecords[ $i ] );

					$content .= '<h2>' . $campRecord->fields->Name . '</h2>';
					$content .= '<p>Location : ' . $campRecord->fields->Location__c . '</p>';
					$content .= '<p>' . $campRecord->fields->Description . '</p>';

					$content .= '
						<table>
							<tr>
								<th>Start Date</th>
								<th>End Date</th>
								<th>Registration Fee</th>
							</tr>
							<tr>
								<td>' . $campRecord->fields->StartDate . '</td>
								<td>' . $campRecord->fields->EndDate . '</td>
								<td>$ ' . $campRecord->fields->Registration_Fee__c . '</td>
							</tr>
						</table>';


					$todayDate = date('Y-m-d');
					$todayDate = date('Y-m-d', strtotime($todayDate));
					$campRegStartDate = '';
					$campRegEndDate = '';

					if( $campRecord->fields->Registration_Date__c != '' && $campRecord->fields->Registration_Date__c != null ) {
						$campRegStartDate = date( 'Y-m-d', strtotime( $campRecord->fields->Registration_Date__c ) );
					}
					if( $campRecord->fields->Registration_End_Date__c != '' && $campRecord->fields->Registration_End_Date__c != null ) {
						$campRegEndDate = date( 'Y-m-d', strtotime( $campRecord->fields->Registration_End_Date__c ) );
					}

					if ( $formCampaignMapping[ $campRecord->fields->Type ] ) {

						if ( $formCampaignMapping[ $campRecord->fields->Type ]->isIndividualRequest == 'true' ) {

							//check if today's date is between start and end date. If in between then only show sign up button
							if( ( $campRegStartDate != '' && $campRegEndDate != '' && ( $todayDate > $campRegStartDate ) && ( $todayDate < $campRegEndDate ) ) ||
							    ( $campRegStartDate != '' && $campRegEndDate == '' && ( $todayDate > $campRegStartDate ) ) ||
							    ( $campRegStartDate == '' && $campRegEndDate != '' && ( $todayDate < $campRegEndDate )  ) ||
							    ( $campRegStartDate == '' && $campRegEndDate == '' )
							) {
								$content .= '<p><a class="button" href="#ex'.$i.'" rel="modal:open">Sign Up</a></p>';
							} else {
								$content .= '<p>Program is not available for registration.</p>';
							}
							//show modal
							$content .= '
							<div id="ex'.$i.'" class="modal">
								<h4>Select Member</h4>
								<ul>';

							foreach ($response_currentaccount_contacts->records as $record_contact) {

								$record_contact = new SObject( $record_contact );

								$content .= '<li><a href="' . esc_url( home_url('/campaign/?cntid=' . $record_contact->Id . '&cmpid=' . $campRecord->Id . '&formid=' . $formCampaignMapping[ $campRecord->fields->Type ]->formNumber) ) . '">' . $record_contact->fields->Name . '</a></li>';
							}

							$content .= '</ul></div>';

						} else {
							//check if today's date is between start and end date. If in between then only show sign up button
							if( ( $todayDate > $campRegStartDate ) && ( $todayDate < $campRegEndDate ) ) {
								$content .= '<p><a class="button" href="' . esc_url( home_url('/campaign?cmpid=' . $campRecord->Id . '&cntid=' . $contactid . '&formid=' . $formCampaignMapping[ $campRecord->fields->Type ]->formNumber) ) . '">Sign Up</a></p>';
							} else {
								$content .= '<p>Program is not available for registration.</p>';
							}


						}

					}


				}//for loop
				return $content;
			} else {
				return '<p>Campaign not found.</p>';
			}
		}
	} else {

		return '<p>No campaign selected.</p>';

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

	// If $connectionData is a string, it means an error/exception occurred. Print the message
	if ( is_string($connectionData) ) {
		return $connectionData;
	}

	$response_user_info = $connectionData->response_user_info;
	$mySforceConnection = $connectionData->SforceConnectionToken;
	$contactid = $connectionData->currentContactId;
	$accountid = $connectionData->currentAccountId;

	$attrs = shortcode_atts( array(
		'showmyjobs' => 'true',
		'showalljobs' => 'false',
		'alloppslink' => 'false'
	), $atts );

	if ( ( isset($_GET['formid']) && $_GET['formid'] ) && shortcode_exists('formassembly') ) { //display form

		$formId = $_GET['formid'];
		return do_shortcode( '[formassembly formid=' . $formId . ']' );

	} else { //display list of volunteer jobs

		if( $attrs[ 'showmyjobs' ] == 'true' ) {
			$query_my_jobs = "select Id, Name, GW_Volunteers__Contact__r.Name, GW_Volunteers__Volunteer_Job__c, GW_Volunteers__Volunteer_Job__r.Name, GW_Volunteers__Start_Date__c, GW_Volunteers__Hours_Worked__c, GW_Volunteers__Volunteer_Job__r.GW_Volunteers__Campaign__c, GW_Volunteers__Volunteer_Job__r.GW_Volunteers__Campaign__r.Name from GW_Volunteers__Volunteer_Hours__c where GW_Volunteers__Contact__c='".$contactid."' AND GW_Volunteers__Start_Date__c > TODAY and GW_Volunteers__Start_Date__c = NEXT_N_DAYS:90";
		}
		if( $attrs[ 'showalljobs' ] == 'true' ) {
		
			$query_jobs = "select GW_Volunteers__Volunteer_Job__r.Id, GW_Volunteers__Volunteer_Job__r.GW_Volunteers__Campaign__r.Name, GW_Volunteers__Volunteer_Job__r.Name, GW_Volunteers__Volunteer_Job__r.GW_Volunteers__Description__c, GW_Volunteers__Start_Date_Time__c , GW_Volunteers__Duration__c from GW_Volunteers__Volunteer_Shift__c where GW_Volunteers__Volunteer_Job__r.GW_Volunteers__Display_on_Website__c = true AND GW_Volunteers__Start_Date_Time__c > TODAY and GW_Volunteers__Start_Date_Time__c = NEXT_N_DAYS:90";
		}
		$querySuccess = true;

		try {
			if( $attrs[ 'showmyjobs' ] == 'true'  ) {
				$response_myjobs = $mySforceConnection->query( $query_my_jobs );
			}
			if( $attrs[ 'showalljobs' ] == 'true'  ) {
				$response_jobs = $mySforceConnection->query( $query_jobs );
			}
		} catch( Exception $e ) {
			return 'Something went wrong :'.$e->getMessage();
			$querySuccess = false;
		}

		if( $querySuccess ) {
			$content = '';

			
			if( $attrs[ 'showmyjobs' ] == 'true' ) {	//show my jobs only when it is set in shortcode
				$content .=	'
				<div>
					<table width="100%">
					<tr>
					<td style="border-top:none;">
						<h4><b>Your Volunteer Jobs</b></h4>
					</td><td>';
					if( $attrs['alloppslink'] == 'true' ) {
						$content .= '<a href="' . esc_url( home_url('/volunteer-jobs') ) . '" class="alignright">More</a>';
					}
				$content .=	'
					</td>
					</tr>';
				
				
				if( count( $response_myjobs->records ) > 0 ) {

					foreach ( $response_myjobs->records as $record_myjob ) {
						$record_myjob = new SObject( $record_myjob );
						$jobRec = new SObject( $record_myjob->fields->GW_Volunteers__Volunteer_Job__r );
						if( $jobRec->fields ) {
							$campaignRec = new SObject( $jobRec->fields->GW_Volunteers__Campaign__r );
						}
						$content .=	'
                        <tr>
                            <td>
                                <div><b>';

						if( $jobRec->fields ) {
							$content .= $jobRec->fields->Name ;
						}

						$content .= '</b></div>';
					}

					$content .= '<div>';

					if( $campaignRec && $campaignRec->fields ) {
						$content .= $campaignRec->fields->Name ;
					}
					$content .= '</div>';

					$content .= '<div>';

					$content .= '<span style="margin-left:7px;">';

					$date = date_create( $record_myjob->fields->GW_Volunteers__Start_Date__c );
					$content .= date_format($date,"F d, Y");

					$content .= '</span></div>';

					$content .= '
							</td>';
					$content .= '
						<td>
							<div align="right"><a href="' . esc_url( home_url('/volunteer-jobs?jobid=' . $jobRec->Id . '&cntid=' . $contactid . '&formid=4713591') ) . '" class="button small">View</a></div>
						</td>
                    </tr>';

				} else {
					$content .= '<tr> <td> <p>No opportunities found</p></td><td></td></tr>';
				}

				$content .= '
						</table>
					</div>';
			}

		}

		if( $attrs[ 'showalljobs' ] == 'true' ) {	//show my jobs only when it is set in shortcode

			$content .= '
				<br/>
				<div>
					<div class="clearfix">
						<h4 class="alignleft">Volunteer Opportunties</h4>';

			$content .= '
				</div>
				<table width="100%">';

			if( count( $response_jobs->records ) > 0 ) {
				foreach ( $response_jobs->records as $record_job ) {
					$record_job = new SObject( $record_job );
					$jobRec = new SObject( $record_job->fields->GW_Volunteers__Volunteer_Job__r );
					if( $jobRec->fields ) {
						$campaignRec = new SObject( $jobRec->fields->GW_Volunteers__Campaign__r );
					}
					$content .= '
							<tr>
								<td>
									<div><b>';

					if( $jobRec->fields ) {
						$content .= $jobRec->fields->Name;
					}

					$content .= '</b></div>';

					$content .= '<div>';

					if( $campaignRec && $campaignRec->fields ) {
						$content .= $campaignRec->fields->Name;
					}

					$content .= '<span style="margin-left:7px;">';
					$date = date_create( $record_job->fields->GW_Volunteers__Start_Date_Time__c );
					$content .= date_format($date,"F d, Y");

					$content .= '</span>
							</div>
						</td>';

					$content .= '
						<td>
							<div align="right"><a href="' . esc_url( home_url('/volunteer-jobs?jobid=' . $jobRec->Id . '&cntid=' . $contactid . '&formid=4713591') ) . '" class="button small">View</a></div>
						</td>
					</tr>';
				}

			} else {
				$content .= '<p>No opportunities found</p>';
			}

			$content .= '</table>
					</div>';
		}

		return $content;

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

	// If $connectionData is a string, it means an error/exception occurred. Print the message
	if ( is_string($connectionData) ) {
		return $connectionData;
	}

	$response_user_info = $connectionData->response_user_info;
	$mySforceConnection = $connectionData->SforceConnectionToken;
	$contactid = $connectionData->currentContactId;
	$accountid = $connectionData->currentAccountId;

	$query_opportunity_recordtype = "select Id from RecordType where Name ='Donation' ";

	$querySuccess = true;
	try {
		$response_opportunity_recordtype = $mySforceConnection->query( $query_opportunity_recordtype );
	} catch( Exception $e ) {
		return 'Something went wrong :'.$e->getMessage();
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
				return 'Something went wrong :'.$e->getMessage();
				$querySuccessInner = false;
			}
			if( $querySuccessInner ) {
				if( count( $response_opportunities->records ) > 0 ) {

					$content = '
					<table>
						<thead>
							<tr>
								<th>Contact Name</th>
								<th>Date</th>
								<th>Campaign</th>
								<th>Amount</th>
							</tr>
						</thead>
						<tbody>';

					foreach ( $response_opportunities->records as $record_opportunity ) {
						$record_opportunity = new SObject( $record_opportunity );
						$tempContactRec = new SObject( $record_opportunity->fields->npsp__Primary_Contact__r );
						$content .= '
                        <tr>
                            <td>';
						if(  $tempContactRec->fields ) {
							$content .= $tempContactRec->fields->Name;
						}
						$content .= '
						    </td>
                            <td>' . $record_opportunity->fields->CloseDate . '</td>
                            <td>';
						$camp = new SObject( $record_opportunity->fields->Campaign );
						$content .= ( $camp->fields && $camp->fields->Name ) ? $camp->fields->Name : 'General';
						$content .= '</td>
                            <td>' . $record_opportunity->fields->Amount . '</td>
                        </tr>';
					}

					$content .= '
						</tbody>
					</table>';

					return $content;
				} else {
					return '<p>No donations found</p>';
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

	// If $connectionData is a string, it means an error/exception occurred. Print the message
	if ( is_string($connectionData) ) {
		return $connectionData;
	}

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
			return 'Something went wrong :'.$e->getMessage();
			$querySuccess = false;
		}
		$content = '';
		if( $querySuccess ) {
			$content .= '<p>';
			if( $attrs['year'] == 'current' ) {
				$content .= 'Total Donation This Year : ';
			} elseif( $attrs['year'] == 'last' ) {
				$content .= 'Total Donation Last Year : ';
			}
			if( count( $response_totaldonation->records ) > 0 ) {

				$totaldonationrecord = new SObject( $response_totaldonation->records[0] );

				if( $totaldonationrecord->fields->expr0 != '' && $totaldonationrecord->fields->expr0 != null ) {
					$content .= '$'.$totaldonationrecord->fields->expr0;
				} else {
					$content .= '$0';
				}
			} else {
				$content .= '$0';
			}
			$content .= '</p>';
		}
		return $content;
	} else {
		return '<p>Donation record type not found</p>';
	}
}

// Shortcode to display total number of volunteer working hours for current year or last year [focus_totaldonation]
add_shortcode( 'focus_totalvolunteerhours', 'render_focus_total_volunteer_hours' );

function render_focus_total_volunteer_hours( $atts ) {
	// Do not render shortcode in the admin area
	if ( is_admin() ) {
		return;
	}

	$connectionData = connectWPtoSFandGetUserInfo();

	// If $connectionData is a string, it means an error/exception occurred. Print the message
	if ( is_string($connectionData) ) {
		return $connectionData;
	}

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
		return 'Something went wrong :'.$e->getMessage();
		$querySuccess = false;
	}
	$content = '';
	if( $querySuccess ) {
		$content .= '<p>';
		if( $attrs['year'] == 'current' ) {
			$content .= 'Total Volunteer Hours This Year : ';
		} elseif( $attrs['year'] == 'last' ) {
			$content .= 'Total Volunteer Hours Last Year : ';
		}
		if( count( $response_totalvolunteerhours->records ) > 0 ) {
			$totalvolunteerhoursrecord = new SObject( $response_totalvolunteerhours->records[0] );
			if( $totalvolunteerhoursrecord->fields->expr0 != '' && $totalvolunteerhoursrecord->fields->expr0 != null ) {
				$content .= $totalvolunteerhoursrecord->fields->expr0;
			} else {
				$content .= '0';
			}
		} else {
			$content .= '0';
		}
		$content .= '</p>';
		return $content;
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

	$connectionData = connectWPtoSFandGetUserInfo();

	// If $connectionData is a string, it means an error/exception occurred. Print the message
	if ( is_string($connectionData) ) {
		return $connectionData;
	}

	$response_user_info = $connectionData->response_user_info;
	$mySforceConnection = $connectionData->SforceConnectionToken;
	$contactid = $connectionData->currentContactId;
	$accountid = $connectionData->currentAccountId;

	$iframe = '<iframe src="' . $attrs['pagelink'] . '?id=' . $contactid . '" width="' . $attrs['iframewidth'] . '" height="' . $attrs['iframeheight'] . '"></iframe>';

	return $iframe;

}

// Display focus account information [focus_accountinfo]
add_shortcode( 'focus_accountinfo', 'render_focus_account_information' );

function render_focus_account_information($atts) {

	// Do not render sortcode in the admin area
	if ( is_admin() ) {
		return;
	}

	$attrs = shortcode_atts( array(
			'showupdateform' => 'false'
		), $atts );

	$connectionData = connectWPtoSFandGetUserInfo();

	// If $connectionData is a string, it means an error/exception occurred. Print the message
	if ( is_string($connectionData) ) {
		return $connectionData;
	}

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

	$content = '';

	$content .= '<div>
		<table width="100%">
			<tr>
    			<td valign="middle" width="60">' . get_avatar( $currentUser->ID, 50 ) . '</td>
    			<td>
				<p></p>
				<h4><b>' . $contactRec->fields->Account->npo02__Informal_Greeting__c . '</b></h4>';

	if( $contactRec->fields->Account->CreatedDate != '' ) {
		$content .= '<div><img src="https://image.flaticon.com/icons/svg/252/252091.svg" alt="" width="21"/>
                    <span>';

		$date = date_create( $contactRec->fields->Account->CreatedDate );
		$content .= 'Family Since '.date_format($date,"Y");

		$content .= '</span></div>';
	}

	$content .= '<p></p>
				</td>
			</tr>
			<tr>
				<td colspan="2">
				<div>
					<p></p>
					<h4 class="alignleft"><b>Activity</b></h4>
					<table width="100%">
						<tr>
							<td>
								<b>$' . $contactRec->fields->Account->npo02__TotalOppAmount__c . '</b> <span>Donations ' .  date("Y") . '</span>
							</td>
							<td width="50">
								<div align="right"><span class="alignright"><a style="white-space: nowrap;" href="' . esc_url( home_url('/donations') ) . '">View All</a></span></div>
							</td>
						</tr>
						<tr>
							<td>
								<b>' . $contactRec->fields->GW_Volunteers__Volunteer_Hours__c . '</b> <span>Volunteer Hours</span>
							</td>
							<td width="50">
								<div align="right"><span class="alignright"><a style="white-space: nowrap;" href="' . esc_url( home_url('/volunteers') ) . '">View All</a></span></div>
							</td>
						</tr>
						<tr>
							<td>
								<b>$' . $contactRec->fields->Account->Total_Due__c . '</b> <span>Total Amount Due</span>
							</td>
							<td width="50">
								<div align="right"><span class="alignright"><a style="white-space: nowrap;" href="' . esc_url( home_url('/programs') ) . '">View All</a></span></div>
							</td>
						</tr>
						<tr><td colspan="2"><div>Focus + Fragile Thanks You!</div></td></tr>
					</table>
				</div>
				</td>
			</tr>
			<tr>
				<td colspan="2" style="border-top:none;">
				<h4 class="alignleft"><b>User Information</b></h4>
				<table width="100%">';

	if( $BillingAddress != '' ) {
		$content .= '<tr>
							<td width="25">
								<img src="https://image.flaticon.com/icons/svg/252/252106.svg" alt="" width="20"/>
							</td>
							<td>
								<div>' . $BillingAddress . '</div>
							</td>
						</tr>';
	}

	if( $contactRec->fields->Account->Phone != '' ) {
		$content .= '<tr>
							<td width="25">
								<img src="https://image.flaticon.com/icons/svg/252/252050.svg" alt="" width="20"/>
							</td>
							<td>
								<div>' . $contactRec->fields->Account->Phone . '</div>
							</td>
						</tr>';
	}

	if( $contactRec->fields->Account->Primary_Email__c != '' ) {
		$content .= '<tr>
							<td width="25">
								<img src="https://image.flaticon.com/icons/svg/252/252049.svg" alt="" width="20"/>
							</td>
							<td>
								<div><a href="mailto:' . $contactRec->fields->Account->Primary_Email__c . '">' . $contactRec->fields->Account->Primary_Email__c . '</a></div>
							</td>
						</tr>
					</table>
                </td>
			</tr>';
	}
	if( $attrs[ 'showupdateform' ] <> '' ) { 
	$content .= '
			<tr><td colspan="2" style="border-top:none;"><a href="https://www.tfaforms.com/'. $attrs[ 'showupdateform' ] . '?actid='.$accountid . '">Update Family Information</a></td><tr>';
	}
	$content .= '
		</table>
	</div>';

	return $content;

}

// Display focus family dashboard [focus_familydashboard]
add_shortcode( 'focus_donation_volunteer_details', 'render_donation_volunteer_details' );

function render_donation_volunteer_details() {
	// Do not render shortcode in the admin area
	if ( is_admin() ) {
		return;
	}

	$connectionData = connectWPtoSFandGetUserInfo();

	// If $connectionData is a string, it means an error/exception occurred. Print the message
	if ( is_string($connectionData) ) {
		return $connectionData;
	}

	$response_user_info = $connectionData->response_user_info;
	$mySforceConnection = $connectionData->SforceConnectionToken;
	$contactid = $connectionData->currentContactId;
	$accountid = $connectionData->currentAccountId;
	$contactRec = $connectionData->contactRecord;
	$siteURL = get_site_url();

	$content = '';

	$content .= '
	<div>
		<p>Thank you!</p>
		<div>
			<div>
				<h2>$' . $contactRec->fields->Account->npo02__TotalOppAmount__c . '</h2>
				<a href="' . esc_url( home_url('/donations') ) . '">View All</a>
				<div>Donations ' . date("Y") . '<br/>';

	if( $contactRec->fields->Account->Level__r ) {
		$content .= '<b>' . $contactRec->fields->Account->Level__r->Name . ' Level Donor</b>';
	}

	$content .= '
				</div>
			</div>
			<div>
				<p>&nbsp;</p>
				<h2><b>' . $contactRec->fields->GW_Volunteers__Volunteer_Hours__c . '</b></h2>
				<a href="' . esc_url( home_url('/volunteers') ) . '">View All</a>
				<div>Volunteer Hours</div>
			</div>
		</div>
	</div>';

	return $content;
}

// Display focus family dashboard [focus_familydashboard]
add_shortcode( 'focus_familydashboard', 'render_focus_family_dashboard' );

function render_focus_family_dashboard($atts) {

	$attrs = shortcode_atts( array(
		'showupdateform' => '',
		'featuredlimit' => '3',
		'upcominglimit' => '5'
	), $atts );

	$content = '
	<div>
		<div class="clearfix">
			<div class="one-half first">' . do_shortcode( '[focus_accountinfo showupdateform="'.$attrs['showupdateform'].'"]' ) . '</div>
			<div class="two-fourths">' . do_shortcode( '[focus_programs featuredlimit="'.$attrs['featuredlimit'].'" upcominglimit="'.$attrs['upcominglimit'].'" alleventslink="true"]' ) . do_shortcode( '[focus_volunteers alloppslink="true"]' ) . '
			</div>
		</div>
	</div>';

	return $content;

}
