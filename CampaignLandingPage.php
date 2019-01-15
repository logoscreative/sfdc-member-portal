<?php
/*
Template Name: CampaignLandingPage
*/
?>
<?php 
get_header();

if( $_GET['id'] ) {

	define("USERNAME", "api@focus-ga.org.Partial");
	define("PASSWORD", "xhYHMa7RLMvDTFH");
	define("SECURITY_TOKEN", "8ofo544KVNo3cxsY8qCf0koA");

	$currentUser = wp_get_current_user();

	define("USER_EMAIL", $currentUser->user_email);

	require_once (get_template_directory() . '/inc/soapclient/SforcePartnerClient.php');

	$mySforceConnection = new SforcePartnerClient();
	$mySforceConnection->createConnection( get_template_directory() . "/inc/PartnerWSDL.xml");
	$mySforceConnection->login(USERNAME, PASSWORD.SECURITY_TOKEN);

	$query_user_info = "select id, Name, accountid from contact where Contact.email = '".USER_EMAIL."'";
	$response_user_info = $mySforceConnection->query($query_user_info);

	if( count( $response_user_info->records ) > 0 ) { 

		$contactid = $response_user_info->records[0]->Id;
		$accountid = $response_user_info->records[0]->fields->AccountId;
		$currentContactName = $response_user_info->records[0]->fields->Name;

		$query_currentaccount_contacts = "select Id, ID__c, Name from Contact where AccountId = '".$accountid."'";
		$response_currentaccount_contacts = $mySforceConnection->query( $query_currentaccount_contacts );

		$query_campaigndetails = "select Id, ID__c, Name, Description, StartDate, EndDate, Registration_Fee__c, isactive, Type from Campaign where ID='".$_GET['id']."'";
		$response_campaigndetails = $mySforceConnection->query( $query_campaigndetails );

		//Fetch mapping of form and campaign type from SF custom object "Program Forms"
		$query_form_campaign_mapping = "select Name, Form_Number__c, Individual_Request__c from Program_Forms__c";
		$response_form_campaign_mapping = $mySforceConnection->query($query_form_campaign_mapping);
		$formCampaignMapping = array();
		foreach ( $response_form_campaign_mapping->records as $record_mapping ) {
			$programFormRecord = (object) [
			    "formNumber" => $record_mapping->fields->Form_Number__c,
			    "isIndividualRequest" => $record_mapping->fields->Individual_Request__c
			];
			//$formCampaignMapping[ $record_mapping->fields->Name ] = $record_mapping->fields->Form_Number__c;
			$formCampaignMapping[ $record_mapping->fields->Name ] = $programFormRecord;
		}

		$siteURL = get_site_url();

		if( count( $response_campaigndetails->records ) > 0 ) {

			$campaigndetails = $response_campaigndetails->records[0];
			?>

			<div class="content">
				<div class="pad group">
				
					<div class="page-title">
						<h2><?php echo $campaigndetails->fields->Name ?></h2>
					</div>

					<?php while ( have_posts() ): the_post(); ?>
				
						<article <?php post_class('entry boxed group'); ?>>						
		
							<div class="entry-inner pad">
								<div class="entry-content themeform">
									<div class="campaign-description">
										<?php echo $campaigndetails->fields->Description; ?>
									</div>
									<div class="">

									</div>
									<div class="campaign-duration gridDiv">
										<div class="colDiv">
											<label>Start Date :</label>
											<div class="elementParent"><?php echo $campaigndetails->fields->StartDate; ?></div>
										</div>
										<div class="colDiv">
											<label>End Date :</label>
											<div class="elementParent"><?php echo $campaigndetails->fields->EndDate; ?></div>
										</div>
									</div>
									<div class="campaign-details gridDiv">
										<div class="colDiv">
											<label>Campaign Type :</label>
											<div class="elementParent">
												<?php echo $campaigndetails->fields->Type; ?>
											</div>
										</div>
										<div class="colDiv">
											<label>Registration Fee :</label>
											<div class="elementParent">
												<?php echo $campaigndetails->fields->Registration_Fee__c; ?>
											</div>
										</div>
									</div>
									<div class="campaign-btn">
										<?php 
											if( $formCampaignMapping[ $campaigndetails->fields->Type ] ) {
												if( $formCampaignMapping[ $campaigndetails->fields->Type ]->isIndividualRequest == 'true' ) {
													//show modal
													echo '<a class="btnYellow" href="javascript:void(0);" onclick="showMembersModal()">Sign Up</a>';
												} else {
													echo '<a class="btnYellow" href="'.$siteURL.'/campaignregistration?cmpid='.$campaigndetails->fields->ID__c.'&cntid='.$contactid.'&formid='.$formCampaignMapping[ $campaigndetails->fields->Type ]->formNumber.'">Sign Up</a>';
												}
											}
										?>
									</div>
									<?php the_content(); ?>

									<div class="clear"></div>
								</div><!--/.entry-content-->	
							</div><!--/.entry-inner-->

						</article>
						
						<?php if ( comments_open() || get_comments_number() ) :	comments_template( '/comments.php', true ); endif; ?>
						
					<?php endwhile; ?>
					
				</div><!--/.pad-->			
			</div><!--/.content-->

			<!-- Family contacts modal - start -->
			<div class="modalBg" style="display: none;" id="individual_requestmodal">
				<div class="modal" style="background: #fff;">
					<a href="javascript:void(0);" class="modalClose" onclick="hideMembersModal();">×</a>
					<p></p>
					<div class="modalScroller" style="padding: 20px;">
						<div class="modal-header">
							<h4>Select Member</h4>
						</div>
						<div class="modal-body">
							<ul>
								<?php
								foreach ($response_currentaccount_contacts->records as $record_contact) { 
								 ?>
									<li class="contact-listitem">
										<?php echo '<a href="'.$siteURL.'/campaignregistration/?cntid='.$record_contact->fields->ID__c.'&cmpid='.$campaigndetails->fields->ID__c.'&formid='.$formCampaignMapping[ $campaigndetails->fields->Type ]->formNumber.'">'.$record_contact->fields->Name.'</a>'; ?>
											
									</li>
								<?php } 
								?>
							</ul>
						</div>
					</div>
				</div>
			</div>
			<!-- Family contacts modal - end -->

		<?php 
			echo '<script>
				function showMembersModal() {
					document.getElementById("individual_requestmodal").style.display = "block"; 
				} 
				function hideMembersModal() { 
					document.getElementById("individual_requestmodal").style.display = "none"; 
				}
			</script>
			<style>
				.modalBg {display: none; background: rgba(0,0,0,0.5); position: fixed; left: 0; right: 0; top: 0; bottom: 0; z-index: 9999; }.modal{ position: relative; max-width: 720px; margin: 0 auto; margin-top: 110px;     box-shadow: 9px 7px 15px rgba(0,0,0,0.2);}a.modalClose { position: absolute; right: 0; color: #ffffff; font-size: 28px; top: 0; margin: -20px; }.modalScroller{max-height:440px; overflow:auto;}.modalBg .wFormContainer{margin:0;}.btnYellow{border-radius: 4px;background: #ceac41; color: #fff; padding: 10px 20px; font-weight: 600;}
			</style>';
		} else { ?>
			<article <?php post_class('entry boxed group'); ?>>
				<div class="entry-inner pad">
					<div class="entry-content themeform">
						Campaign not found
					</div>
				</div>
			</article>
		<?php
		}
	}

} else { ?>
	<article <?php post_class('entry boxed group'); ?>>
		<div class="entry-inner pad">
			<div class="entry-content themeform">
				No campaign selected
			</div>
		</div>
	</article>
<?php
}
get_footer();
?>
