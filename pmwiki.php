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
if (ini_get('register_globals')) 
  foreach($_REQUEST as $k=>$v) { unset(${$k}); }
$UnsafeGlobals = array_keys($GLOBALS); $GCount=0; $FmtV=array();
SDV($FarmD,dirname(__FILE__));
define('PmWiki',1);
@include_once('scripts/version.php');
$GroupPattern = '[[:upper:]][\\w]*(?:-\\w+)*';
$NamePattern = '[[:upper:]\\d][\\w]*(?:-\\w+)*';
$WikiWordPattern = '[[:upper:]][[:alnum:]]*(?:[[:upper:]][[:lower:]0-9]|[[:lower:]0-9][[:upper:]])[[:alnum:]]*';
$WikiDir = new PageStore('wiki.d/$PageName');
$WikiLibDirs = array($WikiDir,new PageStore('$FarmD/wikilib.d/$PageName'));
SDV($WorkDir,'wiki.d');
$InterMapFiles = array("$FarmD/scripts/intermap.txt",'local/localmap.txt');
$KeepToken = "\377\377";  
$K0=array('='=>'','@'=>'<code>');  $K1=array('='=>'','@'=>'</code>');
$Now=time();
$TimeFmt = '%B %d, %Y, at %I:%M %p';
$Newline="\262";
$PageEditFmt = "<div id='wikiedit'>
  <a id='top' name='top'></a>
  <h1 class='wikiaction'>$[Editing \$PageName]</h1>
  <form method='post' action='\$PageUrl?action=edit'>
  <input type='hidden' name='action' value='edit' />
  <input type='hidden' name='pagename' value='\$PageName' />
  <input type='hidden' name='basetime' value='\$EditBaseTime' />
  \$EditMessageFmt
  <textarea name='text' rows='25' cols='60'
    onkeydown='if (event.keyCode==27) event.returnValue=false;'
    >\$EditText</textarea><br />
  $[Author]: <input type='text' name='author' value='\$Author' />
  <input type='checkbox' name='diffclass' value='minor' \$DiffClassMinor />
    $[This is a minor edit]<br />
  <input type='submit' name='post' value=' $[Save] ' />
  <input type='submit' name='preview' value=' $[Preview] ' />
  <input type='reset' value=' $[Reset] ' /></form></div>";
$PagePreviewFmt = "<h2 class='wikiaction'>$[Preview \$PageName]</h2>
  <p><b>$[Page is unsaved]</b></p>
  \$PreviewText
  <hr /><p><b>$[End of preview -- remember to save]</b><br />
  <a href='#top'>$[Top]</a></p>";
$EditMessageFmt = '';
$EditFields = array('text');
$EditFunctions = array('RestorePage','ReplaceOnSave','PostPage',
  'PostRecentChanges','PreviewPage');
$RCDelimPattern = '  ';
$RecentChangesFmt = array(
  'Main.AllRecentChanges' => 
    '* [[$Group.$Name]]  . . . $CurrentTime by $AuthorLink',
  '$Group.RecentChanges' =>
    '* [[$Group/$Name]]  . . . $CurrentTime by $AuthorLink');
$DefaultPageTextFmt = 'Describe [[$Name]] here.';
$ScriptUrl = $_SERVER['SCRIPT_NAME'];
$PubDirUrl = preg_replace('#/[^/]*$#','/pub',$ScriptUrl,1);
$HTMLVSpace = "<p class='vspace'></p>";
$BlockCS = array(); $BlockVS = array();
$UrlExcludeChars = '<>"{}|\\\\^`()[\\]\'';
$QueryFragPattern = "[?#][^\\s$UrlExcludeChars]*";
$SuffixPattern = '(?:-?[[:alnum:]]+)*';
$LinkPageExistsFmt = "<a class='wikilink' href='\$LinkUrl'>\$LinkText</a>";
$LinkPageCreateFmt = 
  "\$LinkText<a class='createlink' href='\$PageUrl?action=edit'>?</a>";
