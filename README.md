# i18n YML Format Converter #

## Overview ##

Converts SilverStripe's language files from a PHP array (the `$lang` global)
to a YML file adhereing to the Rails 2.2 i18n conventions.
This allows us to import the files into getlocalization.com,
and export the translations from there again.

Also normalizes the locale names a bit,
in anticipation of allowing translation fallbacks
in SS3 through the `Zend_Translate` framework.
For example, 'en_US.php' becomes 'en.yml'.

Removes duplicate translations, and moves outdated
translations between sapphire and cms.

## Maintainers ##

 * Ingo Schommer (ingo at silverstripe dot com)

## Usage ##

All modules: `sake dev/tasks/i18nYMLConverterTask`
Single module: `sake dev/tasks/i18nYMLConverterTask module=<mymodule>`
	
By default, the newly created 

## TODO ##

 * Write custom Zend_Translate backend to deal with the YML files in SilverStripe