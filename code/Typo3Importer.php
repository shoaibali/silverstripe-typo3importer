
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
    $deleteExistingCheckBox = new CheckboxField("DeleteExisting", "Clear out all existing Typo3 Imported content?");
    $deleteExistingCheckBox->setValue(TRUE);

    $publishAllCheckBox = new CheckboxField("PublishAll", "Publish everything after the import?");
    $publishAllCheckBox->setValue(TRUE);


    // No need to publish if some are already.
    //if(Versioned::get_by_stage('Typo3Page', 'Live')->Count() > 0)
    

    return new Form($this, "Form", new FieldList(
      $deleteExistingCheckBox,
      $publishAllCheckBox
    ), new FieldList(
      new FormAction("bulkimport", "Being Migration")
    ));
  }
  
  function bulkimport($data, $form) {

    if(isset($data['DeleteExisting']) && $data['DeleteExisting'])
      self::deleteAllTypo3Pages();

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
        echo "Procesing file => " . $file . PHP_EOL . "<br/>";

      $root_tree = $xml->header->pagetree->node;
      $site_tree = array();

      $result = self::buildTree($root_tree, $site_tree, $xml);

      $site_tree = array_pop($site_tree);

      $count = 1; // counter for number of elements
      $level = 0; // counter for depth levels
      $parentRefs = array(); // array to store parent id references

      $iterator = new RecursiveArrayIterator(array_filter($site_tree)); 
      //iterator_apply($iterator, self::migrate($iterator, $level, $count, $parentRefs); 
      self::migrate($iterator, $level, $count, $parentRefs); 

      // publish all pages
      if( isset($data['PublishAll']) && $data['PublishAll'] ) {
        self::publishAllTypo3Pages();
      }

      // cleanup memory
      unset($iterator);
      unset($site_tree);
      unset($parentRefs);
    }

      // Rewrite Typo3 links into SS links
    self::processInternalLinks();

      // publish all pages
      if( isset($data['PublishAll']) && $data['PublishAll'] ) {
        self::publishAllTypo3Pages();
      }

    
    //Director::redirect($this->Link() . 'complete');   
  }
  
  function complete() {
    return array(
      "Content" => "<p>Thanks! Your site tree & content has been imported.</p>",
      "Form" => " ",
    );
  }

  private static function publishAllTypo3Pages(){
      Versioned::reading_stage('Stage');
      $Typo3Pages = Typo3Page::get();
      foreach($Typo3Pages as $Typo3Page){
        $Typo3Page->publish('Stage', 'Live');
      }

  }
  private static function deleteAllTypo3Pages(){
      Versioned::reading_stage('Live');
      // get all Live Typo3Page pages in Live mode and delete them
      $Typo3Pages = Typo3Page::get();
      foreach($Typo3Pages as $Typo3Page){
        $Typo3Page->delete();
      }
      
      Versioned::reading_stage('Stage');
      // get all Live Typo3Page pages in Stage mode and delete them
      $Typo3Pages = Typo3Page::get();
      foreach($Typo3Pages as $Typo3Page){
        $Typo3Page->delete();
      }
  }

  private static function buildTree($root, &$site_tree, $full_xml) {
    $sub_tree = array();
    $root_uid = (string) $root->uid;

    $sub_tree['uid'] = $root_uid;
    $sub_tree['title'] = self::getTitle($root, $full_xml);
    $sub_tree['description'] = self::getDescription($root, $full_xml);
    $content_array = self::getContent($root, $full_xml);
    $content_string = "";

    foreach($content_array as $c => $v) {
      // silly idea to add <h2> tags
      $content_string .= "<h2>" . $v["header"] ."</h2>" . $v["bodytext"];
    }

    $sub_tree['content'] = $content_string;
     // this (pid) must remain the very last thing that gets added to array
    $sub_tree['pid'] = self::getPID($root, $full_xml);

    // If there is children
    if (!empty($root->children()->node)) {
      foreach($root->children()->node as $child){
        $child_uid = (string) $child->uid;
        $sub_tree['children'][$child_uid] = self::buildTree($child, $site_tree, $full_xml);
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

              if( $iterator->key() == "content" ){
                $bodytext = $iterator->current();
              }

              if( $iterator->key() == "pid" ) {

                $parentID = $iterator->current();

                $newPage = new Typo3Page();
                $newPage->IsTypo3 = TRUE;
                $newPage->Typo3UID = $uID;
                $newPage->Typo3PID = $parentID;
                $newPage->Title = $title;

                
                if(isset($bodytext)){
                  //var_dump($bodytext);
                }

                $newPage->Content = ( isset($description) )? $description : "";
                $newPage->Content .= ( isset($bodytext) ) ? $bodytext : "";

                $newPage->URLSegment = NULL;

                // Set parent based on parentRefs;
                if($level > 0) $newPage->ParentID = $parentRefs[$level-1];


                // echo "(" . $level . ")" . " <strong>ParentID</strong> => " . $parentID . 
                //      " <strong>UID</strong> => " . $uID .
                //      " <strong>TITLE</strong> => " . $title .  PHP_EOL . "<br/>";


                // Don't write the page until we have Title, Content and ParentID
                $newPage->write();

                  // Populate parentRefs with the most recent page at every level.   Necessary to build tree
                $parentRefs[$level] = $newPage->ID;

                // Remove no-longer-relevant children from the parentRefs.  Allows more graceful acceptance of files
                // with errors
                for($i=sizeof($parentRefs)-1;$i>$level;$i--) unset($parentRefs[$i]);

                if(!SapphireTest::is_running_test())
                  // echo"<li>Written #$newPage->ID: $newPage->Title (child of $newPage->ParentID)</li>";


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
    // TODO Skip hidden content see getContent:$hidden
    if(!empty($node_uid)){
      $description = $xml->xpath($description_xpath); 
      $description = (string) $description[0];
    }
    return $description;
  }

  private static function getContent($node, $xml){
    $content_xpath = "/T3RecordDocument/header/pid_lookup/page_contents[@index='".(string) $node->uid . "']/table[@index='tt_content']/item";
    $content = "";
    $node_uid = (string) $node->uid;
    $content_complete  = array(); // this array will hold all item id's for their respective contents

    // TODO maybe do some content processing here i.e remove or reconstruct links and strip html tags etc.
    if(!empty($node_uid)){
      $content = $xml->xpath($content_xpath);
      foreach($content as $ck){ 

        // we are going to skip all hidden content
        $hidden =  $xml->xpath("/T3RecordDocument/records/tablerow[@index='tt_content:".(string)$ck["index"]."']/fieldlist/field[@index='hidden']");

        if( !(string)$hidden[0] ) {
          $header = $xml->xpath("/T3RecordDocument/records/tablerow[@index='tt_content:".(string)$ck["index"]."']/fieldlist/field[@index='header']");
          $header = (string) $header[0];

          $bodytext = $xml->xpath("/T3RecordDocument/records/tablerow[@index='tt_content:".(string)$ck["index"]."']/fieldlist/field[@index='bodytext']");

          $bodytext = (string) $bodytext[0];

          // re-link images to /assets directory
          if( (strpos($bodytext, "img")) ){
            if( (strpos($bodytext, "###CMS_URL###") !== FALSE) ) {          
              $bodytext = str_replace("###CMS_URL###", "/assets/", $bodytext);
            } else {
              $bodytext = str_replace("fileadmin", "/assets/fileadmin/", $bodytext);
            }
          }

          $bodytext = html_entity_decode( (string) $bodytext, ENT_QUOTES, "UTF-8");

          $content_complete []= array("header" => $header,
                                      "bodytext" => $bodytext);
        }
      }

    }

    return $content_complete;

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
  
  private static function processInternalLinks(){
    # Get all Typo3Pages
    //Versioned::reading_stage('Live');
    $Typo3Pages = Typo3Page::get();

    foreach($Typo3Pages as $Typo3Page){
      $content = $Typo3Page->Content;
      preg_match_all('/link ([\d]+)/', $content , $matches);

      if (count($matches[1]) !== 0) {
        echo '</br>' .$Typo3Page->Title . '--' . '</br>' . PHP_EOL;
      }

      foreach ($matches[1] as $linkID) {
        $obj = Typo3Page::get()->filter(array('Typo3UID' => $linkID))->First();
        $orig_link_string = 'link ' . (string)$linkID;
        $replace_link_string = 'a '. 'href="[sitetree_link, id=' . (string)$obj->ID .']"';
        if (!$obj->ID) {
          echo " $orig_link_string -- $replace_link_string" . '</br>' . PHP_EOL;
        }

        $content = str_replace($orig_link_string, $replace_link_string, $content);
      }

      // replace all the closing link tags with a tags
      $content = str_replace('/link', '/a', $content);
      $Typo3Page->Content = $content;
      $Typo3Page->write();
    }
  }

}
