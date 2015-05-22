<?PHP
#
#   FILE:  SPT--XMLParser.php
#
#   METHODS PROVIDED:
#       XMLParser()
#           - constructor
#       SomeMethod($SomeParameter, $AnotherParameter)
#           - short description of method
#
#   AUTHOR:  Edward Almasy
#
#   Part of the Scout Portal Toolkit
#   Copyright 2005 Internet Scout Project
#   http://scout.wisc.edu
#

class XMLParser {

    # ---- PUBLIC INTERFACE --------------------------------------------------

    # object constructor
    function XMLParser()
    {
        # set default debug output level
        $this->DebugLevel = 0;

        # create XML parser and tell it about our methods
        $this->Parser = xml_parser_create();
        xml_set_object($this->Parser, $this);
        xml_set_element_handler($this->Parser, "OpenTag", "CloseTag");
        xml_set_character_data_handler($this->Parser, "ReceiveData");

        # initialize tag storage arrays
        $this->TagNames = array();
        $this->TagAttribs = array();
        $this->TagData = array();
        $this->TagParents = array();

        # initialize indexes for parsing and retrieving data
        $this->CurrentParseIndex = -1;
        $this->CurrentSeekIndex = -1;
        $this->NameKeyCache = array();
    }

    # parse text stream and store result
    function ParseText($Text, $LastTextToParse = TRUE)
    {
        # pass text to PHP XML parser
        xml_parse($this->Parser, $Text, $LastTextToParse);
    }

    # move current tag pointer to specified item (returns NULL on failure)
    function SeekTo()    # (args may be tag names or indexes)
    {
        # perform seek based on arguments passed by caller
        $SeekResult = $this->PerformSeek(func_get_args(), TRUE);

        # if seek successful
        if ($SeekResult !== NULL)
        {
            # retrieve item count at seek location
            $ItemCount = count($this->CurrentItemList);
        }
        else
        {
            # return null value to indicate that seek failed
            $ItemCount = NULL;
        }

        # return count of tags found at requested location
        if ($this->DebugLevel > 0)
        {
            print("XMLParser->SeekTo(");
            $Sep = "";
            $DbugArgList = "";
            foreach (func_get_args() as $Arg)
            {
                $DbugArgList .= $Sep."\"".$Arg."\"";
                $Sep = ", ";
            }
            print($DbugArgList.") returned ".intval($ItemCount)." items starting at index ".$this->CurrentSeekIndex."\n");
        }
        return $ItemCount;
    }

    # move seek pointer up one level (returns tag name or NULL if no parent)
    function SeekToParent()
    {
        # if we are not at the root of the tree
        if ($this->CurrentSeekIndex >= 0)
        {
            # move up one level in tree
            $this->CurrentSeekIndex = $this->TagParents[$this->CurrentSeekIndex];

            # clear item list
            unset($this->CurrentItemList);

            # return name of new tag to caller
            $Result = $this->TagNames[$this->CurrentSeekIndex];
        }
        else
        {
            # return NULL indicating that no parent was found
            $Result = NULL;
        }

        # return result to caller
        if ($this->DebugLevel > 0) {  print("XMLParser->SeekToParent() returned ".$Result."<br>\n");  }
        return $Result;
    }

    # move seek pointer to first child of current tag (returns tag name or NULL if no children)
    function SeekToChild($ChildIndex = 0)
    {
        # look for tags with current tag as parent
        $ChildTags = array_keys($this->TagParents, $this->CurrentSeekIndex);

        # if child tag was found with requested index
        if (isset($ChildTags[$ChildIndex]))
        {
            # set current seek index to child
            $this->CurrentSeekIndex = $ChildTags[$ChildIndex];

            # clear item list info
            unset($this->CurrentItemList);

            # return name of new tag to caller
            $Result = $this->TagNames[$this->CurrentSeekIndex];
        }
        else
        {
            # return NULL indicating that no children were found
            $Result = NULL;
        }

        # return result to caller
        if ($this->DebugLevel > 0) {  print("XMLParser->SeekToChild() returned ".$Result."<br>\n");  }
        return $Result;
    }

