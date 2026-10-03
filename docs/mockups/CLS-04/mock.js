function setState(s){document.body.setAttribute('data-state',s);
 document.querySelectorAll('.mockbar .sw button').forEach(function(b){b.setAttribute('aria-pressed',b.dataset.s===s?'true':'false')});
 document.querySelectorAll('[data-show]').forEach(function(e){e.hidden=e.dataset.show.split(' ').indexOf(s)<0});}
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&window.DEF)setState(window.DEF)});
