<?php if (!defined('PmWiki')) exit();
/*  Copyright 2002-2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This file allows features to be easily enabled/disabled in config.php.
    Simply set variables for the features to be enabled/disabled in config.php
    before including this file.  For example:
        $EnableQAMarkup=0;                      #disable Q: and A: tags
        $EnableDefaultWikiStyles=1;             #include default wikistyles
    Each feature has a default setting, if the corresponding $Enable
    variable is not set then you get the default.

    To avoid processing any of the features of this file, set 
        $EnableStdConfig = 0;
    in config.php.
*/

SDV($DefaultPage,"$DefaultGroup.$DefaultName");
if ($pagename=='') $pagename=$DefaultPage;

if (!IsEnabled($EnableStdConfig,1)) return;

if (IsEnabled($EnableAuthorTracking,1)) 
  include_once("$FarmD/scripts/author.php");
if ($action=='diff' && @!$HandleActions['diff'])
  include_once("$FarmD/scripts/pagerev.php");
if (IsEnabled($EnableTemplateLayout,1))
  include_once("$FarmD/scripts/tlayout.php");
if (IsEnabled($EnableMailPosts,0))
  include_once("$FarmD/scripts/mailposts.php");
if (IsEnabled($EnableDiag,0)) 
  include_once("$FarmD/scripts/diag.php");

?>