    # move seek pointer to root of tree
    function SeekToRoot()
    {
        $this->CurrentSeekIndex = -1;
    }

    # move to next tag at current level (returns tag name or NULL if no next)
    function NextTag()
    {
        # get list of tags with same parent as this tag
        $LevelTags = array_keys($this->TagParents, 
                $this->TagParents[$this->CurrentSeekIndex]);

        # find position of next tag in list
        $NextTagPosition = array_search($this->CurrentSeekIndex, $LevelTags) + 1;

        # if there is a next tag
        if (count($LevelTags) > $NextTagPosition)
        {
            # move seek pointer to next tag at this level
            $this->CurrentSeekIndex = $LevelTags[$NextTagPosition];

            # rebuild item list

            # return name of tag at new position to caller
            return $this->TagNames[$this->CurrentSeekIndex];
        }
        else
        {
            # return NULL to caller to indicate no next tag
            return NULL;
        }
    }

    # move to next instance of current tag (returns index or NULL if no next)
    function NextItem()
    {
        # set up item list if necessary
        if (!isset($this->CurrentItemList)) {  $this->RebuildItemList();  }

        # if there are items left to move to
        if ($this->CurrentItemIndex < ($this->CurrentItemCount - 1))
        {
            # move item pointer to next item
            $this->CurrentItemIndex++;

            # set current seek pointer to next item
            $this->CurrentSeekIndex = 
                    $this->CurrentItemList[$this->CurrentItemIndex];

            # return new item index to caller
            $Result = $this->CurrentItemIndex;
        }
        else
        {
            # return NULL value to caller to indicate failure
            $Result = NULL;
        }

        # return result to caller
        return $Result;
    }

    # move to previous instance of current tag (returns index or NULL on fail)
    function PreviousItem()
    {
        # set up item list if necessary
        if (!isset($this->CurrentItemList)) {  $this->RebuildItemList();  }

        # if we are not at the first item
        if ($this->CurrentItemIndex > 0)
        {
            # move item pointer to previous item
            $this->CurrentItemIndex--;

            # set current seek pointer to next item
            $this->CurrentSeekIndex = 
                    $this->CurrentItemList[$this->CurrentItemIndex];

            # return new item index to caller
            return $this->CurrentItemIndex;
        }
        else
        {
            # return NULL value to caller to indicate failure
            return NULL;
        }
    }

    # retrieve tag name from current seek point
    function GetTagName()
    {
        if (isset($this->TagNames[$this->CurrentSeekIndex]))
        {
            return $this->TagNames[$this->CurrentSeekIndex];
        }
        else
        {
            return NULL;
        }
    }

    # retrieve data from current seek point
    function GetData()
    {
        # assume that we will not be able to retrieve data
        $Data = NULL;

        # if arguments were supplied
        if (func_num_args())
        {
            # retrieve index for specified point
            $Index = $this->PerformSeek(func_get_args(), FALSE);

            # if valid index was found
            if ($Index !== NULL)
            {
                # retrieve data at index to be returned to caller
                $Data = $this->TagData[$Index];
            }
        }
        else
        {
            # if current seek index points to valid tag
            if ($this->CurrentSeekIndex >= 0)
            {
                # retrieve data to be returned to caller
                $Data = $this->TagData[$this->CurrentSeekIndex];
            }
        }

        # return data to caller
        if ($this->DebugLevel > 0)
        {  
            print("XMLParser->GetData(");
            if (func_num_args()) {  $ArgString = "";  foreach (func_get_args() as $Arg) {  $ArgString .= "\"".$Arg."\", ";  }  $ArgString = substr($ArgString, 0, strlen($ArgString) - 2);  print($ArgString);  }
            print(") returned ".($Data ? "\"".$Data."\"" : "NULL")."<br>\n");  
        }
        return $Data;
    }

