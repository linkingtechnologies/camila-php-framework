<?php
/**
 * paragraph tag plugin file.
 *
 * @filesource
 *
 * @author guillaume l. <guillaume@geelweb.org>
 * @link http://www.geelweb.org
 * @license http://opensource.org/licenses/bsd-license.php BSD License 
 * @copyright copyright � 2006, guillaume luchet
 * @package Xml2Pdf
 * @subpackage Tag
 * @version CVS:m $Id: xml2pdf.tag.paragraph.php,v 1.3 2006/12/26 08:38:00 geelweb Exp $
 */

// dependances {{{
/**
 * parent class
 */
require_once('Xml2PdfTextTag.php');
// }}}
// doc {{{

/**
 * <paragraph> tag.
 *
 * This tag is used to wride text with options of displaying like
 * background, borders etc...
 *
 * {@example paragraph.xml}
 *
 * @author guillaume l. <guillaume@geelweb.org>
 * @link http://www.geelweb.org
 * @license http://opensource.org/licenses/bsd-license.php BSD License 
 * @copyright copyright � 2006, guillaume luchet
 * @package Xml2Pdf
 * @subpackage Tag
 * @tutorial Xml2Pdf/Xml2Pdf.Tag.paragraph.pkg
 * @version CVS $Id: xml2pdf.tag.paragraph.php,v 1.3 2006/12/26 08:38:00 geelweb Exp $
 */ // }}}
