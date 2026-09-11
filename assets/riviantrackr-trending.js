(function() {
    var applied = false;

    function fontAwesomeLoaded() {
        // document.fonts.check() reports whether the webfont file is actually
        // available, not just whether a stylesheet declared the family.
        try {
            if (document.fonts && typeof document.fonts.check === 'function') {
                return document.fonts.check('1em "Font Awesome 6 Free"') ||
                    document.fonts.check('1em "Font Awesome 6 Pro"') ||
                    document.fonts.check('1em "Font Awesome 5 Free"');
            }
        } catch (e) {}

        // Fallback for browsers without the Font Loading API.
        var testIcon = document.createElement("i");
        testIcon.className = "fa-solid fa-magnifying-glass";
        testIcon.setAttribute("aria-hidden", "true");
        testIcon.style.position = "absolute";
        testIcon.style.left = "-9999px";
        document.body.appendChild(testIcon);
        var computedFont = (window.getComputedStyle(testIcon).fontFamily || "").toLowerCase();
        document.body.removeChild(testIcon);
        return computedFont.indexOf("font awesome") !== -1 || computedFont.indexOf("fontawesome") !== -1;
    }

    function checkFontAwesome() {
        if (applied || !fontAwesomeLoaded()) return;
        applied = true;
        document.querySelectorAll(".riviantrackr-trending-fa-icon").forEach(function(icon) { icon.style.display = "inline-block"; });
        document.querySelectorAll(".riviantrackr-trending-svg-icon").forEach(function(icon) { icon.style.display = "none"; });
    }

    function init() {
        checkFontAwesome();
        if (applied) return;
        // Font Awesome may still be loading; re-check once when fonts settle,
        // with a timed fallback for browsers without document.fonts.
        if (document.fonts && document.fonts.ready && typeof document.fonts.ready.then === 'function') {
            document.fonts.ready.then(checkFontAwesome);
        } else {
            setTimeout(checkFontAwesome, 500);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
