<?php

session_start();
if (@$_REQUEST['details']) 
  $_SESSION['details'] = ($_REQUEST['details']!='n');

if ($action=='browse') 
  echo "<style type='text/css'><!--
    .pass { background-color:#ddffdd; }
    .fail { background-color:#ffdddd; }
    --></style>\n";

$MarkupPatterns[3000]["/^=test\\s+(\\S+)\\s+$KeepToken(\\d+)$KeepToken/e"] =
  "PZZ(\$tname='$1',\$tkeep1=$2)";
$MarkupPatterns[3001]["/=result\\s+(\\S+\\s*)?$KeepToken(\\d+)$KeepToken/e"] =
  "Keep(TestResult(\$pagename,\$tname,\$GLOBALS['KPV'][\$tkeep1],
    str_replace(array('&amp;','&lt;','&gt;'),array('&','<','>'),
      \$GLOBALS['KPV'][$2])))";

function TestResult($pagename,$testname,$testmarkup,$testresult) {
  $testmarkup = trim($testmarkup);
  $testresult = trim($testresult)."\n";
  $out = MarkupToHTML($pagename,$testmarkup);
  $pass = ($out==$testresult) ? 'pass' : 'fail';
  $x = "<table width='100%' border='1' cellspacing='0'>
    <tr><td colspan='2' class='$pass'><b>$pagename</b> - $testname: $pass</td></tr>";
  if (!$pass || @$_SESSION['details'])
    $x .= "<tr><td width='50%'><pre>".htmlspecialchars($out)."</pre></td>
      <td><pre>".htmlspecialchars($testresult)."</pre></td></tr>";
  return "$x</table>";
}

?>
