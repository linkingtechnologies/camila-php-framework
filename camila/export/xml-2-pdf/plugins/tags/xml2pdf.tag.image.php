<?php
/**
 * Image tag plugin file.
 * @filesource
 *
 * @author guillaume l. <guillaume@geelweb.org> 
 * @link http://www.geelweb.org geelweb-dot-org 
 * @license http://opensource.org/licenses/bsd-license.php BSD License 
 * @copyright Copyright � 2006, guillaume luchet
 * @version CVS: $Id: xml2pdf.tag.image.php,v 1.8 2007/01/05 23:07:31 geelweb Exp $
 * @package Xml2Pdf
 * @subpackage Tag
 *
 * @todo find width and height automaticly
 * @todo manag many image format, not just jpg, png.
 */

// doc {{{
/**
 * <image> tag.
 *
 * The tag image is used to add image in the document. You can write text on the
 * image using tags text or paragraph. Tag image can be used to add image on
 * header. 
 *
 * {@example image.xml}
 *
 * @author guillaume l. <guillaume@geelweb.org>
 * @link http://www.geelweb.org
 * @license http://opensource.org/licenses/bsd-license.php BSD License 
 * @copyright copyright � 2006, guillaume luchet
 * @version CVS: $Id: xml2pdf.tag.image.php,v 1.8 2007/01/05 23:07:31 geelweb Exp $
 * @package Xml2Pdf
 * @subpackage Tag
 * @tutorial Xml2Pdf/Xml2Pdf.Tag.image.pkg
 */ // }}}
