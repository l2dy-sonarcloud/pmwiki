<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script configures PmWiki to use utf-8 in page content and
    pagenames.
*/

  global $HTTPHeaders, $U8;
  $HTTPHeaders[] = 'Content-type: text/html; charset=utf-8';
  $U8 = 'u';

  $Newline = "\262\262\262";
  $KeepToken = "\263\263\263";
  $LinkToken = "\376\376\376";
  $pagename = $_GET['pagename'];

  if (!$pagename &&
        preg_match('!^'.preg_quote($_SERVER['SCRIPT_NAME'],'!').'/?([^?]*)!',
            $_SERVER['REQUEST_URI'],$match))
      $pagename = urldecode($match[1]);
  $pagename = preg_replace('!/+$!','',$pagename);

?>
