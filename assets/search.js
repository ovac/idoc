function search(self) {
    if (search.working) { search.queue = true; return; }

    if (self) search.form = self;
    search.working = true;
    if (!search.engine) {
        if (document.querySelector(".language-select-popup")) {
            search.availableLanguages = Array.from(document.querySelectorAll(".language-select-popup img")).map(function(x) { return x.src.match(/\/flags\/([a-z]+)\.png$/)[1] });
            search.defaultLanguage = document.getElementsByClassName("language-select-popup")[0].getAttribute("data-default-language");
            search.lang = document.documentElement.lang;
        }
        fetch(search.form.getAttribute("data-baseurl") + "/search-index.json").then(function(r) { return r.json() }).then(function(data) {
            search.engine = new JsSearch.Search("url");
            search.engine.addIndex("title");
            search.engine.addIndex("description");
            search.engine.addIndex("content");

            search.index = data;
            search.engine.addDocuments(data);

            search.working = false;
            search.queue = false;
            search();
        })
        return;
    }

    var result = search.engine.search(search.form.getElementsByClassName("search-query")[0].value);
    search.result = result; // For debugging purposes
    
    var output = "";

    for (var i = 0; i < result.length; i++) {
        var lang = result[i].url.replace(/^\/+|\/+$|\/+(?=\/)/g, "").split("/")[0];
        if (search.lang && lang != search.lang) { // Different language
            // Search language is default language, but result language is different
            if (search.lang == search.defaultLanguage && search.availableLanguages.indexOf(lang) > -1) continue;
            // Different language and not default language
            if (search.lang != search.defaultLanguage) continue;
        }

        var el = document.createElement("a");
        el.href = search.form.getAttribute("data-baseurl") + result[i].url;
        
        var title = document.createElement("strong");
        title.textContent = result[i].title;

        var match = document.createElement("small");
        match.innerHTML = result[i].description ||
            (result[i].content.substr(0, 250).trim()
            .replace(/\n+/g, "<br>")
            .replace(/((?:(?:(?!<br>)(?:.|\n))+<br>){5})(?:.|\n)*/, "$1").replace(/<br>$/, "") + "…");

        el.appendChild(title);
        el.appendChild(match);

        output += el.outerHTML;
    }

    search.form.nextElementSibling.innerHTML = output;

    search.working = false;
    if (search.queue) search();
}

window.addEventListener("mousedown", function(event) {
    if (!event.target.P(".search")) document.getElementsByClassName("results")[0].innerHTML = "";
});
