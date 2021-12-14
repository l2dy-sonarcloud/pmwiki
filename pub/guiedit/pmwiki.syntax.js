/*  PmSyntax: Syntax highlighting for PmWiki markup
    Copyright 2021 Petko Yotov https://www.pmwiki.org/support
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This file provides Javascript functions for syntax highlighting of 
    PmWiki markup, to be used in the PmWiki documentation, and optionally 
    in the edit form.
*/

(function(){
  var KeepToken = "\034\034", KPV = [];
  var restoreRX = new RegExp(KeepToken+'(\\d+)'+KeepToken, 'g');
  var Kept = new RegExp('^' + KeepToken+'(\\d+)'+KeepToken + '$', '');
  
  var log = console.log;
  function PHSC(x) { return x.replace(/[&]/g, '&amp;').replace(/[<]/g, '&lt;').replace(/[>]/g, '&gt;'); }
  function Restore(all, n) { return KPV[parseInt(n)]; }
  function keep0(text) {
    if(text === '') return '';
    KPV.push(text.replace(restoreRX, Restore));
    return KeepToken+(KPV.length-1)+KeepToken;
  }
  function Keep(text, cname) {
    text = span(cname, text);
    return keep0(text);
  }
  
  function span(cname, text, escaped) {
    if(text==='') return '';
    if(!escaped) text = PHSC(text);
    return "<span class='pm"+cname.replace(/^\*/, 'tag ')
     .split(/ +/g).join(' pm')+"'>" + text + "</span>";
  }
  
  function Keep3(cname, tag1, plain, attrs, tag2) {
    var out = '';
    if(tag1) out += span('tag', tag1);
    if(plain) out += PHSC(plain);
    if(attrs) out += hattr(attrs);
    if(tag2) out += span('tag', tag2);
    if(!out) return '';
    else out = span(cname, out, true);
    return keep0(out);
  }
  
  function hattr(attr) {
    attr = PHSC(attr)
    .replace(/(['"])(.*?)\1/g, function(a){ return Keep(a, 'value'); })
    .replace(/((?:\$:?)?[-\w]+|^)([:=])(\S+)/g, function(a, attr, op, val){
      if(! val.match(Kept)) val = span('value', val, 1);
      if(attr) attr = span('attr', attr, 1);
      return attr + op + val;
    });
    return attr;
  }
  
  var hrx = [ // rule_name, [*=!]classname|function, regexp, [only_in_container_regexp]
    ['_start'],
    ['preserve', '=escaped', /^(\[[@=])(.*)([@=]\])$/s, /\[([@=]).*?\1\]/gs],
    ['joinline', '*bullet', /\\\n/, /([^\\])(\\\n)/g],
    
    // variables
    ['pagevar', 'var', /\{([-\w\/.]+|[*=<>])?\$[$:]?\w+\}/g],
    ['phpvar',  'var', /\$((Enable|Fmt|Upload)\w+|\w+(Fmt|Function|Patterns?|Dirs?|Url)|FarmD|pagename)\b/g],
    ['i18n', 'string', /\$\[.*?\]/g],

    // markup expressions
    ['mx', '!mx', /(\{\([-\w]+)(.*?)(\)\})/g], 
 
    // core meta directives
    ['comment', '=comment', /(\(:comment)(.*?)(:\))/gi],
    ['skin',  '*meta', /\(:no(left|right|(group)?(header|footer)|title|action) *:\)/gi ], 
    ['meta0', '*meta', /\(:(no)?((link|space)wikiwords|linebreaks|toc) *:\)/gi],
    ['meta1', '*meta', /\(:(else\d*|if\d*|if\d*end) *:\)/gi],
    ['meta2', '=meta',    /(\(:(?:title|description|keywords|(?:else\d*)?if\d*))(.*?)(:\))/ig],
    ['meta3', '!meta',    /(\(:(?:template\s+(?:!\s*)?\w+|redirect))(.*?)(:\))/g],
 
    // page text vars, can be empty or multiline
    ['ptv0', '*meta', /\(:[-\w]+ *: *:\)/g],
    ['ptv1', '=meta', /(\(:[\w-]+:)([^\)].*?)(:\))/gs],
    
    ['url', 'url', /((mailto|tel|geo|Attach|PmWiki|Cookbook|Skins|Path):|(?:http|ftp)s?:\/\/)[^\s<>"{}|\\\^`()[\]']*[^\s.,?!<>"{}|\^`()[\]'\\]/g], // before wikistyle
    
    // wikistyles
    ['ws0', '*meta', /%%|^>><</gm],
    ['ws1', '!meta', /(^>>\w[-\w]*)(.*?)(<<)/gm],
    ['ws2', '!meta', /(%(?:define|apply)=\w+)(.*?)(%)/gi],
    ['ws3', '!meta', /(%\w[-\w]*)(.*?)(%)/g],

    // directives, forms
    ['dir0', '*directive', /\(:[-\w]+ *:\)/g],
    ['dir1', '!directive', /(\(:(?:input\s+\w+|[-\w]+))(.*?)(:\))/g],
    
    ['link', 'punct', /(\[\[[\#!~]?|([#+]\s*)?\]\])/g], // link
    
    ['QA', '*heading', /^([QA]:|-{4,})/mg], //Q:/A:, horizontal rule
    ['prop', 'meta',   /^[A-Z][-_a-zA-Z0-9]*:/mgi], // property, or start of line PTV
    
    // list item, initial space, indent, linebreak; inline punctuation; entity
    ['bullet', '*bullet', /^(\s*([*#]+)\s*|-+[<>]\s*|[ \t]+)|\\+$/mg], 
    ['punct',  'punct',   /('[\^_+-]|[\^_+-]'|\{[+-]+|[+-]+\}|\[[+-]+|[+-]+\]|@@|'''''|'''|''|->|~~~~?)/g], 
    ['entity', 'string',  /[&]\#?\w+;/g],

    // simple tables
    ['tablecapt', '=tab', /^(\|\|!)(.+)(!\|\|)$/mg],
    ['tablerow',  '*tab', /(\|\|)+!?/g, /^\|\|.*\|\|.*$/mg],
    ['tableattr', '!tab', /^(\|\|)(.*)($)/mg],
    
    // wikitrails
    ['trail1', '=url', /(<<?\|)(.*?)(\|>>?)/g],
    ['trail2', '=url', /(\^\|)(.*?)(\|\^)/g],
    
    ['pipe', 'punct', /\|/g], // inline
    
    // may contain inline markup
    ['deflist', '=bullet', /^([:]+)(.*?)([:])/mg],
    ['heading', '=heading', /^(!{1,6})(.*)($)/mg],
    
    ['cleanup', PHSC, /[<>&]+/g],// raw HTML/XSS
    ['restore', Restore, restoreRX],
    ['_end']
  ];
  var custom_hrx = {}, sorted_hrx = [];
  for(var i=0; i<hrx.length; i++) custom_hrx[ hrx[i][0] ] = [];
  
  function PmHi1(text, rule){
    if(rule.length>2) {
      var last = rule[rule.length-1], rule2 = rule.slice(0,-1);
      return text.replace(last, function(a){
        return PmHi1(a, rule2);
      });
    }
    var r = rule[0], s = rule[1];
    if(typeof r == 'function') text = text.replace(s, r);
    else text = text.replace(s, function(a, a1, a2, a3){
      if(r.charAt(0)==='!') // tag with attributes
        return Keep3(r.substr(1), a1, '', a2, a3||'');
      if(r.charAt(0)==='=') // tag with plain text
        return Keep3(r.substr(1), a1, a2, '', a3||'');
      return Keep(a, r);
    });
    return text;
  }
  function PmHi(text){
    KPV = [];
    for(var i=0; i<sorted_hrx.length; i++) {
      var rule = sorted_hrx[i];
      if(rule.length<2)  continue; // _start, _end
      text = PmHi1(text, rule);
    }
    return text;
  }
  function PmHiEl(el){
    el.innerHTML = PmHi(el.textContent);
    el.classList.add('pmhlt');
  }

  function PmHiAll(){
    var pm = document.querySelectorAll('table.markup td.markup1 > pre, '
      + '.hlt.pmwiki pre, .hlt.pmwiki + pre, .pmhlt pre, .pmhlt + pre, .pmhlt code');
    for(var j=0; j<pm.length; j++) {
      PmHiEl(pm[j]);
    }
  }
  function str2rx(str) {
    if(typeof str != 'string') return str; // assume regexp
    var a = str.match(/^\/(.*)\/([gimsyu]*)$/);
    if(!a) return false;
    return new RegExp(a[1], a[2]);
  }
  
  var _script;
  function sortRX(){
    _script = document.querySelector('script[src*="pmwiki.syntax.js"]');
    var cm = (window.PmSyntaxCustomMarkup)? window.PmSyntaxCustomMarkup : [];
    
    var custom = _script.dataset.custom;
    if(custom) {
      try {
        var list = JSON.parse(_script.dataset.custom);
        for(var i=0; i<list.length; i++) {
          var rule = list[i];
          for(var j=2; j<rule.length; j++) rule[j] = str2rx(rule[j]);
          cm.push(rule);
        }
      }
      catch(e) { }
    }
    for(var i=0; i<cm.length; i++) {
      var key = cm[i].shift();
      if(custom_hrx.hasOwnProperty(key)) custom_hrx[key].push(cm[i]);
    }
    sorted_hrx = [];
    for(var i=0; i<hrx.length; i++) {
      let key = hrx[i].shift();
      for(var j=0; j<custom_hrx[key].length; j++) {
        sorted_hrx.push(custom_hrx[key][j]);
      }
      sorted_hrx.push(hrx[i]);
    }
  }

  function initEditForm(){
    if(!_script || _script.dataset.mode != "2") return;
    var text = document.querySelector('#wikiedit textarea#text');
    if(!text) return;
    
    var lastTextContent = false;
    var GUIEditInterval = false;
    var resizeObserver;
    
    function updatePre() {
      if(! chk_hlt.checked) return;
      var tc = text.value;
      if(tc===lastTextContent) return;
      
      htext.innerHTML = PmHi(tc+'\n');
      lastTextContent = tc;
    }
    function textScrolled() {
      if(! chk_hlt.checked) return;
      if(ignoreTextScrolled) return;

      if(ignorePreScrolled) clearTimeout(ignorePreScrolled-1);
      ignorePreScrolled = 1 + setTimeout(nullIPS, 100);
      htext.scrollTop = text.scrollTop;
      htext.scrollLeft = text.scrollLeft;
    }
    var ignoreTextScrolled = false, ignorePreScrolled = false;
    function preScrolled() { // browser's in-page search
      if(! chk_hlt.checked) return;
      if(ignorePreScrolled) return;
      if(ignoreTextScrolled) clearTimeout(ignoreTextScrolled-1);
      ignoreTextScrolled = 1 + setTimeout(nullITS, 100);
      text.scrollTop = htext.scrollTop;
      text.scrollLeft = htext.scrollLeft;
    }
    function nullITS(){ignoreTextScrolled = false;}
    function nullIPS(){ignorePreScrolled = false;}

    function resizePre() {
      if(! chk_hlt.checked) return;
      var rect = text.getBoundingClientRect();
      htext.style.width = rect.width + 'px';
      htext.style.height = rect.height + 'px';
      textScrolled();
    }

    function initPre() {
      text.insertAdjacentHTML('beforebegin', '<div id="hwrap"><div id="htext" class="pmhlt"></div></div>');
      updatePre();
      resizePre();
      
      htext.inert = true;
      htext.addEventListener('scroll', preScrolled);
      text.addEventListener('scroll', textScrolled);
      text.addEventListener('input', updatePre);
      GUIEditInterval = setInterval(updatePre, 100); // for GUIEdit buttons

      resizeObserver = new ResizeObserver(resizePre)
      resizeObserver.observe(text);
    }

    function EnableHighlight() {
      chk_hlt_wrap.dataset.checked = chk_hlt.checked;
      if(chk_hlt.checked) {
        localStorage.setItem('EnableHighlight', 1);
        updatePre();
        resizePre();
      }
      else {
        lastTextContent = false;
        localStorage.removeItem('EnableHighlight');
      }
    }

    function initCheckbox(){
      var form = text.closest('form');
      form.insertAdjacentHTML('afterbegin', '<span id="chk_hlt_wrap">'
        +'<input type="checkbox" name="chk_hlt" id="chk_hlt"/><label for="chk_hlt"> '
        + _script.dataset.label +'</label></span>');

      initPre();
      var enabled = localStorage.getItem('EnableHighlight');
      if(enabled) {
        chk_hlt.checked = true;
        EnableHighlight();
      }
      chk_hlt.addEventListener('change', EnableHighlight);
    }
    initCheckbox();
  }

  document.addEventListener('DOMContentLoaded', function(){
    sortRX();
    PmHiAll();
    initEditForm();
  });
})();



