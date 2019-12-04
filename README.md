# DarkMagic

Turn off the lights in Joomla administrator

## What does it do?

Automatically applies a dark theme in Joomla's administrator.

The dark theme **is not** developed by me. It comes from the [N6REJ/joomla3-isis-dark-theme](https://github.com/N6REJ/joomla3-isis-dark-theme) repository. This plugin merely applies it in a way that allows you to easily switch between the light and dark themes without messing around with your site's files.

## Download

I primarily wrote this for my own, personal use. I do publish occasional pre-built Joomla installation packages and make them available through [my GitHub repository's Releases page](https://github.com/nikosdion/darkmagic/releases).

## Support and contributions

As long as _it's not about the CSS_ you can file a GitHub issue with your bug report or feature request. Please follow the template provided.

If you want to contribute code and as long as _it's not about the CSS_ feel free to make a Pull Request. Please follow the template provided.  

## Building the package

### Requirement

Before doing anything else do `git submodule init` in the repo's root.

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