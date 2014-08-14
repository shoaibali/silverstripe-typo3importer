<?php
require_once(dirname(__FILE__).'../../code/Typo3Importer.php');

class Typo3ImporterTest extends FunctionalTest {
	protected $importer = null;

	public function setUp() {
		$this->importer = new Typo3Importer();
		$this->testContent = '<h3>Test content</h3>
													<p>Link</p>
													<link 1234>Link Title</link>
													<p>Another link</p>
													<link http://www.google.com _top>Google</link>
													<link http://www.mysite.com>Mysite</link>
													<p>End</p>';
		self::$is_running_test = true;
	}

	public function tearDown() {
		unset($this->importer);
	}
	
	protected $extraDataObjects = array(
		'SiteTree' // Hack to get SapphireTest refreshing the DB
	);
	
	function testImport() {
		// create sample record
		$page = new SiteTree();
		$page->Title = 'ShouldBeExisting';
		$page->write();
		
		// TODO another better way to do this would be to store small XML in to a string or read it from file 
		// and the save it in to assets/typo3import directory. As part of the fixtures setup/

		$file = dirname(__FILE__) . "/typo3import/t3export.xml";

		$files = array($file); // this can be more than one file hence an array

		$this->importer->bulkimport(array("files" => $files), null);
	
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

	function testSantizeLinkLocation() {
		// case 1: clean link_location, function should return it as it is
		$link_location = '1234';
		$this->assertEquals($this->importer->santizeLinkLocation($link_location), $link_location);

		// case 2: clean link_location (contains # as part of string), function should return it as it is
		$link_location = 'http://testwebsite.com#introduction';
		$this->assertEquals($this->importer->santizeLinkLocation($link_location), $link_location);

		// case 3: link_location starts with a character followed by a number, function should return it as it is
		$link_location = 'a1234';
		$this->assertEquals($this->importer->santizeLinkLocation($link_location), $link_location);

		// case 4: link_location contains number followed by #, function should santize and return the correct link_location
		$link_location = '4662#13992';
		$this->assertEquals($this->importer->santizeLinkLocation($link_location), '4662');

		// case 5: link_location contains number followed by whitespace, function should santize and return the correct link_location
		$link_location = '3351 _top';
		$this->assertEquals($this->importer->santizeLinkLocation($link_location), '3351');
	}

	function testExtractLinksData() {
		$content = $this->testContent;
		list($titles, $links) = $this->importer->extractLinksData($content);

		$this->assertEquals($titles[0], 'Link Title');
		$this->assertEquals($titles[1], 'Google');
		$this->assertEquals($titles[2], 'Mysite');

		$this->assertEquals($links[0], '1234');
		$this->assertEquals($links[1], 'http://www.google.com _top');
		$this->assertEquals($links[2], 'http://www.mysite.com');
	}

	function testReconstructLink() {
		$content = $this->testContent;

		// case 1: everything after the space in the link location must be replaced with cleanedup link
		$link_location = 'http://www.google.com _top';
		$cleanedup_link_location = 'http://www.google.com';
		$cleanedup_content = $this->importer->reconstructLink($content, $link_location);

		// original link location should not be there anymore
		$this->assertFalse(strpos($cleanedup_content, $link_location));
		// original link location must have been replaced by cleanedup link location
		$this->assertContains($cleanedup_link_location, $cleanedup_content);
	}
}
?>