    # retrieve specified attribute(s) from current seek point or specified point below
    #   (first arg is attribute name and optional subsequent args tell where to seek to)
    #   (returns NULL if no such attribute for current or specified tag)
    function GetAttribute()
    {
        # retrieve attribute
        $Args = func_get_args();
        $Attrib = $this->PerformGetAttribute($Args, FALSE);

        # return requested attribute to caller
        if ($this->DebugLevel > 0) {  print("XMLParser->GetAttribute() returned ".$Attrib."<br>\n");  }
        return $Attrib;
    }
    function GetAttributes()
    {
        # retrieve attribute
        $Args = func_get_args();
        $Attribs = $this->PerformGetAttribute($Args, TRUE);

        # return requested attribute to caller
        if ($this->DebugLevel > 0) {  print("XMLParser->GetAttributes() returned ".count($Attribs)." attributes<br>\n");  }
        return $Attribs;
    }


    # ---- PRIVATE INTERFACE -------------------------------------------------

    var $TagNames;
    var $TagAttribs;
    var $TagData;
    var $TagParents;
    var $CurrentParseIndex;
    var $CurrentSeekIndex;
    var $CurrentItemIndex;
    var $CurrentItemList;
    var $CurrentItemCount;
    var $DebugLevel;
    var $NameKeyCache;
    
    # set current debug output level (0-9)
    function SetDebugLevel($NewLevel)
    {
        $this->DebugLevel = $NewLevel;
    }
    
    # callback function for handling open tags
    function OpenTag($Parser, $ElementName, $ElementAttribs)
    {
        # add new tag to list
        $NewTagIndex = count($this->TagNames);
        $this->TagNames[$NewTagIndex] = $ElementName;
        $this->TagAttribs[$NewTagIndex] = $ElementAttribs;
        $this->TagParents[$NewTagIndex] = $this->CurrentParseIndex;
        $this->TagData[$NewTagIndex] = NULL;

        # set current tag to new tag
        $this->CurrentParseIndex = $NewTagIndex;
    }
    
    # callback function for receiving data between tags
    function ReceiveData($Parser, $Data)
    {
        # add data to currently open tag
        $this->TagData[$this->CurrentParseIndex] .= $Data;
    }
    
    # callback function for handling close tags
    function CloseTag($Parser, $ElementName)
    {
        # if we have an open tag and closing tag matches currently open tag
        if (($this->CurrentParseIndex >= 0)
                && ($ElementName == $this->TagNames[$this->CurrentParseIndex]))
        {
            # set current tag to parent tag
            $this->CurrentParseIndex = $this->TagParents[$this->CurrentParseIndex];
        }
    }

    # perform seek to point in tag tree and update seek pointer (if requested)
    function PerformSeek($SeekArgs, $MoveSeekPointer)
    {
        # for each tag name or index in argument list
        $NewSeekIndex = $this->CurrentSeekIndex;
        foreach ($SeekArgs as $Arg)
        {
            # if argument is string
            if (is_string($Arg))
            {
                # look for tags with given name and current tag as parent
                $Arg = strtoupper($Arg);
                if (!isset($this->NameKeyCache[$Arg]))
                {
                    $this->NameKeyCache[$Arg] = array_keys($this->TagNames, $Arg);
                    $TestArray = array_keys($this->TagNames, $Arg);
                }
                $ChildTags = array_keys($this->TagParents, $NewSeekIndex);
				//error_log(" Started Array Intersect ");
                $NewItemList = array_values(
                        array_intersect($this->NameKeyCache[$Arg], $ChildTags));
                $NewItemCount = count($NewItemList);
				//error_log(" Ended Array Intersect ");
                # if matching tag found
                if ($NewItemCount > 0)
                {
                    # update local seek index
                    $NewSeekIndex = $NewItemList[0];

                    # save new item index
                    $NewItemIndex = 0;
                }
                else
                {
                    # report seek failure to caller
                    return NULL;
                }
            }
            else
            {
                # look for tags with same name and same parent as current tag
                $NameTags = array_keys($this->TagNames, $this->TagNames[$NewSeekIndex]);
                $ChildTags = array_keys($this->TagParents, $this->TagParents[$NewSeekIndex]);
                $NewItemList = array_values(array_intersect($NameTags, $ChildTags));
                $NewItemCount = count($NewItemList);

                # if enough matching tags were found to contain requested index
                if ($NewItemCount > $Arg)
                {
                    # update local seek index
                    $NewSeekIndex = $NewItemList[$Arg];

                    # save new item index
                    $NewItemIndex = $Arg;
                }
                else
                {
                    # report seek failure to caller
                    return NULL;
                }
            }
        }

        # if caller requested that seek pointer be moved to reflect seek
        if ($MoveSeekPointer)
        {
            # update seek index
            $this->CurrentSeekIndex = $NewSeekIndex;

            # update item index and list
            $this->CurrentItemIndex = $NewItemIndex;
            $this->CurrentItemList = $NewItemList;
            $this->CurrentItemCount = $NewItemCount;
        }

        # return index of found seek
        return $NewSeekIndex;
    }

