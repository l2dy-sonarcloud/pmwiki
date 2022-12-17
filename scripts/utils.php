<?php if (!defined('PmWiki')) exit();
/*  Copyright 2019-2022 Petko Yotov www.pmwiki.org/petko
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script includes and configures one or more JavaScript utilities, 
    when they are enabled by the wiki administrator, notably:
    
    * Tables of contents
    * Sortable tables
    * Localized time stamps
    * Improved recent changes
    * Syntax highlighting (PmWiki markup)
    * Syntax highlighting (external)
    * Collapsible sections
    * Email obfuscation
    
    To disable all these functions, add to config.php:
      $EnablePmUtils = 0;
*/

function PmUtilsJS() {
  global $PmTOC, $EnableSortable, $EnableHighlight, $EnableLocalTimes, $ToggleNextSelector, 
    $LinkFunctions, $FarmD, $HTMLHeaderFmt, $EnablePmSyntax, $CustomSyntax;

  $utils = "$FarmD/pub/pmwiki-utils.js";
  if (( IsEnabled($PmTOC['Enable'], 0)
    || IsEnabled($EnableSortable, 0)
    || $LinkFunctions['mailto:'] == 'ObfuscateLinkIMap' 
    || IsEnabled($EnableHighlight, 0)
    || IsEnabled($ToggleNextSelector, 0)
    || IsEnabled($EnableLocalTimes, 0)
    ) && file_exists($utils)) {
      $mtime = filemtime($utils);
      SDVA($HTMLHeaderFmt, array('pmwiki-utils' =>
        "<script type='text/javascript' src='\$FarmPubDirUrl/pmwiki-utils.js?st=$mtime'
          data-sortable='".@$EnableSortable."' data-highlight='".@$EnableHighlight."'
          data-pmtoc='".pm_json_encode(@$PmTOC, true)."'
          data-toggle='".PHSC(@$ToggleNextSelector, ENT_QUOTES)."'
          data-localtimes='".@$EnableLocalTimes."' data-fullname='{\$FullName}'></script>"
      ));
  }
  
  if (IsEnabled($EnablePmSyntax, 0)) { # inject before skins and local.css
    $cs = is_array(@$CustomSyntax) ? 
      pm_json_encode(array_values($CustomSyntax), true) : '';
    array_unshift($HTMLHeaderFmt, "<link rel='stylesheet' 
      href='\$FarmPubDirUrl/guiedit/pmwiki.syntax.css'>
    <script src='\$FarmPubDirUrl/guiedit/pmwiki.syntax.js' data-imap='{\$EnabledIMap}'
      data-label=\"$[Highlight]\" data-mode='$EnablePmSyntax'
      data-custom=\"$cs\"></script>");
  }
}
PmUtilsJS();

##  This is a replacement for json_encode+PHSC, but only for arrays that
##  are used by the PmWiki core. It may or may not work in other cases.
function pm_json_encode($x, $encodespecial=false) {
  if (!isset($x) || is_null($x)) return 'null';
  if (is_bool($x)) return $x? "true" : "false";
  if (is_int($x) || is_float($x)) return strval($x);
  
  if (function_exists('json_encode'))
    $out = json_encode($x);
  
  elseif (is_string($x)) ## escape controls and specials per RFC:8259
    $out = '"'.preg_replace_callback("/[\x00-\x1f\\/\\\\\"]/",'cb_rfc8259',$x).'"';
    
  elseif (is_array($x)) {
    $a = array();
    if (array_values($x) === $x) { # numeric sequential array
      foreach($x as $v)
        $a[] = pm_json_encode($v);

      $out = "[".implode(',', $a)."]";
    }
    else { # associative array -> json object
      foreach($x as $k=>$v) {
        $jk = is_int($k)? "\"$k\"" : pm_json_encode($k);
        $jv = pm_json_encode($v);
        $a[] = "$jk:$jv";
      }
      $out = "{".implode(',', $a)."}";
    }
  }
  
  else return 'null'; # other types not yet supported

  return $encodespecial? PHSC($out, ENT_QUOTES) : $out;
}
function cb_rfc8259($m) { 
  return sprintf('\\u00%02x', ord($m[0]));
}

