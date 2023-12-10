<?php

namespace lebedevsergey\ODT2XHTML;

use DOMDocument;
use lebedevsergey\ODT2XHTML\Exceptions\ODT2XHTMLException;
use lebedevsergey\ODT2XHTML\Helpers\FilesHelper;
use XSLTProcessor;

class ODT2XHTML
{
    const ICONS_PATH = '/data/icons/';
    const XSLT_PATH = '/data/xsl/';

    const SM_TO_PIX_COEF = 28.6264;

    static protected $DOCTYPE_TAG = '<!DOCTYPE office:document-meta PUBLIC "-//OpenOffice.org//DTD OfficeDocument 1.0//EN" "office.dtd">';

    static protected $XML_VERSION_TAG = '<?xml version="1.0" encoding="UTF-8"?>';

    static protected $ODT_Extensions_List = [
        /*** OpenDocument extension ***/
        'odb', 'odc', 'odf', 'odg', 'odi', 'odp', 'ods', 'odt',
        'odm', 'otg', 'oth', 'otp', 'ots', 'ott',
        /*** StarOffice extension ***/
        'stc', 'std', 'sti', 'stw',
        'sxc', 'sxd', 'sxg', 'sxi', 'sxm', 'sxw',
    ];

    static protected $ODT_MIME_Types_List = [
        /*** OpenDocument Format ***/
        'odb' => 'application/vnd.oasis.opendocument.database',
        'odc' => 'application/vnd.oasis.opendocument.chart',
        'odf' => 'application/vnd.oasis.opendocument.formula',
        'odg' => 'application/vnd.oasis.opendocument.graphics',
        'odi' => 'application/vnd.oasis.opendocument.image',
        'odp' => 'application/vnd.oasis.opendocument.presentation',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'odt' => 'application/vnd.oasis.opendocument.text',

        /*** OpenDocument Template ***/
        'odm' => 'application/vnd.oasis.opendocument.text-master',
        'otg' => 'application/vnd.oasis.opendocument.graphics-template',
        'oth' => 'application/vnd.oasis.opendocument.text-web',
        'otp' => 'application/vnd.oasis.opendocument.presentation-template',
        'ots' => 'application/vnd.oasis.opendocument.spreadsheet-template',
        'ott' => 'application/vnd.oasis.opendocument.text-template',

        /*** StarOffice Template ***/
        'stc' => 'application/vnd.sun.xml.calc.template',
        'std' => 'application/vnd.sun.xml.draw.template',
        'sti' => 'application/vnd.sun.xml.impress.template',
        'stw' => 'application/vnd.sun.xml.writer.template',

        /*** StarOffice Format ***/
        'sxc' => 'application/vnd.sun.xml.calc',
        'sxd' => 'application/vnd.sun.xml.draw',
        'sxg' => 'application/vnd.sun.xml.writer.global',
        'sxi' => 'application/vnd.sun.xml.impress',
        'sxm' => 'application/vnd.sun.xml.math',
        'sxw' => 'application/vnd.sun.xml.writer',
    ];

    static protected $ICONS_LIST = ['favicon.ico', 'icone.png'];

    static protected $XML_CORPUS = ['meta', 'styles', 'content'];

    protected $tmpBaseFilepath;
    protected $tmpImagesPath;
    protected $tmpXMLFilepath;

    protected $ODTFileName;
    protected $ODTFileBasename;
    protected $ODTFileExtension;

    protected $outHTMLPath;
    protected $outHTMLImgPath;
    protected $outHTMLImgRelativePath;
    protected $outHTMLFilename;
    protected $outCSSFileName;

    protected $HTMLBuffer;
    protected $XMLBuffer;

    protected $header;
    protected $position;


    /**
     * converts ODF to HTML
     * @param $ODTFullPath - path to Open Office document to convert
     * @param $outputPath - path to the resulted HTML folder, HTML file name will be the same as the Open Office document file name
     * @param false $shouldMakeCSSFile - whether resulted CSS styles will be embedded into generated HTML file or will be in a separate CSS file
     * @throws ODT2XHTMLException
     */
    public function convert($ODTFullPath, $outputPath, $shouldMakeCSSFile = false)
    {
        $this->setupPaths($ODTFullPath, $outputPath);

        FilesHelper::createDir($this->outHTMLPath);
        FilesHelper::createDir($this->outHTMLImgPath);

        FilesHelper::unzipFile($ODTFullPath, $this->tmpBaseFilepath);

        $this->createXML();
        $this->moveImages();
        $this->parseXMLWithXSLT($this->getXSLFilePathForODTFileExtension($this->ODTFileExtension));

        $this->createHTMLFile(
            $this->outHTMLPath . '/' . $this->outHTMLFilename,
            $shouldMakeCSSFile,
            $this->outHTMLPath . '/' . $this->outCSSFileName
        );

        $this->addIcons();

        FilesHelper::deleteDirRecursive($this->tmpBaseFilepath);
    }

