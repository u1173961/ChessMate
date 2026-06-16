if (!String.prototype.replaceAll) {
    String.prototype.replaceAll = function (search, replace) {
        var target = this;
        var split = target.split(search);
        var toReplace = Array();
        var tr;
        for (var i = 0; i < split.length; i++) {
            tr = split[i];
            if (tr.trim() !== '') {
                toReplace.push(tr);
            }
        }
        return toReplace.join(replace);
    };
}
if (!String.prototype.startsWith) {
    String.prototype.startsWith = function (searchString, position) {
        return this.substr(position || 0, searchString.length) === searchString;
    };
}
if (typeof capitalize !== "function") {
    function capitalize(string)
    {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }
}
if (typeof benchmark !== "function") {
    function benchmark(toTest, runs)
    {
        var ms = 0;
        var t0;
        var i = 0;
        var result;
        while (i < runs) {
            t0 = performance.now();
            result = toTest();
            ms += (performance.now() - t0);
            i++;
        }
        var avg = ms / runs;
        console.log('function averaged ' + avg + ' ms. over ' + runs + ' run' + (runs > 1 ? 's' : ''));
        return result;
    }
}
if (typeof scrollToObject !== "function") {
    function scrollToObject(object, ms)
    {
        ms = typeof ms == 'undefined' ? 500 : ms;
        var objectPos = $(object).offset().top;
        $('html, body').animate({
            scrollTop: objectPos
        }, ms);
    }
}

if (typeof getPixelValueFromCSS !== "function") {
    function getPixelValueFromCSS(rule, element)
    {
        return parseInt($(element).css(rule).substr(0, $(element).css(rule).length - 2));
    }
}