$LinkPageCreateSpaceFmt = &$LinkPageCreateFmt;
umask(0);
$DefaultGroup = 'Main';
$DefaultName = 'HomePage';
$GroupHeaderFmt = '$Group.GroupHeader';
$GroupFooterFmt = '$Group.GroupFooter';
$PagePathFmt = array('$Group.$1','$1.$1');

$WikiTitle = 'PmWiki';
$HTTPHeaders = array(
  "Expires: Tue, 01 Jan 2002 00:00:00 GMT",
  "Last-Modified: ".gmstrftime('%a, %d %b %Y %H:%M:%S GMT'),
  "Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0",
  "Pragma: no-cache",
  "Content-type: text/html; charset=iso-8859-1;");
$HTMLDoctypeFmt = 
  "<!DOCTYPE html 
    PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\"
    \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
  <html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'><head>\n";
$HTMLTitleFmt = "  <title>\$WikiTitle - \$PageTitle</title>\n";
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

if (strpos(@$_SERVER['QUERY_STRING'],'?')===true) {
  unset($_GET);
  parse_str(str_replace('?','&',$_SERVER['QUERY_STRING']),$_GET);
}

foreach(array('pagename','action','text') as $v) {
  if (isset($_GET[$v])) $$v=$_GET[$v];
  elseif (isset($_POST[$v])) $$v=$_POST[$v];
  else $$v='';
}
if ($action=='') $action='browse';

if (!$pagename && 
    preg_match('!^'.preg_quote($_SERVER['SCRIPT_NAME'],'!').'/?([^?]*)!',
      $_SERVER['REQUEST_URI'],$match))
  $pagename = MakePageName('',$match[1]);

if (file_exists("$FarmD/local/farmconfig.php")) 
  include_once("$FarmD/local/farmconfig.php");
if (file_exists('local/config.php')) 
  include_once('local/config.php');

if (IsEnabled($EnableStdConfig,1))
  include_once("$FarmD/scripts/stdconfig.php");

SDV($DefaultPage,"$DefaultGroup.$DefaultName");
if (!$pagename) $pagename=$DefaultPage;

foreach((array)$InterMapFiles as $f) {
  if (@!($mapfd=fopen($f,"r"))) continue;
  while ($mapline=fgets($mapfd,1024)) {
    if (preg_match('/^\\s*$/',$mapline)) continue;
    list($imap,$url) = preg_split('/\\s+/',$mapline);
    if (strpos($url,'$1')===false) $url.='$1';
    $LinkFunctions["$imap:"] = 'LinkIMap';
    $IMap["$imap:"] = $url;
  }
}

$LinkPattern = implode('|',array_keys($LinkFunctions));
SDV($ImgExtPattern,"\\.(?:gif|jpg|jpeg|png)");
SDV($ImgTagFmt,"<img src='\$LinkUrl' border='0' alt='\$LinkAlt' />");

$MarkupPatterns[50]["/\\r/"] = '';
$MarkupPatterns[100]["/\\[([=@])(.*?)\\1\\]/se"] =
  "Keep(\$K0['$1'].PSS('$2').\$K1['$1'])";
$MarkupPatterns[300]["/(\\\\*)\\\\\n/e"] =
  "Keep(' '.str_repeat('<br />',strlen('$1')))";
$MarkupPatterns[1000]["\n"] = 
  '$lines = array_merge($lines,explode("\n",$x)); return NULL;';
$MarkupPatterns[1050]["/\{\\$(Group|Name)}/e"] =
  "FmtPageName('$$1',\$pagename)";
$MarkupPatterns[1100]["/\{\$(Version|Author|LastModified|LastModifiedBy|LastModifiedHost)}/e"] =
  "\$GLOBALS['$1']";
$MarkupPatterns[1500]['/\\[:title\\s(.*?):\\]/e'] =
  "PZZ(\$GLOBALS['PageTitle']=PSS('$1'))";
