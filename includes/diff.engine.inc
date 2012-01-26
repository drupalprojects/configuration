<?php

/**
 * "Unified" diff formatter.
 *
 * This class formats the diff in classic "unified diff" format.
 */
class UnifiedDiffFormatter extends DiffFormatter
{
    function UnifiedDiffFormatter($context_lines = 4) {
        $this->leading_context_lines = $context_lines;
        $this->trailing_context_lines = $context_lines;
    }

    function _block_header($xbeg, $xlen, $ybeg, $ylen) {
        if ($xlen != 1)
            $xbeg .= "," . $xlen;
        if ($ylen != 1)
            $ybeg .= "," . $ylen;
        return "@@ -$xbeg +$ybeg @@\n";
    }

    function _added($lines) {
        $this->_lines($lines, "+");
    }
    function _deleted($lines) {
        $this->_lines($lines, "-");
    }
    function _changed($orig, $final) {
        $this->_deleted($orig);
        $this->_added($final);
    }
}