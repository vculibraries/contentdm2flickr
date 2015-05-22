<?PHP

#
#   FILE:  Scout--OAIClient.php
#     Provides a client for pulling data from OAI-PMH providers
#     For protocol documentation, see:
#     http://www.openarchives.org/OAI/openarchivesprotocol.html
#
#   METHODS PROVIDED:
#       OAIClient(ServerUrl, Cache)
#           - constructor
#       ServerUrl(NewValue)
#           - Change the base url of the remote repository
#       MetadataPrefix($pfx)
#           - Set the schema we will request from remote
#       SetSpec($set)
#           - Restrict queries to a single set
#             for details, see
#             http://www.openarchives.org/OAI/openarchivesprotocol.html#Set
#       GetIdentification()
#           - Fetch identifying information about the remote repository
#       GetFormats()
#           - Fetch information about what schemas remote can serve
#       GetRecords($start,$end)
#           - Pull records in batches, optionally with date restrictions
#       GetRecord($id)
#           - Pull a single record using a unique identifier
#       GetSets()
#           - Fetch the list of sets available on the remote server
#       MoreRecordsAvailable()
#           - Determine if a batch pull is complete or not
#       ResetRecordPointer()
#           - Restart a batch pull from the beginning
#       SetDebugLevel()
#           - Determine verbosity
#
#   Copyright 2008 Edward Almasy and Internet Scout
#   http://scout.wisc.edu
#

require_once("XMLParser.php");


class OAIClient {

    # ---- PUBLIC INTERFACE --------------------------------------------------

    /**
    * Class constructor.
    *
    * @param ServerUrl URL of target OAI repository server
    * @param Cache name of directory to use to store cached content
    */
    function OAIClient($ServerUrl, $Cache=NULL)
    {
        # set default debug level
        $this->DebugLevel = 0;

        # save OAI server URL
        $this->ServerUrl = $ServerUrl;

        # set default metadata prefix
        $this->MetadataPrefix = "oai_dc";

        # set default set specification for queries
        $this->SetSpec = NULL;

        $this->CacheSequenceNumber = 0;
        if ($Cache !== NULL)
        {
            $this->Cache = $Cache;
            $this->UsingCache = is_dir($Cache);
            if ($this->UsingCache == FALSE )
            {
                mkdir($Cache);
            }
        }
    }

    /**
    * Get or set URL of target OAI repository server.
    *
    * @param NewValue  new URL of target OAI repository server (optional)
    * @return           current URL of target OAI repository server
    */
    function ServerUrl($NewValue = NULL)
    {
        if ($NewValue != NULL)
        {
            $this->ServerUrl = $NewValue;
        }
        return $this->ServerUrl;
    }

    /**
    * Get or set metadata schema for records being retrieved.
    *
    * @param NewValue new metadata prefix (optional)
    * @return               current metadata prefix
    */
    function MetadataPrefix($NewValue = NULL)
    {
        if ($NewValue != NULL)
        {
            $this->MetadataPrefix = $NewValue;
        }
        return $this->MetadataPrefix;
    }

    /**
    * Get or set specification of subset of records to be retrieved.
    *
    * @param NewValue    new set specification (optional)
    * @return           current set specification
    */
    function SetSpec($NewValue = "X-NOSETSPECVALUE-X")
    {
        if ($NewValue != "X-NOSETSPECVALUE-X")
        {
            $this->SetSpec = $NewValue;
        }
        return $this->SetSpec;
    }

    /**
    * Retrieve identification information from repository server.
    * Information is returned as associative array with the following
    * indexes:  "Name", "Email", "URL".
    *
    * @return array containing identification info
    */
    function GetIdentification()
    {
        # query server for XML text
        $XmlText = $this->PerformQuery("Identify");
        $this->DebugOutVar(8,__METHOD__,"XmlText",htmlspecialchars($XmlText));

        # convert XML text into object
        $Xml = simplexml_load_string($XmlText);
        $this->DebugOutVar(9, __METHOD__, "Xml", $Xml);

        # if identification info was found
        $Info = array();
        if (isset($Xml->Identify))
        {
            # extract info
            $Ident = $Xml->Identify;
            $this->GetValFromXml($Ident, "repositoryName", "Name", $Info);
            $this->GetValFromXml($Ident, "adminEmail", "Email", $Info);
            $this->GetValFromXml($Ident, "baseURL", "URL", $Info);
        }

        # return info to caller
        return $Info;
    }
	
	/**
    * Retrieve list of available of Collection sets in the OAI repo 
	* for a given metadataPrefix.
    *
    * @return array containing list of available sets
    */
    function GetSets()
    {
		$Args["metadataPrefix"] = $this->MetadataPrefix;
        # query server for XML text
        $XmlText = $this->PerformQuery("ListSets", $Args);
        $this->DebugOutVar(8,__METHOD__,"XmlText",htmlspecialchars($XmlText));

        # convert XML text into object
        $Xml = simplexml_load_string($XmlText);
        $this->DebugOutVar(9, __METHOD__, "Xml", $Xml);
		# if Set info was found
        $Sets = array();
        if (isset($Xml->ListSets->set))
        {
            # extract info
            $Index = 0;
            foreach ($Xml->ListSets->set as $Set)
            {
                $this->GetValFromXml(
                        $Set, "setSpec", "setSpec", $Sets[$Index]);
                $this->GetValFromXml(
                        $Set, "setName", "setName", $Sets[$Index]);
                $Index++;
            }
        }

        # return info to caller
        return $Sets;
	}

