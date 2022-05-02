## DarkMagic for Joomla 4

This plugin implements Dark Mode for Joomla 4's default administrator (Atum) and frontend (Cassiopeia) templates.

### Requirements

* Joomla 4.0 or 4.1. Tentative support for 4.2.
* PHP 7.2, 7.3, 7.4, 8.0 or 8.1.
* Atum (backend) or Cassiopeia (frontend) template.
* A web browser with Dark Mode support.

**IMPORTANT!** This version of the plugin will NOT work with Joomla 3.

### Changelog

**Bug fixes**

* [HIGH] Fatal error if TinyMCE dark mode is enabled when “Apply dark theme“ is set to anything but Browser (gh-2)
* [LOW] PHP 8.1 deprecated notice from the color handling code
