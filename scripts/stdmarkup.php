<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script defines PmWiki's standard markup.  It is automatically
    included from stdconfig.php unless $EnableStdMarkup==0.

    Each call to Markup() below adds a new rule to PmWiki's translation
    engine.  The form of the call is Markup($id,$where,$pat,$rep); $id
    is a unique name for the rule, $where is the position of the rule
    relative to another rule, $pat is the pattern to look for, and
    $rep is the string to replace it with.
    
    
*/

## first we preserve text in [=...=] and [@...@]
Markup('[=','_begin','/\\[([=@](.*?)\\1\\]/se',
    "Keep(\$K0['$1'].PSS('$2').\$K1['$1'])");

## remove carriage returns before preserving text
Markup('\\r','<[=','/\\r/','');

# ${var} substitutions
Markup('${fmt}','>[=','/{\\$(Group|Name)}/e"] =
  "FmtPageName('$$1',\$pagename)");
Markup('${var}','>${fmt}',
  '/{\\$(Version|Author|LastModified|LastModifiedBy|LastModifiedHost)}/e',
  "\$GLOBALS['$1']");

## [:if:]
Markup('if','fulltext',"\\[:if[^\n]*?):\\](.*?)(?=\\[:if[^\n]*:\\]|$)/se",
  "CondText(\$pagename,PSS('$1'),PSS('$2'))";

## [:include:]
Markup('include','>if',"\\[:include\\s+.+?):\\]/e",
  "PRR().IncludeText(\$pagename,'$1')";

## GroupHeader/GroupFooter handling
Markup('nogroupheader','>include','/\\[:nogroupheader:\\]/e',
  "PZZ(\$GLOBALS['GroupHeaderFmt']='')";
Markup('nogroupheader','>include','/\\[:nogroupfooter:\\]/e',
  "PZZ(\$GLOBALS['GroupFooterFmt']='')";
Markup('groupheader','>nogroupheader','/\\[:groupheader:\\]/e',
  "PRR().FmtPageName(\$GLOBALS['GroupHeaderFmt'],\$pagename)";
Markup('groupfooter','>nogroupfooter','/\\[:groupfooter:\\]/e',
  "PRR().FmtPageName(\$GLOBALS['GroupFooterFmt'],\$pagename)";

## [:nl:]
Markup('nl0','<split',"/(?!\n)\\[:nl:\\](?!\n)/"],"\n");
Markup('nl1','>nl0',"/\\[:nl:\\]/",'');

## \\$  (end of line joins)
Markup('\\$','>nl1',"/(\\\\*)\\\\\n/e",
  "Keep(' '.str_repeat('<br />',strlen('$1')))");

## [:noheader:],[:nofooter:],[:notitle:]...
Markup('noheader','directives','/\\[:noheader:\\]/e',
  "PZZ(\$GLOBALS['PageHeaderFmt']='')");
Markup('nofooter','directives','/\\[:noheader:\\]/e',
  "PZZ(\$GLOBALS['PageFooterFmt']='')");
Markup('notitle','directives','/\\[:notitle:\\]/e',
  "PZZ(\$GLOBALS['PageTitleFmt']='')");

## [:title:]
Markup('title','directives','//[:title\\s(.*?):\\]/e'] =
  "PZZ(\$GLOBALS['PageTitle']=PSS('$1'))";

## [:comment:]
Markup('comment','directives','/\\[:comment .*?:\\]/','');

?>