Class xml2pdf_tag_image {
    // class properties {{{

    /**
     * image file name.
     * @var string
     */
    public $file = null;

    /**
     * top margin.
     * @var float
     */
    public $top = 0;

    /**
     * left margin.
     * @var float
     */
    public $left = 0;

    /**
     * image width.
     * @var float
     */
    public $width = 0;

    /**
     * image height.
     * @var float
     */
    public $height = 0;

    /**
     * positioning mode.
     * @var string
     */
    public $position = 'relative';
    
    /**
     * image's type
     * @var string
     */
    public $type = '';
    
    /**
     * temp file created for attachment, cleaned up after render.
     * @var string|null
     */
    private $_attachmentTmp = null;

    /**
     * parent tag
     * @var object
     */
    private $_parent;

    protected $pdf;
    // }}}
    // xml2pdf_tag_image::__construct() {{{

    public $content = '';
    
    /**
     * Constructor.
     *
     * @param array $tagProperties tag properties
     * @param object $parent Object Xml2PfdTag
     * @return void
     */
    public function __construct($tagProperties, $parent=false) {
        if(isset($tagProperties['FILE'])) {
            $this->file = $tagProperties['FILE'];

            if (strncmp($this->file, 'attachment://', 13) === 0) {
                $this->file = $this->_resolveAttachment(substr($this->file, 13));
            } elseif (!file_exists($this->file)) {
                global $_CAMILA;
                $file = CAMILA_TMPL_DIR . '/images/' . $_CAMILA['lang'] . '/' . $this->file;
                if (file_exists($file) && filesize($file)>0)
                    $this->file = $file;
                else
                    $this->file = '';
            }
        }
        if(isset($tagProperties['WIDTH'])) {
            $this->width = $tagProperties['WIDTH'];
        }
        if(isset($tagProperties['HEIGHT'])) {
            $this->height = $tagProperties['HEIGHT'];
        }
        if(isset($tagProperties['TOP'])) {
            $this->top = $this->mathEval($tagProperties['TOP']);
        }
        if(isset($tagProperties['LEFT'])) {
            $this->left = $this->mathEval($tagProperties['LEFT']);
        }
        if(isset($tagProperties['POSITION'])) {
            $this->position = $tagProperties['POSITION'];
        }
        if(isset($tagProperties['TYPE'])) {
            $this->type = $tagProperties['TYPE'];
        }

        if(isset($tagProperties['CONTENT'])) {
            $this->content = $tagProperties['CONTENT'];
        }

        $this->_parent = $parent;    
        $this->pdf = Pdf::singleton();
   }

    // }}}
   // xml2pdf_tag_image::addContent(string) {{{

    /**
     * Add content.
     *
     * @return void
     */
    public function addContent($content) {
        $this->file = base64_decode($content);
    } 
    
    // }}}
    // xml2pdf_tag_image::close() {{{
    
    /**
     * close the tag.
     *
     * @return void
     */
    public function close() {
        if (is_a($this->_parent, 'xml2pdf_tag_header')) {
                $this->_parent->elements[] = $this;
        } else {
            // Displaying the image
            if($this->position=='relative') {
                $this->left += $this->pdf->GetX();
                $this->top += $this->pdf->GetY();
            }

            if ($this->content!='') {
                $this->file = tempnam(CAMILA_TMP_DIR, 'img');
                $handle = fopen($this->file, 'wb');
                fwrite($handle, base64_decode($this->content));
                fclose($handle);
            }
			

			if ($this->file != '')
			{
            $this->pdf->Image((string)$this->file, $this->left, $this->top, 
                $this->width, $this->height, $this->type);
			}

            if ($this->content!='') {
                @unlink($this->file);
            }

            if ($this->_attachmentTmp !== null) {
                @unlink($this->_attachmentTmp);
                $this->_attachmentTmp = null;
            }

        }
 
    }

    // }}}

    /**
     * Resolve an attachment:// URI to a temp file with the correct extension.
     *
     * URI format: attachment://<table>/<uuid>
     * Type is read from <uuid>.meta; falls back to magic-byte detection.
     * Returns path to a temp file (caller must unlink it via $_attachmentTmp).
     *
     * @param string $uri  everything after "attachment://"
     * @return string  path to temp file, or '' if attachment not found/unsupported
     */
    private function _resolveAttachment($uri) {
        $parts = explode('/', $uri, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return '';
        }
        list($table, $id) = $parts;

        $base = defined('CAMILA_FM_ROOTDIR') ? CAMILA_FM_ROOTDIR : '';
        if ($base === '') {
            return '';
        }

        $bin  = $base . '/attachments/' . $table . '/' . $id . '.bin';
        $meta = $base . '/attachments/' . $table . '/' . $id . '.meta';

        if (!file_exists($bin) || filesize($bin) === 0) {
            return '';
        }

        // resolve extension from .meta
        $ext = '';
        if (file_exists($meta)) {
            $mime = trim(file_get_contents($meta));
            $map  = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg',
                     'image/png'  => 'png', 'image/gif' => 'gif'];
            if (isset($map[$mime])) {
                $ext = $map[$mime];
            }
        }

        // fallback: detect from magic bytes
        if ($ext === '') {
            $header = file_get_contents($bin, false, null, 0, 8);
            if (substr($header, 0, 3) === "\xff\xd8\xff") {
                $ext = 'jpg';
            } elseif (substr($header, 0, 4) === "\x89PNG") {
                $ext = 'png';
            } elseif (substr($header, 0, 3) === 'GIF') {
                $ext = 'gif';
            } else {
                return '';
            }
        }

        // copy to temp file with proper extension so FPDF recognises it
        $tmpBase = tempnam(defined('CAMILA_TMP_DIR') ? CAMILA_TMP_DIR : sys_get_temp_dir(), 'img');
        $tmp = $tmpBase . '.' . $ext;
        if (!copy($bin, $tmp)) {
            return '';
        }


        $this->_attachmentTmp = $tmp;
        $this->type = $ext;
        return $tmp;
    }

    function mathEval($equation) { 
        $equation = preg_replace("/[^0-9+\-.*\/()%]/","",$equation); 
        // fix percentage calcul when percentage value < 10 
        $equation = preg_replace("/([+-])([0-9]{1})(%)/","*(1\$1.0\$2)",$equation); 
        // calc percentage 
        $equation = preg_replace("/([+-])([0-9]+)(%)/","*(1\$1.\$2)",$equation); 
        // you could use str_replace on this next line 
        // if you really, really want to fine-tune this equation 
        $equation = preg_replace("/([0-9]+)(%)/",".\$1",$equation); 
        if ( $equation == "" ) { 
            $return = 0; 
        } else { 
            eval("\$return=" . $equation . ";" ); 
        }
        return $return; 
    } 

}
?>
