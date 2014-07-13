
<?php

class Typo3Importer extends Page_Controller {
	static $allowed_actions = array(
		'Form',
		'bulkimport',
		'complete'
	);

	function __construct() {
		$dataRecord = new Page();
		$dataRecord->Title = $this->Title();
		$dataRecord->URLSegment = get_class($this);
		$dataRecord->ID = -1;
		parent::__construct($dataRecord);
	}

	function init() {
		parent::init();
		if(!Permission::check('ADMIN')) Security::permissionFailure();
	}
	
	function Title() {
		return "Typo3 Importer";
	}
	
	function Content() {
		$msg = <<<HTML
		<p>This tool will let you migrate site structure and contents from a typo3 uncompressed in to SilverStripe:</p>
HTML;

		// check directory exists
		if( $folder = Folder::find('typo3import') ){
			$msg .= <<<HTML
			<p>&#10004 Directory called typo3import found</p>
HTML;
			// then also check if it has xml files
			if( Folder::find('typo3import')->hasChildren() ){
				$files = $folder->Children();
				$file_names = "";

				foreach($files as $file)
					$file_names .= '<li>'. $file->getFilename() .'</li>';

				$msg .= <<<HTML
				<p>&#10004 Typo3 exported XML files in typo3import directory found</p>
				<ul>
					$file_names
				</ul>
HTML;
			} else {
				$msg .= <<<HTML
				<p>&times; No Typo3 XML files found in typo3import directory</p>
HTML;
			}

		} else {
			Folder::find_or_make('typo3import');
			$msg .= <<<HTML
			<p>A directory called typo3import in assets has been created for you.
			Please upload all typo3 exported XML to it</p>
HTML;
		}

		 return $msg;
	}
	
	function Form() {
		return new Form($this, "Form", new FieldList(
			//new FileField("SourceFile", "Tab-delimited file"),
			//new CheckboxField("DeleteExisting", "Clear out all existing content?"),
			new CheckboxField("PublishAll", "Publish everything after the import?")
		), new FieldList(
			new FormAction("bulkimport", "Being Migration")
		));
	}
	
	function bulkimport($data) {

		
		Versioned::reading_stage('Stage');
		
		$files = array();

		if(!SapphireTest::is_running_test()) {
			$folder = Folder::find('typo3import');
			$children = $folder->Children();
			foreach( $children as $child )
				$files []= $child->getFullPath();
		} else {
				$files = $data["files"];
		}

		foreach($files as $file) {
			$parentRefs = array();

			$xml = simplexml_load_file($file);


			if(!SapphireTest::is_running_test())
				echo "Procesing file => " . $file	. PHP_EOL . "<br/>";

			$root_tree = $xml->header->pagetree->node;
			$site_tree = array();

			$result = $this->buildTree($root_tree, $site_tree, $xml);

			$site_tree = array_pop($site_tree);

			$count = 1; // counter for number of elements
			$level = 0; // counter for depth levels
			$parentRefs = array(); // array to store parent id references

			$iterator = new RecursiveArrayIterator(array_filter($site_tree)); 
			//iterator_apply($iterator, self::migrate($iterator, $level, $count, $parentRefs); 
			self::migrate($iterator, $level, $count, $parentRefs); 

			// cleanup memory
			unset($iterator);
			unset($site_tree);
			unset($parentRefs);
		}
		
		//Director::redirect($this->Link() . 'complete');		
	}
	
	function complete() {
		return array(
			"Content" => "<p>Thanks! Your site tree & content has been imported.</p>",
			"Form" => " ",
		);
	}

