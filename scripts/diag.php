<?php if (!defined('PmWiki')) exit();
/*  Copyright 2003-2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This file adds "?action=diag" and "?action=phpinfo" actions to PmWiki.  
    This produces lots of diagnostic output that may be helpful to the 
    software authors when debugging PmWiki or other scripts.
*/

if ($action=='diag') {
  header('Content-type: text/plain');
  print_r($GLOBALS);
  exit();
}

if ($action=='phpinfo') { phpinfo(); exit(); }

@session_start();
if (@$_REQUEST['redirect']) 
  $_SESSION['redirect'] = ($_REQUEST['redirect']!='n');
$EnableRedirect = @$_SESSION['redirect'];

?>
