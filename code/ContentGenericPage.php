<?php
class ContentGenericPage extends Page {

	private static $db = array(
		'Typo3UID' => 'Int',
		'Typo3PID' => 'Int',
		'Description' => 'Text'
	);

	private static $has_one = array(
	);

	private static $icon = 'typo3importer/images/page.png';

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldsToTab("Root.Main", new HtmlEditorField('Description', 'Description'), 'Content');

		return $fields;
	}


}
class ContentGenericPage_Controller extends Page_Controller {

	private static $allowed_actions = array (
	);

	public function init() {
		parent::init();
	}

}
