<?php

use lebedevsergey\ODT2XHTML\Helpers\FilesHelper;
use lebedevsergey\ODT2XHTML\ODT2XHTML;

require __DIR__ . '/vendor/autoload.php';

$ODTFIleName = 'odt2xhtml.odt';
$ODTFilePath = __DIR__ . '/example_files/' . $ODTFIleName;
$ODTHTMLPath = __DIR__ . '/html/' . 'odt_html';

FilesHelper::deleteDirRecursive($ODTHTMLPath); // delete previous HTML
(new ODT2XHTML)->convert($ODTFilePath, $ODTHTMLPath, true);
echo "$ODTFIleName converted to HTML in $ODTHTMLPath\n";


$SXWFileName = 'odt2xhtml.sxw';
$SXWFilePath = __DIR__ . '/example_files/' . $SXWFileName;
$SXWHTMLPath = __DIR__ . '/html/' . 'sxw_html';

FilesHelper::deleteDirRecursive($SXWHTMLPath); // delete previous HTML
(new ODT2XHTML)->convert($SXWFilePath, $SXWHTMLPath, true);
echo "$SXWFileName converted to HTML in $SXWHTMLPath\n";
