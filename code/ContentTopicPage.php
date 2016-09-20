<?php
class ContentTopicPage extends ContentGenericPage {

	private static $db = array();

	private static $has_one = array(
	);

	private static $icon = 'typo3importer/images/page.png';

}
class ContentTopicPage_Controller extends ContentGenericPage_Controller {

	private static $allowed_actions = array (
	);

	public function init() {
		parent::init();
	}

}