    protected function setupPaths($ODTFullPath, $outputPath)
    {
        $this->setupODTFileInfo($ODTFullPath);

        $this->outHTMLFilename = $this->ODTFileName . '.html';
        $this->outCSSFileName = $this->ODTFileName . '.css';

        $this->outHTMLPath = $outputPath;
        $this->outHTMLImgPath = $this->outHTMLPath . '/img';        // dir img
        $this->outHTMLImgRelativePath = 'img';

        $this->tmpBaseFilepath = sys_get_temp_dir() . '/' . $this->ODTFileName;

        $this->tmpXMLFilepath = $this->tmpBaseFilepath . '.xml';    // file xml tmp
        $this->tmpImagesPath = $this->tmpBaseFilepath . '/Pictures';
    }

    protected function addXMLTag($tagName, $XMLFileName = '')
    {
        $fileWriteMode = 'ab';

        if ($tagName != 'closer' || $tagName != 'opener') {
            if (!empty($XMLFileName)) {
                $this->XMLBuffer = file_get_contents($XMLFileName);
            }
            if (!empty($this->XMLBuffer)) {
                $this->XMLBuffer = str_replace(self::$XML_VERSION_TAG, '', $this->XMLBuffer);
            }
        }

        if ($tagName == 'meta' || $tagName == 'styles') {
            switch ($this->ODTFileExtension) {
                case 'sxw' :
                case 'stw' :
                    if (!empty($this->XMLBuffer)) {
                        $this->XMLBuffer = str_replace(self::$DOCTYPE_TAG, '', $this->XMLBuffer);
                    }
                    break;
            }
        }

        switch ($tagName) {
            case 'content':
                /*** add header and footer page ***/
                switch ($this->ODTFileExtension) {
                    case 'odt' :
                    case 'ott' :
                        if (!empty($this->header)) { # add header page
                            $this->XMLBuffer = $this->replaceItemContent('header');
                        }
                        # modify src img
                        $this->XMLBuffer = $this->replaceItemContent('img_odt');
                        if (!empty($this->footer)) { # add footer page
                            $search = '</office:text>';
                            $replace = $this->footer . '</office:text>';
                            $this->XMLBuffer = str_replace($search, $replace, $this->XMLBuffer);
                        }
                        break;
                    case 'sxw' :
                    case 'stw' :
                        $this->XMLBuffer = str_replace(self::$DOCTYPE_TAG, '', $this->XMLBuffer);
                        if (!empty($this->header)) {  # add header page
                            $this->XMLBuffer = $this->replaceItemContent('header');
                        }
                        # modify src img
                        $this->XMLBuffer = $this->replaceItemContent('img_sxw');
                        if (!empty($this->footer)) { # add footer page
                            $search = '</office:body>';
                            $replace = $this->footer . '</office:body>';
                            $this->XMLBuffer = str_replace($search, $replace, $this->XMLBuffer);
                        }
                        break;

                }

                # rebuild text:reference-mark-* in text:reference-mark syntax xml correct : manage element html abbr
                $this->XMLBuffer = $this->replaceItemContent('reference_mark');

                # analyze attribute to transform style's value cm in px
                $this->XMLBuffer = $this->replaceItemContent('analyze_attribute');

                # search text in position indice or exposant to transform it correctly
                $this->rewrite_position();
                break;

            case 'closer':
                $this->XMLBuffer = "\n" . '</office:document>' . "\n";
                break;

            case 'styles':
                # analyze attribute to transform style's value cm in px
                $this->XMLBuffer = $this->replaceItemContent('analyze_attribute');

                if (preg_match_all('/<style:header>(.*)<\/style:header>/Us', $this->XMLBuffer, $matches)) {
                    $this->header = str_replace('style:header', 'text:header', $matches[0][0]);
                }

                if (preg_match_all('/<style:footer>(.*)<\/style:footer>/Us', $this->XMLBuffer, $matches)) {
                    $this->footer = str_replace('style:footer', 'text:footer', $matches[0][0]);
                }
                break;

            case 'opener':
                $this->XMLBuffer = self::$XML_VERSION_TAG . "\n";
                switch ($this->ODTFileExtension) {
                    case 'odt' :
                    case 'ott' :
                        $this->XMLBuffer .= $this->replaceItemContent('open_element_xml4odt');
                        break;
                    case 'sxw' :
                    case 'stt' :
                        $this->XMLBuffer .= $this->replaceItemContent('open_element_xml4sxw');
                        break;
                }
                $fileWriteMode = 'wb';
                break;

        }

        if (is_array($this->XMLBuffer) && is_string($this->XMLBuffer[1])) {
            FilesHelper::writeToFile($this->tmpXMLFilepath, $fileWriteMode, $this->XMLBuffer[1]);
        } else {
            FilesHelper::writeToFile($this->tmpXMLFilepath, $fileWriteMode, $this->XMLBuffer);
        }
    }

