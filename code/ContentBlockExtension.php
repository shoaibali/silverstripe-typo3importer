<?php

class ContentBlockExtension extends DataExtension {

	private static $db = array(
			'Heading' => 'Text',
			'Typo3UID' => 'Int',
			'Typo3PID' => 'Int',
			'CType' => 'ENUM("div, html, text, textpic, image, list, menu")'
	);

	private static $summary_fields = array( 
		'Heading' => 'Heading',
		'Typo3UID' => 'Typo3UID',
		'Typo3PID' => 'Typo3PID',
		'CType' => 'CType'
	);

}
