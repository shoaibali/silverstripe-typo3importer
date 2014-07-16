<?php
class Typo3Page extends Page {

  private static $db = array(
    'Typo3UID' => 'Int',
    'Typo3PID' => 'Int'
  );

  private static $has_one = array(
  );


  public function getCMSFields() {
    $fields = parent::getCMSFields();
   
    $fields->addFieldToTab('Root.Main', new TextField('Typo3UID'), 'Content');
    $fields->addFieldToTab('Root.Main', new TextField('Typo3PID'), 'Content');
   
    return $fields;
  }


}
class Typo3Page_Controller extends Page_Controller {

  private static $allowed_actions = array (
  );

  public function init() {
    parent::init();
  }

}
