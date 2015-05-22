CONTENTdm to Flickr Uploader
==========

CONTENTdm2Flickr is an interface to publish images and metadata from CONTENTdm® OAI-enabled image repositories to Flickr®.

This project was developed by members of the web team at Virginia Commonwealth University Libraries, and was inspired by the [Bulk CONTENTdm to Flickr project at MU-Ohio](http://staff.lib.muohio.edu/~tzocea/files/flickr/).

## Why CONTENTdm22flickr?

 * Flickr is one of the most popular cloud based photo-sharing websites.
 * Flickr provides statistics, organization of pictures into collections and sets, allows user tagging, geo-tagging, comments, and greater SEO, to name a few.
 * As of August 2011 CONTENTdm provides no native mechanism to transport both the metadata and pictures to external sites like Flickr.

## What is this tool?

 * A simple web-based PHP application to export images from CONTENTdm OAI image repositories to Flickr
 * The tool captures both the images and their metadata
 * The tool then pushes those images into a Flickr set (a grouping of pictures within Flickr).

## Examples
Here are some of the VCU Libraries collections published on Flickr using this software:

 * PS Magazine, the Preventive Maintenance Monthly - [on Flickr](http://www.flickr.com/photos/vculibraries/sets/72157627270803855/), [in CONTENTdm](http://go.vcu.edu/psmagazine)
 * Medical Artifacts Collection [on Flickr](http://www.flickr.com/photos/vculibraries/sets/72157627037321816/), [in CONTENTdm](http://go.vcu.edu/medartifacts)
 * Jackson Ward Historic District [on Flickr](http://www.flickr.com/photos/vculibraries/sets/72157626912678597/), [in CONTENTdm](http://go.vcu.edu/jacksonward)
 * Sykes Editorial Cartoon Collection, [on flickr](http://www.flickr.com/photos/vculibraries/sets/72157630065360514/), [in CONTENTdm](http://go.vcu.edu/sykes)

## Installation

### Requirements

 * A CONTENTdm server with OAI enabled (we connected this to CONTENTdm 5.3+ and 6+)
 * PHP 5.1.6 or above on an Apache server
 * Flickr account, API Key and secret key

### Installation

Download and install on a web server, any ol' server will do.

### Configuration

All the configuration is present in the config.php file.

```
define('CDM_URL','http://dig.library.vcu.edu/');
define('OAI_URL',CDM_URL.'cgi-bin/oai.exe');
define('OAI_METADATAPREFIX','oai_dc');
define('CDM_THUMBNAIL_URL', CDM_URL.'cgi-bin/thumbnail.exe');
define('CDM_IMAGE_URL', CDM_URL.'cgi-bin/getimage.exe');
define('FLICKR_API_KEY','<FLICKR API KEY GOES HERE>');
define('FLICKR_SECRET','<FLICKR SECRET KEY GOES HERE>');
define('FLICKR_TOKEN','<FLICKR TOKEN GOES HERE>');
define('STD_TAGS','VCU Libraries, VCU digital collections,');
define('TOOL_HOME_PAGE', CDM_URL.'flickrdev/CONTENTdm2Flickr.php');
```
    
Change the value for CDM_URL from http://dig.library.vcu.edu/ with the web URL for CONTENTdm.

If you are not planning to host the tool in the same location as CONTENTdm then the TOOL_HOME_PAGE value needs to change to correct URL pointing to the main page.

The OAI URL is generally configured to be the same, so verify the installation by visiting CONTENTdm Administration page (Settings tab). There should be a listing for 'Base URL for OAI repository', copy and replace that value here if there is any difference.

The STD_TAGS needs to be changed to list the common tags that would be added to all the pictures that are uploaded by the tool. We have defaulted to our institution and library organization name.

To find the correct Metadata prefix to update the OAI_METADATAPREFIX, go to the link <CONTENTdm Web URL>/cgi-bin/oai.exe?verb=ListMetadataFormats. You will able to see your listing of MetadataFormats, pick the one that you need here. Example formats: oai_dc, qdc, etc.

### Pulling out the right metadata

One of the problems of selecting to use the OAI XML rather than a tab-limited file or XML file on the CONTENTdm server is that sometimes descriptive metadata is shoehorned into blunt tags, e.g: the description tag has been used to hold GIS information, image description, location and URLs.

To make sure that the right information is grabbed from the right record and to make sure the rendering is configurable, the $manipulate_data variable holds all this config:

```
	$manipulate_data = array ( 
        'psm' => array // Array of Rule-sets
                ( 
                array // First Rule-set
                        (
                        'filter_rules' => array 
                                (
                                        "dc:description" => "/^Joe's Dope Sheet/" 
                                ),
                        'mapping_rules' =>  array // mapping rules for the record with description starting with Joe's Dope Sheet
                                ( 
                                        "Title" => "Joe's Dope Sheet (%dc:title%)", 
                                        "Creator and Illustrator" => "%dc:creator,1%; %dc:creator,0%",
                                        "Rights" => "<a href=\"%dc:rights%\">%dc:rights%</a>",
                                        "Collection" => "<a href=\"%dc:identifier,/^http:\/\/.*/%\">%dc:source%</a>"
                                )
                        ),
                array // Second Rule-set
                        (
                        'filter_rules' => array 
                                (
                                        "dc:title" => "/^cover1/"
                                ),
                        'mapping_rules' => array // mapping rules for the record with title starting with cover1 
                                ( 
                                        "Title" => "PS Magazine Cover page", 
                                        "Creator and Illustrator" => "%dc:creator,1%; %dc:creator,0%",
                                        "Rights" => "<a href=\"%dc:rights%\">%dc:rights%</a>",
                                        "Collection" => "<a href=\"%dc:identifier,/^http:\/\/.*/%\">%dc:source%</a>"
                                )
                        )

                ),
```

The $manipulate_data variable has multiple levels of information for different purposes. At the first level is the short name for the collection, which corresponds to the short name or set name in CONTENTdm. This would form the key for the array of rule-sets for the set.

Each rule-set is again an associative array with the key as one of the following:

 * ``filter_rules``: The array associated with this key holds the tags and corresponding values (in regex form) that need to be displayed. So a key-value pair like "dc:description" => "/^Joe's Dope Sheet/" would mean pick all the Records from OAI (and display) that have the description tag in the OAI record that starts with the string 'Joe's Dope Sheet'. This ensures that the tool doesn't display all the records as there can be quite a few records in a huge collection. If you don't want any restrictions, then use an empty array to say exactly that, Here's a sample: 'rhr' => array ('filter_rules' => array ()).

  Note, to make sure that all the records from each set is displayed make sure an entry is made for set in this variable, though you could leave each one of the rule-sets empty.

  Also, you can provide more than one filter rule in a Rule-set. all the values would need to match for the filter to pick the particular record. In other words, the filter rules are ANDed together within a Rule-set.

 * ``mapping_rules``: The array associated with this key will hold all the rules on how to transfer the metadata of the image to the description in Flickr.

  The Keys are emboldened and the values are parsed for tag names referring to OAI record tags.
  
  The tags in the values are enclosed in pair of %'s. When the rule is parsed in the tool, the value for that record field is replaced into this location.

  In case the tag appears more than once in the same OAI record then we can specify the index of the field to be picked. The index is provided after a comma (,) and it starts with the first repetition at 0. Additionally, if it is necessary to refer to last repetition of the tag use the special purpose index of 99999, signifying the last tag of the particular name e.g.: "Creator and Illustrator" => "%dc:creator,1%; %dc:creator,0%"

  Alternatively, a Regex pattern can be provided that describes the data pattern for the correct field to be picked. The pattern is provided after a comma (,) and is enclosed in forward-slashes (/) e.g.: ``"<a href=\"%dc:identifier,/^http:\/\/.*/%\">%dc:source%</a>"``

  If no index provided and the tag appears more than once in the OAI record then the first one is used.

  Only a small number of tags are supported by the Flickr Description text, you might need to verify the use on the Flickr site before using it here.

 * ``special_rules``: The special rules can be any customizations that might be needed to be done to map information to Flickr. Currently there are 2 special rules in use:

    * ``GeoTag``: This rule is used to identify the field in the OAI XML that holds the GIS data (longitude and latitude). The value would be used to set the Flickr Location and hence Flickr will show a map pointing to the location on the page of the image. The level of granularity has been set to maximum (16) in the code. eg.: "GeoTag"	=> "%dc:description,/[0-9]*\.[0-9]*,[+][-][0-9]*\.[0-9]*/%"
	* DateTag: This rule links up with the Flickr feature to maintain the date when the picture was taken. Note: This information will only be used when the EXIF information for the image doesn't contain the date. The granularity of the date is at the lowest level(8) in the code. eg.: "DateTag" => "%dc:date,/^ca\./%"

  Note that the Rule-set is applied in a combination and is ruled by the filter_rules. So if we make a Rule-set, the mapping_rules are applied on the records that are filtered by the filter_rule. This helps associate the mapping rules to a particular type of Record and not to all of them. You can see this in the sample above, the mapping rules for 'Joe's dope sheet' could be different from that of 'cover1'.

The Eventual Description for the sample code above would be rendered as follows:

  Title: Joe's Dope Sheet (Issue 005 1951 page198_page199)
  Creator and Illustrator: Eisner, Will; United States. Dept. of the Army
  Rights: [http://www.library.vcu.edu/copyright.html](http://www.library.vcu.edu/copyright.html)
  Collection: PS: The Preventive Maintenance Monthly Collection

### Flickr Account Setup

 * Get a Flickr account, a free account has limited access but allows for creation of API keys. Also, there may be problems in creating Collections in the Free account (not verified).
 * Get an API Key for the account, See this link for more details: http://www.flickr.com/services/api/misc.api_keys.html
 * Setup the Callback URL on the Flickr page for App configuration. In our case, we use a dummy URL, <CONTENTdm Web URL>/auth.php.
 * The tool expects that you would be using a single Flickr account, and to simplify the authentication, I have used the Flickr Token which remains the same for each session.
 * Add the Flickr API key and Secret Key as per the instructions in the Configuration section. All the configuration is now stored in the same config.php file, so you would need to fill out the API key and Secret Key and then once you know the Flickr Token (in the next 2 steps) you need to fill that up in the same config.php.
 * Next, if your code is hosted under the CONTENTdm web URL, then <CONTENTdm Web URL>/<contentdm2flickr folder>/lib/getToken.php would get you to that page. On the first access of this page you would redirected to the Flickr sign-in page and would need to sign-in using the Flickr account to be used by the tool. Once the authentication is done, you can reopen the same page and it would now have the frob in the URL.
 * Copy the value of the frob from the URL and paste it into the [Flickr token generator](http://www.flickr.com/services/api/explore/flickr.auth.getToken)
 * Paste the token in the Configuration file (config.php) against the FLICKR_TOKEN variable in the configuration file (config.php).

This should be sufficient to make the tool work with your Flickr account.

### OAI Setup - CONTENTdm

CONTENTdm needs to be setup so as to allow the OAI harvesting of information. The tool requires OAI to be enabled and the Compound object access needs to be enabled too. The latter is required if the images are all rolled up into a compound object, in which case, unless enabled they will not be displayed in the tool.

[More details on the CONTENTdm setup](http://www.contentdm.com/help6/server-admin/oai.asp)

## Testing the Tool

After the tool is setup, the best way to test is to open the URL for the page in a web browser. The tool is simple and uses 2 trees, one to represent the images and records in CONTENTdm (this is the one on the left) and another to represent the Flickr collections/sets. Select an image from the tree on the left, select a set from the tree on the right and then choose if the picture needs to be 'Kept Private' or 'Made Public' and press submit.

The tool would print the logs of the pictures and metadata transferred on the page that opens. An individual success message is added for each picture uploaded.

**Important note:** Flickr albums need to be created and placed in sets before you can upload photos.

## Other Useful Links

* [Flickr: API keys](http://www.flickr.com/services/api/misc.api_keys.html) - This is a good starting point for creating a new API key.
* [Flickr authentication spec](http://www.flickr.com/services/api/auth.spec.html)- This is a deeper look at the Flickr authentication.
* [PHPFlickr documentation](http://phpflickr.com/docs/flickr-authentication/) - This link deals with details of the PHP implementation of the Flickr authentication.

## Attribution

The code couldn't have happened without the following open-source projects, thanks for your help!

 * [OAI Client class from the CWIS project](http://scout.wisc.edu)
 * [jQuery Dynatree](http://code.google.com/p/dynatree/)
 * [phpFlickr](http://code.google.com/p/phpflickr/)
