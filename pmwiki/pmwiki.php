<?php
/*
    PmWiki
    Copyright 2001-2004 Patrick R. Michaud
    pmichaud@pobox.com
    http://www.pmichaud.com/

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
error_reporting(E_ALL);
#if (ini_get('register_globals')) {
#  foreach($_REQUEST as $k=>$v) { unset(${$k}); }
$UnsafeGlobals = array_keys($GLOBALS); $GCount=0; $FmtV=array();
define('PmWiki',1);
@include_once('scripts/version.php');
$GroupPattern = '[[:upper:]][\\w]*(?:-\\w+)*';
$NamePattern = '[[:upper:]\\d][\\w]*(?:-\\w+)*';
$WikiWordPattern = '[[:upper:]][[:alnum:]]*(?:[[:upper:]][[:lower:]0-9]|[[:lower:]0-9][[:upper:]])[[:alnum:]]*';
$WikiDir = new PageStore('wiki.d/$Group.$Name');
$WikiLibDirs = array($WikiDir,new PageStore('wikilib.d/$Group.$Name'));
$KeepToken = "\377\377";  
$K0=array('='=>'','@'=>'<code>');  $K1=array('='=>'','@'=>'</code>');
$Now=time();
$TimeFmt = '%B %d, %Y, at %I:%M %p';
$Newline="\262";
$PageEditFmt = "<form method='post' action='\$PageUrl?action=edit'>
  <input type='hidden' name='pagename' value='\$PageName' />
  <input type='hidden' name='action' value='edit' />
  <textarea name='text' cols='70' rows='24'>\$EditText</textarea><br />
  <input type='submit' name='post' value=' Save ' />
  <input type='submit' name='preview' value=' Preview ' />
  </form>";
$EditFields = array('text');
$EditFunctions = array('PostPage','RecentChanges');
$RCDelimPattern = ' \\. ';
$RecentChangesFmt = array(
  'Main.AllRecentChanges' => 
    '* [[$Group.$Name]] . . . $CurrentTime by $AuthorLink',
  '$Group.RecentChanges' =>
    '* [[$Group/$Name]] . . . $CurrentTime by $AuthorLink');
$DefaultPageTextFmt = 'Describe [[$Name]] here.';
$ScriptUrl = $_SERVER['SCRIPT_NAME'];
$PubDirUrl = preg_replace('#/[^/]*$#','/pub',$ScriptUrl,1);
$RedirectDelay = 0;
$DiffFunction = 'Diff';
$SysDiffCmd = '/usr/bin/diff';
$DiffKeepDays = 0;
$HTMLVSpace = "<p class='vspace'></p>";
$BlockCS = array(); $BlockVS = array();
$UrlExcludeChars = '<>"{}|\\\\^`()[\\]\'';
$SuffixPattern = '(?:-?[[:alnum:]]+)*';
$LinkPageExistsFmt = "<a class='wikilink' href='\$PageUrl'>\$LinkText</a>";
$LinkPageCreateFmt = 
  "\$LinkText<a class='createlink' href='\$PageUrl?action=edit'>?</a>";
$LinkPageCreateSpaceFmt = &$LinkPageCreateFmt;
umask(0);

$WikiTitle = 'PmWiki';
$HTTPHeaders = array(
  "Expires: Tue, 01 Jan 2002 00:00:00 GMT",
  "Last-Modified: ".gmstrftime('%a, %d %b %Y %H:%M:%S GMT'),
  "Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0",
  "Pragma: no-cache",
  "Content-type: text/html; charset=utf-8;");
$HTMLDoctypeFmt = 
  "<!DOCTYPE html 
    PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\"
    \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
  <html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'><head>\n";
$HTMLTitleFmt = "  <title>\$WikiTitle - \$HTMLTitle</title>\n";
$HTMLStylesFmt = array("
  body { margin-left:20px; }
  ul, ol, pre, dl, p { margin-top:0px; margin-bottom:0px; }
  .vspace { margin-top:1.33em; }
  .indent { margin-left:40px; }
  ");
$HTMLHeaderFmt = array(
  "<style type='text/css'><!--",&$HTMLStylesFmt,"\n--></style>");
$HTMLBodyFmt = "</head>\n<body>";
$HTMLStartFmt = array('headers:',&$HTMLDoctypeFmt,&$HTMLHeaderFmt,
  &$HTMLTitleFmt,&$HTMLBodyFmt);
$HTMLEndFmt = "\n</body>\n</html>";
$PageStartFmt = array(&$HTMLStartFmt,"\n<div id='contents'>\n");
$PageEndFmt = array('</div>',&$HTMLEndFmt);

$HandleActions = array(
  'browse' => 'HandleBrowse',
  'edit' => 'HandleEdit', 'source' => 'HandleSource');

foreach(array('http:','https:','mailto:','ftp:','news:','gopher:','nap:') 
  as $m) { $LinkFunctions[$m] = 'LinkIMap';  $IMap[$m]="$m$1"; }
$LinkFunctions['<:page>'] = 'LinkPage';

if (strpos($_SERVER['QUERY_STRING'],'?')===true) {
  unset($_GET);
  parse_str(str_replace('?','&',$_SERVER['QUERY_STRING']),$_GET);
}

foreach(array('pagename','action','text','restore','preview') as $v) {
  if (isset($_GET[$v])) $$v=$_GET[$v];
  elseif (isset($_POST[$v])) $$v=$_POST[$v];
  else $$v='';
}
if ($action=='') $action='browse';

if (!$pagename && 
    preg_match('!^'.preg_quote($_SERVER['SCRIPT_NAME'],'!').'/?([^?]*)!',
      $_SERVER['REQUEST_URI'],$match))
  $pagename = $match[1];

include_once('local/config.php');

$LinkPattern = implode('|',array_keys($LinkFunctions));
SDV($ImgExtPattern,"\\.(?:gif|jpg|jpeg|png)");
SDV($ImgTagFmt,"<img src='\$LinkUrl' alt='\$LinkAlt' />");

$MarkupPatterns[50]["/\\r/"] = '';
$MarkupPatterns[100]["/\\[([=@])(.*?)\\1\\]/se"] =
  "Keep(\$K0['$1'].PSS('$2').\$K1['$1'])";
$MarkupPatterns[300]["/(\\\\*)\\\\\n/e"] =
  "Keep(' '.str_repeat('<br />',strlen('$1')))";
$MarkupPatterns[2000]["\n"] = 
  '$lines = array_merge($lines,explode("\n",$x)); return NULL;';
$MarkupPatterns[4000]['/\\[\\[#([A-Za-z][-.:\\w]*)\\]\\]/'] =
  "<a name='$1' id='$1'></a>";
$MarkupPatterns[4100]["/\\[\\[([^|]+)\\|(.*?)\\]\\]($SuffixPattern)/e"] =
  "Keep(MakeLink(\$pagename,PSS('$1'),PSS('$2'),'$3'))";
$MarkupPatterns[4200]["/\\[\\[(.*?)\\]\\]($SuffixPattern)/e"] =
  "Keep(MakeLink(\$pagename,PSS('$1'),NULL,'$2'))";
$MarkupPatterns[4300]['/\\bmailto:(\\S+)/e'] =
  "Keep(MakeLink(\$pagename,'$0','$1'))";
$MarkupPatterns[4350]["/\\b($LinkPattern)([^\\s$UrlExcludeChars]+$ImgExtPattern)(\"([^\"]*)\")?/e"] =
  "Keep(\$GLOBALS['LinkFunctions']['$1'](\$pagename,'$1','$2','$4','',
    \$GLOBALS['ImgTagFmt']))";
$MarkupPatterns[4400]["/\\b($LinkPattern)[^\\s$UrlExcludeChars]*[^\\s.,?!$UrlExcludeChars]/e"] =
  "Keep(MakeLink(\$pagename,'$0','$0'))";
$MarkupPatterns[4500]["/\\b($GroupPattern([\\/.]))?($WikiWordPattern)/e"] =
  "Keep(MakeLink(\$pagename,'$0'))";
$MarkupPatterns[5000]['/^(!{1,6})(.*)$/e'] =
  "'<:block><h'.strlen('$1').'>$2</h'.strlen('$1').'>'";
$MarkupPatterns[5100]['/^(\\*+)/'] = '<:ul,$1>';
$MarkupPatterns[5200]['/^(#+)/'] = '<:ol,$1>';
$MarkupPatterns[5300]['/^(-+)&gt;/'] = '<:indent,$1>';
$MarkupPatterns[5400]['/^\\s*$/'] = '<:vspace>';
$MarkupPatterns[5500]['/^(\\s)/'] = '<:pre,1>';
$MarkupPatterns[5550]['/^\\|\\|.*\\|\\|.*$/e'] =
  "FormatTableRow(PSS('$0'))";
$MarkupPatterns[5555]['/^\\|\\|(.*)$/e'] =
  "PZZ(\$GLOBALS['BlockMarkups']['table'][0] = PSS('<table $1>'))";
$MarkupPatterns[5600]['/^(:+)([^:]+):/'] =
  '<:dl,$1><dt>$2</dt><dd>';
$MarkupPatterns[5700]['/^----+/'] = 
  '<:block><hr />';
$MarkupPatterns[5900]['/^(<:([^>]+)>)?/e'] = "Block('$2');";
$MarkupPatterns[7000]["/'''''(.*?)'''''/"] =
  '<strong><em>$1</em></strong>';
$MarkupPatterns[7010]["/'''(.*?)'''/"] =
  '<strong>$1</strong>';
$MarkupPatterns[7020]["/''(.*?)''/"] =
  '<em>$1</em>';
$MarkupPatterns[7030]["/@@(.*?)@@/"] =
  '<code>$1</code>';
$MarkupPatterns[7040]["/\\[(([-+])+)(.*?)\\1\\]/e"] =
  "'<span style=\'font-size:'.(round(pow(1.2,$2strlen('$1'))*100,0)).'%\'>'.PSS('$3</span>')";
$MarkupPatterns[8000]["/$KeepToken(\\d+?)$KeepToken/e"] =
  '$GLOBALS[\'KPV\'][\'$1\']';

SDVA($BlockMarkups,array(
  'block' => array('','',''),
  'ul' => array('<ul><li>','</li><li>','</li></ul>'),
  'dl' => array('<dl>','</dd>','</dd></dl>'),
  'ol' => array('<ol><li>','</li><li>','</li></ol>'),
  'p' => array('<p>','','</p>'),
  'indent' => 
     array("<div class='indent'>","</div><div class='indent'>",'</div>'),
  'pre' => array('<pre> ',' ','</pre>'),
  'table' => array("<table width='100%'>",'','</table>')));

$CurrentTime = strftime($TimeFmt,$Now);

if (!function_exists($HandleActions[$action])) $action='browse';
$HandleActions[$action]($pagename);
Lock(0);
exit();

## helper functions
function stripmagic($x) 
  { return get_magic_quotes_gpc() ? stripslashes($x) : $x; }
function PSS($x) 
  { return str_replace('\\"','"',$x); }
function PZZ($x,$y='') { return ''; }
function SDV(&$v,$x) { if (!isset($v)) $v=$x; }
function SDVA(&$var,$val) 
  { foreach($val as $k=>$v) if (!isset($var[$k])) $var[$k]=$v; }
function XL($x)  { return $x; }

## Lock is used to make sure only one instance of PmWiki is running when
## files are being written.
function Lock($t) { return; }

## mkgiddir creates a directory, ensuring appropriate permissions
function mkgiddir($dir) { if (!file_exists($dir)) mkdir($dir); }

function FmtPageName($fmt,$pagename) {
  # Perform $-substitutions on $fmt relative to page given by $pagename
  global $GroupPattern,$NamePattern,$GCount,$UnsafeGlobals,$FmtV;
  if (strpos($fmt,'$')===false) return $fmt;                  
  if (!is_null($pagename) && !preg_match("/^($GroupPattern)[\\/.]($NamePattern)\$/",$pagename,$match)) return '';
  $fmt = preg_replace('/\\$([A-Z]\\w*Fmt)\\b/e','$GLOBALS[\'$1\']',$fmt);
  $fmt = preg_replace('/\\$\\[(.+?)\\]/e',"XL(PSS('$1'))",$fmt);
  static $qk = array('$PageUrl','$ScriptUrl','$Group','$Name');
  $qv = array('$ScriptUrl/$Group/$Name',$GLOBALS['ScriptUrl'],$match[1],
    $match[2]);
  $fmt = str_replace('$PageName','$Group.$Name',$fmt);
  $fmt = str_replace($qk,$qv,$fmt);
  if (strpos($fmt,'$')===false) return $fmt;
  static $g;
  if ($GCount != count($GLOBALS)+count($FmtV)) {
    $g = array();
    foreach($GLOBALS as $n=>$v) {
      if (is_array($v) || is_object($v) ||
         isset($FmtV["\$$n"]) || in_array($n,$UnsafeGlobals)) continue;
      $g["\$$n"] = $v;
    }
    $GCount = count($GLOBALS)+count($FmtV);
    krsort($g); reset($g);
  }
  $fmt = str_replace(array_keys($g),array_values($g),$fmt);
  $fmt = str_replace(array_keys($FmtV),array_values($FmtV),$fmt);
  return $fmt;
}

class PageStore {
  var $dirfmt;
  function PageStore($d='wiki.d') { $this->dirfmt=$d; }
  function read($pagename) {
    $newline = "\262";
    $pagefile = FmtPageName($this->dirfmt,$pagename);
    if ($pagefile && $fp=@fopen($pagefile,"r")) {
      while (!feof($fp)) {
        $line = fgets($fp,4096);
        while (substr($line,-1,1)!="\n" && !feof($fp)) 
          { $line .= fgets($fp,4096); }
        @list($k,$v) = explode('=',rtrim($line),2);
        if ($k=='newline') { $newline=$v; continue; }
        $page[$k] = str_replace($newline,"\n",$v);
      }
      fclose($fp);
    }
    return @$page;
  }
  function write($pagename,$page) {
    global $Now,$Version,$Newline;
    $page['name'] = $pagename;
    $page['time'] = $Now;
    $page['host'] = $_SERVER['REMOTE_ADDR'];
    $page['agent'] = $_SERVER['HTTP_USER_AGENT'];
    $page['rev'] = @$page['rev']+1;
    $s = false;
    $pagefile = FmtPageName($this->dirfmt,$pagename);
    mkgiddir(dirname($pagefile));
    if ($pagefile && ($fp=fopen("$pagefile,new","w"))) {
      $s = true && fputs($fp,"version=$Version\nnewline=$Newline\n");
      foreach($page as $k=>$v) 
        if ($k>'') $s = $s&&fputs($fp,str_replace("\n",$Newline,"$k=$v")."\n");
      $s = fclose($fp) && $s;
      if (file_exists($pagefile)) $s = $s && unlink($pagefile);
      $s = $s && rename("$pagefile,new",$pagefile);
    }
    if (!$s)
      Abort("Cannot write page to $pagename ($pagefile)...changes not saved");
  }
  function exists($pagename) {
    $pagefile = FmtPageName($this->dirfmt,$pagename);
    return ($pagefile && file_exists($pagefile));
  }
}

function ReadPage($pagename,$defaulttext=NULL) {
  # read a page from the appropriate directories given by $WikiReadDirsFmt.
  global $WikiLibDirs,$DefaultPageTextFmt,$Now;
  if (is_null($defaulttext)) $defaulttext=$DefaultPageTextFmt;
  Lock(1);
  foreach ($WikiLibDirs as $dir) {
    $page = $dir->read($pagename);
    if ($page) break;
  }
  if ($page['text']=='') 
    $page['text']=FmtPageName($defaulttext,$pagename);
  if (@!$page['time']) $page['time']=$Now;
  return $page;
}

function WritePage($pagename,$page) {
  global $WikiDir;
  $WikiDir->write($pagename,$page);
}

function PageExists($pagename) {
  global $WikiLibDirs;
  foreach((array)$WikiLibDirs as $dir)
    if ($dir->exists($pagename)) return true;
  return false;
}
  
function Abort($msg) {
  # exit pmwiki with an abort message
  echo "<h3>PmWiki can't process your request</h3>
    <p>$msg</p><p>We are sorry for any inconvenience.</p>";
  exit();
}

function Redirect($pagename,$urlfmt='$PageUrl') {
  # redirect the browser to $pagename
  global $EnableRedirect,$RedirectDelay;
  clearstatcache();
  #if (!PageExists($pagename)) $pagename=$DefaultPage;
  $pageurl = FmtPageName($urlfmt,$pagename);
  if (!isset($EnableRedirect) || $EnableRedirect) {
    header("Location: $pageurl");
    header("Content-type: text/html");
    echo "<html><head>
      <meta http-equiv='Refresh' Content='$RedirectDelay; URL=$pageurl' />
      <title>Redirect</title></head><body></body></html>";
  } else echo "<a href='$pageurl'>Redirect to $pageurl</a>";
  exit;
}

function Keep($x) {
  # Keep preserves a string from being processed by wiki markups
  global $KeepToken,$KPV,$KPCount;
  $KPCount++; $KPV[$KPCount]=$x;
  return $KeepToken.$KPCount.$KeepToken;
}

function Block($b) {
  global $BlockMarkups,$HTMLVSpace,$BlockCS,$BlockVS;
  $cs = &$BlockCS[0];  $vspaces = &$BlockVS[0];
  $out = '';
  if (!$b) $b='p,1';
  @list($code,$depth) = explode(',',$b);
  if ($code=='vspace') { 
    $vspaces.="\n"; 
    if (@$cs[0]!='p') return; 
  }
  if ($depth==0) $depth=strlen($depth);
  while (count($cs)>$depth) 
    { $c = array_pop($cs); $out .= $BlockMarkups[$c][2]; }
  if ($depth>0 && $depth==count($cs) && $cs[$depth-1]!=$code)
    { $c = array_pop($cs); $out .= $BlockMarkups[$c][2]; }
  if ($vspaces) { 
    $out .= (@$cs[0]=='pre') ? $vspaces : $HTMLVSpace; 
    $vspaces=''; 
  }
  if ($depth==0) { return $out; }
  if ($depth==count($cs)) { return $out.$BlockMarkups[$code][1]; }
  while (count($cs)<$depth-1) 
    { array_push($cs,'dl'); $out .= $BlockMarkups['dl'][0].'<dd>'; }
  if (count($cs)<$depth) {
    array_push($cs,$code);
    $out .= $BlockMarkups[$code][0];
  }
  return $out;
}

function FormatTableRow($x) {
  global $Block,$TableCellAttr;
  $x = preg_replace('/\\|\\|$/','',$x);
  $td = explode('||',$x); $y='';
  for($i=0;$i<count($td);$i++) {
    if ($td[$i]=='') continue;
    if (preg_match('/^\\s+$/',$td[$i])) $td[$i]='&nbsp;';
    $attr = $TableCellAttr;
    if (preg_match('/^\\s.*\\s$/',$td[$i])) { $attr .= " align='center'"; }
    elseif (preg_match('/^\\s/',$td[$i])) { $attr .= " align='right'"; }
    for ($colspan=1;$i+$colspan<count($td);$colspan++) 
      if ($td[$colspan+$i]!='') break;
    if ($colspan>1) { $attr .= " colspan='$colspan'"; }
    $y .= "<td $attr>".$td[$i].'</td>';
  }
  return "<:table,1><tr>$y</tr>";
}

function LinkIMap($pagename,$imap,$path,$title,$txt,$fmt=NULL) {
  global $IMap;
  $path = str_replace(' ','%20',$path);
  $FmtV['$LinkUrl'] = str_replace('$1',$path,$IMap[$imap]);
  $FmtV['$LinkText'] = $txt;
  $FmtV['$LinkAlt'] = str_replace(array('"',"'"),array('&#34;','&#39;'),$title);
  if (!$fmt) $fmt="<a class='urllink' href='\$LinkUrl'>\$LinkText</a>";
  return str_replace(array_keys($FmtV),array_values($FmtV),$fmt);
}

function LinkPage($pagename,$imap,$path,$title,$txt,$fmt=NULL) {
  global $LinkPageExistsFmt,$LinkPageCreateSpaceFmt,$LinkPageCreateFmt,$FmtV;
  $PageNameChars = '-[:alnum:]';
  preg_match('/^(?:(.*)([.\\/]))?([^.\\/]+)$/',$path,$m);
  if (!$m[1]) $group=FmtPageName('$Group',$pagename);
  else $group=str_replace(' ','',ucwords(preg_replace("/[^$PageNameChars]+/",' ',$m[1])));
  $name = str_replace(' ','',ucwords(preg_replace("/[^$PageNameChars]+/",' ',$m[3])));
  $tgtname = "$group.$name";
  if (!$fmt) {
    if (PageExists($tgtname)) $fmt=$LinkPageExistsFmt;
    elseif (preg_match('/\\s/',$txt)) $fmt=$LinkPageCreateSpaceFmt;
    else $fmt=$LinkPageCreateFmt;
  }
  $FmtV['$LinkText'] = $txt;
  return FmtPageName($fmt,$tgtname);
}

function MakeLink($pagename,$tgt,$txt=NULL,$suffix=NULL) {
  global $LinkPattern,$LinkFunctions,$UrlExcludeChars,$ImgExtPattern,$ImgTagFmt;
  $t = preg_replace('/[()]/','',trim($tgt));
  preg_match("/^($LinkPattern)?(.+?)(\"(.*)\")?$/",$t,$m);
  if (!$m[1]) $m[1]='<:page>';
  if (is_null($txt)) {
    $txt = preg_replace('/\\([^)]*\\)/','',$tgt);
    if ($m[1]=='<:page>') $txt = preg_replace('!^.*/!','',$txt);
  }
  if (preg_match("/^($LinkPattern)?([^$UrlExcludeChars]+$ImgExtPattern)(\"(.*)\")?$/",$txt,$tm)) 
    $txt = $LinkFunctions[$tm[1]]($pagename,$tm[1],$tm[2],@$tm[4],'',$ImgTagFmt);
  else $txt .= $suffix;
  $out = $LinkFunctions[$m[1]]($pagename,$m[1],$m[2],@$m[4],$txt);
  return $out;
}