    /**
    * Retrieve list of available metadata formats from repository server.
    *
    * @return array containing list of available metadata formats
    */
    function GetFormats()
    {
        # query server for XML text
        $XmlText = $this->PerformQuery("ListMetadataFormats");
        $this->DebugOutVar(8,__METHOD__,"XmlText",htmlspecialchars($XmlText));

        # convert XML text into object
        $Xml = simplexml_load_string($XmlText);
        $this->DebugOutVar(9, __METHOD__, "Xml", $Xml);

        # if format info was found
        $Formats = array();
        if (isset($Xml->ListMetadataFormats->metadataFormat))
        {
            # extract info
            $Index = 0;
            foreach ($Xml->ListMetadataFormats->metadataFormat as $Format)
            {
                $this->GetValFromXml(
                        $Format, "metadataPrefix", "Name", $Formats[$Index]);
                $this->GetValFromXml(
                        $Format, "schema", "Schema", $Formats[$Index]);
                $this->GetValFromXml(
                        $Format, "metadataNamespace", "Namespace",
                        $Formats[$Index]);
                $Index++;
            }
        }

        # return info to caller
        return $Formats;
    }

    /**
    * Retrieve records from repository server.
    *
    * @param StartDate  start of date range for retrieval  (optional)
    * @param EndDate    end of date range for retrieval (optional)
    * @return           array of records returned from repository
    */
    function GetRecords($StartDate = NULL, $EndDate = NULL)
    {
        if( $this->Cache != NULL )
        {
            $cache_fname = sprintf("%s/%010x",
                                   $this->Cache,
                                   $this->CacheSequenceNumber);
            $this->CacheSequenceNumber++;
        }

        if( $this->Cache == NULL or $this->UsingCache == FALSE )
        {
            # if we have resumption token from prior query
            if (isset($this->ResumptionToken))
            {
                # use resumption token as sole argument
                $Args["resumptionToken"] = $this->ResumptionToken;
            }
            else
            {
                # set up arguments for query
                $Args["metadataPrefix"] = $this->MetadataPrefix;
                if ($StartDate) {  $Args["from"] = $StartDate;  }
                if ($EndDate)   {  $Args["until"] = $EndDate;  }
                if ($this->SetSpec) {  $Args["set"] = $this->SetSpec;  }
            }
			error_log(" Started Performing HTTP Query ");
            # query server for XML text
            $XmlText = $this->PerformQuery("ListRecords", $Args);
			error_log(" Ended Performing HTTP Query ");
            if( $this->Cache != NULL )
            {
                file_put_contents( $cache_fname, $XmlText );
            }
        }
        else
        {
            # Get XML text from the cache
            $XmlText = file_get_contents( $cache_fname );
        }

        $this->DebugOutVar(8, __METHOD__,"XmlText",htmlspecialchars($XmlText));
		
        return $this->GetRecordsFromXML($XmlText, "listrecords" );
    }

    /**
     * Get a single record from a repositry server
     *
     * NOTE: due to the history and politics involved, it is generally
     * preferable to use GetRecords() to pull a full dump from the
     * remote provider and then filter that to get a subset.  The
     * thinking here is that pulling in batches will result in fewer
     * queries to the remote, which is kinder to their hardware.  Pull
     * single records with caution, when only a small number of them
     * are required.
     *
     * @param Id  The unique identifier of the desired record
     * @return    array of records (zero or one entries) returned
     */
    function GetRecord($Id)
    {
        $Args["metadataPrefix"] = $this->MetadataPrefix;
        $Args["identifier"] = $Id;

        # query server for XML text
        $XmlText = $this->PerformQuery("GetRecord", $Args);
        $this->DebugOutVar(8, __METHOD__,"XmlText",htmlspecialchars($XmlText));

        return $this->GetRecordsFromXML($XmlText, "getrecord" );
    }

    /**
    * Check whether more records are available after last GetRecords().
    *
    * @return TRUE if more records are available, otherwise FALSE
    */
    function MoreRecordsAvailable()
    {
        return isset($this->ResumptionToken) ? TRUE : FALSE;
    }

    /**
    * Clear any additional records available after last GetRecords().
    */
    function ResetRecordPointer()
    {
        unset($this->ResumptionToken);
        $this->CacheSequenceNumber = 0;
    }

    /**
    * Set current debug output level.
    *
    * @param NewLevel numerical debugging output level (0-9)
    */
    function SetDebugLevel($NewLevel)
    {
        $this->DebugLevel = $NewLevel;
    }


    # ---- PRIVATE INTERFACE -------------------------------------------------

    private $ServerUrl;
    private $MetadataPrefix;
    private $SetSpec;
    private $DebugLevel;
    private $ResumptionToken;
    private $Cache;
    private $UsingCache;
    private $CacheSequenceNumber;

