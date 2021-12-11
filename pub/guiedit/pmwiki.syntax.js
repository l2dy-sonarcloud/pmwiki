/*
  EditHighlight: Syntax highlighting for the PmWiki edit form
  Author (c) 2021 Petko Yotov https://www.pmwiki.org/support
  License: GNU GPLv2 or any more recent version released by the FSF
  Version: 20211211
*/

(function(){
  var KeepToken = "\034\034", KPV = [];
  var restoreRX = new RegExp(KeepToken+'(\\d+)'+KeepToken, 'g');
  
  function PHSC(x) { return x.replace(/[&]/g, '&amp;').replace(/[<]/g, '&lt;').replace(/[>]/g, '&gt;'); }
  function Restore(all, n) { return KPV[parseInt(n)]; }
  function Keep(text, cname){
    text = PHSC(text).replace(restoreRX, Restore);
    if(cname) text = "<span class='pm"+cname+"'>" + text + "</span>";
    KPV.push(text);
    return KeepToken+(KPV.length-1)+KeepToken;
  }
  
  function hattr(attr) {
    attr = attr.replace(/(['"])(.*?)\1/g, function(a){ return Keep(a, 'value'); });
    
    attr = attr.replace(/((?:\$:?)?[-\w]+)([:=])(\S+)/g, function(a, attr, op, val){
      return Keep(Keep(attr, 'attr') + op + Keep(val, 'value'), '');
    });
    attr = attr.replace(/\S+/g, function(a){ 
      if(a.indexOf(KeepToken)>=0) return a;
      return Keep(a, 'plain');
    });
    return attr;
  }
  
  function hheading(a, a1, a2){
    return Keep(a1, 'heading') + Keep(a2, 'hline');
  }
  
  function hmeta(a, a1, a2, a3){
    return Keep(a1 + Keep(a2, 'plain') + a3, 'meta');// not hattr
  }
  function hmetaattr(a, a1, a2, a3){
    return Keep(a1 + hattr(a2) + a3, 'meta');
  }
  function hdir(a, a1, a2, a3){
    return Keep(a1 + hattr(a2) + a3, 'directive');
  }
  function hmx(a, a1, a2, a3){
    return Keep(a1 + hattr(a2) + a3, 'mx');
  }
  
  function htab(a){
    if(a.substr(2).indexOf('||') == -1) return Keep(a.substr(0, 2) + hattr(a.substr(2)), 'tab'); // first line
    if(a.match(/^\|\|!.*!\|\|$/)) return Keep(a, 'tab'); // caption
    return a.replace(/(\|\|)+!?/g, function(b){return Keep(b, 'tab');}); // cells
  }
  
  var hrx = [ // find a way to add custom patterns
    [/\[([@=])(?:.|\n)*?\1\]/g, 'escaped'],
    [/([^\\])(\\\n)/g, function(a, a1, a2){ return a1 + Keep(a2, 'bullet'); }],
    [/\\+$/m, 'bullet'],
    [/\(:comment.*?:\)/gi, 'comment'],
    
    [/\{([-\w\/.]+|[*=])?\$[$:]?\w+\}/g, 'var'],
    [/\$\[.*?\]/g, 'string'], // i18n
    [/\$((Enable|Fmt|Upload)\w+|\w+(Fmt|Function|Patterns?|Dirs?|Url)|FarmD|pagename)\b/g, 'var'],
    
    [/\(:(else\d*|if\d*|if\d*end):\)/gi, 'meta'], // empty conditional
    [/(\(:(?:title|description|keywords|redirect|(?:else)?if\d*))(.*?)(:\))/ig, hmeta],

    [/\(:[-\w]+ *:\s*:\)/ig, 'meta'], // empty ptv

    
    [/\(:no(left|right|(group)?header|(group)?footer|title|action):\)/gi, 'meta' ], // core meta
    [/\(:(no)?(linkwikiwords|spacewikiwords|linebreaks):\)/gi, 'meta'], // core meta
    [/(\(:[\w-]+:)([^\)](?:.|\n)*?)(:\))/g, hmeta], // PTV, can be multiline
    
    [/[&]\#?\w+;/g, 'string'], // entity
    
    [/(\{\([-\w]+)(.*?)(\)\})/g, hmx], // markup expressions
      
    [/%(\w[-\w]+)?%|^>>(\w[-\w]+)?<</gim, 'meta'], // short wikistyle
    [/(^>>[-\w]+)(.*?)(<<)/gim, hmetaattr], // wikistyle
    [/(%(?:define|apply)=\w+)(.*?)(%)/gi, hmetaattr], // wikistyle
    [/(%[-\w]+)(.*?)(%)/gi, hmetaattr], // wikistyle

    [/(\(:template\s+(?:!\s*)?\w+)(.*?)(:\))/g, hmetaattr], // templates
    [/(\(:input\s+\w+)(.*?)(:\))/g, hdir], // forms
    [/(\(:[-\w]+)(.*?)(:\))/g, hdir], // other directives
    
    [/^\|\|.*$/mg, htab], // simple tables
    
    [/(\[\[[\#!~]?|([#+]\s*)?\]\])/g, 'punctuation'], // link
    [/((mailto|tel|Attach|PmWiki|Cookbook|Path):|(?:http|ftp)s?:\/\/)[^\s<>"{}|\\\^`()[\]']*[^\s.,?!<>"{}|\^`()[\]'\\]/g, 'link'],

    [/^([QA]:|-{4,})/mg, 'heading'], //Q:/A:, horizontal rule
    [/^[A-Z][_a-zA-Z0-9]*:/mgi, 'meta'], // property, or start of line PTV
    

    [/^(\s*([*#]+)\s*|-+[<>]\s*|[ \t]+)/mg, 'bullet'], // list item, initial space
    [/^:.*?:/mg, 'bullet'], // definition term / PTV
    [/^(!{1,6})(.*)$/mg, hheading], // heading

    [/('[\^_+-]|[\^_+-]'|\{[+-]+|[+-]+\}|\[[+-]+|[+-]+\]|@@|'''''|'''|''|\||->|~~~~?)/g, 'punctuation'], // inline
    [/[<>&]+/g, ''],// just escape entities

    [restoreRX, Restore]
  ];
  
  function PmHi(text){
    KPV = [];
    for(var i=0; i<hrx.length; i++) {
      let s = hrx[i][0], r = hrx[i][1];
      if(typeof r == 'function') text = text.replace(s, r);
      else text = text.replace(s, function(a){return Keep(a, r)});
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
    var script = document.querySelector('script[src*="pmwiki.syntax.js"]');
    if(!script || script.dataset.mode != "2") return;
    var text = document.querySelector('#wikiedit textarea#text');
    if(!text) {return;}
    
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
      text.insertAdjacentHTML('beforebegin', '<div id="hwrap"><pre id="htext" class="pmhlt"></pre></div>');
      updatePre();
      resizePre();

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
        +'<input type="checkbox" name="chk_hlt" id="chk_hlt"/><label for="chk_hlt"> Highlight</label></span>');

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
    PmHiAll();
    initEditForm();
  });
})();



