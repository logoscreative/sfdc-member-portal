<?php
/**
 * Plugin Name: SFDC Web portal
 * Plugin URI: http://www.focus-ga.org/
 * Description: Non-Profit SF Member Portal
 * Version: 3.0
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
			//has_shortcode( $post_content, 'sfdc_programs' ) ||
			has_shortcode( $post_content, 'sfdc_featured_programs' ) ||
			has_shortcode( $post_content, 'sfdc_upcoming_programs' ) ||
			has_shortcode( $post_content, 'sfdc_campaign' ) ||
			has_shortcode( $post_content, 'sfdc_volunteers' ) ||
			has_shortcode( $post_content, 'sfdc_donations' ) ||
			has_shortcode( $post_content, 'sfdc_totaldonations' ) ||
			has_shortcode( $post_content, 'sfdc_totaldvolunteerhours' ) ||
			has_shortcode( $post_content, 'sfdc_volunteercalendar' ) ||
			has_shortcode( $post_content, 'sfdc_accountinfo' ) ||
			has_shortcode( $post_content, 'sfdc_donation_volunteer_details' )
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
		return '<p>We can not find your records. Please call administrator for more details</p>';
	}
	return (object) [
		"response_user_info"  => $response_user_info,
		"SforceConnectionToken" => $mySforceConnection,
		"currentContactId" => $contactid,
		"currentAccountId" => $accountid,
		"contactRecord" => $contactRec
	];
}


function callRestAPI( $requestUrl ) {
	//call rest api service from salesforce to fetch featured campaigns
	$curlError = 'false';

	$errorMsg = '';

	//$url = "https://test.salesforce.com/services/oauth2/token";
	$url = "https://login.salesforce.com/services/oauth2/token ";


	$post = array(
		"grant_type" => "password",
		"client_id" => SFDC_MEMBER_PORTAL_CLIENT_ID,
		"client_secret" => SFDC_MEMBER_PORTAL_CLIENT_SECRET,
		//"username" => SFDC_MEMBER_PORTAL_SANDBOX_USERNAME,
		"username" => SFDC_MEMBER_PORTAL_USERNAME,
		//"password" => SFDC_MEMBER_PORTAL_SANDBOX_PASSWORD
		"password" => SFDC_MEMBER_PORTAL_PASSWORD
	);

	$postText = http_build_query($post);

	$curl = curl_init();

	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $postText);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

	$json_response = curl_exec($curl);

	$status = curl_getinfo( $curl, CURLINFO_HTTP_CODE );

	if ( $status != 200 ) {
		$errorMsg = "Error: call to token URL $url failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno( $curl );
		$curlError = 'true';
	}

	curl_close($curl);

	if( $curlError == 'false' ) {

		$response = json_decode( $json_response, true );

		if( isset( $response['access_token'] ) && isset( $response['instance_url'] ) ) {

			$access_token = $response['access_token'];
			$instance_url = $response['instance_url'];

			$url = $instance_url.$requestUrl;

			$curl2 = curl_init($url);
			curl_setopt($curl2, CURLOPT_HEADER, false);
			curl_setopt($curl2, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl2, CURLOPT_HTTPHEADER,
				array("Authorization: OAuth $access_token"));

			$json_response2 = curl_exec($curl2);

			$status = curl_getinfo( $curl2, CURLINFO_HTTP_CODE );

			if ( $status != 200 ) {
				$errorMsg = "Error: call to token URL $url failed with status $status, response $json_response2, curl_error " . curl_error($curl2) . ", curl_errno " . curl_errno( $curl2 );
				$curlError = 'true';
			}
		}
	}
	if( $curlError == 'true' ) {
		$data = $errorMsg;
	} else {
		$data = $json_response2;
	}

	//$data => failure message or return data
	return (object) [
		"isError"  => $curlError,
		"data" => $data
	];
}

function render_wp_card( $card_name,  $apiResponse, $more_btn, $attrs )
{
	$wpCardContent = '<table>';
	$wpCardContent .= '<tr>
	<td style="border-top:none;">
		<h4><b>'.$card_name.'</b></h4>
	</td><td>';
	if( $attrs && $attrs['alleventslink'] != '' ) {
		$wpCardContent .= '<a href="' . esc_url( home_url('"'.$more_btn.'"') ) . '" class="alignright">More</a>';
	}
	$wpCardContent .=	'
	</td>
	</tr>';
	if( isset( $apiResponse ) && $apiResponse->isError == 'false' ) {
		$wpCardData = json_decode (json_decode( $apiResponse->data  ));

		if( isset( $wpCardData->fieldsWrapperList ) ) {

			if( isset( $wpCardData->recordList ) && count( $wpCardData->recordList ) > 0 ) {
				$idx = 0;
				foreach( $wpCardData->recordList as $recordData ) {

					if( $card_name == 'Upcoming Events' ) {
						if( isset( $attrs['upcominglimit'] ) && $attrs['upcominglimit'] != '' && $idx == $attrs['upcominglimit'] ) {
							break;
						}
					} else {
						if( isset( $attrs['featuredlimit'] ) && $attrs['featuredlimit'] != '' && $idx == $attrs['featuredlimit'] ) {
							break;
						}
					}

					$wpCardContent .= '<tr>';
					$wpCardContent .= '<td>';

					$firstFieldBold = true;
					foreach ($wpCardData->fieldsWrapperList as $fieldData ) {
						//$wpCardContent .= '<td>';
						$record = new SObject( $recordData);
						$record = get_object_vars( $record );
						$relFieldName = $fieldData->name;
						if( isset( $record['campaignRecord']->$relFieldName ) ) {
							if ($firstFieldBold == true) {
								$wpCardContent .= '<b>';
							}
							//$fieldValue = $recordData->campaignRecord->$relFieldName;
							$fieldValue1 = trim($recordData->campaignRecord->$relFieldName,' ');
							$fieldValue2 = trim($fieldValue1, '<br>');
							$fieldValue = trim($fieldValue2);
							//$wpCardContent .= ;

							if($fieldData->isDateTime) {

								$date = date_create( $fieldValue );
								$wpCardContent .= date_format( $date,"F d, Y" );

							}
							else if($fieldData->isCurrency) {
								$wpCardContent .= '$ '.trim($fieldValue);
							}
							else if($fieldData->isCheckbox) {
								if($fieldValue == 1) {
									$wpCardContent .= 'Yes';
								}
								else {
									$wpCardContent .= 'No';
								}
							}
							else {
								$wpCardContent .= trim($fieldValue);
							}

							if ($firstFieldBold == true) {
								$wpCardContent .= '</b>';
								$firstFieldBold = false;
							}
							$wpCardContent .= '<br/>';
						}

					}
					$wpCardContent .= '</td>';
					$wpCardContent .= '<td>';
					//view link
					$showParentParam = '&showParent=true';
					if( $card_name == 'Upcoming Events' ) {
						$showParentParam = '&showParent=false';
					}

					$wpCardContent .= '<div align="right">
                                <a class="button small" href="' . esc_url( home_url('/campaign?cmpid=' . $recordData->campaignRecord->Id . $showParentParam ) ) . '">';
					if( isset( $wpCardData->landingPageButtonLink ) ) {
						$wpCardContent .= $wpCardData->landingPageButtonLink;
					} else {
						$wpCardContent .= 'View';
					}
					$wpCardContent .= '</a>
                            </div>';
					$wpCardContent .= '</td>';
					$wpCardContent .= '</tr>';
					$idx++;
				
					//Pay now link
					if( isset( $recordData->payNowLink ) && $recordData->payNowLink != '' ) {
						$wpCardContent .= '<tr>';
						$wpCardContent .= '<td>';

						$wpCardContent .= $recordData->payNowLink;


						$wpCardContent .= '</td>';
						$wpCardContent .= '</tr>';
					}
					
				}

			} else {
				$wpCardContent .= '<tr><td colspan="'.count( $wpCardContent->fieldsWrapperList ).'">No data found</td></tr>';
			}

			$wpCardContent .= '</table>';
		} else {
			$wpCardContent .= '<tr>
			<td style="border-top:none;" colspan="2" >
				<h4> No Data Found </h4>
			</td>
			</tr> </table>';

		}

	}  else {

		$wpCardContent .= '<tr>
			<td style="border-top:none;" colspan="2" >
				<h4> No Data Found </h4>
			</td>
			</tr> </table>';

		//$wpCardContent .= $apiResponse->data;	// show error message if failed
	}

	return $wpCardContent;

}
// Add sfdc_feature program  shortcode [sfdc_featured_programs]
add_shortcode( 'sfdc_featured_programs', 'wp_sfdc_featured_programs' );

function wp_sfdc_featured_programs( $atts ) {
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
	//exit();
	$accountid = $connectionData->currentAccountId;
	$contactRec = $connectionData->contactRecord;

	$attrs = shortcode_atts( array(
		'featuredlimit' => '',
		'upcominglimit' => '',
		'alleventslink' => ''
	), $atts );

	//call rest api service from salesforce to fetch featured campaigns
	$url = "/services/apexrest/v1/programs?cntid=".$contactid."&featured=true";
	//$url = "/services/apexrest/v1/programs?cntid=0035600000HZSjc&featured=true";

	/*if( $attrs && $attrs['featuredlimit'] != '' ) {
		$url .= '&limit='.$attrs['featuredlimit'];
	}*/

	$featuredEventsAPIResponse = callRestAPI( $url );

	$featuredEventsContent = render_wp_card( "Featured Events", $featuredEventsAPIResponse,'programs', $attrs);
	//return $featuredEventsContent.$upcomingEventsContent;
	return $featuredEventsContent;
}


