<?php
/*
		Provides an interface to publish ContentDM pictures and metadata to Flickr.
		An open source code that has been hosted on contentdm2flickr.googlecode.com
		
		The project was made by the Virginia Commonwealth University Library Web Development team
		Developers: Naveen Shetty, shettynr@vcu.edu
								Erin White, erwhite@vcu.edu
								
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
    along with this program.  If not, see <http://www.gnu.org/licenses/>.	
		
		The code uses following opensource projects, thanks for your help!
			OAI Client class from the CWIS project - http://scout.wisc.edu
			jQuery Dynatree - http://code.google.com/p/dynatree/
			phpFlickr - http://code.google.com/p/phpflickr/
			
			Revision History
			----------------
			Developer: shettynr 	Date:07/13/2011
			- added the handling of the pattern 99999 - to be treated as the last index of an tag
			- made a change in the pattern to split the GeoTags (found irregularities in the data)
			- added the DateTag as a special rule
			- changed the description from a normal variable to array
			
			Developer: shettynr		Date:09/15/2011
			- changed the flickr tree generation logic as a bug was reported when a single 
				collection existed in Flickr
			- added new functionality to allow sets (without any collections) to be displayed 
				and used in the UI.
				
			Developer: shettynr		Date:09/20/2011
			- removed the debug statements to print the list of collections and sets on Flickr
			- Print Flickr Set had a bug
			
			Developer: shettynr   Date:09/28/2011
			- Check if there are no Sets in a Collection

*/
// include the OAI client library 
include 'lib/OAIClient.php';
include 'config.php';
// load phpFlickr 3.1 library
require_once("lib/phpFlickr.php");

$oaiClient = new OAIClient(OAI_URL);
$oaiClient->MetadataPrefix(OAI_METADATAPREFIX);
$frob = new phpFlickr(FLICKR_API_KEY, FLICKR_SECRET);
$frob->setToken(FLICKR_TOKEN);
$stdTags = STD_TAGS;

$Sets = array();
?>
 <meta http-equiv="content-type" content="text/html; charset=ISO-8859-1"> 
  <title>CONTENTdm 2 Flickr</title> 
 
  <script src="jquery132/jquery.js" type="text/javascript"></script> 
  <script src="jquery132/ui.core.js" type="text/javascript"></script> 
  <script src="jquery132/jquery.cookie.js" type="text/javascript"></script> 
  <link href="css/ui.dynatree.css" rel="stylesheet" type="text/css"> 
  <script src="jquery132/jquery.dynatree.min.js" type="text/javascript"></script> 
 
  <script type="text/javascript"> 
    $(function(){
      $("#cdm_tree").dynatree({
        //Tree parameters
        persist: false,
        checkbox: true,
        selectMode: 3,
        activeVisible: true,
		  
        //Un/check real checkboxes recursively after selection
        onSelect: function(select, dtnode) {
          dtnode.visit(function(dtnode){
			//alert(dtnode.data.key);
			//alert("#chb-"+dtnode.data.key);
            $test = $("#chb-"+dtnode.data.key).attr("checked",select);
            },null,true);
			
        },
        //Hack to prevent appearing of checkbox when node is expanded/collapsed
        onExpand: function(select, dtnode) {
          $("#chb-"+dtnode.data.key).attr("checked",dtnode.isSelected()).addClass("hidden");
        }
      });
      //Hide real checkboxes
      $("#cdm_tree :checkbox").addClass("hidden");
      //Update real checkboxes according to selections
      $.map($("#cdm_tree").dynatree("getTree").getSelectedNodes(),
        function(dtnode){
          $("#chb-"+dtnode.data.key).attr("checked",true);
          dtnode.activate();
        });
		  
      });
  </script>
<?php

if (isset($_POST) && !empty($_POST)) {
	validate();
} else {
	index();
}