$MarkupPatterns[1505]['/\\[:keywords\\s(.*?):\\]/e'] =
  "PZZ(\$GLOBALS['HTMLHeaderFmt'][] = \"<meta name='keywords' content='\".
    str_replace(\"'\",'&#039;',PSS('$1')).\"' />\")";
$MarkupPatterns[1600]['[:noheader:]'] = 
  "\$GLOBALS['PageHeaderFmt']='';";
$MarkupPatterns[1605]['[:nofooter:]'] =
  "\$GLOBALS['PageFooterFmt']='';";
$MarkupPatterns[1610]['[:notitle:]'] =
  "\$GLOBALS['PageTitleFmt']='';";
$MarkupPatterns[1615]['/\\[:nogroup(header|footer):\\]/'] = '';
$MarkupPatterns[1620]['/\\[:comments .*?:\\]/'] = '';
$MarkupPatterns[3000]['/\\[\\[#([A-Za-z][-.:\\w]*)\\]\\]/e'] =
  "Keep(\"<a name='$1' id='$1'></a>\")";
$MarkupPatterns[3100]["/\\[\\[([^|\\]]+)\\|(.*?)\\]\\]($SuffixPattern)/e"] =
  "Keep(MakeLink(\$pagename,PSS('$1'),PSS('$2'),'$3'))";
$MarkupPatterns[3200]["/\\[\\[(.*?)\\]\\]($SuffixPattern)/e"] =
  "Keep(MakeLink(\$pagename,PSS('$1'),NULL,'$2'))";
$MarkupPatterns[3300]['/\\bmailto:(\\S+)/e'] =
  "Keep(MakeLink(\$pagename,'$0','$1'))";
$MarkupPatterns[3400]["/\\b($LinkPattern)([^\\s$UrlExcludeChars]+$ImgExtPattern)(\"([^\"]*)\")?/e"] =
  "Keep(\$GLOBALS['LinkFunctions']['$1'](\$pagename,'$1','$2','$4','',
    \$GLOBALS['ImgTagFmt']))";
$MarkupPatterns[3500]["/\\b($LinkPattern)[^\\s$UrlExcludeChars]*[^\\s.,?!$UrlExcludeChars]/e"] =
  "Keep(MakeLink(\$pagename,'$0','$0'))";
$MarkupPatterns[3600]["/\\b($GroupPattern([\\/.]))?($WikiWordPattern)/e"] =
  "Keep(MakeLink(\$pagename,'$0'))";
$MarkupPatterns[5000]['/^(!{1,6})(.*)$/e'] =
  "'<:block><h'.strlen('$1').'>$2</h'.strlen('$1').'>'";
$MarkupPatterns[5100]['/^(\\*+)/'] = '<:ul,$1>';
$MarkupPatterns[5150]['/^(#+)/'] = '<:ol,$1>';
$MarkupPatterns[5200]['/^(-+)&gt;/'] = '<:indent,$1>';
$MarkupPatterns[5250]['/^(:+)([^:]+):/'] =
  '<:dl,$1><dt>$2</dt><dd>';
$MarkupPatterns[5300]['/^\\s*$/'] = '<:vspace>';
$MarkupPatterns[5350]['/^(\\s)/'] = '<:pre,1>';
$MarkupPatterns[5400]['/^\\|\\|.*\\|\\|.*$/e'] =
  "FormatTableRow(PSS('$0'))";
$MarkupPatterns[5450]['/^\\|\\|(.*)$/e'] =
  "PZZ(\$GLOBALS['BlockMarkups']['table'][0] = PSS('<table $1>'))";
$MarkupPatterns[5500]['/^----+/'] = 
  '<:block><hr />';
$MarkupPatterns[5900]['/^(<:([^>]+)>)?/e'] = "Block('$2');";
$MarkupPatterns[7000]["/'''''(.*?)'''''/"] =
  '<strong><em>$1</em></strong>';
$MarkupPatterns[7100]["/'''(.*?)'''/"] =
  '<strong>$1</strong>';
