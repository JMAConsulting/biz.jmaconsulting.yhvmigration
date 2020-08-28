<?php

class CRM_Yhvmigration_BAO_VolunteerImport {

  public static function syncContacts($ctx, $start, $end) {
    $vols = CRM_Core_DAO::executeQuery("SELECT * FROM volunteers LIMIT $start, $end")->fetchAll();
    if (empty($vols)) {
        return;
    }
    foreach ($vols as $volunteer) {
    	$cids[] = self::createVolunteer($volunteer);
    }
  }

  public static function createVolunteer($contact) {
    $params = [
      'contact_type' => 'Individual',
      'first_name' => $contact['FirstName'],
      'last_name' => $contact['LastName'],
      'email' => $contact['Email'],
	    'external_identifier' => $contact['FileNum'],
    ];
    $dedupeParams = CRM_Dedupe_Finder::formatParams($params, 'Individual');
    $dedupeParams['check_permission'] = FALSE;
    $dupes = CRM_Dedupe_Finder::dupesByParams($dedupeParams, 'Individual');
    $cid = CRM_Utils_Array::value('0', $dupes, NULL);
    if ($cid) {
      $params['contact_id'] = $cid;
    }

    try {
      $params['contact_sub_type'] = 'Volunteer';
      $volunteer = civicrm_api3('Contact', 'create', $params);

      // Do more volunteer specific stuff.
	    self::createVolunteerSpecifics($volunteer, $contact);
    }
    catch (CiviCRM_API3_Exception $e) {
      // Handle error here.
      $errorMessage = $e->getMessage();
      $errorCode = $e->getErrorCode();
      $errorData = $e->getExtraParams();
      $error = [
        'error_message' => $errorMessage,
        'error_code' => $errorCode,
        'error_data' => $errorData,
      ];
      CRM_Core_Error::debug_var('Error in processing information:', $error);
    }

    return $contact['id'];
  }

  public static function createVolunteerSpecifics($volunteer, $contact) {
		$fields = CRM_Yhvmigration_Utils::getCustomFields();
		$params = ['contact_type' => 'Individual', 'contact_id' => $volunteer['id']];
		foreach ($fields as $dbField => $customField) {
			if (empty($contact[$dbField])) {
				continue;
			}
			$custom = CRM_Yhvmigration_Utils::getCustomFieldID($customField);
			if ($dbField == 'Status') {
				$params[$custom] = CRM_Yhvmigration_Utils::getStatus($contact[$dbField]);
			}
			elseif ($dbField == 'Age Range') {
				$params[$custom] = CRM_Yhvmigration_Utils::getAgeRange($contact[$dbField]);
			}
			elseif ($dbField == 'Birthday') {
				$params[$custom] = CRM_Yhvmigration_Utils::getDecade($contact[$dbField], $dbField);
			}
			elseif ($dbField == 'ChineseName') {
				$params[$custom] = CRM_Yhvmigration_Utils::getChineseName($contact[$dbField], $dbField);
			}
			else {
				$params[$custom] = $contact[$dbField];
			}
		}
		civicrm_api3('Contact', 'create', $params);
  }

}