// Add sfdc upcoming Programs shortcode [sfdc_upcoming_programs]
add_shortcode( 'sfdc_upcoming_programs', 'wp_sfdc_upcoming_programs' );

function wp_sfdc_upcoming_programs( $atts ) {

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

	//call rest api service from salesforce to fetch upcoming campaigns
	$url = "/services/apexrest/v1/programs?cntid=".$contactid."&upcoming=true";
	//$url = "/services/apexrest/v1/programs?cntid=0035600000HZSjc&upcoming=true";
	/*if( $attrs && $attrs['upcominglimit'] != '' ) {
		$url .= '&limit='.$attrs['upcominglimit'];
	}*/
	$upcomingEventsAPIResponse = callRestAPI( $url );
	$upcomingEventsContent = render_wp_card( "Upcoming Events", $upcomingEventsAPIResponse, 'programs', $attrs);
	return $upcomingEventsContent;
}

// Add Campaigns shortcode [sfdc_campaign]
add_shortcode( 'sfdc_campaign', 'render_sfdc_campaign_landing_page' );

function render_sfdc_campaign_landing_page() {

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
		// return do_shortcode( '[formassembly formid=' . $formId . ' iframe=true]' );
		return '<p><a href="https://tfaforms.com/' . $formId . '?' . $_SERVER['QUERY_STRING'] . '" target="_blank">Open Form in New Window</a></p>' . do_shortcode( '[formassembly formid=' . $formId . ' iframe=true]' );

	} elseif( isset( $_GET['cmpid'] ) && $_GET['cmpid'] ) {

		$query_currentaccount_contacts    = "select Id, Name from Contact where AccountId = '" . $accountid . "'";

		$query_campaigndetails    = "select Id, Name, Description, Location__c, StartDate, EndDate, Registration_Fee__c, isActive, Type, Registration_Date__c, Registration_End_Date__c, Parent.Id  from Campaign where Id='" . sanitize_text_field($_GET['cmpid']) . "'";

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

			//print_r($response_campaigndetails);
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

// Add Volunteer Jobs [sfdc_volunteers]
add_shortcode( 'sfdc_volunteers', 'render_sfdc_volunteer_landing_page' );


function render_wp_card_volunteer( $card_name,  $apiResponse, $more_btn, $attrs, $contactid )
{
	$wpCardContent = '<table>';
	$wpCardContent .= '<tr>
	<td style="border-top:none;">
		<h4><b>'.$card_name.'</b></h4>
	</td><td>';
	if( $attrs['alloppslink'] == 'true' ) {
		$wpCardContent .= '<a href="' . esc_url( home_url('"'.$more_btn.'"') ) . '" class="alignright">More</a>';
	}
	$wpCardContent .=	'
	</td>
	</tr>';
	if( isset( $apiResponse ) && $apiResponse->isError == 'false' ) {
		$wpCardData = json_decode (json_decode( $apiResponse->data  ));
		/*echo "<pre>";
		print_r($wpCardData);
		exit(); */
		if( isset( $wpCardData->fieldsWrapperList ) ) {

			if( isset( $wpCardData->recordList ) && count( $wpCardData->recordList ) > 0 ) {
				foreach( $wpCardData->recordList as $recordList) {

					$wpCardContent .= '<tr>';
					$wpCardContent .= '<td>';
					foreach( $recordList as $key=>$record1 ) {
						if($record1 == '')
							continue;
						$firstFieldBold = true;
						foreach ($wpCardData->fieldsWrapperList as $fieldData ) {
							$record = new SObject( $record1 );
							$record = get_object_vars( $record );
							$fieldName = $fieldData->name;
							$pos = strrpos($fieldData->name, ".");
							if($pos)
							{
								$relField = explode('.', $fieldData->name);
								$fieldName = $relField[1];
								if( isset( $record[$relField[0]] ) ) {
									$relFieldName = $relField[1];
									if( isset( $record[$relField[0]]->$relFieldName) ) {
										if ($firstFieldBold == true) {
											$wpCardContent .= '<b>';
										}

										$fieldValue1 = trim($record[$relField[0]]->$relFieldName,' ');
										$fieldValue2 = trim($fieldValue1, '<br>');
										$fieldValue = trim($fieldValue2);
										if($fieldData->isDateTime) {
											//$wpCardContent .= Date('Y-m-d h:s A',strtotime($fieldValue));
											$date = date_create( $fieldValue );
											$wpCardContent .= date_format( $date,"F d, Y" );
										}
										else if($fieldData->isCurrency) {
											$wpCardContent .= '$ '.trim($fieldValue);
										}
										else if($fieldData->isCheckbox) {
											if($fieldValue == 1)
												$wpCardContent .= 'Yes';
											else
												$wpCardContent .= 'No';
										}
										else {
											$wpCardContent .= trim($fieldValue);
										}

										if ($firstFieldBold == true) {
											$wpCardContent .= '</b>';
										}

										$wpCardContent .= '<br/>';
									}
								}
							}
							else
							{
								if( isset( $record[ $fieldName ] ) ) {
									if ($firstFieldBold == true) {
										$wpCardContent .= '<b>';
									}

									//$fieldValue = strip_tags($record[ $fieldName ], '<br/>');
									$fieldValue1 = trim($record[ $fieldName ],' ');
									$fieldValue2 = trim($fieldValue1, '<br>');
									$fieldValue = trim($fieldValue2);
									if($fieldData->isDateTime) {
										//$wpCardContent .= Date('Y-m-d h:s A',strtotime($fieldValue));
										$date = date_create( $fieldValue );
										$wpCardContent .= date_format( $date,"F d, Y" );
									}
									else if($fieldData->isCurrency) {
										$wpCardContent .= '$ '.trim($fieldValue);
									}
									else if($fieldData->isCheckbox) {
										if($fieldValue == 1)
											$wpCardContent .= 'Yes';
										else
											$wpCardContent .= 'No';
									}
									else {
										$wpCardContent .= trim($fieldValue);
									}

									if ($firstFieldBold == true) {
										$wpCardContent .= '</b>';
									}

									$wpCardContent .= '<br/>';
								}
							}
							$firstFieldBold = false;
						}
					}
					$wpCardContent .= '</td>';

					//view link
					$viewBtnText = 'View';
					if( isset( $wpCardData->landingPageButtonLink ) ) {
						$viewBtnText = $wpCardData->landingPageButtonLink;
					}
					$wpCardContent .= '<td>';

					$wpCardContent .= '<div align="right"><a href="' . esc_url( home_url('/volunteer-jobs?jobid=' . $recordList->volunteerJobRecord->Id . '&cntid=' . $contactid . '&formid=4713591') ) . '" class="button small">'.$viewBtnText.'</a></div>';

					$wpCardContent .= '</td>';

					$wpCardContent .= '</tr>';
				}

			} else {
				$wpCardContent .= '<tr><td colspan="'.count( $wpCardContent->fieldsWrapperList ).'">No data found</td></tr>';
			}

			$wpCardContent .= '</table>';
		} else {
			$wpCardContent .= '<tr>
			<td style="border-top:none;" colspan="2" >
				<h4> No Data Found </h4>
			</td>
			</tr> </table>';

		}

	}  else {

		$wpCardContent .= '<tr>
			<td style="border-top:none;" colspan="2" >
				<h4> No Data Found </h4>
			</td>
			</tr> </table>';

		//$wpCardContent .= $apiResponse->data;	// show error message if failed
	}

	return $wpCardContent;

}

function render_sfdc_volunteer_landing_page( $atts ) {

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
		'showalljobs' => 'true',
		'alloppslink' => 'true'
	), $atts );

	if ( ( isset($_GET['formid']) && $_GET['formid'] ) && shortcode_exists('formassembly') ) { //display form
		$formId = $_GET['formid'];
		//return do_shortcode( '[formassembly formid=' . $formId . ' iframe=true]' );
		return '<p><a href="https://tfaforms.com/' . $formId . '?' . $_SERVER['QUERY_STRING'] . '" target="_blank">Open Form in New Window</a></p>' . do_shortcode( '[formassembly formid=' . $formId . ' iframe=true]' );

	} else {
		$showmyjobs =  $showalljobs = $alloppslink =  '';
		//echo "<pre>";
		if( $attrs[ 'showmyjobs' ] == 'true' ) {
			$url = "/services/apexrest/v1/programs_volunteers/getVolunteerDetails?contactId=".$contactid."&showmyjobs=true";
			//$url = "/services/apexrest/v1/programs_volunteers/getVolunteerDetails?contactId=0035600000HZSjI&showmyjobs=true";
			$showMyjobsAPIResponse = callRestAPI( $url );
			$showmyjobs = render_wp_card_volunteer( "My Jobs", $showMyjobsAPIResponse,'volunteers', $attrs, $contactid );
		}

		if( $attrs[ 'showalljobs' ] == 'true' ) {
			$url = "/services/apexrest/v1/programs_volunteers/getVolunteerDetails?showalljobs=true";
			$showAlljobsAPIResponse = callRestAPI( $url );
			$showalljobs = render_wp_card_volunteer( "All Jobs", $showAlljobsAPIResponse,'volunteers', $attrs, $contactid );
		}
		return $showmyjobs.$showalljobs;

	}
}