function index(){
	global $Sets;
	global $oaiClient;
	//$compoundObject = array('44 p.');
	//$imageObject = array('image/jp2', 'image/jpeg', 'image/jpeg2', 'jpeg');
	//$count = 0;

	$Sets = $oaiClient->GetSets();
	echo '<h1>CONTENTdm to Flickr</h1> '."\n";
	echo '<p>Max image dimensions: '.MAX_DERIVATIVE_WIDTH.' x '.MAX_DERIVATIVE_HEIGHT.'</p>';
	ob_start();
	echo '<form method="POST" action="CONTENTdm2Flickr.php"> '."\n";
	cdm_tree();
	echo '<div id="form_buttons">';
	echo '<input type="submit" value="Upload >>"  class="button"/> <br/> <br/>';
	echo '<input type="radio" name="visibility" value="public"> Make Public </input><br/>';
	echo '<input type="radio" name="visibility" value="private" checked ="true"> Keep Private </input><br/>';
	
	echo '</div>';
	flickr_tree();
	ob_end_flush();
	echo '<div id="attribution">';
	echo "This tool was made with the help of the following Opensource projects:  <br /> <li/> OAI Client class from the CWIS project - http://scout.wisc.edu
			<br /> <li/> jQuery Dynatree - http://code.google.com/p/dynatree/
			<br /> <li/> phpFlickr - http://code.google.com/p/phpflickr/";
	echo '</div>';
	echo "</form>";
}

/*
	Checks if a flickr set is selected on the index page.
	Control is passed to upload_images after the validation is successful,
	else an error message is displayed
*/
function validate() {
	echo '<h1> Uploading images </h1>';
	echo '<a href="'.TOOL_HOME_PAGE.'"> << Click here to go back to Upload page >> </a><hr>';
	if(!isset($_POST['selected_flickr_set']) || empty($_POST['selected_flickr_set'])) {
		echo "Oops! You didn't select the Flickr Set to which the file will be uploaded.";
	} else {
		upload_images();
	}
	echo '<hr><a href="'.TOOL_HOME_PAGE.'"> &laquo; Back to upload page</a>';
}

/*
	Debug code to print an array
*/
function p($arr) {
	echo '<pre>';
	print_r($arr);
	echo '</pre>';
}

/*
	Generate a jQuery (Dynatree - http://code.google.com/p/dynatree/) tree to 
	display the XML harvested from the OAI repository. Each collction in the 
	repository appears in the list and the associated records for that collection.
	Note: Records only appear if the config is set right for the collection.
	
	A thumbnail is displayed next to the record title for ease of use.
*/

function cdm_tree () {
	global $frob;
	global $oaiClient;
	global $Sets;
	global $filter_collections;
	global $manipulate_data;
	global $thumbnails;
	echo '  <div id="cdm_tree"> '."\n";
	echo "   <ul>\n";
	$setIndex = 0;
	foreach ($Sets as $set) {
		$setIndex ++;
		echo "\t".'<li id="key'.$setIndex.'" title="'.$set['setSpec'].'">';
		//echo $set['setName'] ;
		$thmb_no = 6;
		$thmb_url = CDM_THUMBNAIL_URL.'?CISOROOT=/'.$set['setSpec'].'&CISOPTR='.$thmb_no;
		if (array_key_exists(trim($set['setSpec']), $thumbnails)) {
			if ($thumbnails[trim($set['setSpec'])] != "na") {
				$thmb_no = $thumbnails[trim($set['setSpec'])];
				$thmb_url = CDM_THUMBNAIL_URL.'?CISOROOT=/'.$set['setSpec'].'&CISOPTR='.$thmb_no;
			} else {
				$thmb_url = CDM_URL.'flickrdev/css/nia.png';
			}
		} 
		echo '<img class="cdm_thumbnail" src="'.$thmb_url.'" alt="thumbnail" />';
		echo "\n\t\t".'<input type="checkbox" id="chb-key'.$setIndex.'" name="selected_set_'.$set['setSpec'].'" value="'.$set['setSpec'].'" />'.$set['setName'];
		//error_log(implode(array_keys($manipulate_data)).'-'.$set['setSpec'].'-');
		if (array_key_exists($set['setSpec'], $manipulate_data)) {
			echo "\n\t\t\t".'<ul> ';
			$recIndex = 0;
			$oaiClient->SetSpec($set['setSpec']);
			do {
				$Records = $oaiClient->GetRecords();
				foreach ($Records['ListRecords']['record'] as $record)
				{
					$ruleIndex = getSpecificRulesIndex($record);
					//if ($set['setSpec'] == 'psm') {error_log('Set name : ' . $set['setSpec'] . ' Rule number ' . $ruleIndex);}
					if($ruleIndex != -1) {
						$recIndex ++;
						$id = explode('/',$record['header']['identifier']);
						echo "\n\t\t\t\t".'<li id="key'.$setIndex.'-'.($recIndex-1).'">';
						echo '<img class="cdm_thumbnail" src="'.CDM_THUMBNAIL_URL.'?CISOROOT=/'.$set['setSpec'].'&CISOPTR='.$id[1].'" alt="'.$record['metadata']['oai_dc:dc']['dc:title'].'" />';
						echo '<input type="checkbox" id="chb-key'.$setIndex.'-'.($recIndex-1).'" name="selected_set_'.$set['setSpec'].'.'.$id[1].'" value="'.$record['header']['identifier'].'" /> ';
						if(count($record['metadata']['oai_dc:dc']['dc:title'])>1) {
							echo $record['metadata']['oai_dc:dc']['dc:title'][1];
						} else {
							echo $record['metadata']['oai_dc:dc']['dc:title'];
						}
						echo '</li> '."\n";
					}
				}
			} while ( $oaiClient->MoreRecordsAvailable() );
			echo "\n\t\t\t".'</ul>'."\n";
			$oaiClient->ResetRecordPointer();
		}
		echo "</li>\n";
	}
	echo "</ul>";
	echo "</div>";
	
}

