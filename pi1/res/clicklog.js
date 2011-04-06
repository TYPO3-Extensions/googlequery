function addLoadEvent(func) {
	var oldonload = window.onload;
	if (typeof window.onload != 'function') {
		window.onload = func;
	} else {
		window.onload = function() {
			if (oldonload) {
				oldonload();
			}
			func();
		}
	}
}

function loadClickLog() {
	for (
		var cl_clk = function(b, a, d, f, e, g, h, j) {
			if (!document.images)return false;
			var c = encodeURIComponent || escape,k = document.createElement("img");
			b = gsa_host + "/click?" + (a ? "&q=" + c(a) : "") + (d ? "&ct=" + c(d) : "") + (f ? "&cd=" + c(f) : "") + (b ? "&url=" + c(b.replace(/#.*/, "")).replace(/\+/g, "%2B") : "");
			if (e != null && typeof e != "undefined")b += "&r=" + c(e);
			if (g != null && typeof g != "undefined")b += "&s=" + c(g);
			if (h != null && typeof h != "undefined")b += "&site=" + c(h);
			if (j != null && typeof j != "undefined")b += "&src_id=" + c(j);
			k.src = b;
			return true
		},
		cl_link_clicked = function(b) {
			var a = this;
			if (!a.getAttribute && b) {
				if (b.target)a = b.target; else if (b.srcElement)a = b.srcElement;
				if (a.nodeType == 3)a = a.parentNode
			}
			b = a.getAttribute("cdata");
			var d = a.getAttribute("ctype"),f = ( parseInt(a.getAttribute("pos")) + 1),e = a.getAttribute("src_id");
			d || (d = "OTHER");
			a = a.href ? a.href : "#";
			cl_clk(a, page_query, d, b, f, page_start, page_site, e);
			return true;
		},

	ar = document.getElementsByTagName("a"),
	arlen = ar.length,i = 0; i < arlen; i++) {
		var el = ar[i];
		if (!el.onmousedown && el.getAttribute("rel") == 'logclick') el.onmousedown = cl_link_clicked;
	}

	cl_clk(null, page_query, "load", null, null, page_start, page_site, null);

}

addLoadEvent(loadClickLog);