function MarkupToHTML($pagename,$text) {
  # convert wiki markup text to HTML output
  global $MarkupPatterns,$BlockCS,$BlockVS,$K0,$K1;

  array_unshift($BlockCS,array()); array_unshift($BlockVS,'');
  ksort($MarkupPatterns);
  foreach($MarkupPatterns as $n=>$a)
    foreach($a as $p=>$r) $markpats[$p]=$r;
  foreach((array)$text as $l) $lines[] = htmlspecialchars($l,ENT_NOQUOTES);
  while (count($lines)>0) {
    $x = array_shift($lines);
    foreach($markpats as $p=>$r) {
      if (substr($p,0,1)=='/') $x=preg_replace($p,$r,$x); 
      elseif ($p=='' || strstr($x,$p)!==false) $x=eval($r);
      if (is_null($x)) continue 2;
    }
    if ($x>'') $out[] = "$x\n";
  }
  $x = Block('block');
  if ($x>'') $out[] = "$x\n";
  array_shift($BlockCS); array_shift($BlockVS);
  return implode('',(array)$out);
}

function PrintFmt($pagename,$fmt) {
  global $HTTPHeaders,$FmtV;
  if (is_array($fmt)) 
    { foreach($fmt as $f) PrintFmt($pagename,$f); return; }
  $x = FmtPageName($fmt,$pagename);
  if (preg_match("/^headers:/",$x)) {
    foreach($HTTPHeaders as $h) (@$sent++) ? @header($h) : header($h);
    return;
  }
  if (preg_match('/^wiki:(.+)$/',$x,$match)) 
    { PrintWikiPage($pagename,$match[1]); return; }
  echo $x;
}