/*
	A list of the Collections and corresponding sets are displayed on the screen for selection
	Note: the code assumes the existence of Collections in your flickr page if you have 
	no collections, then the Tree might comeout to be wrong (needs to be updated for that)
*/

function flickr_tree () {
	global $frob;
	$response = $frob->collections_getTree();
	echo '<div id="flickr_tree"> <ul>';
	$setIndex=0;
	if(!empty($response['collections'])) {
		if(is_array($response['collections']['collection'])) {
			$collections = $response['collections']['collection'];
		} else {
			// when only one collection exists
			$collections = $response['collections'];
		}
		foreach($collections as $collection) {
			$setIndex ++;
			echo "\t".'<li id="key'.$setIndex.'" title="'.$collection['title'].'">';
			echo $collection['title']."\n\t\t<ul>";
			$recIndex = 0;
			if(array_key_exists('set', $collection) && !empty($collection['set'])) {
				if(is_array($collection['set'])) {
					foreach ($collection['set'] as $set) {
							printFlickrSet($setIndex, $recIndex, $set);
							$recIndex ++;
					}
				}
			}
			echo "\n\t\t</ul>";
		}
	} else {
		// when no collections exist, check for sets
		$photosets_resp = $frob->photosets_getList();
		if(!empty($photosets_resp['photoset'])) {
			/*if(is_array($photosets_resp['photosets']['photoset'])) {
				$sets = $photosets_resp['photosets']['photoset'];
			} else {
				// when only one set exists
				$sets = $photosets_resp['photosets'];
			}*/
			$sets = $photosets_resp['photoset'];
			$recIndex = 0;
			foreach($sets as $set) {
				printFlickrSet($setIndex, $recIndex, $set);
				$recIndex ++;
			}
		} else {
			//when no sets or collections exist
			echo "\n\t\t".'<li> You have no Collections or Sets in your Flickr Account';
			echo "\n".', see <a href="http://www.flickr.com/help/photos/">http://www.flickr.com/help/photos/</a></li>';
		}
	}
	echo "</ul></div>";
}

/*
	Prints one Flickr set on the page.
*/
function printFlickrSet($setIndex, $recIndex, $set){
	echo "\n\t\t\t\t".'<li id="key'.$setIndex.'-'.$recIndex.'">';
	echo '<input type="radio" id="flr-key'.$setIndex.'-'.$recIndex;
	echo '" name="selected_flickr_set" value="'.$set['id'].'" />';
	echo $set['title'] ;
	echo "</li>";
}

/*
	Uploads the images and the metadata into the selected Flickr set.
	It calls the methods to generate the description, the text tags and the Geo tags.
	
	The output from this function is printed on the page for logging pusposes.
*/

