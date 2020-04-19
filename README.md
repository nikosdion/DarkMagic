# DarkMagic

Dark Mode for Joomla 3

## What does it do?

Automatically applies a dark theme in Joomla's administrator backend and public frontend.

Dark Mode themes are only applied when you are using the Isis template in the backend and the Protostar template in the front-end. If you have customized the template you may need to override the Dark Mode CSS files provided by this plugin to take into account your modifications.

This plugin does not make Dark Mode possible, it only makes it easier. You can always instead custom.css / user.css files in the built-in Joomla templates' folders to enable Dark Mode. The reason this pluign exists is that you don't need to mess around with your site's files. If you change your mind about Dark Mode just disable a plugin and you're back to normal. Easy-peasy!

## Download

I primarily wrote this for my own, personal use. I do publish occasional pre-built Joomla installation packages and make them available through [my GitHub repository's Releases page](https://github.com/nikosdion/darkmagic/releases).

## Support and contributions

As long as your problem is not about a core Joomla feature you don't understand how to use, debugging CSS on your site or supporting third party extensions I am happy to help if you file a GitHub issue.

If you want to contribute code, as long as it's not about supporting third party extensions, feel free to make a PR. If your PR is about adding support for additional CSS files so you could more easily support third party extensions, though, it will be considered and plausibly accepted.

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