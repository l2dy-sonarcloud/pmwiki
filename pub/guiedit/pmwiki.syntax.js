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
  var restoreRXV = new RegExp('^' + KeepToken+'(\\d+)'+KeepToken + '$', '');
  
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
    return "<span class='pm"+cname.split(/ +/g).join(' pm')+"'>" + text + "</span>";
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
      if(! val.match(restoreRXV)) val = span('value', val, 1);
      if(attr) attr = span('attr', attr, 1);
      return attr + op + val;
    });
    return attr;
  }
  
  var hrx = [
    ['_start', '', ''],
    ['preserve', [/\[([@=])(?:.|\n)*?\1\]/g, /^(\[[@=])((?:.|\n)*)([@=]\])$/], '=escaped'],
    ['joinline', [/([^\\])(\\\n)/g, /\\\n/], 'bullet tag'],
    
    // variables
    ['pagevar', /\{([-\w\/.]+|[*=<>])?\$[$:]?\w+\}/g, 'var'],
    ['phpvar', /\$((Enable|Fmt|Upload)\w+|\w+(Fmt|Function|Patterns?|Dirs?|Url)|FarmD|pagename)\b/g, 'var'],
    ['i18n', /\$\[.*?\]/g, 'string'],

    // markup expressions
    ['mx', /(\{\([-\w]+)(.*?)(\)\})/g, '!mx'], 
 
    // core meta directives
    ['comment', /(\(:comment)(.*?)(:\))/gi, '=comment'],
    ['cond0', /\(:(else\d*|if\d*|if\d*end) *:\)/gi, 'meta tag'], // empty conditional
    ['skin', /\(:no(left|right|(group)?(header|footer)|title|action) *:\)/gi, 'meta tag' ], 
    ['meta0', /\(:(no)?((link|space)wikiwords|linebreaks) *:\)/gi, 'meta tag'],
    ['meta', /(\(:(?:title|description|keywords|(?:else\d*)?if\d*))(.*?)(:\))/ig, '=meta'],
    ['meta1', /(\(:(?:template\s+(?:!\s*)?\w+|redirect))(.*?)(:\))/g, '!meta'],
 

    // page text vars, can be multiline
    ['ptv0', /\(:[-\w]+ *: *:\)/g, 'meta tag'],
    ['ptv', /(\(:[\w-]+:)([^\)](?:.|\n)*?)(:\))/g, '=meta'],
    
    
    ['url', /((mailto|tel|geo|Attach|PmWiki|Cookbook|Skins|Path):|(?:http|ftp)s?:\/\/)[^\s<>"{}|\\\^`()[\]']*[^\s.,?!<>"{}|\^`()[\]'\\]/g, 'url'], // before wikistyle
    
    // wikistyles
    ['ws0', /%%|^>><</gm, 'meta tag'],
    ['ws1', /(^>>[-\w]+)(.*?)(<<)/gm, '!meta'],
    ['ws2', /(%(?:define|apply)=\w+)(.*?)(%)/gi, '!meta'],
    ['ws3', /(%\w[-\w]*)(.*?)(%)/g, '!meta'],

    // directives, forms
    ['dir0', /\(:[-\w]+ *:\)/g, 'directive tag'],
    ['dir', /(\(:(?:input\s+\w+|[-\w]+))(.*?)(:\))/g, '!directive'],
    
    ['link', /(\[\[[\#!~]?|([#+]\s*)?\]\])/g, 'punctuation'], // link
    
    ['QA', /^([QA]:|-{4,})/mg, 'heading tag'], //Q:/A:, horizontal rule
    ['var1', /^[A-Z][-_a-zA-Z0-9]*:/mgi, 'meta'], // property, or start of line PTV
    
    ['bullet', /^(\s*([*#]+)\s*|-+[<>]\s*|[ \t]+)/mg, 'bullet tag'], // list item, initial space, indent
    
    ['punct', /('[\^_+-]|[\^_+-]'|\{[+-]+|[+-]+\}|\[[+-]+|[+-]+\]|@@|'''''|'''|''|->|~~~~?)/g, 'punctuation'], // inline
    ['linebreak', /\\+$/gm, 'bullet tag'],
    ['entity', /[&]\#?\w+;/g, 'string'], // entity

    // simple tables
    ['tablecaption', /^(\|\|!)(.+)(!\|\|)$/mg, '=tab'],
    ['tablerow', [/^\|\|.*\|\|/mg, /(\|\|)+!?/g], 'tab tag'],
    ['tableattr', /^(\|\|)(.*)($)/mg, '!tab'],
    
    // wikitrails
    ['trail1', /(<<?\|)(.*?)(\|>>?)/g, '=meta'],
    ['trail2', /(\^\|)(.*?)(\|\^)/g, '=meta'],
    
    ['pipe', /\|/g, 'punctuation'], // inline
    
    // may contain inline markup
    ['dlist', /^([:]+)(.*?)([:])/mg, '=bullet'],
    ['heading', /^(!{1,6})(.*)($)/mg, '=heading'],
    
    ['cleanup', /[<>&]+/g, PHSC],// raw HTML/XSS
    ['restore', restoreRX, Restore],
    ['_end', '', '']
  ];
  var custom_hrx = {}, sorted_hrx = [];
  for(var i=0; i<hrx.length; i++) custom_hrx[ hrx[i][0] ] = [];
  
  function PmHi1(text, s, r){
    if(Array.isArray(s)) {
      text = text.replace(s[0], function(a){
        return PmHi1(a, s[1], r);
      });
    }
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
      let s = sorted_hrx[i][0], r = sorted_hrx[i][1];
      if(!s) continue; // _start, _end
      text = PmHi1(text, s, r);
    }
    return text;
  }
  function PmHiEl(el){
    el.innerHTML = PmHi(el.textContent);
    el.classList.add('pmhlt');
  }

  function PmHiAll(){
    var pm = document.querySelectorAll('table.markup td.markup1 > pre, .hlt.pmwiki pre, .hlt.pmwiki + pre, .pmhlt pre, .pmhlt + pre, .pmhlt code');
    for(var j=0; j<pm.length; j++) {
      PmHiEl(pm[j]);
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
  var _script;
  document.addEventListener('DOMContentLoaded', function(){
    _script = document.querySelector('script[src*="pmwiki.syntax.js"]');
    var cm = (window.PmSyntaxCustomMarkup)? window.PmSyntaxCustomMarkup : [];
    
    var custom = _script.dataset.custom;
    if(custom) {
      try {
        var list = JSON.parse(_script.dataset.custom);
        for(var i=0; i<list.length; i++) 
          cm.push(list[i][0], new RegExp(list[i][1], list[i][2]), list[i][3]);
      }
      catch(e) { }
    }
    for(var i=0; i<cm.length; i++) {
      var key = cm[i].shift();
      if(custom_hrx.hasOwnProperty(key)) custom_hrx[key].push(cm[i]);
      else {
        hrx.push([key, cm[i][0], cm[i][1]]);
        custom_hrx[key] = [];
      }
    }
    sorted_hrx = [];
    for(var i=0; i<hrx.length; i++) {
      let key = hrx[i].shift();
      for(var j=0; j<custom_hrx[key].length; j++) {
        sorted_hrx.push(custom_hrx[key][j]);
      } 
      sorted_hrx.push(hrx[i]);
    }    
    PmHiAll();
    initEditForm();
  });
})();



