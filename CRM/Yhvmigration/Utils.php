<?php

	class CRM_Yhvmigration_Utils {

		public static function getCustomFields() {
			$fields = [
				'RegisterDate' => 'RegisterDate',
				'PoliceCheckDate' => 'PoliceCheckDate',
				'TBtest' => 'TBtest',
				'ChineseName' => 'Chinese_Name',
				'Year of Birth' => 'Birth_Year',
				'SpeakEn' => 'Speak_English',
				'SpeakCan' => 'Speak_Cantonese',
				'SpeakMan' => 'Speak_Mandarin',
				'WriteChinese' => 'Write_Chinese',
				'WriteEng' => 'Write_English',
				'ChineseTyping' => 'Chinese_Typing',
				'EngTyping' => 'English_Typing',
				'Car' => 'Car',
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
				$contact = civicrm_api3('Contact', 'create', $fields);
				if (!empty($contact['id'])) {
					// Create relationship with volunteer.
					$params = [
						'contact_id_a' => $id,
						'contact_id_b' => $contact['id'],
						'relationship_type_id' => ucfirst($fields['relation']),
					];
					self::deleteEntities('Relationship', $params);
					civicrm_api3('Relationship', 'create', $params);
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
					$options[] = $contact[$option];
				}
			}
			if ($isText) {
				$options = implode(',', $options);
			}
			if (!empty($options)) {
				$customFieldID = CRM_Yhvmigration_Utils::getCustomFieldID($name);
				civicrm_api3('Contact', 'create', [
					'id' => $volunteer['id'],
					'contact_type' => 'Individual',
					'custom_' . $customFieldID => $options,
				]);
			}
		}

		public static function getCustomFieldID($name) {
			return 'custom_' . CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_custom_field WHERE name = %1", [1 => [$name, 'String']]);
		}
	}