    /**
     * @param $filePath
     * @throws ODT2XHTMLException
     */
    protected function setupODTFileInfo($filePath)
    {
        $info = pathinfo($filePath);
        $this->ODTFileExtension = isset($info['extension']) ? $info['extension'] : '';
        $this->ODTFileName = isset($info['filename']) ? $info['filename'] : '';
        $this->ODTFileBasename = isset($info['basename']) ? $info['basename'] : '';

        if (!in_array($this->ODTFileExtension, self::$ODT_Extensions_List)) {
            $this->throwError("Incorrect file extension: " . $this->ODTFileExtension);
        }

        if (!in_array($this->ODTFileExtension, self::$ODT_Extensions_List)) {
            $this->throwError("Incorrect file extension: " . $this->ODTFileExtension);
        }

        if (!$this->validateMIMEType($filePath)) {
            $this->throwError("Incorrect file MIME type!");
        }

        if (empty($this->ODTFileName)) {
            $this->throwError("Empty input file name!!");
        }
    }

    /**
     * @param $itemName
     * @return string|string[]|null
     */
    protected function replaceItemContent($itemName)
    {
        if ($itemName === 'open_element_xml4odt') {
            return '<office:document xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0">';
        }

        if ($itemName === 'open_element_xml4sxw') {
            return '<office:document xmlns:office="http://openoffice.org/2000/office">';
        }

        if ($itemName === 'link_css') {
            $this->search = '/<style type="text\/css">(.*)<\/style>/s';
            return preg_replace($this->search, $this->getCSSCode(), $this->HTMLBuffer);
        }

        if ($itemName === 'title') {
            $this->search = '/<head>/s';
            return preg_replace($this->search, $this->getHTMLTitle(), $this->HTMLBuffer);
        }

        switch ($itemName) {
            case 'header' :
                $this->search = '!<office:forms(.*?)/>!';
                $this->replace = '<office:forms$1/>' . $this->header;
                $this->subject = $this->XMLBuffer;
                return preg_replace($this->search, $this->replace, $this->subject);
            case 'img_sxw' :
                $this->search = '!xlink:href="\#Pictures/([.a-zA-Z_0-9]*)"!s';
                return preg_replace_callback($this->search, [$this, 'mk_xlink_href'], $this->XMLBuffer);
            case 'img_odt' :
                $this->search = '#xlink:href="Pictures/([.a-zA-Z_0-9]*)"#s';
                return preg_replace_callback($this->search, [$this, 'mk_xlink_href'], $this->XMLBuffer);
            case 'analyze_attribute' :
                $this->search = '/="(.*?)"/s';
                return preg_replace_callback($this->search, [$this, 'analyze_attribute'], $this->XMLBuffer);
            case 'reference_mark' :
                $this->search = '/<text:reference-mark-start text:name="(.*)"\/>(.*)<text:reference-mark-end text:name="(.*)"\/>/SU';
                $this->replace = '<text:reference-mark text:name="$1">$2</text:reference-mark>';
                return preg_replace($this->search, $this->replace, $this->XMLBuffer);
        }
    }

