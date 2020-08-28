<?php

	class CRM_Yhvmigration_Utils {

		public static function getCustomFields() {
			$fields = [
				'FileNum' => 'FileNum',
				'Status' => 'Status',
				'RegisterDate' => 'RegisterDate',
				'PoliceCheckDate' => 'PoliceCheckDate',
				'TBtest' => 'TBtest',
				'ChineseName' => 'Chinese_Name',
				'Age Range' => 'Age_18',
				'Birthday' => 'Birth_Year',
			];
			return $fields;
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

		public static function getAgeRange($age) {
			return 1; // TODO: FIXME.
		}

		public static function getDecade($birthday, $field) {
			$year = date('Y', strtotime($birthday));
			if ($year <= 1949) {
				return "Before 1950";
			}
			$optionValues = self::lookupValues($field, 'name');
			foreach ($optionValues as $option) {
				if (strpos($option, '-') !== false) {
					$part = explode('-', $option);
					if ($year >= $part[0] && $year <= $part[1]) {
						$decade = $option;
						break;
					}
				}
			}
			return $decade;
		}

		public static function getChineseName($field) {
			if (preg_match("/\p{Han}+/u", $field)) {
				return $field;
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

		public static function getCustomFieldID($name) {
			return 'custom_' . CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_custom_field WHERE name = %1", [1 => [$name, 'String']]);
		}
	}