# ODT2XHTML #

### What is it? ###
* ODT2XHTML is a small library for converting documents in Open Document Format to HTML. 
* Supports most ODF text formatting features including embedded images, lists, tables, subscripts, supescripts, etc.
* <i>Based on heavily refactored code from [Odt2Xhtml library](https://github.com/hucste/Odt2Xhtml) by Stéphane HUC. 
Example ODF files are taken from original library</i>

### How to use it? ###
* Add ODT2XHTML to your project `composer require lebedevsergey/odt2xhtml`
* create ODT2XHTML object: `$converter = new ODT2XHTML()`
* call `convert` method: `$converter->convert($ODTFilePath, $ODTHTMLPath, true);`

where: 
- `$ODTFilePath` - path to Open Office document to convert
- `$ODTHTMLPath` - path to the resulted HTML folder, HTML file name will be the same as the Open Office document file name
but with `.html` extension
- the third value is the boolean flag whether resulted CSS styles will be embedded into generated HTML file or will be in a separate CSS file 

See also included example code in `example.php`.

### What else? ###
* supports files in: 
    * Open Document Format with extensions: `.odb, .odc, .odf, .odg, .odi, .odp, .ods, .odt, .odm, .otg, .oth, .otp, .ots, .ott` 
    * StarOffice format with extensions: `.stc, .std, .sti, .stw, .sxc, .sxd, .sxg, .sxi, .sxm, .sxw`
* PHP 5.6 or higher is required

### Authors? ###
* base code &copy; 2007-2011 Stéphane HUC
* &copy; 2020 Sergey Lebedev, licensed under the Apache License, Version 2.0
* Feel free to contact me at:
    * https://habrahabr.ru/users/sunman/
    * http://stackoverflow.com/users/7135046/sergeylebedev
    * https://www.facebook.com/sergei.lebedev.5891
