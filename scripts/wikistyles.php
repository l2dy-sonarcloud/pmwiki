<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script predefines WikiStyles for use in PmWiki pages.  The
    script is automatically included by the stdconfig.php script unless
    disabled by
        $EnableStdWikiStyles = 0;
*/

# the [:css:] directive allows authors to define new CSS classes
$MarkupPatterns[3600]['/\\[:css\\s+(\\.\\w[-\\w]*\\s*\\{[^}]*})\\s*:\\]/e'] =
  "PZZ(\$GLOBALS['HTMLStylesFmt'][]=PSS('$1\n'))";
?>