// Add Volunteer Jobs [sfdc_volunteers]
add_shortcode( 'sfdc_donations', 'render_sfdc_donation_landing_page' );

function render_sfdc_donation_landing_page() {

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

// Shortcode to show total amount of donations current year or last year [sfdc_totaldonations]
add_shortcode( 'sfdc_totaldonations', 'render_sfdc_total_donation_amount' );

function render_sfdc_total_donation_amount( $atts ) {

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

// Shortcode to display total number of volunteer working hours for current year or last year [sfdc_totaldonations]
add_shortcode( 'sfdc_totaldvolunteerhours', 'render_sfdc_total_volunteer_hours' );

function render_sfdc_total_volunteer_hours( $atts ) {
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

// Add Volunteer Jobs [sfdc_volunteercalendar]
add_shortcode( 'sfdc_volunteercalendar', 'render_sfdc_volunteer_calendar' );

function render_sfdc_volunteer_calendar( $atts ) {

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

// Display sfdc account information [sfdc_accountinfo]
add_shortcode( 'sfdc_accountinfo', 'render_sfdc_account_information' );

function render_sfdc_account_information($atts) {

	// Do not render sortcode in the admin area
	if ( is_admin() ) {
		return;
	}

	$attrs = shortcode_atts( array(
		'showupdateform' => 'false',
		'debug' => 'false'
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

	if( $attrs[ 'debug' ] == 'true' ) {
		echo 'contactid: '.$contactid.'<br />';
		echo 'accountid: '.$accountid.'<br />';
		echo 'contactRec: '; print_r( $contactRec );
		echo '<br />';
		echo 'contactRec fields: '; print_r( $contactRec->fields );
		echo '<br />';
		echo 'contactRec fields account: '; print_r( $contactRec->fields->Account );
		echo '<br />';
		echo 'contactRec fields account informal greetings : '; print_r( $contactRec->fields->Account->npo02__Informal_Greeting__c );
		echo '<br />';
		echo 'contactRec fields account informal greetings333 : '; print_r( $contactRec->fields->Account->fields->npo02__Informal_Greeting__c );
		echo '<br />';
	}

	$BillingAddress = '';
	if( $contactRec->fields->Account->fields->BillingStreet ) {
		$BillingAddress .= $contactRec->fields->Account->fields->BillingStreet.' <br/>';
	}
	if( $contactRec->fields->Account->fields->BillingCity ) {
		$BillingAddress .= $contactRec->fields->Account->fields->BillingCity.', ';
	}
	if( $contactRec->fields->Account->fields->BillingState ) {
		$BillingAddress .= $contactRec->fields->Account->fields->BillingState.' ';
	}
	if( $contactRec->fields->Account->fields->BillingPostalCode ) {
		$BillingAddress .= $contactRec->fields->Account->fields->BillingPostalCode.' ';
	}
	$currentUser = wp_get_current_user();

	$content = '';

	$content .= '<div>
		<table width="100%">
			<tr>
    			<td valign="middle" width="60">' . get_avatar( $currentUser->ID, 50 ) . '</td>
    			<td>
				<p></p>
				<h4><b>' . $contactRec->fields->Account->fields->npo02__Informal_Greeting__c . '</b></h4>';

	if( $contactRec->fields->Account->fields->CreatedDate != '' ) {
		$content .= '<div><img src="https://image.flaticon.com/icons/svg/252/252091.svg" alt="" width="21"/>
                    <span>';

		$date = date_create( $contactRec->fields->Account->fields->CreatedDate );
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
								<b>$' . $contactRec->fields->Account->fields->npo02__TotalOppAmount__c . '</b> <span>Donations ' .  date("Y") . '</span>
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
								<b>$' . $contactRec->fields->Account->fields->Total_Due__c . '</b> <span>Total Amount Due</span>
							</td>
							<td width="50">
								<div align="right"><span class="alignright"><a style="white-space: nowrap;" href="' . esc_url( home_url('/programs') ) . '">View All</a></span></div>
							</td>
						</tr>
						<!--<tr><td colspan="2"><div>Focus + Fragile Thanks You!</div></td></tr>-->
					</table>
				</div>
				</td>
			</tr>
			<tr>
				<td colspan="2" style="border-top:none;">
				<h4 class="alignleft"><b>User Information</b></h4>
				<table width="100%">
				';

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

	if( $contactRec->fields->Account->fields->Phone != '' ) {
		$content .= '<tr>
							<td width="25">
								<img src="https://image.flaticon.com/icons/svg/252/252050.svg" alt="" width="20"/>
							</td>
							<td>
								<div>' . $contactRec->fields->Account->fields->Phone . '</div>
							</td>
						</tr>';
	}

	if( $contactRec->fields->Account->fields->Primary_Email__c != '' ) {
		$content .= '<tr>
						<td width="25">
							<img src="https://image.flaticon.com/icons/svg/252/252049.svg" alt="" width="20"/>
						</td>
						<td>
							<div><a href="mailto:' . $contactRec->fields->Account->fields->Primary_Email__c . '">' . $contactRec->fields->Account->fields->Primary_Email__c . '</a></div>
						</td>
					</tr>';
	}
	$content .= '
				</table>
				</td>
			</tr>';
	if( $attrs[ 'showupdateform' ] <> '' ) {
		$content .= '
			<tr>
				<td colspan="2" style="border-top:none;">
					<a href="https://www.tfaforms.com/'. $attrs[ 'showupdateform' ] . '?actid='.$accountid . '">Update Family Information</a>
				</td>
			</tr>';
	}
	$content .= '
		</table>
	</div>';

	return $content;

}

// Display focus family dashboard [sfdc_familydashboard]
add_shortcode( 'sfdc_donation_volunteer_details', 'render_donation_volunteer_details' );

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
				<h2>$' . $contactRec->fields->Account->fields->npo02__TotalOppAmount__c . '</h2>
				<a href="' . esc_url( home_url('/donations') ) . '">View All</a>
				<div>Donations ' . date("Y") . '<br/>';

	if( $contactRec->fields->Account->fields->Level__r ) {
		$content .= '<b>' . $contactRec->fields->Account->fields->Level__r->Name . ' Level Donor</b>';
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