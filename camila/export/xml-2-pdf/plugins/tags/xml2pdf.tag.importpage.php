<?php
/**
 * Tag <importpage>
 *
 * Imports a page from an existing PDF and uses it as the background of the
 * current page. Place it immediately after <page>, before other content tags.
 *
 * Attributes:
 *   file  (required) — absolute path to the source PDF
 *   page  (optional) — page number to import, default 1
 *
 * Example:
 *   <importpage file="/var/www/html/templates/modulo.pdf" page="1" />
 *
 * Requires FPDI (setasign/fpdi). If FPDI is not loaded this tag is silently skipped.
 */

class xml2pdf_tag_importpage {

    public $content = '';

    private $file = '';
    private $page = 1;
    private $pdf;

    public function __construct($tagProperties, $parent = false) {
        $this->pdf = Pdf::singleton();

        if (isset($tagProperties['FILE'])) {
            $this->file = $tagProperties['FILE'];

            if (!file_exists($this->file) && defined('CAMILA_TMPL_DIR')) {
                global $_CAMILA;
                $lang = isset($_CAMILA['lang']) ? $_CAMILA['lang'] : '';
                $candidate = CAMILA_TMPL_DIR . '/' . $lang . '/' . $this->file;
                if (file_exists($candidate)) {
                    $this->file = $candidate;
                }
            }
        }
        if (isset($tagProperties['PAGE'])) {
            $this->page = (int)$tagProperties['PAGE'];
        }
    }

    public function addContent($content) {
        $this->content .= $content;
    }

    public function close() {
        if (!method_exists($this->pdf, 'setSourceFile')) {
            return;
        }

        if (empty($this->file) || !file_exists($this->file)) {
            trigger_error('xml2pdf_tag_importpage: file not found: ' . $this->file, E_USER_WARNING);
            return;
        }

        try {
            $this->pdf->setSourceFile($this->file);
            $tplId = $this->pdf->importPage($this->page);
            $this->pdf->useTemplate($tplId, 0, 0, null, null, true);
        } catch (\Exception $e) {
            trigger_error('xml2pdf_tag_importpage: ' . $e->getMessage(), E_USER_WARNING);
        }
    }
}
?>
