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
Markup('.class','directive',
  '/\\[:css\\s+(\\.\\w[-\\w]*\\s*\\{[^}]*})\\s*:\\]/e',
  "PZZ(\$GLOBALS['HTMLStylesFmt'][]=PSS('$1\n'))");

# allow other CSS properties in WikiStyles
$WikiStyleCSSPatterns[] = 'display|margin[-\\w]*|border[-\\w]*';

# the 'block' style is a shortcut for apply=block
SDV($WikiStyle['block'],array('apply'=>'block'));

# some styles for justifying text
foreach(array('center','left','right') as $k)
  SDV($WikiStyle[$k],array('text-align'=>$k, 'align'=>'block'));

# the 'newwin' style causes links to open in a new window
SDV($WikiStyle['newwin'],array('target' => '_blank'));

# the 'comment' style turns text into a comment via display:none; property
$WikiStyleCSSPatterns[] = 'display';
SDV($WikiStyle['comment'],array('display'=>'none'));

# define standard color text styles from CSS color names
foreach(array('black','white','red','yellow','blue','gray',
  'silver','maroon','green', 'navy', 'purple') as $c)
    SDV($WikiStyle[$c],array('color'=>$c));

# example of defining a color from a #rrggbb spec
SDV($WikiStyle['darkgreen'],array('color'=>'#006400'));

?>
