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
  var special = /\$+:?(?!\))|[#!*?&+|,]+|\s-/g;
  var Kept = new RegExp('^' + KeepToken+'(\\d+)'+KeepToken + '$', '');
  
  var log = console.log;
  function aE(el, ev, fn) {
    if(typeof el == 'string') el = dqsa(el);
    for(var i=0; i<el.length; i++) el[i].addEventListener(ev, fn);
  }
  function dqs(str)  { return document.querySelector(str); }
  function dqsa(str) { return document.querySelectorAll(str); }
  function tap(q, fn) { aE(q, 'click', fn); };
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
     .split(/[ _]+/g).join(' pm')+"'>" + text + "</span>";
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
      return keep0(attr + op + val);
    })
    .replace(special, function(a){ return Keep(a, 'attr tag'); });
    return attr;
  }
  
  var hrx = [ // rule_name, [*=!]classname|function, [container_rx], rx
    ['_start'],
    ['preserve', '=escaped', /\[([@=]).*?\1\]/gs, /^(\[[@=])(.*)([@=]\])$/s],
    ['joinline', '*bullet', /([^\\])(\\\n)/g, /\\\n/],
    
    // variables
    ['pagevar', 'var', /\{([-\w\/.]+|[*=<>])?\$[$:]?\w+\}/g],
    ['pagevar2', 'string', /\{(\034\034\d+\034\034)\$[$:]?\w+\}/g],
    ['phpvar',  'var', /\$((Enable|Fmt|Upload)\w+|\w+(Fmt|Function|Patterns?|Dirs?|Url)|FarmD|pagename)\b/g],
    ['i18n', 'string', /\$\[.*?\]/g],

    // markup expressions
    ['mx', '!mx', /(\{\([-\w]+)(.*?)(\)\})/g], 
 
    // page text vars, can be empty or multiline
    ['ptv0', '*meta', /\(: *\w[-\w]* *: *:\)/g],
    ['ptv1', '=meta', /(\(: *\w[\w-]* *:)([^\)].*?)(:\))/gs],
    
    // core meta directives
    ['comment', '=comment', /(\(:comment)(.*?)(:\))/gi],
    ['skin',  '*meta', /\(:no(left|right|title|action|(group)?(header|footer)) *:\)/gi ], 
    ['meta0', '*meta', /\(:(no)?((link|space)wikiwords|linebreaks|toc) *:\)/gi],
    ['meta1', '*meta', /\(:(else\d*|if\d*|if\d*end|nl) *:\)/gi],
    ['meta2', '=meta', /(\(:(?:title|description|keywords))(.*?)(:\))/ig],
    ['meta3', '=meta>*attr', /(\(:(?:(?:else\d*)?if\d*))(.*?)(:\))/ig, special],
    ['meta4', '!meta', /(\(:(?:template\s[ !]*\w+|redirect))(.*?)(:\))/g],

    // urls can have percents so before wikistyle (populated by InterMap)
    ['_url'],
    
    // wikistyles
    ['ws0', '*meta', /%%|^>><</gm],
    ['ws1', '!meta', /(^>>\w[-\w]*)(.*?)(<<)/gm],
    ['ws2', '!meta', /(%(?:define|apply)=\w+)(.*?)(%)/gi],
    ['ws3', '!meta', /(%\w[-\w]*)(.*?)(%)/g],

    // directives, forms
    ['dir0', '*directive', /\(: *\w[-\w]* *:\)/g],
    ['dir1', '!directive', /(\(: *(?:input\s+\w+|\w[-\w]*))(.*?)(:\))/g],
    
    // inline
    ['link', 'punct', /(\[\[[\#!~]?|([#+]\s*)?\]\])/g], // link
    ['bullet', '*bullet', /^(\s*([*#]+)\s*|-+[<>]\s*|[ \t]+)|\\+$/mg], 
    
    ['QA', '*heading', /^([QA]:|-{4,})/mg], //Q:/A:, horizontal rule
    ['prop', 'meta',   /^[A-Z][-_a-zA-Z0-9]*:/mgi], // property, or start of line PTV
    
    // list item, initial space, indent, linebreak; inline punctuation; entity
    ['punct',  'punct',   /('[\^_+-]|[\^_+-]'|\{[+-]+|[+-]+\}|\[[+-]+|[+-]+\]|@@|'''''|'''|''|->|~~~~?)/g], 
    ['entity', 'string',  /[&]\#?\w+;/g],

    // simple tables
    ['tablecapt', '=tab', /^(\|\|!)(.+)(!\|\|)$/mg],
    ['tablerow',  '!tab', /^\|\|.*\|\|.*$/mg, /((?:\|\|)+)(!?)()/g],
    ['tableattr', '!tab', /^(\|\|)(.*)($)/mg],
    
    // wikitrails
    ['trail1', '=url', /(<<?\|)(.*?)(\|>>?)/g],
    ['trail2', '=url', /(\^\|)(.*?)(\|\^)/g],
    
    ['pipe', 'punct', /\|/g], // inline, after trails
    
    // may contain inline markup
    ['deflist', '=bullet', /^([:]+)(.*?)([:])/mg],
    ['heading', '=heading', /^(!{1,6})(.*)($)/mg],
    
    ['cleanup', PHSC, /[<>&]+/g],// raw HTML/XSS
    ['restore', Restore, restoreRX],
    ['_end']
  ];
  var custom_hrx = {}, sorted_hrx = [];
  for(var i=0; i<hrx.length; i++) {
    custom_hrx[ hrx[i][0] ] = [];
    custom_hrx[ '>'+hrx[i][0] ] = [];
  }
  
  function PmHi1(text, rule){
    var r = rule[0], s = rule[1];
    if(!!rule[2]) {
      var m = (typeof r == 'function') ? false : r.split(/[>]/g);
      if(m && m.length>1) { // parent>nested
        r = m[0];
        text = text.replace(s, function(a){
          for(var i=1; i<m.length; i++) {
            if(rule[i+1]) a = PmHi1(a, [m[i], rule[i+1]]);
          }
          return a;
        })
        // NOT return
      }
      else { // one classname, return match only_in_container
        return text.replace(s, function(a){
          return PmHi1(a, [r, rule[2]]);
        });
      }
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
    var pm = dqsa('table.markup td.markup1 > pre, '
      + '.hlt.pmwiki pre, .hlt.pmwiki + pre, .pmhlt pre, .pmhlt + pre, .pmhlt code');
    for(var j=0; j<pm.length; j++) {
      PmHiEl(pm[j]);
    }
    if(pm.length) tap('.toggle-pmhlt', toggleStyles);
  }
  
  function toggleStyles(e) {
    e.preventDefault();
    var c1 = 'pmhlt', c2 = 'pmhlt-disabled';
    var x = dqsa('.'+c1);
    if(x.length==0) {
      x = dqsa('.'+c2);
      c2 = c1;
    }
    for(var i=0; i<x.length; i++) x[i].className = c2;
  }
  
  function str2rx(str) {
    if(typeof str.flags == 'string') return str; // regexp
    if(typeof str != 'string') {
      log("Not a string", str);
      return false;
    }
    var a = str.match(/^\/(.*)\/([gimsyu]*)$/);
    try {
      if(a) return new RegExp(a[1], a[2]); 
      return new RegExp(str, 'g');  
    }
    catch(e) {
      log('Could not create RegExp.', str);
    }
  }
  
  var _script;
  function sortRX(){
    _script = dqs('script[src*="pmwiki.syntax.js"]');
    var cm = (window.PmSyntaxCustomMarkup)? window.PmSyntaxCustomMarkup : [];
    var imaps =  [_script.dataset.imap];
    var custom = _script.dataset.custom;
    if(custom) {
      try {
        var list = JSON.parse(_script.dataset.custom);
      }
      catch(e) {
        log("Parsing custom rules failed.", _script.dataset.custom);
        var list = [];
      }
      
      for(var i=0; i<list.length; i++) {
        var rule = list[i];
        if(typeof rule == 'string') rule = rule.split(/\s+/g);
        if(rule[0]=='InterMap') {
          imaps.push(rule[1]);
          continue;
        }
        for(var j=2; j<rule.length; j++) rule[j] = str2rx(rule[j]);
        cm.push(rule);
      }
    }
    var uec = '<>"{}|\\\\^`()[\\]\'';
    cm.push(['>_url', 'url', new RegExp(
      '\\b(' +imaps.join('|')+ ')[^\\s'+uec+']*[^\\s.,?!'+uec+']', 'g'
    )]);
    for(var i=0; i<cm.length; i++) {
      var key = cm[i][0].replace(/^</, '');
      if(custom_hrx.hasOwnProperty(key)) custom_hrx[key].push(cm[i].slice(1));
      else log('No rule name to attach to.', cm[i]);
    }
    sorted_hrx = [];
    for(var i=0; i<hrx.length; i++) {
      var key = hrx[i][0];
      var keys = [key, '>'+key];
      for(var k=0; k<2; k++) {
        if(k) sorted_hrx.push(hrx[i].slice(1));
        var kk = keys[k];
        for(var j=0; j<custom_hrx[kk].length; j++) {
          sorted_hrx.push(custom_hrx[kk][j]);
        }
      }
    }
  }

  function initEditForm(){
    if(!_script || _script.dataset.mode != "2") return;
    var text = dqs('#wikiedit textarea#text');
    if(!text) return;
    
    var lastTextContent = false;
    var GUIEditInterval = false;
    var resizeObserver;
    
    function updatePre() {
      if(! chk_hlt.checked) return;
      var tc = text.value;
      if(tc===lastTextContent) return;
      
      var clone = htext.cloneNode(false);
      htext.parentNode.replaceChild(clone, htext);
      htext.innerHTML = PmHi(tc+'\n');
      htext.addEventListener('scroll', preScrolled);
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
      var w = Math.floor(rect.width) + 'px', h = Math.floor(rect.height) + 'px';
      text.style.width = w;
      text.style.height = h;
      htext.style.width = w;
      htext.style.height = h;
      textScrolled();
    }

    function initPre() {
      text.insertAdjacentHTML('beforebegin', '<div id="hwrap"><div id="htext" class="pmhlt"></div></div>');
      updatePre();
      resizePre();
      
      htext.inert = true;
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
        +'<input type="checkbox" name="chk_hlt" id="chk_hlt" /><label for="chk_hlt"> '
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