$MarkupPatterns[7200]["/''(.*?)''/"] =
  '<em>$1</em>';
$MarkupPatterns[7300]["/@@(.*?)@@/"] =
  '<code>$1</code>';
$MarkupPatterns[7400]["/\\[(([-+])+)(.*?)\\1\\]/e"] =
  "'<span style=\'font-size:'.(round(pow(1.2,$2strlen('$1'))*100,0)).'%\'>'.PSS('$3</span>')";
$MarkupPatterns[9000]["/$KeepToken(\\d+?)$KeepToken/e"] =
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
function IsEnabled(&$var,$f=0)
  { return (isset($var)) ? $var : $f; }
function XL($x)  { return $x; }

## Lock is used to make sure only one instance of PmWiki is running when
## files are being written.  It does not "lock pages" for editing.
function Lock($op) { 
  global $WorkDir,$LockFile;
  SDV($LockFile,"$WorkDir/.flock");
  static $lockfp,$curop;
    if (!$lockfp) {
    $lockfp=fopen($LockFile,"w") or
      Abort("Cannot acquire lockfile","Lockfile");
  }
  if ($op<0) { flock($lockfp,LOCK_UN); fclose($lockfp); $lockfp=0; $curop=0; }
  elseif ($op==0) { flock($lockfp,LOCK_UN); $curop=0; }
  elseif ($op==1 && $curop<1) { flock($lockfp,LOCK_SH); $curop=1; }
  elseif ($op==2 && $curop<2) { flock($lockfp,LOCK_EX); $curop=2; }
}

## mkgiddir creates a directory, ensuring appropriate permissions
function mkgiddir($dir) { if (!file_exists($dir)) mkdir($dir); }

## MakePageName is used to convert a string into a valid pagename.
## If no group is supplied, then it uses $PagePathFmt to look
## for the page in other groups, or else uses the group of the
## pagename passed as an argument.
function MakePageName($pagename,$x) {
  global $MakePageNameFunction,$PageNameChars,$PagePathFmt;
  if (@$MakePageNameFunction) return $MakePageNameFunction($pagename,$x);
  SDV($PageNameChars,'-[:alnum:]');
  if (!preg_match('/(?:([^.\\/]+)[.\\/])?([^.\\/]+)$/',$x,$m)) return '';
  $name=str_replace(' ','',ucwords(preg_replace("/[^$PageNameChars]+/",' ',$m[2])));
  if ($m[1]) {
    $group = str_replace(' ','',ucwords(preg_replace("/[^$PageNameChars]+/",' ',$m[1])));
    return "$group.$name";
  }
  foreach((array)$PagePathFmt as $pg) {
    $pn = FmtPageName(str_replace('$1',$name,$pg),$pagename);
    if (PageExists($pn)) return $pn;
  }
  $group=preg_replace('/[\\/.].*$/','',$pagename);
  return "$group.$name";
}
  
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
  function delete($pagename) {
    global $Now;
    $pagefile = FmtPageName($this->dirfmt,$pagename);
    @rename($pagefile,"$pagefile,$Now");
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

function SetPage($pagename,$page) {
  global $PageTitle,$TimeFmt,$LastModified,$LastModifiedBy,$LastModifiedHost;
  $PageTitle = FmtPageName('$Name',(@$page['name'])?$page['name']:$pagename);
  $LastModified = strftime($TimeFmt,$page['time']);
  $LastModifiedBy = @$page['author'];
  $LastModifiedHost = @$page['host'];
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
  SDV($RedirectDelay,0);
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

function PrintFmt($pagename,$fmt) {
  global $HTTPHeaders,$FmtV;
  if (is_array($fmt)) 
    { foreach($fmt as $f) PrintFmt($pagename,$f); return; }
  $x = FmtPageName($fmt,$pagename);
  if (preg_match("/^headers:/",$x)) {
    foreach($HTTPHeaders as $h) (@$sent++) ? @header($h) : header($h);
    return;
  }
  if (preg_match('/^function:(\S+)\s*(.*)$/s',$x,$match) &&
      function_exists($match[1]))
    { $match[1]($pagename,$match[2]); return; }
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
      if ($page['text']) 
        echo MarkupToHTML($pagename,ProcessIncludes($pagename,$page['text']));
      return;
    }
  }
}

