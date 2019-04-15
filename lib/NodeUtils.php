<?php
/**
 * PORT NOTES
 *
 * This code page consists entirely of serializeOne(), which began
 * as a private method on the Node object, but was moved to its own
 * code page for the following reason:
 *
 *      The `serializeOne()` function used to live on the `Node.prototype`
 *      as a private method `Node#_serializeOne(child)`, however that requires
 *      a megamorphic property access `this._serializeOne` just to get to the
 *      method, and this is being done on lots of different `Node` subclasses,
 *      which puts a lot of pressure on V8's megamorphic stub cache. So by
 *      moving the helper off of the `Node.prototype` and into a separate
 *      function in this helper module, we get a monomorphic property access
 *      `NodeUtils.serializeOne` to get to the function and reduce pressure
 *      on the megamorphic stub cache.
 *      See https://github.com/fgnass/domino/pull/142 for more information.
 */

namespace DOM\NodeUtils;

$hasRawContent = array(
        "STYLE" => true,
        "SCRIPT" => true,
        "XMP" => true,
        "IFRAME" => true,
        "NOEMBED" => true,
        "NOFRAMES" => true,
        "PLAINTEXT" => true
);

$emptyElements = array(
        "area" => true,
        "base" => true,
        "basefont" => true,
        "bgsound" => true,
        "br" => true,
        "col" => true,
        "embed" => true,
        "frame" => true,
        "hr" => true,
        "img" => true,
        "input" => true,
        "keygen" => true,
        "link" => true,
        "meta" => true,
        "param" => true,
        "source" => true,
        "track" => true,
        "wbr" => true
);

$extraNewLine = array(
        /* Removed in https://github.com/whatwg/html/issues/944 */
        /*
        "pre" => true,
        "textarea" => true,
        "listing" => true
        */
);

function escape($s)
{
        return str_replace(
                /* PORT: PHP7: \u{00a0} */
                /*
                 * NOTE: '&'=>'&amp;' must come first! Processing done LTR,
                 * so otherwise we will recursively replace the &'s.
                 */
                array("&","<",">","\u{00a0}"),
                array("&amp;", "&lt;", "&gt;", "&nbsp;"),
                $s
        );
}

function escapeAttr($s)
{
        return str_replace(
                array("&", "\"", "\u{00a0}"),
                array("&amp;", "&quot;", "&nbsp;"),
                $s
        );

        /* TODO: Is there still a fast path in PHP? (see NodeUtils.js) */
}

function attrname($a)
{
        $ns = $a->$namespaceURI;

        if (!$ns) {
                return $a->localName;
        }

        if ($ns === \DOM\util\NAMESPACE_XML) {
                return "xml:$a->localName";
        }
        if ($ns === \DOM\util\NAMESPACE_XLINK) {
                return "xlink:$a->localName";
        }
        if ($ns === \DOM\util\NAMESPACE_XMLNS) {
                if ($a->localName === 'xmlns') {
                        return 'xmlns';
                } else {
                        return "xmlns:$a->localName";
                }
        }

        return $a->name;
}

function serializeOne($kid, $parent)
{
        $s = "";

        switch ($kid->nodeType) {
        case 1: // ELEMENT_NODE
                $ns = $kid->namespaceURI;
                $html = ($ns === \DOM\util\NAMESPACE_HTML);

                if ($html || $ns === \DOM\util\NAMESPACE_SVG || $ns === \DOM\util\NAMESPACE_MATHML) {
                        $tagname = $kid->localName;
                } else {
                        $tagname = $kid->tagName;
                }

                $s += "<" + $tagname;

                for ($j=0, $k=$kid->_numattrs; $j<$k; $j++) {
                        /* _attr(): see L890 in Element.js */
                        $a = $kid->_attr($j);
                        $s += " " + attrname($a);

                        /*
                         * PORT: TODO: Need to ensure this value is NULL
                         * rather than undefined?
                         */
                        if ($a->value !== NULL) {
                                $s += '="' + escapeAttr($a->value) + '"';
                        }
                }

                $s += '>';

                if (!($html && isset($emptyElements[$tagname]))) {
                        /* PORT: TODO: Check this serialize function */
                        $ss = $kid->serialize();
                        if ($html && isset($extraNewLine[$tagname]) && $ss[0]==='\n') {
                                $s += '\n';
                        }
                        /* Serialize children and add end tag for all others */
                        $s += $ss;
                        $s += '</' + $tagname + '>';
                }
                break;

        case 3: // TEXT_NODE
        case 4: // CDATA_SECTION_NODE

                $parenttag;

                if ($parent->nodeType === 1 /*ELEMENT_NODE*/ && $parent->namespaceURI === \DOM\util\NAMESPACE_HTML) {
                        $parenttag = $parent->tagName;
                } else {
                        $parenttag = '';
                }

                if ($hasRawContent[$parenttag] || ($parenttag==='NOSCRIPT' && $parent->ownerDocument->_scripting_enabled)) {
                        $s += $kid->data;
                } else {
                        $s += escape($kid->data);
                }
                break;

        case 8: // COMMENT_NODE
                $s += '<!--' + $kid->data + '-->';
                break;

        case 7: // PROCESSING_INSTRUCTION_NODE
                $s += '<?' + $kid->target + ' ' + $kid->data + '?>';
                break;

        case 10: // DOCUMENT_TYPE_NODE
                $s += '<!DOCTYPE ' + $kid->name;

                if (false) {
                        // Latest HTML serialization spec omits the public/system ID
                        if ($kid->publicID) {
                                $s += ' PUBLIC "' + $kid->publicId + '"';
                        }

                        if ($kid->systemId) {
                                $s += ' "' + $kid->systemId + '"';
                        }
                }

                $s += '>';
                break;
        default:
                /* PORT: TODO: Write this function */
                \DOM\util\InvalidStateError();
        }

        return $s;
}