function PrintWikiPage($pagename,$wikilist=NULL) {
  if (is_null($wikilist)) $wikilist=$pagename;
  $pagelist = preg_split('/\s+/',$wikilist,-1,PREG_SPLIT_NO_EMPTY);
  foreach($pagelist as $p) {
    if (PageExists($p)) {
      $page = ReadPage($p,'');
      if ($page['text']) echo MarkupToHTML($pagename,$page['text']);
      return;
    }
  }
}
   
function HandleBrowse($pagename) {
  global $FmtV,$HandleBrowseFmt,$PageStartFmt,$PageEndFmt;
  # handle display of a page
  $page = ReadPage($pagename);
  if (!$page) Abort('Invalid page name');
  $FmtV['$PageText'] = MarkupToHTML($pagename,$page['text']);
  SDV($HandleBrowseFmt,array(&$PageStartFmt,'$PageText',&$PageEndFmt));
  PrintFmt($pagename,$HandleBrowseFmt);
}

function Diff($oldtext,$newtext) {
  global $TempDir,$SysDiffCmd;
  if (!$SysDiffCmd) return '';
  $tempold = tempnam($TempDir,'old');
  if ($oldfp=fopen($tempold,'w')) { fputs($oldfp,$oldtext); fclose($oldfp); }
  $tempnew = tempnam($TempDir,'new');
  if ($newfp=fopen($tempnew,'w')) { fputs($newfp,$newtext); fclose($newfp); }
  $diff = '';
  $diff_handle = popen("$SysDiffCmd $tempold $tempnew",'r');
  if ($diff_handle) {
    while (!feof($diff_handle)) $diff .= fread($diff_handle,4096);
    pclose($diff_handle);
  }
  @unlink($tempold); @unlink($tempnew);
  return $diff;
} 

