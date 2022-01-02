/**
 *  @package   DarkMagic
 *  @copyright Copyright (c)2019-2022 Nicholas K. Dionysopoulos
 *  @license   GNU General Public License version 3, or later
 */

window.setTimeout(function() {
    const postponedCss = Joomla.getOptions('plg_system_darkmagic.postponedCSS');
    const head = document.getElementsByTagName("head")[0];

    for (const url in postponedCss)
    {
        if (!postponedCss.hasOwnProperty(url)) {
            continue;
        }

        const link = document.createElement("link");

        link.rel   = "stylesheet";
        link.type  = "text/css";
        link.href  = url;
        link.media = postponedCss[url];

        head.appendChild(link);
    }
}, 250);