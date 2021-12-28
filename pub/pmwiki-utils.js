/*
  JavaScript utilities for PmWiki
  (c) 2009-2021 Petko Yotov www.pmwiki.org/petko
  based on PmWiki addons DeObMail, AutoTOC and Ape
  licensed GNU GPLv2 or any more recent version released by the FSF.

  libsortable() "Sortable tables" adapted for PmWiki from
  a Public Domain event listener by github.com/tofsjonas
*/

(function(){
  function aE(el, ev, fn) {
    if(typeof el == 'string') el = dqsa(el);
    for(var i=0; i<el.length; i++) el[i].addEventListener(ev, fn);
  }
  function dqs(str)  { return document.querySelector(str); }
  function dqsa(str) { return document.querySelectorAll(str); }
  function tap(q, fn) { aE(q, 'click', fn); };
  function pf(x) {var y = parseFloat(x); return isNaN(y)? 0:y; }
  function zpad(n) {return (n<10)?"0"+n : n; }
  function adjbb(el, html) { el.insertAdjacentHTML('beforebegin', html); }
  function adjbe(el, html) { el.insertAdjacentHTML('beforeend', html); }
  function adjab(el, html) { el.insertAdjacentHTML('afterbegin', html); }
  function adjae(el, html) { el.insertAdjacentHTML('afterend', html); }
  function PHSC(x) { return x.replace(/[&]/g, '&amp;').replace(/[<]/g, '&lt;').replace(/[>]/g, '&gt;'); }

  var __script__ = dqs('script[src*="pmwiki-utils.js"]');
  var wikitext = document.getElementById('wikitext');
  var log = console.log;

  function PmXMail() {
    var els = document.querySelectorAll('span._pmXmail');
    var LinkFmt = '<a href="%u" class="mail">%t</a>';

    for(var i=0; i<els.length; i++) {
      var x = els[i].querySelector('span._t');
      var txt = cb_mail(x.innerHTML);
      var y = els[i].querySelector('span._m');
      var url = cb_mail(y.innerHTML.replace(/^ *-&gt; */, ''));

      if(!url) url = 'mailto:'+txt.replace(/^mailto:/, '');

      url = url.replace(/"/g, '%22').replace(/'/g, '%27');
      var html = LinkFmt.replace(/%u/g, url).replace(/%t/g, txt);
      els[i].innerHTML = html;
    }
  }
  function cb_mail(x){
    return x.replace( /<span class=(['"]?)_d\1>[^<]+<\/span>/ig, '.')
      .replace( /<span class=(['"]?)_a\1>[^<]+<\/span>/ig, '@');
  }

  function is_toc_heading(el) {
    if(el.offsetParent === null) {return false;}  // hidden
    if(el.closest('.notoc,.markup2')) {return false;} 
    return true;
  }
  function posy(el) {
    var top = 0;
    if (el.offsetParent) {
      do {
        top += el.offsetTop;
      } while (el = el.offsetParent);
    }
    return top;
  }

  function any_id(h) {
    if(h.id) {return h.id;} // %id=anchor%
    var a = h.querySelector('a[id]'); // inline [[#anchor]]
    if(a && a.id) {return a.id;}
    var prev = h.previousElementSibling;
    if(prev) { // [[#anchor]] before !!heading
      var a = prev.querySelectorAll('a[id]');
      if(a.length) {
        last = a[a.length-1];
        if(last.id && ! last.nextElementSibling) {
          var atop = posy(last) + last.offsetHeight;
          var htop = posy(h);
          if( Math.abs(htop-atop)<20 ) {
            h.appendChild(last);
            return last.id;
          }
        }
      }
    }
    return false;
  }
  function inittoggle() {
    var tnext = __script__.dataset.toggle;
    if(! tnext) { return; }
    var x = dqsa(tnext);
    if(! x.length) return;
    for(var i=0; i<x.length; i++) togglenext(x[i]);
    tap(tnext, togglenext);
    tap('.pmtoggleall', toggleall);
  }
  function togglenext(z) {
    var el = z.type == 'click' ? this : z;
    var attr = el.dataset.pmtoggle=='closed' ? 'open' : 'closed';
    el.dataset.pmtoggle = attr;
  }
  function toggleall(){
    var curr = this.dataset.pmtoggleall;
    if(!curr) curr = 'closed';
    var toggles = dqsa('*[data-pmtoggle="'+curr+'"]');
    var next = curr=='closed' ? 'open' : 'closed';
    for(var i=0; i<toggles.length; i++) {
      toggles[i].dataset.pmtoggle = next;
    }
    var all = dqsa('.pmtoggleall');
    for(var i=0; i<all.length; i++) {
      all[i].dataset.pmtoggleall = next;
    }
  }

  function autotoc() {
    if(dqs('.noPmTOC')) { return; } // (:notoc:) in page
    var dtoc = __script__.dataset.pmtoc;
    try {dtoc = JSON.parse(dtoc);} catch(e) {dtoc = false;}
    if(! dtoc) { return; } // error

    if(! dtoc.Enable || !dtoc.MaxLevel) { return; } // disabled

    if(dtoc.NumberedHeadings)  {
      var specs = dtoc.NumberedHeadings.toString().split(/\./g);
      for(var i=0; i<specs.length; i++) {
        if(specs[i].match(/^[1AI]$/i)) numheadspec[i] = specs[i];
      }
    }

    var query = [];
    for(var i=1; i<=dtoc.MaxLevel; i++) {
      query.push('h'+i);
    }
    if(dtoc.EnableQMarkup) query.push('p.question');
    var pageheadings = wikitext.querySelectorAll(query.join(','));
    if(!pageheadings.length) { return; }

    var toc_headings = [ ];
    var minlevel = 1000, hcache = [ ];
    for(var i=0; i<pageheadings.length; i++) {
      var h = pageheadings[i];
      if(! is_toc_heading(h)) {continue;}
      toc_headings.push(h);
    }
    if(! toc_headings.length) return;

    var tocdiv = dqs('.PmTOCdiv');
    var shouldmaketoc = ( tocdiv || (toc_headings.length >= dtoc.MinNumber && dtoc.MinNumber != -1)) ? 1:0;
    if(!dtoc.NumberedHeadings && !shouldmaketoc) return;

    for(var i=0; i<toc_headings.length; i++) {
      var h = toc_headings[i];
      var level = pf(h.tagName.substring(1));
      if(! level) level = 6;
      minlevel = Math.min(minlevel, level);
      var id = any_id(h);
      hcache.push([h, level, id]);
    }

    prevlevel = 0;
    var html = '';
    for(var i=0; i<hcache.length; i++) {
      var hc = hcache[i];
      var actual_level = hc[1] - minlevel;
//       if(actual_level>prevlevel+1) actual_level = prevlevel+1;
//       prevlevel = actual_level;

      var currnb = numberheadings(actual_level);
      if(! hc[2]) {
        hc[2] = 'toc-'+currnb.replace(/\.+$/g, '');
        hc[0].id = hc[2];
      }
      if(dtoc.NumberedHeadings && currnb.length) adjab(hc[0], currnb+' ');

      if(! shouldmaketoc) { continue; }
      var txt = hc[0].textContent.replace(/^\s+|\s+$/g, '').replace(/</g, '&lt;');
      var sectionedit = hc[0].querySelector('.sectionedit');
      if(sectionedit) {
        var selength = sectionedit.textContent.length;
        txt = txt.slice(0, -selength);
      }
      
      html += '&nbsp;'.repeat(3*actual_level)
        + '<a href="#'+hc[2]+'">' + txt + '</a><br>\n';
      if(dtoc.EnableBacklinks) adjbe(hc[0], ' <a class="back-arrow" href="#_toc">&uarr;</a>');
      
    }

    if(! shouldmaketoc) return;

    html = "<b>"+dtoc.contents+"</b> "
      +"[<input type='checkbox' id='PmTOCchk'><label for='PmTOCchk'>"
      +"<span class='pmtoc-show'>"+dtoc.show+"</span>"
      +"<span class='pmtoc-hide'>"+dtoc.hide+"</span></label>]"
      +"<div class='PmTOCtable'>" + html + "</div>";

    if(!tocdiv) {
      var wrap = "<div class='PmTOCdiv'></div>";
      if(dtoc.ParentElement && dqs(dtoc.ParentElement)) {
        adjab(dqs(dtoc.ParentElement), wrap);
      }
      else {
        adjbb(hcache[0][0], wrap);
      }
      tocdiv = dqs('.PmTOCdiv');

    }
    if(!tocdiv) return; // error?
    tocdiv.className += " frame";
    tocdiv.id = '_toc';

    tocdiv.innerHTML = html;

    if(window.localStorage.getItem('closeTOC')) { dqs('#PmTOCchk').checked = true; }
    aE('#PmTOCchk', 'change', function(e){
      window.localStorage.setItem('closeTOC', this.checked ? "close" : '');
    });

    var hh = location.hash;
    if(hh.length>1) {
      var cc = document.getElementById(hh.substring(1));
      if(cc) cc.scrollIntoView();
    }
  }

  var numhead = [0, 0, 0, 0, 0, 0, 0];
  var numheadspec = '1 1 1 1 1 1 1'.split(/ /g);
  function numhead_alpha(n, upper) {
    if(!n) return '_';
    var alpha = '', mod, start = upper=='A' ? 65 : 97;
    while (n>0) {
      mod = (n-1)%26;
      alpha = String.fromCharCode(start + mod) + '' + alpha;
      n = (n-mod)/26 | 0;
    }
    return alpha;
  }
  function numhead_roman(n, upper) {
    if(!n) return '_';
    // partially based on http://blog.stevenlevithan.com/?p=65#comment-16107
    var lst = [ [1000,'M'], [900,'CM'], [500,'D'], [400,'CD'], [100,'C'], [90,'XC'],
      [50,'L'], [40,'XL'], [10,'X'], [9,'IX'], [5,'V'], [4,'IV'], [1,'I'] ];
    var roman = '';
    for(var i=0; i<lst.length; i++) {
      while(n>=lst[i][0]) {
        roman += lst[i][1];
        n -= lst[i][0];
      }
    }
    return (upper == 'I') ? roman : roman.toLowerCase();
  }

  function numberheadings(n) {
    if(n<numhead[6]) for(var j=numhead[6]; j>n; j--) numhead[j]=0;
    numhead[6]=n;
    numhead[n]++;
    var qq = '';
    for (var j=0; j<=n; j++) {
      var curr = numhead[j];
      var currspec = numheadspec[j];
      if(currspec.match(/a/i)) { curr = numhead_alpha(curr, currspec); }
      else if(currspec.match(/i/i)) { curr = numhead_roman(curr, currspec); }

      qq+=curr+".";
    }
    return qq;
  }

  function makesortable() {
    if(! pf(__script__.dataset.sortable)) return;
    var tables = dqsa('table.sortable,table.sortable-footer');
    for(var i=0; i<tables.length; i++) {
      // non-pmwiki-core table, already ready
      if(tables[i].querySelector('thead')) continue;

      tables[i].classList.add('sortable'); // for .sortable-footer

      var thead = document.createElement('thead');
      tables[i].insertBefore(thead, tables[i].firstChild);

      var rows = tables[i].querySelectorAll('tr');
      thead.appendChild(rows[0]);
      var tbody = tables[i].querySelector('tbody');
      if(! tbody) {
        tbody = tables[i].appendChild(document.createElement('tbody'));
        for(var r=1; r<rows.length; r++) tbody.appendChild(rows[r]);
      }
      if(tables[i].className.match(/sortable-footer/)) {
        var tfoot = tables[i].appendChild(document.createElement('tfoot'));
        tfoot.appendChild(rows[rows.length-1]);
      }
      mkdatasort(rows);
    }
    libsortable();
  }
  function mkdatasort(rows) {
    var hcells = rows[0].querySelectorAll('th,td');
    var specialsort = [], span;
    for(var i=0; i<hcells.length; i++) {
      sortspan = hcells[i].querySelector('.sort-number,.sort-number-us,.sort-date');
      if(sortspan) specialsort[i] = sortspan.className;
    }
    if(! specialsort.length) return;
    for(var i=1; i<rows.length; i++) {
      var cells = rows[i].querySelectorAll('td,th');
      var k = 0;
      for(var j=0; j<cells.length && j<specialsort.length; j++) {
        if(! specialsort[j]) continue;
        var t = cells[j].innerText, ds = '';
        if(specialsort[j] == 'sort-number-us') {ds = t.replace(/[^-.\d]+/g, ''); }
        else if(specialsort[j] == 'sort-number') {ds = t.replace(/[^-,\d]+/g, '').replace(/,/g, '.'); }
        else if(specialsort[j] == 'sort-date') {ds = new Date(t).getTime(); }
        if(ds) cells[j].setAttribute('data-sort', ds);
      }
    }
  }
  function libsortable(){
    // adapted from Public Domain code by github.com/tofsjonas
    function getValue(obj) {
      obj = obj.cells[column_index];
      return obj.getAttribute('data-sort') || obj.innerText;
    }
    function reclassify(element, cname) {
      element.classList.remove('dir-u', 'dir-d');
      if(cname) element.classList.add(cname);
    }
    var column_index;
    document.addEventListener('click', function(e) {
      var element = e.target;
      if (element.nodeName != 'TH') return;
      var table = element.offsetParent;
      if (!table.classList.contains('sortable')) return
                              
      var cells = element.parentNode.cells;
      for (var i = 0; i < cells.length; i++) {
        if (cells[i] === element) {
          column_index = i;
        } else {
          reclassify(cells[i], '');
        }
      }
      var cname = 'dir-d', reverse = false;
      if (element.classList.contains(cname)) {
        cname = 'dir-u';
        reverse = true;
      }
      reclassify(element, cname);
      var tbody = table.tBodies[0];
      var rows = [];
      for(var r=0; r<tbody.rows.length; r++) rows.push(tbody.rows[r]);
      
      rows.sort(function(x, y) {
        var a = getValue(reverse? y:x),
            b = getValue(reverse? x:y);
        return isNaN(a - b) ? a.localeCompare(b) : a - b;
      });
      for (i = 0; i < rows.length; i++) {
        tbody.appendChild(rows[i]);
      }
    });
  }

  function highlight_pre() {
    if (typeof hljs == 'undefined') return;
    
    var x = dqsa('.highlight,.hlt');
    
    for(var i=0; i<x.length; i++) {
      if(x[i].className.match(/(^| )(pm|pmwiki)( |$)/)) { continue;} // core highlighter
      var pre = Array.prototype.slice.call(x[i].querySelectorAll('pre,code'));
      var n = x[i].nextElementSibling;
      if (n && n.tagName == 'PRE') pre.push(n);
      for(var j=0; j<pre.length; j++) {
        pre[j].className += ' ' + x[i].className;
        hljs.highlightElement(pre[j]);
      }
    }
  }
  
  var Now, ltmode, daymonth;
  function fmtLocalTime(stamp) {
    var d = new Date(stamp*1000);
    var tooltip = PHSC(d.toLocaleString().replace(/(\d:\d\d):\d\d/, '$1'));
    if(ltmode == 2)
      return [tooltip, tooltip];
    if(Now-d < 24*3600000) 
      return [zpad(d.getHours()) + ':'+ zpad(d.getMinutes()), tooltip];
    var D = zpad(d.getDate()), M = zpad(d.getMonth()+1);
    var thedate = daymonth.replace(/%d/, D).replace(/%m/, M);
    if(Now-d < 334*24*3600000) return [thedate, tooltip];
    return [thedate + '/' + d.getFullYear(), tooltip];
  }
  
  function localTimes() {
    ltmode = pf(__script__.dataset.localtimes)
    if(! ltmode) return;
    var times = dqsa('span[class^="time-"]');
    
    Now = new Date();
    daymonth =  new Date(2021, 11, 26, 17)
      .toLocaleDateString().match(/26.*12/)? '%d/%m': '%m/%d';
    
    var latest = 0;
    var a = self.location.href.match(/since=(\d+)/);
    var previous = a? pf(a[1]) : false;
    var h72 = Now.getTime()/1000-72*3600;
    
    for(var i=0; i<times.length; i++) {
      var a = times[i].className.match(/^time-([a-z0-9]+)(?:-([a-z0-9]+))?$/);
      if(!a) continue;
 
      var stamp = parseInt(a[1], 36);
      var prevstamp = a[2] ? stamp - parseInt(a[2], 36) : false;
      if(!latest) latest = stamp;
      var li = times[i].closest('li');
      var link = li.querySelector('a');
      if(link.className.match(/createlinktext|wikilink|selflink/)) {
        var diff = link.href + '?action=diff#diff' + stamp;
      }
      else diff = link.href + '#diff' + stamp; // recent uploads, other?
      times[i].innerHTML = '<a href="'+diff+'">'+times[i].innerHTML+'</a>&nbsp;&nbsp;';
      li.insertBefore(times[i], link);
      
      if(previous && stamp>previous) li.classList.add('rcnew');

      if(prevstamp && prevstamp >= h72) {
        var h = diff.replace(/[#]diff\d+/, '&fmt=rclist');
        adjbe(li, ' <b class="rcplus" data-url="'+h+'">+</b>');
      }
    }
    
    var difflinks = dqsa('a[href*="action=upload#diff"], a[href*="action=diff#diff"], a[href^="#diff"]');
    if(!difflinks.length) return;

    for(var i=0; i<difflinks.length; i++) {
      var link = difflinks[i];
      var a = link.href.match(/[#]diff(\d+)$/);
      if(!a) continue;
      var x = fmtLocalTime(a[1]);
      
      link.innerHTML = x[0];
      link.setAttribute('title', x[1]);
    }
    var pagetitle = dqs('#wikititle h1, h1.pagetitle');
    if(pagetitle) {
      var time = zpad(Now.getHours()) + ':'+ zpad(Now.getMinutes());
      var upd = location.href.replace(/[?&]since=\d+$/, '');
      upd += (upd.indexOf('?')>0? '&':'?') + 'since='+latest;
      adjbe(pagetitle, ' <a href="' + upd +'" class="rcreload">'+time+'</a>');
    }
    aE('.rcnew', 'mouseup', function(e){
      if(e.which == 2) this.classList.remove('rcnew');
    });
    tap('.rcplus', function(e){
      let plus = this;
      plus.style.display = 'none';
      let basehref = plus.dataset.url.replace(/&fmt=rclist/, '#diff');
      let fmt = '<p class="outdent"><a href="'+basehref+'%d" title="%T">%t</a> %s</p>\n';
      fetch(plus.dataset.url)
      .then(function(resp){return resp.text();})
      .then(function(text){
        var lines = text.split(/\n/g);
        var out = '';
        for(var i=0; i<lines.length; i++) {
          a = lines[i].match(/^(\d+):(.*)$/);
          if(!a) continue;
          var time = fmtLocalTime(pf(a[1]));
          out += fmt.replace(/%d/, a[1]).replace(/%T/, time[1])
            .replace(/%t/, time[0]).replace(/%s/, a[2]);
        }
        if(out) adjae(plus, out);
      })
      .catch(log);
    });
  }

  function ready(){
    PmXMail();
    inittoggle();
    autotoc();
    makesortable();
    highlight_pre();
    localTimes();
  }
  if( document.readyState !== 'loading' ) ready();
  else window.addEventListener('DOMContentLoaded', ready);
})();
