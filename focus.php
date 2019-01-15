<?php
/**
 * Plugin Name: FOCUS Web portal
 * Plugin URI: http://www.focus-ga.com/WordPress/focus
 * Description: Cloudland Technologies - FOCUS Member Portal
 * Version: 1.0
 * Author: Paul Cannon
 * Author URI: http://www.cloudlandtechnologies.com
 */

function wp_focus_program(){

$currentUser = wp_get_current_user();

define( 'FOCUS__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

define("USERNAME", "api@focus-ga.org.Partial");
define("PASSWORD", "xhYHMa7RLMvDTFH");
define("SECURITY_TOKEN", "8ofo544KVNo3cxsY8qCf0koA");

//define("USER_EMAIL", $current_user->user_login);
define("USER_EMAIL", $currentUser->user_email);

require_once (FOCUS__PLUGIN_DIR . 'soapclient/SforcePartnerClient.php');

$mySforceConnection = new SforcePartnerClient();
$mySforceConnection->createConnection(FOCUS__PLUGIN_DIR . "PartnerWSDL.xml");
$mySforceConnection->login(USERNAME, PASSWORD.SECURITY_TOKEN);

$query_user_info = "select id, Name, accountid from contact where Contact.email = '".USER_EMAIL."'";
$response_user_info = $mySforceConnection->query($query_user_info);
$siteURL = get_site_url();
//if respective contact found at SF then only show programs
if( count( $response_user_info->records ) > 0 ) { 
	$contactid = $response_user_info->records[0]->Id;
	$accountid = $response_user_info->records[0]->fields->AccountId;
	$currentContactName = $response_user_info->records[0]->fields->Name;

	$query_programs_signedup = "select Contact.Name, Contact.Id, Campaign.name, Campaign.StartDate, Campaign.Type from campaignmember where contactid in (select Contact.id from Contact where Contact.accountid = '".$accountid."')";
	$response_programs_signedup = $mySforceConnection->query($query_programs_signedup);


	$query_programs_scheduled = "select Id, Name, StartDate, Registration_Fee__c, isactive, Type, RecordTypeId, ID__c from Campaign where isactive=true and startdate > TODAY and startdate = NEXT_N_DAYS:90";
	$response_programs_scheduled = $mySforceConnection->query($query_programs_scheduled);

	?>
		<h2>My Programs</h2>
		<table>
			<tr>
				<th></th>
				<th>Name </th>
				<th>Program </th>
				<th>Date</th>
			</tr>
	<?php
			foreach ($response_programs_signedup->records as $record_signedup) {
				echo '<tr>
					<td><i class="fa fa-calendar"></i></td>
					<td>'.$record_signedup->fields->Contact->fields->Name.'</td>
					<td>'.$record_signedup->fields->Campaign->fields->Name.'</td>
					<td>'.$record_signedup->fields->Campaign->fields->StartDate.'</td>
				</tr>';
			}
	?>
		</table>
		<br/>
		<h2>Featured Programs</h2>
		<table>
			<tr>
				<th></th>
				<th>Program </th>
				<th>Date</th>
				<th>Cost</th>
				<th>Sign Up</th>
			</tr>
		<?php
			foreach ($response_programs_scheduled->records as $record_scheduled) {
				$addtolist = TRUE;
				foreach ($response_programs_signedup->records as $record_signedup) {
					if ($record_signedup->fields->Campaign->fields->Name == $record_scheduled->fields->Name) {
						$addtolist = FALSE;
					}
				}
				if ($addtolist == TRUE) {
					echo '<tr>
						<td><i class="fa fa-calendar"></i></td>
						<td>'.$record_scheduled->fields->Name.'</td>
						<td>'.$record_scheduled->fields->StartDate.'</td>
						<td>'.$record_scheduled->fields->Registration_Fee__c.'</td>
						<td>';
							echo '<a href="'.$siteURL.'/campaign-details?id='.$record_scheduled->Id.'">More Details</a>';	
					echo '</td>
					</tr>';
				}
			}
		?>
		</table>
	<?php		
	} else { //if not respective contact as WP user found at SF then display message
		echo '<p>We can not find your record at FOCUS. Please call administrator for more details</p>';
	}
}
add_shortcode('focus_programs','wp_focus_program');

?>
