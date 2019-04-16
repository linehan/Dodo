<?php
namespace domo

/**
 * NAMESPACE_*
 * ```````````
 * Strings denoting the various document namespaces
 */

const NAMESPACE_HTML = "http://www.w3.org/1999/xhtml";
const NAMESPACE_XML = "http://www.w3.org/XML/1998/namespace";
const NAMESPACE_XMLNS = "http://www.w3.org/2000/xmlns/";
const NAMESPACE_MATHML = "http://www.w3.org/1998/Math/MathML";
const NAMESPACE_SVG = "http://www.w3.org/2000/svg";
const NAMESPACE_XLINK = "http://www.w3.org/1999/xlink";

/**
 * Node types
 * ``````````
 * Integers enumerating the various specialized node types
 */

const ELEMENT_NODE = 1;
const ATTRIBUTE_NODE = 2;
const TEXT_NODE = 3;
const CDATA_SECTION_NODE = 4;
const ENTITY_REFERENCE_NODE = 5;
const ENTITY_NODE = 6;
const PROCESSING_INSTRUCTION_NODE = 7;
const COMMENT_NODE = 8;
const DOCUMENT_NODE = 9;
const DOCUMENT_TYPE_NODE = 10;
const DOCUMENT_FRAGMENT_NODE = 11;
const NOTATION_NODE = 12;

/**
 * DOCUMENT_POSITION_*
 * ```````````````````
 * Bitmasks indicating position of a node x relative to a node y.
 * Returned from x->compareDocumentPosition(y)
 */

/* x and y are not part of the same tree */
const DOCUMENT_POSITION_DISCONNECTED = 1;
/* y precedes x */
const DOCUMENT_POSITION_PRECEDING = 2;
/* y follows x */
const DOCUMENT_POSITION_FOLLOWING = 4;
/* y is an ancestor of x */
const DOCUMENT_POSITION_CONTAINS = 8;
/* y is a descendant of x */
const DOCUMENT_POSITION_CONTAINED_BY = 16;
/* whatever you need it to be */
const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

?>
