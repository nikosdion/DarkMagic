/**
 *  @package   DarkMagic
 *  @copyright Copyright (c)2019-2022 Nicholas K. Dionysopoulos
 *  @license   GNU General Public License version 3, or later
 */
document.addEventListener("DOMContentLoaded", function() {
    window.setTimeout(function() {
        var options = Joomla.getOptions('plg_system_darkmagic.tiny_dark', {
            css:         '',
            conditional: false
        });

        if (!options.css)
        {
            return;
        }

        const editors = document.querySelectorAll(".tox-edit-area > iframe");

        if (!editors.length)
        {
            return;
        }

        editors.forEach(function (elEditor)
        {
            const link = document.createElement("link");

            link.rel  = "stylesheet";
            link.type = "text/css";
            link.href = options.css;

            if (options.conditional)
            {
                link.media = "(prefers-color-scheme: dark)";
            }

            elEditor.contentDocument.querySelector("head").appendChild(link);
        });

    }, 250);

    var plgSystemDarkMagicPreviewMagicInterval = window.setInterval(function() {
        var options = Joomla.getOptions('plg_system_darkmagic.tiny_dark', {
            css:         '',
            conditional: false
        });

        if (!options.css)
        {
            clearInterval(plgSystemDarkMagicPreviewMagicInterval);

            return;
        }

        const previews = document.querySelectorAll(".tox-navobj iframe");

        if (!previews.length)
        {
            return;
        }

        previews.forEach(function (elPreviews)
        {
            const link = document.createElement("link");

            link.rel  = "stylesheet";
            link.type = "text/css";
            link.href = options.css;

            if (options.conditional)
            {
                link.media = "(prefers-color-scheme: dark)";
            }

            elPreviews.contentDocument.querySelector("head").appendChild(link);
        });

    }, 1000);
});