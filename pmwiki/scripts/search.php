<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

*/

Markup('searchresults','directives','/\\[:searchresults\\s*(.*?):\\]/e',
  "Keep(SearchResults(\$pagename,array('text'=>PSS('$1'))))");

function SearchResults($pagename,$opt) {
  global $GroupPattern,$SearchPatterns;
  $opt = array_merge(@$_REQUEST,$opt);
  if (!$opt['text']) $opt['text']=stripmagic(@$_REQUEST['text']);
  if (!$opt['text']) return '';
  $terms = preg_split('/((?<!\\S)[-+]?[\'"].*?[\'"](?!\\S)|\\S+)/',
    $opt['text'],-1,PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
  if (preg_match("!^($GroupPattern(\\|$GroupPattern)*)?/!i",@$terms[0],$match)) 
  { 
    $opt['group'] = @$match[1]; 
    $terms[0]=str_replace(@$match[1].'/','',$terms[0]);
  }
  $excl = array(); $incl = array();
  foreach($terms as $t) {
    if (trim($t)=='') continue;
    if (preg_match('/^([^\'":=]*)[:=]([\'"]?)(.*?)\\2$/',$t,$match)) 
      { $opt[$match[1]] = $match[3]; continue; }
    preg_match('/^([-+]?)([\'"]?)(.+?)\\2$/',$t,$match);
    if ($match[1]=='-') $excl[] = $match[3];
    else $incl[] = $match[3];
  }
  $pats = (array)@$SearchPatterns;
  if (@$opt['group']) array_unshift($pats,"/^({$opt['group']})\./i");
  $pagelist = ListPages($pats);
  $matches = array();
  foreach($pagelist as $pagefile) {
    $page = ReadPage($pagefile);  Lock(0);  if (!$page) continue;
    $text = $pagefile."\n".$page['text'];
    foreach($excl as $t) if (stristr($text,$t)) continue 2;
    foreach($incl as $t) if (!stristr($text,$t)) continue 2;
    $matches[] = $pagefile;
  }
  return implode(' ',$matches);
}