function Keep($x) {
  # Keep preserves a string from being processed by wiki markups
  global $KeepToken,$KPV,$KPCount;
  $KPCount++; $KPV[$KPCount]=$x;
  return $KeepToken.$KPCount.$KeepToken;
}

function ProcessIncludes($pagename,$text) {
  global $MaxIncludes,$FmtV,$K0,$K1,$IncludeBadAnchorFmt;
  SDV($MaxIncludes,10);
  SDV($IncludeBadAnchorFmt,"include:\$PageName - #\$BadAnchor \$[not found]\n");
  for($i=0;$i<$MaxIncludes;$i++) {
    $text = preg_replace("/\\[([=@])(.*?)\\1\\]/se",
      "Keep(\$K0['$1'].htmlentities(PSS('$2'),ENT_NOQUOTES).\$K1['$1'])",$text);
    if (!preg_match("/\\[:include\\s+([^#]+?)(#(\\w[-.:\\w]*)?(#(\\w[-.:\\w]*)?)?)?:\\]/",$text,$match)) break;
    @list($inclrepl,$inclname,$a,$aa,$b,$bb) = $match;
    $inclpage = ReadPage(MakePageName($pagename,$inclname));
    $incltext = $inclpage['text'];
    $FmtV['$BadAnchor'] = '';
    if ($bb && !ctype_digit($bb) && strpos($incltext,"[[#$bb]]")===false)
      $FmtV['$BadAnchor']=$bb;
    if ($aa && !ctype_digit($aa) && strpos($incltext,"[[#$aa]]")===false)
      $FmtV['$BadAnchor']=$aa;
    if ($FmtV['$BadAnchor'])
      $incltext=FmtPageName($IncludeBadAnchorFmt,$inclname);
    if (ctype_digit($bb))
      $incltext=preg_replace("/^(([^\\n]*\\n)\{0,$bb}).*$/s",'$1',$incltext,1);
    elseif ($bb)
      $incltext=preg_replace("/[^\\n]*\\[\\[#$bb\\]\\].*$/s",'',$incltext,1);
    if (ctype_digit($aa)) {
      $aa--; $incltext=preg_replace("/^([^\\n]*\\n)\{0,$aa}/s",'',$incltext,1);
      if (!$b) $incltext=preg_replace("/\\n.*$/s",'',$incltext,1);
    } elseif ($aa && $b)
      $incltext=preg_replace("/^.*?([^\\n]*\\[\\[#$aa\\]\\])/s",'$1',$incltext,1);
    elseif ($aa)      
      $incltext=preg_replace("/^.*?([^\\n]*\\[\\[#$aa\\]\\]( *\\n)?[^\\n]*).*/s",'$1',$incltext,1);
    $text = str_replace($inclrepl,$incltext,$text);
  }
  while (preg_match("/(.?)\\[:HF (\\S+) (\\S+):\\](.?)/",$text,$match)) {
    if (strpos($text,"[:{$match[2]}:]")===false && $pagename!=$match[3]) {
      $inclpage = ReadPage($match[3],'');
      $incltext = $inclpage['text'];
      if ($match[1] && $match[1]!="\n") $incltext = "\n".$incltext;
      if ($match[4] && $match[4]!="\n") $incltext .= "\n";
    } else $incltext='';
    $text = str_replace($match[0],$match[1].$incltext.$match[4],$text);
  }
  return $text;
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
    if (preg_match('/^!(.*)$/',$td[$i],$match)) 
      { $td[$i]=$match[1]; $t='th'; }
    else $t='td';
    $attr = $TableCellAttr;
    if (preg_match('/^\\s.*\\s$/',$td[$i])) { $attr .= " align='center'"; }
    elseif (preg_match('/^\\s/',$td[$i])) { $attr .= " align='right'"; }
    elseif (preg_match('/\\s$/',$td[$i])) { $attr .= " align='left'"; }
    for ($colspan=1;$i+$colspan<count($td);$colspan++) 
      if ($td[$colspan+$i]!='') break;
    if ($colspan>1) { $attr .= " colspan='$colspan'"; }
    $y .= "<$t $attr>".$td[$i]."</$t>";
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
  global $QueryFragPattern,$LinkPageExistsFmt,
    $LinkPageCreateSpaceFmt,$LinkPageCreateFmt,$FmtV;
  preg_match("/^([^#?]+)($QueryFragPattern)?$/",$path,$match);
  $tgtname = MakePageName($pagename,$match[1]); $qf=@$match[2];
  if (!$fmt) {
    if (PageExists($tgtname)) $fmt=$LinkPageExistsFmt;
    elseif (preg_match('/\\s/',$txt)) $fmt=$LinkPageCreateSpaceFmt;
    else $fmt=$LinkPageCreateFmt;
  }
  $FmtV['$LinkUrl'] = FmtPageName("\$PageUrl$qf",$tgtname);
  $FmtV['$LinkText'] = $txt;
  return FmtPageName($fmt,$tgtname);
}