function upload_images(){
  global $frob;
	global $oaiClient;
	global $stdTags;

	if (isset($_POST) && !empty($_POST)) {
		foreach ($_POST as $postSet => $postValue) {
			$postedInfo = explode('_',$postSet);
			if(count($postedInfo) == 4 && $postedInfo[1] == 'set') {
				$cdmSet = $postedInfo[2];
				$cdmRec = $postValue;
				$cdmRec = explode('/',$cdmRec);
				$url = CDM_IMAGE_URL.'?CISOROOT=/'.$cdmSet.'&CISOPTR='.$cdmRec[1].'&DMSCALE='.MAX_DERIVATIVE_SCALE.'&DMWIDTH='.MAX_DERIVATIVE_WIDTH.'&DMHEIGHT='.MAX_DERIVATIVE_HEIGHT;
				$img = './temp/'.$cdmSet.'_'.$cdmRec[1].'.jpg';
				// image files cached would be skipped
				if (!file_exists($img)) {
					file_put_contents($img, file_get_contents($url));
				}
				$oaiClient->SetSpec($cdmSet);
				$record = $oaiClient->GetRecord(implode('/',$cdmRec));
				$is_public = 0;
				if(isset($_POST['visibility']) && !empty($_POST['visibility'])) {
					if ($_POST['visibility'] == 'private') {
						$is_public = 0;
					} else {
						$is_public = 1;
					}
				}
				$title = $record['GetRecord']['record']['metadata']['oai_dc:dc']['dc:title'];
				if (is_array($title)){
					$title = $title[0];
				}
				$tags = $stdTags;
		       // add subjects to tags
		       // $tags = $stdTags.create_tags($record);
				// add the Title to tags
				//$tags = $tags.','.$title;
				$description = generate_description($record['GetRecord']['record']);
				if(!empty($description["title"])) {
					$title = $description["title"];
				}
				if (file_exists($img)){
					if(($idPhoto = $frob->sync_upload($img, $title, $description["desc"], $tags, $is_public, 0, 0, 0))>0) {
						echo '<img class="cdm_thumbnail" src="'.CDM_THUMBNAIL_URL.'?CISOROOT=/'.$cdmSet.'&CISOPTR='.$cdmRec[1].'" alt="'.$title.'" />';
						echo '<br><b> File upload for image titled "' . $title. '" was successful! </b> <br/>';
						echo '<u>Tags:</u>'.$tags.'<br/>';
						echo 'Description'.$description["desc"].'<br/>';
						
						$frob->photosets_addPhoto ($_POST['selected_flickr_set'], $idPhoto);
						if(!empty($description["geoTag"])) {
							// Geo Location granularity has been set at 16 (highest) to signify street level address
							$frob->photos_geo_setLocation ($idPhoto, $description["geoTag"][0], $description["geoTag"][1], 16, 2);
							echo '<b>Geo Location:</b> '.$description["geoTag"][0].','.$description["geoTag"][1].'<br/>';
						}
						if(!empty($description["dateTag"])) {
							// Date Granularity is set to 8 for Circa dates, for more detailed dates change the value
							// See http://www.flickr.com/services/api/misc.dates.html
							$frob->photos_setDates ($idPhoto, NULL, $description["dateTag"], 8);
							echo '<b>Date Tag:</b> '.$description["dateTag"].'<br/>';
						}
						flush();
					} else {
						echo 'File upload for image titled "' . $title. '" has failed!<br/>';
					}
				}
			}
		}
	}
}

