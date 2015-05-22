<?php
/*
Description: This is the config file for the contentdm2flickr tool. More details in the Wiki for this tool.
Copyright (C) 2011  VCU Libraries

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see http://www.gnu.org/licenses/

*/

define('CDM_URL','http://your.contentdm.url:81/');
define('OAI_URL',CDM_URL.'cgi-bin/oai.exe');
// options for VCU CDM are oai_dc and qdc (not supported by uploader yet)
define('OAI_METADATAPREFIX','oai_dc');
define('CDM_THUMBNAIL_URL', CDM_URL.'cgi-bin/thumbnail.exe');
define('CDM_IMAGE_URL', CDM_URL.'cgi-bin/getimage.exe');
// limit the size of the images uploaded to flickr
define('MAX_DERIVATIVE_WIDTH', 2500);
define('MAX_DERIVATIVE_HEIGHT', 2500);
define('MAX_DERIVATIVE_SCALE', 50);


define('FLICKR_API_KEY','');
define('FLICKR_SECRET','');
define('FLICKR_TOKEN','');

define('STD_TAGS','VCU Libraries, VCU digital collections');
define('TOOL_HOME_PAGE', CDM_URL.'flickrdev/CONTENTdm2Flickr.php');


$stopwords = array (', etc.',',');
//description line item pattern
$desc_line_item_pattern = "<b>%key%</b>: %value%\n\n";

//thumbnails for the front page: each of the keys in the associated array below would be the 
// set names for the collection and the number is the thumbnail number (picture in the collection)
// if the number is replaced with "na" a deafault picture is picked 
$thumbnails = array( "car" => 6, "com" => 6, "cmh" => 6, "pec" => 6, "fff" => 6, "mcv" => 6, "rpi" => 6, "jwh" => 6, "mar" => 6, "nur" => 6, "psm" => 6, "postcard" => 6,  "rca" => 6, "rcp" => 6, "rhr" => 6, "san" => 6, "ohi" => 6, "voices" => 6, );

// the pictures in the collections listed below would be loaded on the Flickr Uploader page
// rest would be left out as the listings and picture loading take a lot of time.

// Filters the item records under a collection based on a regex pattern
// in the case below all records with description = Joe's Dope Sheet 
// OR title = cover1 would be picked to be displayed on the screen
$manipulate_data 
= array ( 
	// example collection JWH with sample rules. see readme for more info.
	'jwh' => array
	 	( 
		array 
			(
			// example of filtering which items you want to pull from a collection using filter_rules
			'filter_rules' => array 
				(
					"dc:format" => "/^Black and white photograph/"
				),
			// mapping rules define how your metadata will display
			'mapping_rules' =>  array 
				( 
					"Address/Title" => "%dc:title%",
					"Other Title(s)" => "%dc:title,1%", 
					"Photographer" => "%dc:creator,1%",
					"Original Description (from Book)" => "%dc:description,0%",
					"City/Location" => "%dc:coverage%",
					"Date of photograph" => "%dc:date,/^ca\./%",
					"Map URL" => "%dc:description,/^http:\/\/.*/%",
					"Original Publication" => "%dc:source,0%",
					"Rights" => "<a href=\"%dc:rights%\">%dc:rights%</a>",
					"Reference URL" => "<a href=\"%dc:identifier,99999%\">%dc:identifier,99999%</a>",
					"Collection" => "<a href=\"http://go.vcu.edu/jacksonward\">%dc:source,1%</a>"
					//"Collection" => "<a href=\"%dc:identifier,99999%\">%dc:source,1%</a>"
				),
			// special rules add more metadata
			'special_rules' => array
				(
				 	"GeoTag"	=> "%dc:description,/^[0-9]*\.[0-9]*[,]?[+]?[-][0-9]*\.[0-9]*/%",
					"DateTag" => "%dc:date,/^ca\./%"
				)
			)
		),
);
?>
													