    /*** search text in position indice or exposant and transform it ***/
    protected function rewrite_position()
    {
        # search styles text-position
        switch ($this->ODTFileExtension) {

            case 'odt' :
            case 'ott' :
                $pattern = '`<style:style style:name="T([0-9]+)" style:family="text"><style:text-properties style:text-position="(.*?)"/></style:style>`es';
                if (preg_match_all($pattern, $this->XMLBuffer, $matches)) {
                    $this->make_position($matches);
                }
                break;

            case 'sxw' :
            case 'stw' :
                $pattern = '`<style:style style:name="T([0-9]+)" style:family="text"><style:properties style:text-position="(.*?)"/></style:style>`es';
                if (preg_match_all($pattern, $this->XMLBuffer, $matches)) {
                    $this->make_position($matches);
                }
                break;
        }

        # search text relative to style text-position
        $pattern = '`<text:span text:style-name="T([0-9]+)">(.*?)</text:span>`es';
        if (!empty($this->position) && preg_match_all($pattern, $this->XMLBuffer, $matches)) {
            foreach ($matches[1] as $key => $value) {

                if (in_array($value, $this->position['name'])) {
                    foreach ($this->position['name'] as $key2 => $value2) {

                        if ($value2 == $value) {
                            # build search text:span
                            $this->position['search'][$key2] = '<text:span text:style-name="T' . $this->position['name'][$key2] . '">';
                            $this->position['search'][$key2] .= $matches[2][$key];
                            $this->position['search'][$key2] .= '</text:span>';

                            # build replace text:
                            $this->position['replace'][$key2] = '<text:' . $this->position['string'][$key2] . ' text:style-name="T' . $this->position['name'][$key2] . '">';
                            $this->position['replace'][$key2] .= $matches[2][$key];
                            $this->position['replace'][$key2] .= '</text:' . $this->position['string'][$key2] . '>';
                        }

                    }
                }
            }
        }

        # replace search text position par replace text position
        if (!empty($this->position['search']) && is_array($this->position['search'])) {
            foreach ($this->position['search'] as $key => $value) {
                $this->XMLBuffer = str_replace($value, $this->position['replace'][$key], $this->XMLBuffer);
            }
        }
    }

    /*** transform values cm in px ***/
    protected function analyze_attribute($matchGroups)
    {
        $foundAttribute = $matchGroups[1];

        if (strpos($foundAttribute, 'cm') === false) {
            $resultAttribute = $foundAttribute;
        } else {
            if (strpos($foundAttribute, ' ') !== false) {
                $x = explode(' ', $foundAttribute);
            }

            if (empty($x) || !is_array($x)) {
                $resultAttribute = round(floatval($foundAttribute) * self::SM_TO_PIX_COEF);
                $resultAttribute = $resultAttribute . 'px';
            } else {
                foreach ($x as $k => $v) {

                    if (preg_match('|cm$|', $v)) {
                        $v = round(floatval($v) * self::SM_TO_PIX_COEF);
                        $x[$k] = $v . 'px';
                    }

                    for ($i = 0; $i < count($x); $i++) {
                        if ($i == 0) $resultAttribute = $x[$i];
                        else $resultAttribute .= ' ' . $x[$i];
                    }

                }
            }
        }

        return '="' . $resultAttribute . '"';
    }


    /*** make new file xml with ODT2XHTML files xml ***/
    protected function createXML()
    {
        $this->addXMLTag('opener');

        /*** build corpus xml: meta, styles, content ***/
        foreach (self::$XML_CORPUS as $value) {
            $XMLFileName = $this->tmpBaseFilepath . '/' . $value . '.xml';
            $this->addXMLTag($value, $XMLFileName);
        }

        $this->addXMLTag('closer');
    }

    protected function createCSSFile($filePath)
    {
        if (empty($this->HTMLBuffer)) {
            return;
        }

        $pattern = '/<style type="text\/css">(.*)<\/style>/s';
        if (preg_match_all($pattern, $this->HTMLBuffer, $matches)) {
            $buffer = trim($matches[1][0]);
            FilesHelper::writeToFile($filePath, 'w', $buffer);
        }

        $this->HTMLBuffer = $this->replaceItemContent('link_css');
    }


    /**
     * @return string
     */
    protected function getCSSCode()
    {
        return '<link rel="stylesheet" href="' . $this->outCSSFileName . '" type="text/css" media="screen" title="Default" />';
    }

    /*** modify title code html ***/
    protected function getHTMLTitle()
    {
        $this->title = '<head>' . "\n\t";
        $this->title .= '<title>&quot;';
        $this->title .= $this->ODTFileBasename;
        $this->title .= '&quot;';
        $this->title .= '</title>';
        return $this->title;
    }

