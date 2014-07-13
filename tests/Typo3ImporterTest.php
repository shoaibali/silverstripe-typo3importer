<?php
class Typo3ImporterTest extends FunctionalTest {
  
  protected $extraDataObjects = array(
    'SiteTree' // Hack to get SapphireTest refreshing the DB
  );
  
    
  function testImport() {
    // create sample record
    $page = new SiteTree();
    $page->Title = 'ShouldBeExisting';
    $page->write();
    

    $importer = singleton('Typo3Importer');

    // TODO another better way to do this would be to store small XML in to a string or read it from file 
    // and the save it in to assets/typo3import directory. As part of the fixtures setup/

    $file = dirname(__FILE__) . "/typo3import/t3export.xml";

    $files = array($file); // this can be more than one file hence an array

    $importer->bulkimport(array("files" => $files));
  
    $existing = DataObject::get_one('SiteTree', "\"Title\" = 'ShouldBeExisting'");
    $parent1 = DataObject::get_one('SiteTree', "\"Title\" = 'Title 1'");
    $child1_1 = DataObject::get_one('SiteTree', "\"Title\" = 'Title 2'");
    $child1_2 = DataObject::get_one('SiteTree', "\"Title\" = 'Title 3'");
    $child1_3 = DataObject::get_one('SiteTree', "\"Title\" = 'Title 4'");
    $child1_4 = DataObject::get_one('SiteTree', "\"Title\" = 'Title 5'"); 
    
    $this->assertInstanceOf('SiteTree', $existing);
    $this->assertInstanceOf('SiteTree', $parent1);
    $this->assertInstanceOf('SiteTree', $child1_1);
    $this->assertInstanceOf('SiteTree', $child1_2);
    $this->assertInstanceOf('SiteTree', $child1_3);
    $this->assertInstanceOf('SiteTree', $child1_4);
    
    $this->assertEquals($child1_1->ParentID, $parent1->ID);
    $this->assertEquals($child1_2->ParentID, $child1_1->ID);
    $this->assertEquals($child1_3->ParentID, $child1_1->ID);
    $this->assertEquals($child1_4->ParentID, $child1_3->ID);

  }

}
?>