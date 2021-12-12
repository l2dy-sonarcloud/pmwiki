/*
  EditHighlight: Syntax highlighting for the PmWiki edit form
  Author (c) 2021 Petko Yotov https://www.pmwiki.org/support
  License: GNU GPLv2 or any more recent version released by the FSF
  Version: 20211211
*/

(function(){
  var KeepToken = "\034\034", KPV = [];
  var restoreRX = new RegExp(KeepToken+'(\\d+)'+KeepToken, 'g');
  var restoreRXV = new RegExp('^' + KeepToken+'(\\d+)'+KeepToken + '$', '');
  
  function PHSC(x) { return x.replace(/[&]/g, '&amp;').replace(/[<]/g, '&lt;').replace(/[>]/g, '&gt;'); }
  function Restore(all, n) { return KPV[parseInt(n)]; }
  function Keep(text, cname) {
    text = PHSC(text).replace(restoreRX, Restore);
    if(cname) text = "<span class='pm"+cname.split(/ +/g).join(' pm')+"'>" + text + "</span>";
    KPV.push(text);
    return KeepToken+(KPV.length-1)+KeepToken;
  }
  
  function Keep3(cname, tag1, plain, attrs, tag2) {
    var out = '<span class="pm'+cname+'">';
    if(tag1) out += '<span class="pmtag">'+PHSC(tag1)+'</span>';
    if(plain) out += PHSC(plain);
    if(attrs) out += hattr(attrs);
    if(tag2) out += '<span class="pmtag">'+PHSC(tag2)+'</span>';
    
    out += '</span>';
    
    KPV.push(out.replace(restoreRX, Restore));
    return KeepToken+(KPV.length-1)+KeepToken;
  }
  
  function hattr(attr) {
    attr = PHSC(attr);
    attr = attr.replace(/(['"])(.*?)\1/g, function(a){ return Keep(a, 'value'); });
    
    attr = attr.replace(/((?:\$:?)?[-\w]+)([:=])(\S+)/g, function(a, attr, op, val){
      if(! val.match(restoreRXV)) val = '<span class="pmvalue">' + val + "</span>";
      return '<span class="pmattr">' + attr + "</span>" + op + val;
    });
    
    return attr;
  }
  
  function hesc(a, t1, sigil, content, t2){
    return Keep3('escaped', t1, content, '', t2);
  }
  function joinlines(a, a1, a2) {
    return a1 + Keep(a2, 'bullet');
  }
  function hmeta(a, a1, a2, a3){
    return Keep3('meta', a1, a2, '', a3);
  }
  function hmetaattr(a, a1, a2, a3){
    return Keep3('meta', a1, '', a2, a3);
  }
  function hdir(a, a1, a2, a3){
    return Keep3('directive', a1, '', a2, a3);
  }
  function hmx(a, a1, a2, a3){
    return Keep3('mx', a1, '', a2, a3);
  }
  function hheading(a, a1, a2){
    return Keep(a1, 'heading') + Keep(a2, 'hline');
  }
  
  function htab(a){
    if(a.substr(2).indexOf('||') == -1) return Keep3('tab', a.substr(0, 2), '', a.substr(2)); // first line
    var b = a.match(/^(\|\|!)(.*)(!\|\|)$/); // caption
    if(b) return Keep3('tab', b[1], b[2], '', b[3]);
    return a.replace(/(\|\|)+!?/g, function(c){return Keep(c, 'tab tag');}); // cells
  }
  
  var hrx = [ // find a way to add custom patterns
    [10, /(\[([@=]))((?:.|\n)*?)(\2\])/g, hesc],
    [20, /([^\\])(\\\n)/g, joinlines],
    [30, /\\+$/m, 'bullet'],
    [40, /\(:comment.*?:\)/gi, 'comment'],
    
    [50, /\{([-\w\/.]+|[*=])?\$[$:]?\w+\}/g, 'var'],
    [60, /\$\[.*?\]/g, 'string'], // i18n
    [70, /\$((Enable|Fmt|Upload)\w+|\w+(Fmt|Function|Patterns?|Dirs?|Url)|FarmD|pagename)\b/g, 'var'],
    
    [80, /\(:(else\d*|if\d*|if\d*end):\)/gi, 'meta tag'], // empty conditional
    [90, /(\(:(?:title|description|keywords|redirect|(?:else)?if\d*))(.*?)(:\))/ig, hmeta],

    [100, /\(:[-\w]+ *:\s*:\)/ig, 'meta tag'], // empty ptv

    
    [110, /\(:no(left|right|(group)?header|(group)?footer|title|action):\)/gi, 'meta tag' ], // core meta
    [120, /\(:(no)?(linkwikiwords|spacewikiwords|linebreaks):\)/gi, 'meta tag'], // core meta
    [130, /(\(:[\w-]+:)([^\)](?:.|\n)*?)(:\))/g, hmeta], // PTV, can be multiline
    
    [140, /[&]\#?\w+;/g, 'string'], // entity
    
    [150, /(\{\([-\w]+)(.*?)(\)\})/g, hmx], // markup expressions
      
    [160, /%(\w[-\w]+)?%|^>>(\w[-\w]+)?<</gim, 'meta tag'], // short wikistyle
    [170, /(^>>[-\w]+)(.*?)(<<)/gim, hmetaattr], // wikistyle
    [180, /(%(?:define|apply)=\w+)(.*?)(%)/gi, hmetaattr], // wikistyle
    [190, /(%[-\w]+)(.*?)(%)/gi, hmetaattr], // wikistyle

    [200, /(\(:template\s+(?:!\s*)?\w+)(.*?)(:\))/g, hmetaattr], // templates
    [210, /(\(:input\s+\w+)(.*?)(:\))/g, hdir], // forms
    [220, /(\(:[-\w]+)(.*?)(:\))/g, hdir], // other directives
    
    [230, /^\|\|.*$/mg, htab], // simple tables
    
    [240, /(\[\[[\#!~]?|([#+]\s*)?\]\])/g, 'punctuation'], // link
    [250, /((mailto|tel|Attach|PmWiki|Cookbook|Path):|(?:http|ftp)s?:\/\/)[^\s<>"{}|\\\^`()[\]']*[^\s.,?!<>"{}|\^`()[\]'\\]/g, 'link'],

    [260, /^([QA]:|-{4,})/mg, 'heading tag'], //Q:/A:, horizontal rule
    [270, /^[A-Z][_a-zA-Z0-9]*:/mgi, 'meta'], // property, or start of line PTV
    

    [280, /^(\s*([*#]+)\s*|-+[<>]\s*|[ \t]+)/mg, 'bullet'], // list item, initial space
    [290, /^:.*?:/mg, 'bullet'], // definition term / PTV
    [300, /^(!{1,6})(.*)$/mg, hheading], // heading

    [310, /('[\^_+-]|[\^_+-]'|\{[+-]+|[+-]+\}|\[[+-]+|[+-]+\]|@@|'''''|'''|''|\||->|~~~~?)/g, 'punctuation'], // inline

    [800, /[<>&]+/g, PHSC],// escape entities
    [900, restoreRX, Restore]
  ];
  var sorted_hrx = [];
  
  function PmHi(text){
    KPV = [];
    for(var i=0; i<sorted_hrx.length; i++) {
      let s = sorted_hrx[i][1], r = sorted_hrx[i][2];
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
        + script.dataset.label +'</label></span>');

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
    if(window.PmSyntaxCustomMarkup) {
      for(var i=0; i<window.PmSyntaxCustomMarkup.length; i++) {
        hrx.push(window.PmSyntaxCustomMarkup[i]);
      }
    }
    sorted_hrx = hrx.sort(function(a, b){return a[0]-b[0];});
    console.log(sorted_hrx);
    PmHiAll();
    initEditForm();
  });
})();



