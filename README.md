# HTML Validator Plugin 

[![Build Status](https://travis-ci.org/themichaelhall/html-validator-plugin.svg?branch=master)](https://travis-ci.org/themichaelhall/html-validator-plugin)
[![AppVeyor](https://ci.appveyor.com/api/projects/status/github/themichaelhall/html-validator-plugin?branch=master&svg=true)](https://ci.appveyor.com/project/themichaelhall/html-validator-plugin/branch/master)
[![codecov.io](https://codecov.io/gh/themichaelhall/html-validator-plugin/coverage.svg?branch=master)](https://codecov.io/gh/themichaelhall/html-validator-plugin?branch=master)
[![StyleCI](https://styleci.io/repos/116175078/shield?style=flat&branch=master)](https://styleci.io/repos/116175078)
[![License](https://poser.pugx.org/michaelhall/html-validator-plugin/license)](https://packagist.org/packages/michaelhall/html-validator-plugin)
[![Latest Stable Version](https://poser.pugx.org/michaelhall/html-validator-plugin/v/stable)](https://packagist.org/packages/michaelhall/html-validator-plugin)
[![Total Downloads](https://poser.pugx.org/michaelhall/html-validator-plugin/downloads)](https://packagist.org/packages/michaelhall/html-validator-plugin)

HTML validator plugin for the [BlueMvc PHP framework](https://github.com/themichaelhall/bluemvc).

## Requirements

- PHP >= 7.1

## Install with Composer

``` bash
$ composer require michaelhall/html-validator-plugin
```

## Basic usage

Once installed, this plugin validates every HTML output via the validator.w3.org API and replaces the result with an error message if validation failed. 

Validation results are cached for identical content.

```php
$htmlValidatorPlugin = new \MichaelHall\HtmlValidatorPlugin\HtmlValidatorPlugin();

// Do not validate anything under /ajax/
$htmlValidatorPlugin->addIgnorePath('/ajax/');

// ...or the path /foo/bar
$htmlValidatorPlugin->addIgnorePath('/foo/bar');

// By default, the plugin only runs in debug mode.
// To enable in release mode. (Not recommended) 
$htmlValidatorPlugin->enableInReleaseMode();

// Add to application.
$application->addPlugin($htmlValidatorPlugin);
```

## License

MIT
