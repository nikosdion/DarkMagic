/*
 *  @package   DarkMagic
 *  @copyright Copyright (c)2019-2019 Nicholas K. Dionysopoulos
 *  @license   GNU General Public License version 3, or later
 */

if (typeof nikosdion === "undefined")
{
    var nikosdion = {};
}

if (typeof nikosdion.darkMagic === "undefined")
{
    nikosdion.darkMagic = {};
}

nikosdion.darkMagic.initialize = function () {
    // Make sure we have a browser that supports media match queries
    if (typeof window.matchMedia === "undefined")
    {
        return;
    }

    var mediaQuery     = "screen and (prefers-color-scheme: dark)";
    var mediaQueryList = window.matchMedia(mediaQuery);

    nikosdion.darkMagic.listener(mediaQueryList);

    if (typeof mediaQueryList.addEventListener === "undefined")
    {
        mediaQueryList.addListener(nikosdion.darkMagic.listener);
    }
    else
    {
        mediaQueryList.addEventListener("change", nikosdion.darkMagic.listener);
    }
};

nikosdion.darkMagic.listener = function (e) {
    var cssId       = "nikosdionDarkMagic";
    var darkModeCSS = "../plugins/system/darkmagic/darktheme/administrator/templates/isis/css/custom.css";

    // Dark Mode
    if (e.matches)
    {
        // Already dark?
        if (document.getElementById(cssId))
        {
            return;
        }

        var head   = document.getElementsByTagName("head")[0];
        var link   = document.createElement("link");
        link.id    = cssId;
        link.rel   = "stylesheet";
        link.type  = "text/css";
        link.href  = darkModeCSS;
        link.media = "screen";
        head.appendChild(link);

        return;
    }

    // Light Mode
    if (!document.getElementById(cssId))
    {
        // Already Light Mode
        return;
    }

    // Get all link elements
    var allLinks = document.getElementsByTagName("link");

    // Find the element with our CSS file and send it to oblivion
    for (var i = 0; i < allLinks.length; i++)
    {
        if (!allLinks[i])
        {
            continue;
        }

        var href = allLinks[i].getAttribute("href");

        if (href === null)
        {
            continue;
        }

        if (href !== darkModeCSS)
        {
            continue;
        }

        allLinks[i].parentNode.removeChild(allLinks[i]);
    }
};

nikosdion.darkMagic.initialize();