function PostPage($pagename,&$page,&$new) {
  global $Now,$WikiDir,$IsPagePosted,$DiffFunction,$DiffKeepDays;
  if (@$_REQUEST['post']) {
    if ($new['text']==$page['text']) { Redirect($pagename); return; }
    $new["author:$Now"] = @$Author;
    $new["host:$Now"] = $_SERVER['REMOTE_ADDR'];
    $diffclass = preg_replace('/\\W/','',@$_POST['diffclass']);
    if ($page["time"]>0) 
      $new["diff:$Now:{$page['time']}:$diffclass"] =
        $DiffFunction($new['text'],$page['text']);
    $keepgmt = $Now-$DiffKeepDays * 86400;
    $keys = array_keys($new);
    foreach($keys as $k)
      if (preg_match("/^\\w+:(\\d+)/",$k,$match) && $match[1]<$keepgmt)
        unset($new[$k]);
    WritePage($pagename,$new);
    $IsPagePosted=true;
  }
}

function RecentChanges($pagename,&$page,&$new) {
  global $IsPagePosted,$RecentChangesFmt,$RCDelimPattern;
  if (!$IsPagePosted) return;
  foreach($RecentChangesFmt as $rcfmt=>$pgfmt) {
    $rcname = FmtPageName($rcfmt,$pagename);  if (!$rcname) continue;
    $pgtext = FmtPageName($pgfmt,$pagename);  if (!$pgtext) continue;
    if (@$seen[$rcname]++) continue;
    $rcpage = ReadPage($rcname,'');
    $rcelim = preg_quote(preg_replace("/$RCDelimPattern.*$/",' ',$pgtext),'/');
    $rcpage['text'] = preg_replace("/[^\n]*$rcelim.*\n/","",$rcpage['text']);
    if (!preg_match("/$RCDelimPattern/",$rcpage['text'])) 
      $rcpage['text'] .= "$pgtext\n";
    else
      $rcpage['text'] = preg_replace("/([^\n]*$RCDelimPattern.*\n)/",
        "$pgtext\n$1",$rcpage['text'],1);
    WritePage($rcname,$rcpage);
  }
}
    
function HandleEdit($pagename) {
  global $IsPagePosted,$EditFields,$EditFunctions,$FmtV,
    $HandleEditFmt,$PageStartFmt,$PageEditFmt,$PageEndFmt;
  $IsPagePosted = false;
  $page = ReadPage($pagename);
  $new = $page;
  foreach((array)$EditFields as $k) 
    if (isset($_POST[$k])) $new[$k]=str_replace("\r",'',stripmagic($_POST[$k]));
  foreach((array)$EditFunctions as $fn) $fn($pagename,$page,$new);
  if ($IsPagePosted) { Redirect($pagename); return; }
  $FmtV['$EditText'] = htmlspecialchars($new['text'],ENT_NOQUOTES);
  SDV($HandleEditFmt,array(&$PageStartFmt,&$PageEditFmt,&$PageEndFmt));
  PrintFmt($pagename,$HandleEditFmt);
}

function HandleSource($pagename) {
  header("Content-type: text/plain");
  $page = ReadPage($pagename);
  echo $page['text'];
}

?> 