function MakeLink($pagename,$tgt,$txt=NULL,$suffix=NULL,$fmt=NULL) {
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
  $out = $LinkFunctions[$m[1]]($pagename,$m[1],$m[2],@$m[4],$txt,$fmt);
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
   
function HandleBrowse($pagename) {
  # handle display of a page
  global $FmtV,$GroupHeaderFmt,$GroupFooterFmt,
    $HandleBrowseFmt,$PageStartFmt,$PageEndFmt,$PageRedirectFmt;
  Lock(1);
  $page = ReadPage($pagename);
  if (!$page) Abort('Invalid page name');
  SetPage($pagename,$page);
  $hdname = FmtPageName($GroupHeaderFmt,$pagename);
  $ftname = FmtPageName($GroupFooterFmt,$pagename);
  $text = ProcessIncludes($pagename,
    "[:HF nogroupheader $hdname:]".$page['text'].
    "[:HF nogroupfooter $ftname:]");
  SDV($PageRedirectFmt,"<p><i>($[redirected from] 
    <a href='\$PageUrl?action=edit'>\$PageName</a>)</i></p>\$HTMLVSpace\n");
  if (@!$_GET['from']) {
    $PageRedirectFmt = '';
    if (preg_match('/\\[:redirect\\s+(.+?):\\]/',$text,$match)) {
      $rname = MakePageName($pagename,$match[1]);
      if (PageExists($rname)) Redirect($rname,"\$PageUrl?from=$pagename");
    }
  } else $PageRedirectFmt=FmtPageName($PageRedirectFmt,$_GET['from']);
  $FmtV['$PageText'] = MarkupToHTML($pagename,$text);
  SDV($HandleBrowseFmt,array(&$PageStartFmt,&$PageRedirectFmt,'$PageText',
    &$PageEndFmt));
  PrintFmt($pagename,$HandleBrowseFmt);
}


function RestorePage($pagename,&$page,&$new,$restore=NULL) {
  if (is_null($restore)) $restore=@$_REQUEST['restore'];
  if (!$restore) return;
  $t = $page['text'];
  $nl = (substr($t,-1)=="\n");
  $t = explode("\n",$t);
  if ($nl) array_pop($t);
  krsort($page); reset($page);
  foreach($page as $k=>$v) {
    if ($k<$restore) break;
    foreach(explode("\n",$v) as $x) {
      if (preg_match('/^(\\d+)(,(\\d+))?([adc])(\\d+)/',$x,$match)) {
        $a1 = $a2 = $match[1];
        if ($match[3]) $a2=$match[3];
        $b1 = $match[5];
        if ($match[4]=='d') array_splice($t,$b1,$a2-$a1+1);
        if ($match[4]=='c') array_splice($t,$b1-1,$a2-$a1+1);
        continue;
      }
      if (substr($x,0,2)=='< ') { $nlflag=true; continue; }
      if (preg_match('/^> (.*)$/',$x,$match)) {
        $nlflag=false;
        array_splice($t,$b1-1,0,$match[1]); $b1++;
      }
      if ($x=='\\ No newline at end of file') $nl=$nlflag;
    }
  }
  if ($nl) $t[]='';
  $new['text']=implode("\n",$t);
  return $new['text'];
}

## ReplaceOnSave performs any text replacements (held in $ROSPatterns)
## on the new text prior to saving the page.
function ReplaceOnSave($pagename,&$page,&$new) {
  global $ROSPatterns;
  if (!@$_POST['post']) return;
  foreach((array)$ROSPatterns as $pat=>$repfmt) 
    $new['text'] = 
      preg_replace($pat,FmtPageName($repfmt,$pagename),$new['text']);
}

function Diff($oldtext,$newtext) {
  global $WorkDir,$SysDiffCmd;
  SDV($SysDiffCmd,"/usr/bin/diff");
  if (!$SysDiffCmd) return '';
  $tempold = tempnam($WorkDir,'old');
  if ($oldfp=fopen($tempold,'w')) { fputs($oldfp,$oldtext); fclose($oldfp); }
  $tempnew = tempnam($WorkDir,'new');
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
  global $DiffKeepDays,$DiffFunction,$DeleteKeyPattern,
    $Now,$WikiDir,$IsPagePosted;
  SDV($DiffKeepDays,3650);
  SDV($DiffFunction,'Diff');
  SDV($DeleteKeyPattern,"^\\s*delete\\s*$");
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
    if (preg_match("/$DeleteKeyPattern/",$new['text']))
      $WikiDir->delete($pagename);
    else WritePage($pagename,$new);
    $IsPagePosted=true;
  }
}

