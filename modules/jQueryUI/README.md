# jQueryUI for Omeka S

This module does nothing on itself but provides [jQueryUI] JS library for other modules

## Usage

Install and activate this module, then add the following to your templates:

```php
$this->headScript()->appendFile($this->assetUrl('jquery-ui.min.js', 'jQueryUI'));
$this->headLink()->appendStylesheet($this->assetUrl('jquery-ui.min.css', 'jQueryUI'));
```

[jQueryUI]: https://jqueryui.com/
