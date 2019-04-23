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

namespace domo;

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
        $ns = $a->namespaceURI();

        if (!$ns) {
                return $a->localName();
        }

        if ($ns === NAMESPACE_XML) {
                return 'xml:'.$a->localName();
        }
        if ($ns === NAMESPACE_XLINK) {
                return 'xlink:'.$a->localName();
        }
        if ($ns === NAMESPACE_XMLNS) {
                if ($a->localName() === 'xmlns') {
                        return 'xmlns';
                } else {
                        return 'xmlns:' . $a->localName();
                }
        }

        return $a->name();
}

function serializeOne($child, $parent)
{
        $s = "";

        switch ($child->_nodeType) {
        case ELEMENT_NODE: 
                $ns = $child->namespaceURI();
                $html = ($ns === NAMESPACE_HTML);

                if ($html || $ns === NAMESPACE_SVG || $ns === NAMESPACE_MATHML) {
                        $tagname = $child->localName();
                } else {
                        $tagname = $child->tagName();
                }

                $s += "<" + $tagname;

                foreach ($child->attributes) {
                        $s += " " + attrname($a);

                        /*
                         * PORT: TODO: Need to ensure this value is NULL
                         * rather than undefined?
                         */
                        if ($a->value() !== NULL) {
                                $s += '="' + escapeAttr($a->value()) + '"';
                        }
                }

                $s += '>';

                if (!($html && isset($emptyElements[$tagname]))) {
                        /* PORT: TODO: Check this serialize function */
                        $ss = $child->serialize();
                        if ($html && isset($extraNewLine[$tagname]) && $ss[0]==='\n') {
                                $s += '\n';
                        }
                        /* Serialize children and add end tag for all others */
                        $s += $ss;
                        $s += '</' + $tagname + '>';
                }
                break;

        case TEXT_NODE:
        case CDATA_SECTION_NODE: 
                if ($parent->_nodeType === ELEMENT_NODE && $parent->namespaceURI() === NAMESPACE_HTML) {
                        $parenttag = $parent->tagName();
                } else {
                        $parenttag = '';
                }

                if ($hasRawContent[$parenttag] || ($parenttag==='NOSCRIPT' && $parent->ownerDocument()->_scripting_enabled)) {
                        $s += $child->data();
                } else {
                        $s += escape($child->data());
                }
                break;

        case COMMENT_NODE:
                $s += '<!--' + $child->data() + '-->';
                break;

        case PROCESSING_INSTRUCTION_NODE: 
                $s += '<?' + $child->target() + ' ' + $kid->data() + '?>';
                break;

        case DOCUMENT_TYPE_NODE:
                $s += '<!DOCTYPE ' + $child->name();

                if (false) {
                        // Latest HTML serialization spec omits the public/system ID
                        if ($child->_publicID) {
                                $s += ' PUBLIC "' + $child->_publicId + '"';
                        }

                        if ($child->_systemId) {
                                $s += ' "' + $child->_systemId + '"';
                        }
                }

                $s += '>';
                break;
        default:
                \domo\error("InvalidState");
        }

        return $s;
}
