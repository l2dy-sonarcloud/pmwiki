<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

*/

function SearchResults($pagename,$opt) {
  global $GroupPattern;
  if (!$opt['text']) $opt['text']=$_REQUEST['text'];
  $terms = preg_split('/((?<!\\S)[-+]?[\'"].*?[\'"](?!\\S)|\\S+)/',
    $opt['text'],-1,PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
  if (preg_match("!^($GroupPattern(\\|$GroupPattern)*)?/!i",$terms[0],$match))
    { $opt['group'] = $match[1]; array_shift($terms); }
  $excl() = array(); $incl = array();
  foreach($terms as $t) {
    if (trim($t)=='') continue;
    if (preg_match('/^([^\'":=]*)[:=]([\'"]?)(.*?)\\2$/',$t,$match)) 
      { $opt[$match[1]] = $match[3]; continue; }
    preg_match('/^([-+]?)([\'"]?)(.+?)\\2$/',$t,$match);
    if ($match[1]=='-') $excl[] = $match[3];
    else $incl[] = $match[3];
  }
  $pats = (array)$SearchPatterns;
  if ($opt['group']) array_unshift($pats,"/^({$opt['group']})\./");
  $pagelist = ListPages($pats);
}