    function PerformGetAttribute($Args, $GetMultiple)
    {
        # assume that we will not be able to retrieve attribute
        $ReturnVal = NULL;

        # retrieve attribute name and (possibly) seek arguments
        if (!$GetMultiple)
        {
            $AttribName = strtoupper(array_shift($Args));
        }

        # if arguments were supplied
        if (count($Args))
        {
            # retrieve index for specified point
            $Index = $this->PerformSeek($Args, FALSE);

            # if valid index was found
            if ($Index !== NULL)
            {
                # if specified attribute exists
                if (isset($this->TagAttribs[$Index][$AttribName]))
                {
                    # retrieve attribute(s) at index to be returned to caller
                    if ($GetMultiple)
                    {
                        $ReturnVal = $this->TagAttribs[$Index];
                    }
                    else
                    {
                        $ReturnVal = $this->TagAttribs[$Index][$AttribName];
                    }
                }
            }
        }
        else
        {
            # if current seek index points to valid tag
            if ($this->CurrentSeekIndex >= 0)
            {
                # if specified attribute exists
                if (isset($this->TagAttribs[$this->CurrentSeekIndex][$AttribName]))
                {
                    # retrieve attribute(s) to be returned to caller
                    if ($GetMultiple)
                    {
                        $ReturnVal = $this->TagAttribs[$this->CurrentSeekIndex];
                    }
                    else
                    {
                        $ReturnVal = $this->TagAttribs[$this->CurrentSeekIndex][$AttribName];
                    }
                }
            }
        }

        # return requested attribute to caller
        return $ReturnVal;
    }

    # rebuild internal list of tags with the same tag name and same parent as current
    function RebuildItemList()
    {
        # get list of tags with the same parent as current tag
        $SameParentTags = array_keys($this->TagParents, 
                $this->TagParents[$this->CurrentSeekIndex]);

        # get list of tags with the same name as current tag
        $SameNameTags = array_keys($this->TagNames, 
                $this->TagNames[$this->CurrentSeekIndex]);

        # intersect lists to get tags with both same name and same parent as current
        $this->CurrentItemList = array_values(
                array_intersect($SameNameTags, $SameParentTags));

        # find and save index of current tag within item list
        $this->CurrentItemIndex = array_search(
                $this->CurrentSeekIndex, $this->CurrentItemList);

        # save length of item list
        $this->CurrentItemCount = count($this->CurrentItemList);
    }

    # internal method for debugging
    function DumpInternalArrays()
    {
        foreach ($this->TagNames as $Index => $Name)
        {
            printf("[%03d] %-12.12s %03d %-30.30s \n", $Index, $Name, $this->TagParents[$Index], trim($this->TagData[$Index]));
        }
    }
}


?>
