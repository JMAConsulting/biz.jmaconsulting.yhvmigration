<?php

class CRM_Yhvmigration_BAO_VolunteerImport {

  public static function syncContacts($ctx, $start, $end) {
    $query = "SELECT
      `FileNum`,
						`FirstName`,
						`LastName`,
						`ChineseName`,
						`RegisterDate`,
						`Year of Birth` AS `YearOfBirth`,
						`TBtest`,
						`PoliceCheckDate`,
						`Email`,
						`Mobile`,
						`HomePhone`,
						`BusinessPhone`,
						`BusinessExt`,
						`Sex`,
						`Street`,
						`City`,
						`PostalCode`,
						`EmCon1LastName`,
						`EmCon1FirstName`,
						`This person is my1` AS `rel1`,
						`EmConPhone1`,
						`EmConEmail1`,
						`EmCon2LastName`,
						`EmCon2FirstName`,
						`This person is my2` AS `rel2`,
						`EmConPhone2`,
						`EmConEmail2`,
						`SpeakEng`,
						`SpeakCant`,
						`SpeakMand`,
						`WriteChinese`,
						`WriteEng`,
						`ChineseTyping`,
						`EngTyping`,
						`OtherLanguage1`,
						`OtherLanguage2`,
						`OtherLanguage3`,
						`OtherLanguage(not-on-list)1`,
						`OtherLanguage(not-on-list)2`,
						`Car`,
						`Area of Education #1` AS `area1`,
						`Area of Education #2` AS `area2`,
						`Area of Education #3` AS `area3`,
						`AreaOfEducation(NotOnList)1`,
						`AreaOfEducation(NotOnList)2`,
						`AreaOfEducation(NotOnList)3`,
						`Profession/Qualifications/Skills1`,
						`Profession/Qualifications/Skills2`,
						`Profession/Qualifications/Skills3`,
						`Profession/Qualifications/Skills4`,
						`Profession/Qualifications/Skills(NotOnList)1`,
						`Profession/Qualifications/Skills(NotOnList)2`,
						`Profession/Qualifications/Skills(NotOnList)3`,
						`Profession/Qualifications/Skills(NotOnList)4`
      FROM volunteers LIMIT $start, $end";
    $vols = CRM_Core_DAO::executeQuery($query)->fetchAll();
    if (empty($vols)) {
      return;
    }
    foreach ($vols as $volunteer) {
      $cids[] = self::createVolunteer($volunteer);
    }
  }