/*
	Creates tags for flickr as per the information in the subject field(s) of the OAI xml

*/
function create_tags($record) {
	$tags = '';
	global $stopwords;
	// add subjects
	if(array_key_exists('dc:subject',$record['GetRecord']['record']['metadata']['oai_dc:dc'])) {
		$subject = $record['GetRecord']['record']['metadata']['oai_dc:dc']['dc:subject'];
		if (is_array($subject)) {
			foreach($subject as $tag_list) {
				foreach($stopwords as $stopword) {
					$tag_list = str_replace($stopword,'',$tag_list);
				}
				// don't split subjects
				//$tag_list = preg_split('/( -- |--|; |;)/', $tag_list);
				$tag_list = preg_split('/(; |;)/', $tag_list);
				$tags = $tags.', '.implode(', ', $tag_list);
			}
		} else if(count($subject) == 1) {
				foreach($stopwords as $stopword) {
					$subject = str_replace($stopword,'',$subject);
				}
				// don't split subjects
				//$tag_list = preg_split('/( -- |--|; |;)/', $subject);
				$tag_list = preg_split('/(; |;)/', $subject);
				$tags = $tags.', '.implode(', ', $tag_list);
		} else {
			$tags = '';
		}
	}
	return $tags;
}

/*
	Generate a Description with minimal set of tags to be displayed under the picture in flickr.
	The Description is constructed based on the config (mapping_rules for the collection).
	
	Note: More details are provided in the config.php file.
*/

function generate_description($record) {
	// from the config file
	global $manipulate_data;
	global $desc_line_item_pattern;
	$item = $record['metadata']['oai_dc:dc'];
	$setName = $record['header']['setSpec'];
	// if mapping rules exist for the set value in the record
	$description = "\n";
	$index = getSpecificRulesIndex($record);
	$GeoCoords = array();
	$title_from_desc = '';
	if($index != -1) {
		if(array_key_exists('mapping_rules', $manipulate_data[$setName][$index])) {
			foreach($manipulate_data[$setName][$index]['mapping_rules'] as $header => $mappingRule) {
				$line_item = array ('key'=> $header,'value'=>parse_and_replace($item, $mappingRule));
				if (!empty($line_item['value'])) {
					if(strpos($header, 'Title') !== false) {
						if (strlen($title_from_desc) != 0) {
							$title_from_desc = $title_from_desc.','.$line_item['value'];
						} else {
							$title_from_desc = $line_item['value'];
						}
					}
					$description = $description.parse_and_replace($line_item, $desc_line_item_pattern);
				}
			}
		}
		if(array_key_exists('special_rules', $manipulate_data[$setName][$index])) {
			foreach($manipulate_data[$setName][$index]['special_rules'] as $header => $splRule) {
				switch($header) {
					case "GeoTag":
					  error_log($splRule.' :: '.parse_and_replace($item, $splRule));
						$GeoCoords = preg_split('/[,]?[+]/',parse_and_replace($item, $splRule));
						break;
					case "DateTag":
						$DateTags = preg_replace('/ca. /', '', parse_and_replace($item, $splRule))."-01-01";
						error_log('Circa '. $DateTags);
						break;
				}
			}
		}
	}
	
	return array(
							 "desc" => $description, 
							 "title" => $title_from_desc,  
							 "geoTag" => $GeoCoords, 
							 "dateTag" => $DateTags
							 );
}

/*
	The record details are matched against the filters provided for the collection.
	If a filter matches the details then the index for that filter is passed back 
	to the calling function, if not a -1 is sent back.
*/
function getSpecificRulesIndex($record) {
	global $manipulate_data;
	$setName = $record['header']['setSpec'];
	$record = $record['metadata']['oai_dc:dc'];
	$position = 0;
	$idx = -1;
	if(array_key_exists($setName, $manipulate_data)) {
		foreach($manipulate_data[$setName] as $ruleSet) {
			$predicate = 0;
			if(array_key_exists('filter_rules', $ruleSet)){
				foreach($ruleSet['filter_rules'] as $tagName => $filterRule){
					if(array_key_exists($tagName, $record)) {
						//error_log('Test message' .$tagName.'='.$filterRule);
						if (is_array($record[$tagName])) {
							// when there are multiple tags with the same name							
							$arr_matched = preg_grep($filterRule, $record[$tagName]);
							//error_log('Matched Rules ' . implode($arr_matched, ':'));
							if(!empty($arr_matched)) {
								$predicate = $predicate + count($arr_matched);
							}
						} else {
							//error_log('Preg match:'.preg_match($filterRule, $record[$tagName]).' Tag data:'.$record[$tagName]);
							$predicate = $predicate + preg_match($filterRule, $record[$tagName]);
						}
					}
				}
			} else {
				// When no filter rules are mentioned then all the records need to be displayed
				// set the predicate to 1, so that the mapping for the same is done.
				$predicate = 1;
			}
			if ($predicate >= 1) {
				//error_log('Predicate ' . $predicate);
				$idx = $position;
				return $idx;
			}
			$position++;
		}
	}
	return $idx;
}


