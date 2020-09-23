<?php

use CRM_Yhvmigration_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Yhvmigration_Form_VolunteerImport extends CRM_Core_Form {
  const QUEUE_NAME = 'vol-pull';
  const END_URL    = 'civicrm/volunteerimport';
  const END_PARAMS = 'state=done';
  const VOLUNTEER_BATCH = 500;

  public function buildQuickForm() {
    $fields = [
      'volunteers' => ts('Volunteers'),
						'work_hours' => ts('Work Hours'),
		    'awards' => ts('Previous Winners'),
    ];
    foreach ($fields as $field => $title) {
      $this->addElement('checkbox', $field, $title);
    }
    $this->assign('importFields', array_keys($fields));
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Import'),
        'isDefault' => TRUE,
      ),
      array(
        'type' => 'cancel',
        'name' => ts('Cancel'),
      ),
    ));
    parent::buildQuickForm();
  }

  public function postProcess() {
    $submitValues = $this->_submitValues;
    $runner = self::getRunner($submitValues);
    if ($runner) {
      // Run Everything in the Queue via the Web.
      $runner->runAllViaWeb();
    } else {
      CRM_Core_Session::setStatus(ts('Nothing to import. Make sure you have selected required option to pull from EventBrite.'));
    }
  }

  /**
   * Set up the queue.
   */
  public static function getRunner($submitValues) {
    $syncProcess = array(
      'volunteers' => 'migrateVolunteers',
						'work_hours' => 'migrateWorkHours',
		    'awards' => 'migrateWinners',
    );
    // Setup the Queue
    $queue = CRM_Queue_Service::singleton()->create(array(
      'name'  => self::QUEUE_NAME,
      'type'  => 'Sql',
      'reset' => TRUE,
    ));
    foreach ($syncProcess as $key => $value) {
      if (!empty($submitValues[$key])) {
        $task  = new CRM_Queue_Task(
          ['CRM_Yhvmigration_Form_VolunteerImport', $value],
          [$key],
          "Import {$key} from Yee Hong."
        );
        $queue->createItem($task);
      }
    }
    // Setup the Runner
    $runnerParams = array(
      'title' => ts('Yee Hong Migration: updating contacts from Yee Hong'),
      'queue' => $queue,
      'errorMode'=> CRM_Queue_Runner::ERROR_ABORT,
      'onEndUrl' => CRM_Utils_System::url(self::END_URL, self::END_PARAMS, TRUE, NULL, FALSE),
    );

    $runner = new CRM_Queue_Runner($runnerParams);
    return $runner;
  }

  public static function migrateVolunteers(CRM_Queue_TaskContext $ctx) {
    $count = CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM volunteers");
    for ($i=1; $i<=$count; $i+=self::VOLUNTEER_BATCH) {
      $end = $i + self::VOLUNTEER_BATCH;
      $start = $i - 1;
      $ctx->queue->createItem( new CRM_Queue_Task(
        array('CRM_Yhvmigration_Form_VolunteerImport', 'createUpdateContacts'),
        [$start, $end],
        "Adding contacts from Yee Hong to CiviCRM... "
      ));
    }

    return CRM_Queue_Task::TASK_SUCCESS;
  }
  
  public static function migrateWorkHours(CRM_Queue_TaskContext $ctx) {
  		$count = CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM WorkHours");
				for ($i=1; $i<=$count; $i+=self::VOLUNTEER_BATCH) {
						$end = $i + self::VOLUNTEER_BATCH;
						$start = $i - 1;
						$activityType = 'Volunteer';
						$ctx->queue->createItem( new CRM_Queue_Task(
								array('CRM_Yhvmigration_Form_VolunteerImport', 'createActivities'),
								[$start, $end, $activityType],
								"Adding work hours from Yee Hong to CiviCRM... "
						));
				}
		  return CRM_Queue_Task::TASK_SUCCESS;
		}

		public static function migrateWinners(CRM_Queue_TaskContext $ctx) {
				$count = CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM PreviousAwardWinners");
				for ($i=1; $i<=$count; $i+=self::VOLUNTEER_BATCH) {
						$end = $i + self::VOLUNTEER_BATCH;
						$start = $i - 1;
						$activityType = 'Volunteer Award';
						$ctx->queue->createItem( new CRM_Queue_Task(
								array('CRM_Yhvmigration_Form_VolunteerImport', 'createActivities'),
								[$start, $end, $activityType],
								"Adding work hours from Yee Hong to CiviCRM... "
						));
				}
				return CRM_Queue_Task::TASK_SUCCESS;
		}

  public static function createUpdateContacts(CRM_Queue_TaskContext $ctx, $start, $end) {
    CRM_Yhvmigration_BAO_VolunteerImport::syncContacts($ctx, $start, $end);
    return CRM_Queue_Task::TASK_SUCCESS;
  }
  
  public static function createActivities(CRM_Queue_TaskContext $ctx, $start, $end, $activityType) {
				CRM_Yhvmigration_BAO_VolunteerImport::syncActivities($ctx, $start, $end, $activityType);
				return CRM_Queue_Task::TASK_SUCCESS;
		}

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
