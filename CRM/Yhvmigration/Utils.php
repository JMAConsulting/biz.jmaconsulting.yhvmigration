<?php

	class CRM_Yhvmigration_Utils {

		public static function getCustomFields() {
			$fields = [
				'RegisterDate' => 'Registration_Date',
				'PoliceCheckDate' => 'PoliceCheckDate',
				'TBtest' => 'TB_Test',
				'ChineseName' => 'Chinese_Name',
				'YearOfBirth' => 'Birth_Year',
				'SpeakEn' => 'Speak_English_',
				'SpeakCan' => 'Speak_Cantonese_',
				'SpeakMan' => 'Speak_Mandarin_',
				'WriteChinese' => 'Write_Chinese_',
				'WriteEng' => 'Write_English_',
				'ChineseTyping' => 'Chinese_Typing_',
				'EngTyping' => 'English_Typing_',
				'Car' => 'Car_',
			];
			return $fields;
		}

		public static function getYesNo($bool) {
			if (!empty($bool) && $bool == 'TRUE') {
				return '1';
			}
			return '0';
		}

		public static function getStatus($status) {
			switch ($status) {
				case 'A':
					return 'Active';
				case 'N':
					return 'Non-Active';
				default:
					break;
			}
		}

		public static function createEmergencyContact($id, $fields) {
			if (!empty($fields['first_name']) || !empty($fields['last_name']) || !empty($fields['email'])) {
					$dedupeParams = CRM_Dedupe_Finder::formatParams($fields, 'Individual');
					$dedupeParams['check_permission'] = 0;
					$dupes = CRM_Dedupe_Finder::dupesByParams($dedupeParams, 'Individual', NULL, [], EM_DEDUPE_RULE);
					$fields['contact_id'] = CRM_Utils_Array::value('0', $dupes, NULL);
					$contact = civicrm_api3('Contact', 'create', $fields);
					$conA = $id;
					$conB = $contact['id'];
					// Create emergency contact phone.
					if (!empty($fields['phone'])) {
							CRM_Yhvmigration_Utils::deleteEntities('Phone', ['contact_id' => $contact['id']]);
							civicrm_api3('Phone', 'create', ['contact_id' => $contact['id'], 'phone' => $fields['phone']]);
					}
					if (!empty($fields['relation'])) {
							$relationId = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_relationship_type WHERE name_a_b = %1", [1 => [$fields['relation'], 'String']]);
					}
					else {
							$relationId = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_relationship_type WHERE name_a_b = 'Relative 親戚'");
					}
					if (!empty($fields['relation']) && strpos($fields['relation'], 'Grandparent') !== false) {
							// We need to switch the type.
							$relationId = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_relationship_type WHERE name_b_a = %1", [1 => [$fields['relation'], 'String']]);
							$conA = $contact['id'];
							$conB = $id;
					}
					if (!empty($conA) && !empty($conB)) {
						// Create relationship with volunteer.
						$params = [
							 'contact_id_a' => $conA,
								'contact_id_b' => $conB,
								'relationship_type_id' => $relationId,
						];
						try {
								civicrm_api3('Relationship', 'create', $params);
						}
						catch (CiviCRM_API3_Exception $e) {
								$errorMessage = $e->getMessage();
      		$errorCode = $e->getErrorCode();
      		$errorData = $e->getExtraParams();
      		$error = [
										'error_message' => $errorMessage,
										'error_code' => $errorCode,
										'error_data' => $errorData,
								];
      		CRM_Core_Error::debug_var('Error in processing relationship with emergency contact:', $error);
						}
					}
				}
		}

		public static function getChineseName($field) {
			if (preg_match("/\p{Han}+/u", $field)) {
				return $field;
			}
		}

		public static function getGender($field) {
			switch ($field) {
				case 'M':
					return 'Male';
				case 'F':
					return 'Female';
				default:
					return 'Other';
			}
		}

		public static function deleteEntities($entity, $params) {
			$params += ['options' => ['limit' => 0]];
			$entities = civicrm_api3($entity, 'get', $params)['values'];
			foreach ($entities as $toDelete) {
				civicrm_api3($entity, 'delete', ['id' => $toDelete['id']]);
				if ($entity == 'Relationship') {
					// We delete the related contact as well.
					civicrm_api3('Contact', 'delete', ['id' => $toDelete['contact_id_b']]);
				}
			}
		}

		public static function lookupValues($field, $returnValue) {
			$customFieldName = self::getCustomFields()[$field];
			$optionGroupName = CRM_Core_DAO::singleValueQuery("SELECT g.name
        FROM civicrm_custom_field c
        INNER JOIN civicrm_option_group g ON g.id = c.option_group_id
        WHERE c.name = %1", [1 => [$customFieldName, 'String']]);
			return CRM_Core_OptionGroup::values($optionGroupName, FALSE, FALSE, FALSE, NULL, $returnValue);
		}


		public static function addMultiValue($fields, $name, $volunteer, $contact, $isText) {
				$options = [];
				foreach ($fields as $option) {
						if (!empty($contact[$option])) {
								$options[] = trim($contact[$option]);
						}
				}
				if ($isText) {
						$options = trim(implode(',', $options));
				}
				if (!empty($options)) {
						$customFieldID = CRM_Yhvmigration_Utils::getCustomFieldID($name);
		
						civicrm_api3('Contact', 'create', [
							'id' => $volunteer['id'],
							'contact_type' => 'Individual',
							$customFieldID => $options,
						]);
				}
		}
		
		public static function getCustomFieldID($name, $groupId = VOLUNTEER_INFO_CUSTOM) {
				return 'custom_' . CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_custom_field WHERE name = %1 AND custom_group_id = %2", [1 => [$name, 'String'], 2 => [$groupId, 'Integer']]);
		}
	}