/*
	Searches for a needle string in a haystack (string) and returns the part 
	before the needle. PHP 5.1.6 doesn't have a function for this but PHP 5.3.0 does.
*/
function rstrstr($haystack,$needle)
{
		if (strpos($haystack, $needle)===false) {
			return substr($haystack, 0);
		} else {
			return substr($haystack, 0,strpos($haystack, $needle));
		}
}

/*
	Searches and replaces any patterns in the mapping rules provided.
	The details of the possible mapping rules have been higlighted in the config file
*/
function parse_and_replace($item, $mappingRule) {
	$position = 0;
	$replace_values = array();
	$parse_for_tags = null;
	$matches_found = preg_match_all('/%[^%]+%/', $mappingRule, $parse_for_tags, PREG_OFFSET_CAPTURE);
	$rest_of_str = preg_split('/%[^%]+%/', $mappingRule, -1, PREG_SPLIT_OFFSET_CAPTURE);
	if(isset($matches_found) && $matches_found !== false) {
		foreach($parse_for_tags[0] as $tag) {
			$replaced_with = '';
			$tag[0] = preg_replace('/%/','',$tag[0]);
			$parts = array();
			$parts[0] = rstrstr($tag[0], ',');
			$parts[1] = substr(strstr($tag[0], ','), 1);
			if (count($parts) > 1) {
				if(array_key_exists($parts[0], $item)) {
					if(is_numeric($parts[1])) {
						if(array_key_exists($parts[1], $item[$parts[0]])) {
							$replaced_with = $item[$parts[0]][$parts[1]];
						} else if ($parts[1] == 99999) {
							// 99999 indicates the last value of the particular tag say the last identifier tag on the record.
							// if there is only one identifier then the last is also the first.
							if (is_array($item[$parts[0]])) {
								//error_log('in the LAST index part :'.$parts[0]. ' '.count($item[$parts[0]])-1);
								$replaced_with = $item[$parts[0]][count($item[$parts[0]])-1];
							} else {
								//error_log('in the LAST index part : no index - single element');
								$replaced_with = $item[$parts[0]];
							}
						} else if(array_key_exists(0, $item[$parts[0]])){
							// if the index doesn't exist
							$replaced_with = $item[$parts[0]][0];
						}
					} else {
						if (is_array($item[$parts[0]]) && !empty($parts[1])) {
							$replaced_with = preg_grep($parts[1], $item[$parts[0]]);
							if (!empty($replaced_with) && is_array($replaced_with)) {
								$vals = array_values($replaced_with);
								$replaced_with = $vals[0];
							}
						} else {
							if(is_array($item[$parts[0]])) {
								// if the field has an array of values in the XML then pick the first one
								// and no index is provided as part of the pattern
								$replaced_with = $item[$parts[0]][0];
							} else {
								$replaced_with = $item[$parts[0]];
							}
						}
					}
				} else {
					// if the tag is not present on this record : do nothing return an empty
				}
			} else {
				if(array_key_exists($parts[0], $item)) {
					if(is_array($item[$parts[0]])) {
						// if the field has an array of values in the XML then pick the first one
						// and no index is provided as part of the pattern
						$replaced_with = $item[$parts[0]][0];
					} else {
						$replaced_with = $item[$parts[0]];
					}
				} else {
					// if the tag is not present on this record : do nothing return an empty
				}
			}
			$replace_values[$position] = $replaced_with;
			$position ++;
		}
		$final_output = '';
		$position = 0;
		foreach($replace_values as $val) {
			$final_output = $final_output.$rest_of_str[$position][0].$val;
			$position ++;
		}
		$final_output = $final_output.$rest_of_str[$position][0];
	} else {
		// In case when there are no tags in the pattern, then it is a hard code.
		$final_output = $mappingRule;
	}
	return $final_output;
}

?>