function PostRecentChanges($pagename,&$page,&$new) {
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

function PreviewPage($pagename,&$page,&$new) {
  global $IsPageSaved,$GroupHeaderFmt,$GroupFooterFmt,$FmtV,$PagePreviewFmt;
  if (!$IsPageSaved && @$_REQUEST['preview']) {
    $hdname = FmtPageName($GroupHeaderFmt,$pagename);
    $ftname = FmtPageName($GroupFooterFmt,$pagename);
    $text = ProcessIncludes($pagename,
      "[:HF nogroupheader $hdname:]".$new['text'].
      "[:HF nogroupfooter $ftname:]");
    $FmtV['$PreviewText'] = MarkupToHTML($pagename,$text);
  } else $PagePreviewFmt = '';
}
  
function HandleEdit($pagename) {
  global $IsPagePosted,$EditFields,$EditFunctions,$FmtV,$Now,
    $HandleEditFmt,$PageStartFmt,$PageEditFmt,$PagePreviewFmt,$PageEndFmt;
  $IsPagePosted = false;
  Lock(2);
  $page = ReadPage($pagename);
  SetPage($pagename,$page);
  $new = $page;
  foreach((array)$EditFields as $k) 
    if (isset($_POST[$k])) $new[$k]=str_replace("\r",'',stripmagic($_POST[$k]));
  foreach((array)$EditFunctions as $fn) $fn($pagename,$page,$new);
  if ($IsPagePosted) { Redirect($pagename); return; }
  $FmtV['$EditText'] = htmlspecialchars($new['text'],ENT_NOQUOTES);
  $FmtV['$EditBaseTime'] = $Now;
  SDV($HandleEditFmt,array(&$PageStartFmt,
    &$PageEditFmt,&$PagePreviewFmt,&$PageEndFmt));
  PrintFmt($pagename,$HandleEditFmt);
}

function HandleSource($pagename) {
  Lock(1);
  header("Content-type: text/plain");
  $page = ReadPage($pagename);
  echo $page['text'];
}

?> 
