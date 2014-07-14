# Typo3 Importer Module

## Maintainer Contact

 * Shoaib Ali 
   <shoaib (at) catalyst (dot) net (dot) nz>

## Requirements
 
 * SilverStripe 3.0 or later

## Installation

Download or git clone this repository in to root of your SilverStripe installation

Run /dev/build?flush=all

Add the line before to file silverstripe_urls.acl: 
```
#!php

^\/Typo3Importer
```

Restart Squid3 and Apache: sudo service squid3 restart && sudo service apache2 restart

## Usage 

Login to your CMS administration area and visit Files section.

Add a Folder called typo3import

Upload all the XML files for each individual sections from Typo3.

Visit `http://localhost/Typo3Importer?flush=1`.

*Publish everything after the import?* - This will publish each of the pages that the importer creates. 
If you don't tick this, then the pages will be left as draft-only.
			
## Related

See the [static importer module](http://silverstripe.org/static-importer-module/) for a more sophisticated
importer based on crawling existing HTML pages, and extracting content via XPATH.