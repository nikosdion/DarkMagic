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

    // Remove the TinyMCE skin CSS
    nikosdion.darkMagic.removeCSSFile("lightgray/skin.min.css");
    nikosdion.darkMagic.removeCSSFile("lightgray/skin.css");
    nikosdion.darkMagic.removeCSSFile("charcoal/skin.min.css");
    nikosdion.darkMagic.removeCSSFile("charcoal/skin.css");

    // Dark Mode
    if (e.matches)
    {
        // Add the Dark Mode CSS
        nikosdion.darkMagic.injectCSSFile(cssId, darkModeCSS)

        // Load the Charcoal TinyMCE sking
        nikosdion.darkMagic.injectCSSFile("darkMagicTinyMCETemplate",
            "../media/editors/tinymce/skins/charcoal/skin.min.css");
        nikosdion.darkMagic.reskinTinyMCE('charcoal');

        return;
    }

    //  Remove the Dark Mode CSS
    nikosdion.darkMagic.removeCSSFile(darkModeCSS)

    // Load the LightGray TinyMCE sking
    nikosdion.darkMagic.injectCSSFile("darkMagicTinyMCETemplate",
        "../media/editors/tinymce/skins/lightgray/skin.min.css");
    nikosdion.darkMagic.reskinTinyMCE('lightgray');
};

nikosdion.darkMagic.reskinTinyMCE = function (skinName) {
    if (typeof window.tinyMCE === "undefined")
    {
        return;
    }

    // TODO Check if I should mess with TinyMCE (both option and whether tinymce exists)
    if (!true)
    {
        return;
    }

    // Set the skin
    Joomla.optionsStorage.plg_editor_tinymce.tinyMCE.default.skin = skinName;

    // Commit and remove all TinyMCE editors
    var deleteKeys = [];

    for (var key in Joomla.editors.instances)
    {
        if (!Joomla.editors.instances.hasOwnProperty(key))
        {
            continue;
        }

        // Commit the content
        var thisEditor = Joomla.editors.instances[key];

        // Make sure it's TinyMCE and remove it
        var thisInstance = thisEditor.instance;

        if (
            (typeof thisInstance.plugins !== "object") ||
            (typeof thisInstance.remove !== "function") ||
            (typeof thisEditor.onSave !== "function")
        )
        {
            // Probably not TinyMCE...
            continue;
        }

        var textboxID = thisEditor.id;

        thisInstance.loadedCSS = {};
        thisInstance.remove();
        deleteKeys.push(key);
    }

    // If I didn't remove at least one editor I'm done.
    if (deleteKeys.length === 0)
    {
        return;
    }

    // Delete Joomla's internal editor keys
    deleteKeys.map(function (key) {
        delete Joomla.editors.instances[key];
    });

    // Re-render TinyMCE editors, document and subforms
    Joomla.JoomlaTinyMCE.setupEditors();

    if (window.jQuery)
    {
        jQuery(document).on("subform-row-add", function (event, row) {
            Joomla.JoomlaTinyMCE.setupEditors(row);
        });
    }
};

nikosdion.darkMagic.injectCSSFile = function (cssId, darkModeCSS) {
    if (document.getElementById(cssId))
    {
        return false;
    }

    var head   = document.getElementsByTagName("head")[0];
    var link   = document.createElement("link");
    link.id    = cssId;
    link.rel   = "stylesheet";
    link.type  = "text/css";
    link.href  = darkModeCSS;
    link.media = "screen";
    head.appendChild(link);

    return true;
};

nikosdion.darkMagic.removeCSSFile = function (partialURL) {
    var allLinks = document.getElementsByTagName("link");
    var result   = false;

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

        if (href.substr(-partialURL.length) !== partialURL)
        {
            continue;
        }

        allLinks[i].parentNode.removeChild(allLinks[i]);
        result = true;
    }

    return result;
};

setTimeout(nikosdion.darkMagic.initialize, 250);