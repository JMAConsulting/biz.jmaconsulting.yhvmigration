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
    ];
    // We dupe with the external identifier.
	  $existingContact = civicrm_api3('Contact', 'get', ['external_identifier' => $contact['FileNum']]);
    if (!empty($existingContact['id'])) {
      $params['contact_id'] = $existingContact['id'];
    }
    else {
    	$params['external_identifier'] = $contact['FileNum'];
    }

    // Additional contact params.
	  if (!empty($contact['Sex'])) {
		  $params['gender_id'] = CRM_Yhvmigration_Utils::getGender($contact['Sex']);
	  }

    try {
      $params['contact_sub_type'] = 'Volunteer';
      $volunteer = civicrm_api3('Contact', 'create', $params);

      // Create Phone and Address
	    self::createPhoneAndAddress($volunteer, $contact);

      // Do more volunteer specific stuff.
	    self::createVolunteerSpecifics($volunteer, $contact);

	    // Emergency Contacts.
	    self::createEmergencyContacts($volunteer, $contact);

	    // Add languages.
	    $languageFields = [
	    	'OtherLanguage1',
		    'OtherLanguage2',
		    'OtherLanguage3',
	    ];
	    CRM_Yhvmigration_Utils::addMultiValue($languageFields, LANGUAGES, $volunteer, $contact, FALSE);

	    $languageNotOnList = [
		    'OtherLanguage(not-on-list)1',
		    'OtherLanguage(not-on-list)2',
	    ];
	    CRM_Yhvmigration_Utils::addMultiValue($languageNotOnList, OTHER_LANGUAGE, $volunteer, $contact, TRUE);

	    // Add areas of education.
	    $areas = [
	    	'Area of Education #1',
		    'Area of Education #2',
		    'Area of Education #3',
	    ];
	    CRM_Yhvmigration_Utils::addMultiValue($areas, AREA_OF_EDUCATION, $volunteer, $contact, FALSE);

	    $areasNotOnList = [
		    'AreaOfEducation(NotOnList)1',
		    'AreaOfEducation(NotOnList)2',
		    'AreaOfEducation(NotOnList)3',
		    'Other Area of Education',
	    ];
	    CRM_Yhvmigration_Utils::addMultiValue($areasNotOnList, OTHER_AREAS, $volunteer, $contact, TRUE);

	    // Add professions and skills.
	    $skills = [
		    'Profession/Qualifications/Skills1',
		    'Profession/Qualifications/Skills2',
		    'Profession/Qualifications/Skills3',
		    'Profession/Qualifications/Skills4',
	    ];
	    CRM_Yhvmigration_Utils::addMultiValue($skills, SKILLS, $volunteer, $contact, FALSE);

	    $otherSkills = [
		    'Profession/Qualifications/Skills(NotOnList)1',
		    'Profession/Qualifications/Skills(NotOnList)2',
		    'Profession/Qualifications/Skills(NotOnList)3',
		    'Profession/Qualifications/Skills(NotOnList)4',
	    ];
	    CRM_Yhvmigration_Utils::addMultiValue($otherSkills, OTHER_SKILLS, $volunteer, $contact, TRUE);
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

  public static function createPhoneAndAddress($volunteer, $contact) {
  	$params = [
  		'street_address' => CRM_Utils_Array::value('Street', $contact, NULL),
		  'city' => CRM_Utils_Array::value('City', $contact, NULL),
		  'postal_code' => CRM_Utils_Array::value('PostalCode', $contact, NULL),
		  'contact_id' => $volunteer['id'],
		  'state_province_id' => ucfirst(CRM_Utils_Array::value('Province', $contact, 'Ontario')),
		  'country_id' => 'CA',
	  ];
  	// Handle special cases.
		if (!empty($contact['Country'])) {
			if ($contact['Country'] == 'USA') {
				$params['country_id'] = 'US';
			}
			else {
				$params['country_id'] = $contact['Country'];
			}
	  }
  	// Delete addresses before we recreate.
	  CRM_Yhvmigration_Utils::deleteEntities('Address', ['contact_id' => $volunteer['id']]);
	  // Now, create the address.
	  civicrm_api3('Address', 'create', $params);

	  // We do the same for phone number.
	  if (!empty($contact['Mobile'])) {
		  // Delete all phones associated with the contact first.
		  CRM_Yhvmigration_Utils::deleteEntities('Phone', ['contact_id' => $volunteer['id'], 'phone_type_id' => 'Mobile']);
		  // Now, create the phone.
		  civicrm_api3('Phone', 'create', ['contact_id' => $volunteer['id'], 'phone' => $contact['Mobile']]);
	  }
	  if (!empty($contact['HomePhone'])) {
		  // Delete all phones associated with the contact first.
		  CRM_Yhvmigration_Utils::deleteEntities('Phone', ['contact_id' => $volunteer['id'], 'phone_type_id' => 'Phone']);
		  // Now, create the phone.
		  civicrm_api3('Phone', 'create', ['contact_id' => $volunteer['id'], 'phone' => $contact['Mobile']]);
	  }
	  if (!empty($contact['BusinessPhone'])) {
		  // Delete all phones associated with the contact first.
		  CRM_Yhvmigration_Utils::deleteEntities('Phone', ['contact_id' => $volunteer['id'], 'phone_type_id' => 'Work']);
		  // Now, create the phone.
		  civicrm_api3('Phone', 'create', ['contact_id' => $volunteer['id'], 'phone' => $contact['Work'], 'phone_ext' => $contact['BusinessExt']]);
	  }
  }

  public static function createEmergencyContacts($volunteer, $contact) {
  	$emergency1Fields = [
  		'first_name' => $contact['EmCon1FirstName'],
  		'last_name' => $contact['EmCon1LastName'],
		  'email' => $contact['EmConEmail1'],
		  'phone' => $contact['EmConPhone1'],
		  'relation' => $contact['This person is my1'],
	  ];
  	CRM_Yhvmigration_Utils::createEmergencyContact($volunteer['id'], $emergency1Fields);

	  $emergency2Fields = [
		  'first_name' => $contact['EmCon2FirstName'],
		  'last_name' => $contact['EmCon2LastName'],
		  'email' => $contact['EmConEmail2'],
		  'phone' => $contact['EmConPhone2'],
		  'relation' => $contact['This person is my2'],
	  ];
	  CRM_Yhvmigration_Utils::createEmergencyContact($volunteer['id'], $emergency2Fields);
  }

  public static function createVolunteerSpecifics($volunteer, $contact) {
		$fields = CRM_Yhvmigration_Utils::getCustomFields();
		$params = ['contact_type' => 'Individual', 'contact_id' => $volunteer['id']];
		foreach ($fields as $dbField => $customField) {
			$custom = CRM_Yhvmigration_Utils::getCustomFieldID($customField);
			if (empty($contact[$dbField])) {
				continue;
			}
			elseif ($dbField == 'ChineseName') {
				$params[$custom] = CRM_Yhvmigration_Utils::getChineseName($contact[$dbField], $dbField);
			}
			else {
				if (in_array($contact[$dbField], ['TRUE', 'FALSE'])) {
					$params[$custom] = CRM_Yhvmigration_Utils::getYesNo($contact[$dbField]);
				}
				else {
					$params[$custom] = $contact[$dbField];
				}
			}
		}
	  try {
		  civicrm_api3('Contact', 'create', $params);
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
  }

}