	function buildTree($root, &$site_tree, $full_xml) {
	  $sub_tree = array();
	  $root_uid = (string) $root->uid;

    $sub_tree['uid'] = $root_uid;
    $sub_tree['title'] = self::getTitle($root, $full_xml);
    $sub_tree['description'] = self::getDescription($root, $full_xml);
		$sub_tree['pid'] = self::getPID($root, $full_xml);

    // If there is children
    if (!empty($root->children()->node)) {
      foreach($root->children()->node as $child){
        $child_uid = (string) $child->uid;
        $sub_tree['children'][$child_uid] = $this->buildTree($child, $site_tree, $full_xml);
      }

	  	$site_tree[$root_uid] = $sub_tree;
	  }
	  return $sub_tree;
	}

	private static function migrate($iterator, $level, &$count, &$parentRefs) { 


    while ( $iterator->valid() ) { 
        if ( $iterator->hasChildren() ) {
            self::migrate($iterator->getChildren(), $level, $count, $parentRefs);
        } else { 

            if( ($iterator->current() !== "") ){


            	if( $iterator->key() == "uid" ) {
            		$uID = $iterator->current();
            	}

            	if( $iterator->key() == "title" ) {
            		$title = $iterator->current();
            	}

            	if( $iterator->key() == "description" ) {
            		$description = $iterator->current();
            	}

            	if( $iterator->key() == "pid" ) {
            		$parentID = $iterator->current();

								$newPage = new Page();
								$newPage->Title = $title;
								$newPage->Content = ( isset($description) )? $description : "";
								$newPage->URLSegment = NULL;
								
								// Set parent based on parentRefs;
								if($level > 0) $newPage->ParentID = $parentRefs[$level-1];


								// echo "(" . $level . ")" . " <strong>ParentID</strong> => " . $parentID . 
								// 			" <strong>UID</strong> => " . $uID .
								// 			" <strong>TITLE</strong> => " . $title .  PHP_EOL . "<br/>";


								// Don't write the page until we have Title, Content and ParentID
								 $newPage->write();
								 $newPage->publish('Stage', 'Live');

							 	// Populate parentRefs with the most recent page at every level.   Necessary to build tree
								$parentRefs[$level] = $newPage->ID;

								// Remove no-longer-relevant children from the parentRefs.  Allows more graceful acceptance of files
								// with errors
								for($i=sizeof($parentRefs)-1;$i>$level;$i--) unset($parentRefs[$i]);

								if(!SapphireTest::is_running_test())
									echo"<li>Written #$newPage->ID: $newPage->Title (child of $newPage->ParentID)</li>";


								// Memory cleanup
								$newPage->destroy();
								unset($newPage);

								$level++;  // counter for depth


            	}

              $count++;  // counter for each element in array
            }
        } 
      $iterator->next();     
		}
	}

	private static function getTitle($node, $xml){
	  //$title_xpath = "/T3RecordDocument/records/tablerow[@index='pages:" . (string) $node->uid . "']/fieldlist/field[@index='title']";
	  $title_xpath = "/T3RecordDocument//records/table/rec[@index='" . (string) $node->uid . "']/title";
	  $title = "";
	  $node_uid = (string)$node->uid;

	  if(!empty($node_uid)){
	   $title = $xml->xpath($title_xpath);
	   $title = (string) $title[0];
	  }

	  return $title;
	}

	private static function getDescription($node, $xml){
	  $description_xpath = "/T3RecordDocument/records/tablerow[@index='pages:" . (string) $node->uid . "']/fieldlist/field[@index='description']";
	  $description = "";
	  $node_uid = (string) $node->uid;

	  if(!empty($node_uid)){
	    $description = $xml->xpath($description_xpath); 
	    $description = (string) $description[0];
	  }
	  return $description;
	}


	private static function getPID($node, $xml){
	  $pid_xpath = "/T3RecordDocument/header/records/table/rec[@index='". (string) $node->uid . "']/pid";
	  $pid = "";
	  $node_uid = (string)$node->uid;

	  if(!empty($node_uid)){
	   $pid = $xml->xpath($pid_xpath);
	   $pid = (string)$pid[0];
	  }

	  return $pid;
	}
	
}