    /*** make code html to xlink:href ***/
    protected function mk_xlink_href($matchGroups)
    {
        $imgFileName = $matchGroups[1];
        return 'xlink:href="' . $this->outHTMLImgRelativePath . '/' . $imgFileName . '"';
    }

    /***
     * this function is run by method rewrite_position(),
     * to create an array $this->position
     ***/
    protected function make_position($match)
    {
        if (!empty($match) && is_array($match)) {
            foreach ($match[1] as $key => $value) {

                $this->position['name'][$key] = $value;
                $this->position['string'][$key] = substr($match[2][$key], 0, 3);

            }
        }
    }

    /*** make directory image and moving images ***/
    protected function moveImages()
    {
        if (!file_exists($this->tmpImagesPath)) {
            return;
        }

        if (!$this->handle = opendir($this->tmpImagesPath)) {
            $this->throwError('Cannot open tmp folder ' . $this->tmpImagesPath);
        }

        while (false !== $imgFilename = readdir($this->handle)) {
            if (FilesHelper::doesFileExist($this->tmpImagesPath . '/' . $imgFilename)) {
                /*** move img at temp directory to img directory ***/
                $sourcePath = $this->tmpImagesPath . '/' . $imgFilename;
                $destPath = $this->outHTMLImgPath . '/' . $imgFilename;
                if (!rename($sourcePath, $destPath)) {
                    $this->throwError("Cannot move images from $sourcePath to $destPath" . $imgFilename);
                }

                chmod($this->outHTMLImgPath . '/' . $imgFilename, 0644);
            }
        }
        closedir($this->handle);
    }

    /*** move icon ***/
    protected function addIcons()
    {
        $iconsPath = __DIR__ . self::ICONS_PATH;
        foreach (self::$ICONS_LIST as $item) {
            $sourcePath = $iconsPath . $item;
            $destPath = $this->outHTMLImgPath . '/' . $item;
            if (!copy($sourcePath, $destPath)) {
                $this->throwError("Cannot copy icon from $sourcePath to $destPath");
            }
        }
    }


    protected function createHTMLFile($HTMLFilePath, $shouldMakeCSSFile = false, $CSSFilePath = '')
    {
        if (empty($this->HTMLBuffer)) {
            return;
        }

        $this->HTMLBuffer = $this->replaceItemContent('title');

        if ($shouldMakeCSSFile) {
            $this->createCSSFile($CSSFilePath);
        }

        FilesHelper::writeToFile($HTMLFilePath, 'w', $this->HTMLBuffer);
    }

    /**
     * @param $file
     * @return bool
     */
    protected function validateMIMEType($file)
    {
        $fileDataMimeType = FilesHelper::getFileMIMEInfo($file, 'mime_type');
        if (empty($fileDataMimeType)) {
            return true;
        }

        $fileExtMimeType = isset(self::$ODT_MIME_Types_List[$this->ODTFileExtension]) ? self::$ODT_MIME_Types_List[$this->ODTFileExtension] : '';

        if (empty($fileExtMimeType) || (strcmp($fileExtMimeType, $fileDataMimeType) !== 0 && $fileDataMimeType !== 'application/octet-stream')) {
            return false;
        }

        return true;
    }

    /*** PHP Convert XML ***/
    protected function parseXMLWithXSLT($XSLFilePath)
    {
        $dom = new DOMDocument();
        $dom->load($XSLFilePath);
        $xslt = new XSLTProcessor();
        $xslt->importStylesheet($dom);

        if (!FilesHelper::doesFileExist($this->tmpXMLFilepath)) {
            return;
        }

        $dom = new DOMDocument();
        $dom->load($this->tmpXMLFilepath);

        $this->HTMLBuffer = html_entity_decode($xslt->transformToXML($dom));
    }

    /*** choose better file xsl segun file's extension ***/
    protected function getXSLFilePathForODTFileExtension($ODTFileExtension)
    {
        $XSLFilesPath = __DIR__ . self::XSLT_PATH;

        $xslFileFullPath = '';
        switch ($ODTFileExtension) {
            case 'odt' :
            case 'ott' :
                $xslFileFullPath = $XSLFilesPath . 'odt2xhtml.xsl';
                break;
            case 'sxw' :
            case 'stw' :
                $xslFileFullPath = $XSLFilesPath . 'sxw2xhtml.xsl';
                break;
        }

        return $xslFileFullPath;
    }

    /**
     * @param $message
     * @throws ODT2XHTMLException
     */
    protected function throwError($message)
    {
        throw new ODT2XHTMLException($message);
    }
}