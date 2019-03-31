var toc = document.getElementsByClassName("toc")[0];
toc.style.top = (toc.getBoundingClientRect().top + 25 + window.scrollY) + "px";
toc.classList.add("livetoc");

function tocGenerate() {
    var structure = "";
    var hs = document.querySelectorAll("main article h1, main article h2, main article h3, main article h4, main article h5, main article h6");
    var last = 1;
    var begin = true;
    for (var i = 0; i < hs.length; i++) {
        var current = parseInt(hs[i].tagName.substr(1));
        if (current == last) {
            structure += (begin ? "" : "</li>") + "<li><a href=\"#" + hs[i].id + "\">" + hs[i].innerHTML + "</a>";
        } else if (current > last) {
            structure += "<ul>";
            for (var c = last; c < current - 1; c++) structure += "<li><ul>";
            structure += "<li><a href=\"#" + hs[i].id + "\">" + hs[i].innerHTML + "</a>";
        } else {
            structure += "</li>";
            // Go back to last skipped indent
            for (var c = current; c < last; c++) structure += "</ul></li>";
            structure += "<li><a href=\"#" + hs[i].id + "\">" + hs[i].innerHTML + "</a>";
        }
        begin = false;
        last = current;
    }
    structure = structure.replace(/<ul><li>(?=<ul>)/g, "<ul class=\"offset\"><li class=\"offset\">");
    toc.firstElementChild.innerHTML = structure;
}

function tocUpdate() {
    var hs = document.querySelectorAll("main article h1, main article h2, main article h3, main article h4, main article h5, main article h6");
    var current = hs[0];
    for (var i = 0; i < hs.length; i++) {
        if (hs[i].getBoundingClientRect().top <= hs[i].offsetHeight && hs[i].id) current = hs[i];
    }
    if (current) {
        var active = toc.querySelector(".active");
        current = toc.querySelector("a[href=\"#" + current.id + "\"]");
        if (!current) return;
        if (active) active.classList.remove("active");
        current.classList.add("active");
        var currentBcr = current.getBoundingClientRect();
        var tocBcr = toc.getBoundingClientRect();
        if (currentBcr.top < tocBcr.top || currentBcr.top + currentBcr.height > tocBcr.top + tocBcr.height) {
            var scrollTop = 0, e = current.parentElement;
            while (!e.classList.contains("toc")) {
                scrollTop += e.offsetTop;
                e = e.parentElement;
            }
            toc.scrollTop = scrollTop - tocBcr.height / 2 + currentBcr.height / 2;
        }
    }
}

tocGenerate();

window.addEventListener("scroll", tocUpdate);
tocUpdate();