Class xml2pdf_tag_paragraph extends Xml2PdfTextTag {
    // class properties {{{
    
    /**
     * paragraph width.
     * @var float
     */
    public $width = null;
    
    /**
     * draw paragraph borders.
     * @var boolean
     */
    public $border = PDF_DEFAULT_PARAGRAPH_BORDER;
    
    /**
     * paragraph borders color.
     * @var string
     */
    public $borderColor = PDF_DEFAULT_PARAGRAPH_BORDERCOLOR;
    
    /**
     * fill the paragraph.
     * @var boolean
     */
    public $fill = PDF_DEFAULT_PARAGRAPH_FILL;
    
    /**
     * paragraph fill color.
     * @var string
     */
    public $fillColor = PDF_DEFAULT_PARAGRAPH_FILLCOLOR;
    
    /**
     * paragraph top margin.
     * @var float
     */
    public $top = 0;

    /**
     * paragraph left margin.
     * @var float
     */
    public $left = 0;

    /**
     * type of positioning.
     * @var string
     */
    public $position = PDF_DEFAULT_PARAGRAPH_POSITION;

    /**
     * paragraph alignment
     * @var string
     */
    public $align = PDF_DEFAULT_PARAGRAPH_ALIGN;

    /**
     * vertical alignment inside the box (top|middle|bottom).
     * @var string
     */
    public $valign = 'top';

    /**
     * box height in current unit — required for valign middle/bottom.
     * @var float
     */
    public $height = 0;

    /**
     * parent tag.
     * @var object
     */
    protected $parent = false;

    // }}}
    // xml2pdf_tag_paragraph::__construct() {{{
    
    /**
     * Constructor.
     *
     * Parse the tag attributes.
     *
     * @param array $tagProperties tag attributes
     * @param object $parent parent tag
     * @return void
     */
    public function __construct($tagProperties, $parent) {
        parent::__construct($tagProperties);
        if(is_a($parent, 'xml2pdf_tag_header') || is_a($parent, 'xml2pdf_tag_footer')) {
            $this->parent = $parent;
        }
        // parse properties
        if(isset($tagProperties['WIDTH'])) {
            $this->width = $tagProperties['WIDTH'];
        }
        if(isset($tagProperties['BORDER'])) {
            $this->border = $tagProperties['BORDER'];
        }
        if(isset($tagProperties['BORDERCOLOR'])) {
            $this->borderColor = $tagProperties['BORDERCOLOR'];
        }
        if(isset($tagProperties['FILL'])) {
            $this->fill = $tagProperties['FILL'];
        }
        if(isset($tagProperties['FILLCOLOR'])) {
            $this->fillColor = $tagProperties['FILLCOLOR'];
        }
        if(isset($tagProperties['TOP'])) {
            $this->top = $this->mathEval($tagProperties['TOP']);
        }
        if(isset($tagProperties['LEFT'])) {
            $this->left = $this->mathEval($tagProperties['LEFT']);
        }
        if(isset($tagProperties['HEIGHT'])) {
            $this->height = (float)$tagProperties['HEIGHT'];
        }
        if(isset($tagProperties['VALIGN'])) {
            $v = strtolower($tagProperties['VALIGN']);
            if(in_array($v, array('top','middle','bottom'))) {
                $this->valign = $v;
            }
        }
        if(isset($tagProperties['POSITION'])) {
            $this->position = (strtolower($tagProperties['POSITION'])=='absolute')?
                'absolute':'relative';
        }
        if(isset($tagProperties['ALIGN'])) {
            switch(strtoupper($tagProperties['ALIGN'])) {
                case 'L':
                case 'LEFT':
                    $this->align = 'L';
                    break;
                case 'R':
                case 'RIGHT':
                    $this->align = 'R';
                    break;
                case 'C':
                case 'CENTER':
                    $this->align = 'C';
                    break;
            }
        }

        if(isset($tagProperties['TEXTALIGN'])) {
            $this->textAlign = strtoupper($tagProperties['TEXTALIGN']);
        }

    }

    // }}}
    // xml2pdf_tag_paragraph::close() {{{
    
    /**
     * Render the paragraph or add it to the parent tag.
     * 
     * @return void
     */
    public function close() {
        if($this->parent) {
            $this->parent->elements[] = $this;
            return;
        }

        $style = str_replace ( 'U', '', $this->fontStyle);

        if (!array_key_exists($this->font.$style, $this->pdf->CoreFonts))
            $this->pdf->AddFont($this->font, $style);

        // calc the paragraph left and top using : align, position, left and top
        if($this->position=='absolute') {
            $x = $this->left;
            $y = $this->top;
        } else {
            $pageWidth = 210 - $this->pdf->lMargin - $this->pdf->rMargin;
            if($this->left) {
                $x = $this->pdf->GetX() + $this->left;
            } elseif($this->align) {
                if($this->align=='L') {
                    $x = $this->pdf->lMargin;
                } elseif($this->align=='R') {
                    $x = $pageWidth - $this->width + $this->pdf->rMargin;
                } elseif($this->align=='C') {
                    $x = ($pageWidth - $this->width) / 2 + $this->pdf->lMargin;
                }
            } else {
                $x = $this->pdf->GetX();
            }
            if($this->top) {
                $y = $this->pdf->GetY() + $this->top;
            } else {
                $y = $this->pdf->GetY();
            }
        }
        if(!$x) {
            $x = $this->pdf->lMargin;
        }
        if(!$y) {
            $y = $this->pdf->tMargin;
        }
        if($this->height > 0 && $this->valign !== 'top') {
            $style = str_replace('U', '', $this->fontStyle);
            $this->pdf->setFont($this->font, $style, $this->fontSize);
            $numLines = $this->_countLines($this->content, $this->width);
            $textHeight = max($numLines, 1) * (float)$this->lineHeight;
            $shift = ($this->valign === 'bottom')
                ? $this->height - $textHeight
                : ($this->height - $textHeight) / 2;
            $y += max(0, $shift);
        }

        $this->pdf->SetXY($x, $y);

        // set the paragraph font, fill, border and draw params
        $borderColor = Xml2Pdf::convertColor($this->borderColor);
        $this->pdf->SetDrawColor($borderColor['r'], $borderColor['g'], $borderColor['b']);
        $fillColor = Xml2Pdf::convertColor($this->fillColor);
        $this->pdf->SetFillColor($fillColor['r'], $fillColor['g'], $fillColor['b']);        
        $fontColor = Xml2Pdf::convertColor($this->fontColor);
        $this->pdf->setTextColor($fontColor['r'], $fontColor['g'], $fontColor['b']);

        $this->pdf->setFont($this->font, $this->fontStyle, $this->fontSize);

        if (intval($this->lineSpacing) > 0)
            $this->lineHeight = $this->pdf->FontSize * intval($this->lineSpacing) / 100;
                    
        // write the content
        $this->pdf->multicell($this->width, $this->lineHeight, $this->content,
                              $this->border, $this->textAlign, $this->fill);
    }

    // }}}
    // xml2pdf_tag_paragraph::_countLines() {{{

    private function _countLines($text, $width) {
        if($width <= 0 || trim($text) === '') return 1;
        $lines   = 1;
        $current = '';
        foreach(preg_split('/( |\n)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) as $token) {
            if($token === "\n") { $lines++; $current = ''; continue; }
            $test = ($current === '') ? $token : $current . $token;
            if($this->pdf->GetStringWidth($test) > $width) {
                $lines++;
                $current = $token;
            } else {
                $current = $test;
            }
        }
        return $lines;
    }

    // }}}
}
?>