  public static function createVolunteer($contact) {
    if (empty($contact['ChineseName']) && (strpos($contact['FirstName'], '?') !== false || strpos($contact['LastName'], '?') !== false)) {
      return $contact['FileNum'];
    }
    $params = [
      'contact_type' => 'Individual',
      'first_name' => $contact['FirstName'],
      'last_name' => $contact['LastName'],
      'email' => $contact['Email'],
    ];

    // Check for duplicates.
    $dedupeParams = CRM_Dedupe_Finder::formatParams($params, 'Individual');
    $dedupeParams['check_permission'] = 0;
    $dupes = CRM_Dedupe_Finder::dupesByParams($dedupeParams, 'Individual', NULL, [], 1);
    $params['contact_id'] = CRM_Utils_Array::value('0', $dupes, NULL);

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
        'area1',
        'area2',
        'area3',
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
      civicrm_api3('Phone', 'create', ['contact_id' => $volunteer['id'], 'phone' => $contact['Mobile'], 'phone_type_id' => 'Mobile']);
    }
    if (!empty($contact['HomePhone'])) {
      // Delete all phones associated with the contact first.
      CRM_Yhvmigration_Utils::deleteEntities('Phone', ['contact_id' => $volunteer['id'], 'phone_type_id' => 'Phone', 'location_type_id' => 'Home']);
      // Now, create the phone.
      civicrm_api3('Phone', 'create', ['contact_id' => $volunteer['id'], 'phone' => $contact['HomePhone'], 'location_type_id' => 'Home', 'phone_type_id' => 'Phone',]);
    }
    if (!empty($contact['BusinessPhone'])) {
      // Delete all phones associated with the contact first.
      CRM_Yhvmigration_Utils::deleteEntities('Phone', ['contact_id' => $volunteer['id'], 'phone_type_id' => 'Phone', 'location_type_id' => 'Work']);
      // Now, create the phone.
      civicrm_api3('Phone', 'create', ['contact_id' => $volunteer['id'], 'phone' => $contact['BusinessPhone'], 'phone_ext' => $contact['BusinessExt'], 'phone_type_id' => 'Phone', 'location_type_id' => 'Work']);
    }
  }

  public static function createEmergencyContacts($volunteer, $contact) {
    $emergency1Fields = [
      'first_name' => $contact['EmCon1FirstName'],
      'last_name' => $contact['EmCon1LastName'],
      'email' => $contact['EmConEmail1'],
      'phone' => $contact['EmConPhone1'],
      'relation' => $contact['rel1'],
      'contact_type' => 'Individual',
    ];
    CRM_Yhvmigration_Utils::createEmergencyContact($volunteer['id'], $emergency1Fields);

    $emergency2Fields = [
      'first_name' => $contact['EmCon2FirstName'],
      'last_name' => $contact['EmCon2LastName'],
      'email' => $contact['EmConEmail2'],
      'phone' => $contact['EmConPhone2'],
      'relation' => $contact['rel2'],
      'contact_type' => 'Individual',
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

  public static function syncActivities($ctx, $start, $end, $activityType) {
    if ($activityType == 'Volunteer') {
      $table = 'volunteer_hours_new';
    }
    if ($activityType == 'Volunteer Award') {
      $table = 'volunteer_awards';
    }
    $sql = "select v.id,
    v.FileNumber,
    v.Award,
    v.`Award Year` as `Award_Year`
    from volunteer_awards v
    LEFT JOIN civicrm_value_volunteer_awa_11 h ON h.external_id_85 = v.id 
    INNER JOIN civicrm_contact c ON c.external_identifier = v.FileNumber COLLATE utf8_unicode_ci
    WHERE h.external_id_85 IS NULL LIMIT $start, $end";
    /* $sql = "SELECT h.* FROM $table h
    LEFT JOIN civicrm_value_volunteering_12 v ON v.external_id_new_84 = h.id
    WHERE v.external_id_new_84 IS NULL
    LIMIT $start, $end"; */
    $items = CRM_Core_DAO::executeQuery($sql)->fetchAll();
    foreach ($items as $item) {
      if ($activityType == 'Volunteer Award') {
        self::createVolunteerAward($item, $activityType);
      }
      if ($activityType == 'Volunteer') {
        self::createWorkHours($item, $activityType);
      }
    }
  }

  public static function createWorkHours($workHour, $activityType) {
    if (empty($workHour) || empty($workHour['FileNumber'])) {
      return;
    }
    // Do a lookup to match the fileNum.
    $existingContact = civicrm_api3('Contact', 'get', ['external_identifier' => $workHour['FileNumber']]);
    if (!empty($existingContact['id'])) {
      $cid = $existingContact['id'];
    }
    $activityParams = [
      'target_contact_id' => $cid,
      'source_contact_id' => $cid,
      'activity_type_id' => $activityType,
      'duration' => $workHour['hours'],
    ];

    // Add custom fields.
    $customFields = [
      'Location' => 'Location',
      'Division' => 'Division',
      'Program' => 'Program',
      'Funder' => 'Funder',
      'id' => 'External_ID_New',
    ];
    $activityParams['activity_date_time'] = date('Y-m-d', strtotime($workHour['year'] . '-' . $workHour['month'] . '-01'));
    foreach ($customFields as $db => $name) {
      $custom = CRM_Yhvmigration_Utils::getCustomFieldID($name, WORKHOUR_CUSTOM);
      $activityParams[$custom] = $workHour[$db];
    }

    try {
      $activity = civicrm_api3('Activity', 'create', $activityParams);
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
      CRM_Core_Error::debug_var('Error in processing workhour information:', $error);
    }
  }

  public static function createVolunteerAward($award, $activityType) {
    if (empty($award) || empty($award['FileNumber'])) {
      return;
    }
    // Do a lookup to match the fileNum.
    $existingContact = civicrm_api3('Contact', 'get', ['external_identifier' => $award['FileNumber']]);
    if (!empty($existingContact['id'])) {
      $cid = $existingContact['id'];
    }
    $activityParams = [
      'target_contact_id' => $cid,
      'source_contact_id' => $cid,
      'activity_type_id' => 58,
    ];
    $activityParams['activity_date_time'] = date('Y-m-d', strtotime($award['Award_Year'] . '-01-01'));

    // Add custom fields.
    $customFields = [
      'Award_Year' => 'Award_for_Year',
      'Award' => 'Award_Name',
      'id' => 'External_ID',
      //'Month' => 'Month',
    ];
    foreach ($customFields as $db => $name) {
      $custom = CRM_Yhvmigration_Utils::getCustomFieldID($name, AWARD_CUSTOM);
      $activityParams[$custom] = $award[$db];
    }

    try {
      civicrm_api3('Activity', 'create', $activityParams);
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
      CRM_Core_Error::debug_var('Error in processing award information:', $error);
    }
  }

}