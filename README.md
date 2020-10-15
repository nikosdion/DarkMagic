# DarkMagic

DarkMagic - Turn off the lights in your Joomla! 4 administrator template

Copyright (C) 2020  Nicholas K. Dionysopoulos

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

## What does it do?

Automatically applies a dark theme in Joomla 4's administrator backend and public frontend.

## Download

I primarily wrote this for my own, personal use. I do publish occasional pre-built Joomla installation packages and make them available through [my GitHub repository's Releases page](https://github.com/nikosdion/darkmagic/releases).

## Caveats

Dark Mode themes are only applied when you are using the Atum template in the backend. If you have customized the template you may need to override the Dark Mode CSS files provided by this plugin to take into account your modifications.

This plugin does not make Dark Mode possible, it only makes it easier. You can always instead custom.css / user.css files in the built-in Joomla templates' folders to enable Dark Mode. The reason this pluign exists is that you don't need to mess around with your site's files. If you change your mind about Dark Mode just disable a plugin and you're back to normal. Easy-peasy!

Dark Mode has a lower contrast than the normal, light mode. If you're using Dark Mode you'll need to keep your screen brightness / backlight at a higher setting than if you're using light mode at night. This is deliberate, not a bug. If you are looking for something with a black background and white foreground what you're looking for is called "high contrast" or "inverse color" mode, not dark mode. This plugin _does not_ do high contrast / inverse color mode.

## Support and contributions

Please bear in mind that using Free and Open Source Software does not entitle you to free support or labor from its developers. There are no guarantees that your request will be implemented or ever responded to in a certain timeframe or at all.

In an effort to save both of us time and frustration I am going to explain what I can help with and how you should contact me about it.

Certain things are out of this project's scope and when filing a GitHub issue. Using core Joomla features, debugging / implementing CSS on your site and supporting third party extensions are out of scope. Using this plugin in an environment other than the one described in its requirements including but not limited to different PHP and Joomla versions, different frontend or backend templates or browsers are also out of scope. How your browser and OS implements dark mode is also out of scope, _especially_ if you are on Linux, older Windows versions or macOS versions older than Catalina.

In scope issues are wrong colors in dark mode for core Joomla features only. Do note that some third party plugins may extend or override core features. For example: WYSIWYG editors, plugins which add fields to the Users page, or plugins which override the Media Manager and the media selection fields. These are considered third party extensions and are out of scope. 

If you have an in-scope issue please do file a GitHub issue where you give concise and precise reproduction instructions _on a brand new Joomla site with only DarkMagic installed and enabled_ and a clear description of your issue. Screenshots in PNG or JPG format attached to your GitHub issue are welcome. Screencasts (videos; inline or on third party sites like YouTube), animated GIFs, PDFs, Word documents etc are not acceptable and won't be opened unless you are explicitly asked to provide them.

If you want to contribute code, as long as it's not about supporting third party extensions, feel free to make a PR. If your PR is about adding support for additional CSS files so you could more easily support third party extensions, though, it will be considered and might even be accepted.

Please only use GitHub issues to contact me about this project. The one and only exception for that is security issues.

Finally, please be kind and understanding when contacting me. I am doing this on my very limited spare time. Thank you for keeping the conversation reasonable and civil!

## Building the package

### Quick'n'dirty build

In the simplest form you can ZIP the `plugins/system/darkmagic` folder's contents and install it on your site.

### Full build process

If you want to go through the build process I use you will need to have the following tools:

* A command line environment. Using Bash under Linux / Mac OS X works best.
* A PHP CLI binary in your path
* Phing installed account-wide on your machine
* Command line Git executables

You will also need the following path structure inside a folder on your system

* **darkmagic** This repository
* **buildfiles** [Akeeba Build Tools](https://github.com/akeeba/buildfiles)

You will need to use the exact folder names specified here.

Go into the `build` directory of this repository.

Create a dev release installation package with

		phing git
		
The installable ZIP file is stored in the `release` directory which will be created inside the repository's root.