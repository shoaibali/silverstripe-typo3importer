<?php
class ContentHolderPage extends ContentGenericPage {

	private static $db = array();

	private static $has_one = array(
	);

	private static $icon = 'typo3importer/images/holder.png';

}
class ContentHolderPage_Controller extends ContentGenericPage_Controller {

	private static $allowed_actions = array (
	);

	public function init() {
		parent::init();
	}

}