    # perform OAI query and return resulting data to caller
    private function PerformQuery($QueryVerb, $Args = NULL)
    {
        # open stream to OAI server

        if (strpos($this->ServerUrl, "?") === FALSE)
        {
            $QueryUrl = $this->ServerUrl."?verb=".$QueryVerb;
        }
        else
        {
            $QueryUrl = $this->ServerUrl."&verb=".$QueryVerb;
        }

        if ($Args)
        {
            foreach ($Args as $ArgName => $ArgValue)
            {
                $QueryUrl .= "&".urlencode($ArgName)."=".urlencode($ArgValue);
            }
        }
        $FHndl = fopen($QueryUrl, "r");

        # if stream was successfully opened
        $Text = "";
        if ($FHndl !== FALSE)
        {
            # while lines left in response
            while (!feof($FHndl))
            {
                # read line from server and add it to text to be parsed
                $Text .= fread($FHndl, 10000000);
            }
        }

        # close OAI server stream
        fclose($FHndl);

        # return query result data to caller
        return $Text;
    }

    # set array value if available in simplexml object
    private function GetValFromXml($Xml, $SrcName, $DstName, &$Results)
    {
        if (isset($Xml->$SrcName))
        {
            $Results[$DstName] = trim($Xml->$SrcName);
        }
    }

    # print variable contents if debug is above specified level
    private function DebugOutVar($Level, $MethodName, $VarName, $VarValue)
    {
        if ($this->DebugLevel >= $Level)
        {
            print("\n<pre>".$MethodName."()  ".$VarName." = \n");
            print_r($VarValue);
            print("</pre>\n");
        }
    }

    # Recursively dump tags inside a metadata section, flattening them
    # as we go.
    private function DumpTagsRecursive(&$Records, $Index, $Parser, $ParentTagName=NULL)
    {
        $TagName = $Parser->GetTagName();
        do
        {
            $StorageTagName = ($ParentTagName!==NULL) ?
                $ParentTagName."/".$TagName : $TagName;

            if ($Parser->SeekToChild() ){
                $this->DumpTagsRecursive( $Records, $Index, $Parser, $StorageTagName );
                $Parser->SeekToParent();
            }
            else
            {
                $Records[$Index]["metadata"][$StorageTagName][] = $Parser->GetData();
            }
        } while ($TagName = $Parser->NextTag());
    }

    # Query has been sent, we need to retrieve records that came from it.
    private function GetRecordsFromXML($XmlText, $ParseTo ){
		error_log(" Started GetRecordsFromXML ");
        # create XML parser and pass it text
		error_log(" Started Parser Creation ");
        $Parser = new XMLParser();
		error_log(" Ended Parser Creation ");
        $Parser->ParseText($XmlText);

        $this->DebugOutVar(9, __METHOD__, "Parser", $Parser);

        # if records were found
        $Records = array();
        $ItemCount = $Parser->SeekTo("oai-pmh", $ParseTo, "record");
        if ($ItemCount)
        {
            # for each record
            $Index = 0;
            do
            {
                # grab record identifier and date
                $Records[$Index]["identifier"]=$Parser->GetData("header",
                                                                "identifier");
                $Records[$Index]["datestamp"]=$Parser->GetData("header",
                                                               "datestamp");

                # grab metadata
                $SeekResult = $Parser->SeekTo("metadata");
                if ($SeekResult)
                {
                    $SeekResult = $Parser->SeekToChild();
                    if ($SeekResult)
                    {
                        $Records[$Index]["format"] = $Parser->GetTagName();
						error_log($Records[$Index]["format"]);
                        $SeekResult = $Parser->SeekToChild();
                        if ($SeekResult)
                        {
                            $this->DumpTagsRecursive($Records, $Index, $Parser);
                            $Parser->SeekToParent();
                        }
                        $Parser->SeekToParent();
                    }
                    $Parser->SeekToParent();
                }

                # grab search info (if any)
                /*$SeekResult = $Parser->SeekTo("about");
                if ($SeekResult)
                {
                    $SeekResult = $Parser->SeekTo("searchInfo");
                    if ($SeekResult)
                    {
                        $SeekResult = $Parser->SeekToChild();
                        if ($SeekResult)
                        {
                            $TagName = $Parser->GetTagName();
                            do
                            {
                                $Records[$Index]["about"]["SEARCHINFO"][$TagName][] =
                                        $Parser->GetData();
                            } while ($TagName = $Parser->NextTag());
                            $Parser->SeekToParent();
                        }
                        $Parser->SeekToParent();
                    }
                    $Parser->SeekToParent();
                }
				*/
                $Index++;
            }
            while ($Parser->NextItem());
        }

        # look for resumption token and save if found
        $Parser->SeekToRoot();
        $SeekResult = $Parser->SeekTo(
                "oai-pmh", "listrecords", "resumptiontoken");
        if ($SeekResult !== NULL)
        {
            $this->ResumptionToken = $Parser->GetData();
        }
        else
        {
            unset($this->ResumptionToken);
        }

        # return records to caller
		error_log(" Ended GetRecordsFromXML ");
        return $Records;
    }

}

?>
