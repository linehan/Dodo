<?php
/******************************************************************************
 * whatwg.php
 * ----------
 * Contains lots of broken-out implementations of algorithms
 * described in WHATWG and other specifications.
 *
 * It was broken out so that the methods in the various classes
 * could be simpler, and to allow for re-use in other places.
 *
 * It also makes it easier to read and understand in isolation from
 * the context of a class, where there can be many conveniences that
 * affect the implementation.
 *
 * That said, it may be a problem having so much on this one page,
 * so perhaps we need to re-examine things.
 *
 ******************************************************************************/
namespace domo\whatwg;

require_once('Node.php');
require_once('Element.php');
require_once('Document.php');
require_once('Attr.php');
require_once('utilities.php');

/******************************************************************************
 * TREE PREDICATES AND MUTATION
 ******************************************************************************/

/* https://dom.spec.whatwg.org/#dom-node-comparedocumentposition */
function compare_document_position(\domo\Node $node1, \domo\Node $node2): int
{
        /* #1-#2 */
        if ($node1 === $node2) {
                return 0;
        }

        /* #3 */
        $attr1 = NULL;
        $attr2 = NULL;

        /* #4 */
        if ($node1->_nodeType === \domo\ATTRIBUTE_NODE) {
                $attr1 = $node1;
                $node1 = $attr1->ownerElement();
        }
        /* #5 */
        if ($node2->_nodeType === \domo\ATTRIBUTE_NODE) {
                $attr2 = $node2;
                $node2 = $attr2->ownerElement();

                if ($attr1 !== NULL && $node1 !== NULL && $node2 === $node1) {
                        foreach ($node2->attributes as $a) {
                                if ($a === $attr1) {
                                        return \domo\DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC + \domo\DOCUMENT_POSITION_PRECEDING;
                                }
                                if ($a === $attr2) {
                                        return \domo\DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC + \domo\DOCUMENT_POSITION_FOLLOWING;
                                }
                        }
                }
        }

        /* #6 */
        if ($node1 === NULL || $node2 === NULL || $node1->__node_document() !== $node2->__node_document() || $node1->__is_rooted() !== $node2->__is_rooted()) {
                /* UHH, in the spec this is supposed to add DOCUMENT_POSITION_PRECEDING or DOCUMENT_POSITION_FOLLOWING
                 * in some consistent way, usually based on pointer comparison, which we can't do here. Hmm. Domino
                 * just straight up omits it. This is stupid, the spec shouldn't ask this. */
                return (\domo\DOCUMENT_POSITION_DISCONNECTED + \domo\DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC);
        }

        /* #7 */
        $node1_ancestors = array();
        $node2_ancestors = array();
        for ($n = $node1->parentNode(); $n !== NULL; $n = $n->parentNode()) {
                $node1_ancestors[] = $n;
        }
        for ($n = $node2->parentNode(); $n !== NULL; $n = $n->parentNode()) {
                $node2_ancestors[] = $n;
        }

        if (in_array($node1, $node2_ancestors) && $attr1 === NULL) {
                return \domo\DOCUMENT_POSITION_CONTAINS + \domo\DOCUMENT_POSITION_PRECEDING;
        } else if ($node1 === $node2 && $attr2 !== NULL) {
                return \domo\DOCUMENT_POSITION_CONTAINS + \domo\DOCUMENT_POSITION_PRECEDING;
        }

        /* #8 */
        if (in_array($node2, $node1_ancestors) && $attr2 === NULL) {
                return \domo\DOCUMENT_POSITION_CONTAINED_BY + \domo\DOCUMENT_POSITION_FOLLOWING;
        } else if ($node1 === $node2 && $attr1 !== NULL) {
                return \domo\DOCUMENT_POSITION_CONTAINED_BY + \domo\DOCUMENT_POSITION_FOLLOWING;
        }

        /* #9 */
        $node1_ancestors = array_reverse($node1_ancestors);
        $node2_ancestors = array_reverse($node2_ancestors);
        $len = min(count($node1_ancestors), count($node2_ancestors));

        for ($i = 1; $i < $len; $i++) {
                if ($node1_ancestors[$i] !== $node2_ancestors[$i]) {
                        if ($node1_ancestors[$i]->__sibling_index() < $node2_ancestors[$i]->__sibling_index()) {
                                return \domo\DOCUMENT_POSITION_PRECEDING;
                        }
                }
        }

        #10
        return \domo\DOCUMENT_POSITION_FOLLOWING;
}

/*
 * DOM-LS Removes the 'prefix' and 'namespaceURI' attributes from
 * Node and places them only on Element and Attr.
 *
 * Due to the fact that an Attr (should) have an ownerElement,
 * these two algorithms only operate on Elements.
 *
 * The spec actually says that if an Attr has no ownerElement,
 * then the algorithm returns NULL.
 *
 * Anyway, they operate only on Elements.
 */
/* https://dom.spec.whatwg.org/#locate-a-namespace */
function locate_namespace(\domo\Node $node, ?string $prefix): ?string
{
        if ($prefix === '') {
                $prefix = NULL;
        }

        switch ($this->_nodeType) {
        case \domo\ENTITY_NODE:
        case \domo\NOTATION_NODE:
        case \domo\DOCUMENT_TYPE_NODE:
        case \domo\DOCUMENT_FRAGMENT_NODE:
                break;
        case \domo\ELEMENT_NODE:
                if ($node->namespaceURI()!==NULL && $node->prefix()===$prefix) {
                        return $node->namespaceURI();
                }
                foreach ($node->attributes as $a) {
                        if ($a->namespaceURI() === \domo\NAMESPACE_XMLNS) {
                                if (($a->prefix() === 'xmlns' && $a->localName() === $prefix)
                                ||  ($prefix === NULL && $a->prefix() === NULL && $a->localName() === 'xmlns')) {
                                        $val = $a->value();
                                        return ($val === "") ? NULL : $val;
                                }
                        }
                }
                break;
        case \domo\DOCUMENT_NODE:
                if ($this->_documentElement) {
                        return locate_namespace($this->_documentElement, $prefix);
                }
                break;
        case \domo\ATTRIBUTE_NODE:
                if ($this->_ownerElement) {
                        return locate_namespace($this->_ownerElement, $prefix);
                }
               break;
        default:
                if (NULL === ($parent = $node->parentElement())) {
                        return NULL;
                } else {
                        return locate_namespace($parent, $ns);
                }
        }

        return NULL;
}

/* https://dom.spec.whatwg.org/#locate-a-namespace-prefix */
function locate_prefix(\domo\Node $node, ?string $ns): ?string
{
        if ($ns === "" || $ns === NULL) {
                return NULL;
        }

        switch ($node->_nodeType) {
        case \domo\ENTITY_NODE:
        case \domo\NOTATION_NODE:
        case \domo\DOCUMENT_FRAGMENT_NODE:
        case \domo\DOCUMENT_TYPE_NODE:
                break;
        case \domo\ELEMENT_NODE:
                if ($node->namespaceURI()!==NULL && $node->namespaceURI()===$ns) {
                        return $node->prefix();
                }

                foreach ($node->attributes as $a) {
                        if ($a->prefix() === "xmlns" && $a->value() === $ns) {
                                return $a->localName();
                        }
                }
                break;
        case \domo\DOCUMENT_NODE:
                if ($node->_documentElement) {
                        return locate_prefix($node->_documentElement, $ns);
                }
                break;
        case  \domo\ATTRIBUTE_NODE:
                if ($node->_ownerElement) {
                        return locate_prefix($node->_ownerElement, $ns);
                }
                break;
        default:
                if (NULL === ($parent = $node->parentElement())) {
                        return NULL;
                } else {
                        return locate_prefix($parent, $ns);
                }
        }

        return NULL;
}

function insert_before_or_replace(\domo\Node $node, \domo\Node $parent, ?\domo\Node $before, bool $replace): void
{
        /*
         * TODO: FACTOR: $before is intended to always be non-NULL
         * if $replace is true, but I think that could fail unless
         * we encode it into the prototype, which is non-standard.
         * (we are combining the 'insert before' and 'replace' algos)
         */

        /******************* PRE-FLIGHT CHECKS *******************/

        if ($node === $before) {
                return;
        }

        if ($node instanceof \domo\DocumentFragment && $node->__is_rooted()) {
                \domo\error("HierarchyRequestError");
        }

        /******************** COMPUTE AN INDEX *******************/
        /* NOTE: MUST DO HERE BECAUSE STATE WILL CHANGE */

        if ($parent->_childNodes) {
                if ($before !== NULL) {
                        $ref_index = $before->__sibling_index();
                } else {
                        $ref_index = count($parent->_childNodes);
                }
                if ($node->_parentNode===$parent && $node->__sibling_index()<$ref_index) {
                        $ref_index--;
                }
        }

        $ref_node = $before ?? $parent->firstChild();

        /************ IF REPLACING, REMOVE OLD CHILD *************/

        if ($replace) {
                if ($before->__is_rooted()) {
                        $before->__node_document()->__mutate_remove($before);
                        $before->__uproot();
                }
                $before->_parentNode = NULL;
        }

        /************ IF BOTH ROOTED, FIRE MUTATIONS *************/

        $bothWereRooted = $node->__is_rooted() && $parent->__is_rooted();

        if ($bothWereRooted) {
                /* "soft remove" -- don't want to uproot it. */
                $node->_remove();
        } else {
                if ($node->_parentNode) {
                        $node->remove();
                }
        }

        /************** UPDATE THE NODE LIST DATA ***************/

        $insert = array();

        if ($node instanceof \domo\DocumentFragment) {
                for ($n=$node->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        $insert[] = $n; /* TODO: Needs to clone? */
                        $n->_parentNode = $parent;
                }
        } else {
                $insert[0] = $node; /* TODO: Needs to clone? */
                $insert[0]->_parentNode = $parent;
        }

        if (empty($insert)) {
                if ($replace) {
                        if ($ref_node !== NULL /* If you work it out, you'll find that this condition is equivalent to 'if $parent has children' */) {
                                \domo\ll_replace($ref_node, NULL);
                        }
                        if ($parent->_childNodes === NULL && $parent->_firstChild === $before) {
                                $parent->_firstChild = NULL;
                        }
                }
        } else {
                if ($ref_node !== NULL) {
                        if ($replace) {
                                \domo\ll_replace($ref_node, $insert[0]);
                        } else {
                                \domo\ll_insert_before($insert[0], $ref_node);
                        }
                }
                if ($parent->_childNodes !== NULL) {
                        if ($replace) {
                                array_splice($parent->_childNodes, $ref_index, 1, $insert);
                        } else {
                                array_splice($parent->_childNodes, $ref_index, 0, $insert);
                        }
                        foreach ($insert as $i => $n) {
                                $n->_index = $ref_index + $i;
                        }
                } else if ($parent->_firstChild === $before) {
                        $parent->_firstChild = $insert[0];
                }
        }

        /*********** EMPTY OUT THE DOCUMENT FRAGMENT ************/

        if ($node instanceof \domo\DocumentFragment) {
                /*
                 * TODO: Why? SPEC SAYS SO!
                 */
                if ($node->_childNodes) {
                        /* TODO PORT: easiest way to do this in PHP and preserves references */
                        $node->_childNodes = array();
                } else {
                        $node->_firstChild = NULL;
                }
        }

        /************ ROOT NODES AND FIRE MUTATION HANDLERS *************/

        $d = $parent->__node_document();

        if ($bothWereRooted) {
                $parent->__mod_time_update();
                $d->__mutate_move($insert[0]);
        } else {
                if ($parent->__is_rooted()) {
                        $parent->__mod_time_update();
                        foreach ($insert as $n) {
                                $n->__root($d);
                                $d->__mutate_insert($n);
                        }
                }
        }
}

/*
TODO: Look at the way these were implemented in the original;
there are some speedups esp in the way that you implement
things like "node has a doctype child that is not child
*/
function ensure_insert_valid(\domo\Node $node, \domo\Node $parent, ?\domo\Node $child): void
{
        /*
         * DOM-LS: #1: If parent is not a Document, DocumentFragment,
         * or Element node, throw a HierarchyRequestError.
         */
        switch ($parent->_nodeType) {
        case \domo\DOCUMENT_NODE:
        case \domo\DOCUMENT_FRAGMENT_NODE:
        case \domo\ELEMENT_NODE:
                break;
        default:
                \domo\error("HierarchyRequestError");
        }

        /*
         * DOM-LS #2: If node is a host-including inclusive ancestor
         * of parent, throw a HierarchyRequestError.
         */
        if ($node === $parent) {
                \domo\error("HierarchyRequestError");
        }
        if ($node->__node_document() === $parent->__node_document() && $node->__is_rooted() === $parent->__is_rooted()) {
                /*
                 * If the conditions didn't figure it out, then check
                 * by traversing parentNode chain.
                 */
                for ($n=$parent; $n!==NULL; $n=$n->parentNode()) {
                        if ($n === $node) {
                                \domo\error("HierarchyRequestError");
                        }
                }
        }

        /*
         * DOM-LS #3: If child is not null and its parent is not $parent, then
         * throw a NotFoundError
         */
        if ($child !== NULL && $child->_parentNode !== $parent) {
                \domo\error("NotFoundError");
        }

        /*
         * DOM-LS #4: If node is not a DocumentFragment, DocumentType,
         * Element, Text, ProcessingInstruction, or Comment Node,
         * throw a HierarchyRequestError.
         */
        switch ($node->_nodeType) {
        case \domo\DOCUMENT_FRAGMENT_NODE:
        case \domo\DOCUMENT_TYPE_NODE:
        case \domo\ELEMENT_NODE:
        case \domo\TEXT_NODE:
        case \domo\PROCESSING_INSTRUCTION_NODE:
        case \domo\COMMENT_NODE:
                break;
        default:
                \domo\error("HierarchyRequestError");
        }

        /*
         * DOM-LS #5. If either:
         *      -node is a Text and parent is a Document
         *      -node is a DocumentType and parent is not a Document
         * throw a HierarchyRequestError
         */
        if (($node->_nodeType === \domo\TEXT_NODE          && $parent->_nodeType === \domo\DOCUMENT_NODE)
        ||  ($node->_nodeType === \domo\DOCUMENT_TYPE_NODE && $parent->_nodeType !== \domo\DOCUMENT_NODE)) {
                \domo\error("HierarchyRequestError");
        }

        /*
         * DOM-LS #6: If parent is a Document, and any of the
         * statements below, switched on node, are true, throw a
         * HierarchyRequestError.
         */
        if ($parent->_nodeType !== \domo\DOCUMENT_NODE) {
                return;
        }

        switch ($node->_nodeType) {
        case \domo\DOCUMENT_FRAGMENT_NODE:
                /*
                 * DOM-LS #6a-1: If node has more than one
                 * Element child or has a Text child.
                 */
                $count_text = 0;
                $count_element = 0;

                for ($n=$node->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->_nodeType === \domo\TEXT_NODE) {
                                $count_text++;
                        }
                        if ($n->_nodeType === \domo\ELEMENT_NODE) {
                                $count_element++;
                        }
                        if ($count_text > 0 && $count_element > 1) {
                                \domo\error("HierarchyRequestError");
                                // TODO: break ? return ?
                        }
                }
                /*
                 * DOM-LS #6a-2: If node has one Element
                 * child and either:
                 */
                if ($count_element === 1) {
                        /* DOM-LS #6a-2a: child is a DocumentType */
                        if ($child !== NULL && $child->_nodeType === \domo\DOCUMENT_TYPE_NODE) {
                               \domo\error("HierarchyRequestError");
                        }
                        /*
                         * DOM-LS #6a-2b: child is not NULL and a
                         * DocumentType is following child.
                         */
                        if ($child !== NULL) {
                                for ($n=$child->nextSibling(); $n!==NULL; $n=$n->nextSibling()) {
                                        if ($n->_nodeType === \domo\DOCUMENT_TYPE_NODE) {
                                                \domo\error("HierarchyRequestError");
                                        }
                                }
                        }
                        /* DOM-LS #6a-2c: parent has an Element child */
                        for ($n=$parent->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                                if ($n->_nodeType === \domo\ELEMENT_NODE) {
                                        \domo\error("HierarchyRequestError");
                                }
                        }
                }
                break;
        case \domo\ELEMENT_NODE:
                /* DOM-LS #6b-1: child is a DocumentType */
                if ($child !== NULL && $child->_nodeType === \domo\DOCUMENT_TYPE_NODE) {
                       \domo\error("HierarchyRequestError");
                }
                /* DOM-LS #6b-2: child not NULL and DocumentType is following child. */
                if ($child !== NULL) {
                        for ($n=$child->nextSibling(); $n!==NULL; $n=$n->nextSibling()) {
                                if ($n->_nodeType === \domo\DOCUMENT_TYPE_NODE) {
                                        \domo\error("HierarchyRequestError");
                                }
                        }
                }
                /* DOM-LS #6b-3: parent has an Element child */
                for ($n=$parent->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->_nodeType === \domo\ELEMENT_NODE) {
                                \domo\error("HierarchyRequestError");
                        }
                }
                break;
        case \domo\DOCUMENT_TYPE_NODE:
                /* DOM-LS #6c-1: parent has a DocumentType child */
                for ($n=$parent->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->_nodeType === \domo\DOCUMENT_TYPE_NODE) {
                                \domo\error("HierarchyRequestError");
                        }
                }
                /*
                 * DOM-LS #6c-2: child is not NULL and an Element
                 * is preceding child,
                 */
                if ($child !== NULL) {
                        for ($n=$child->previousSibling(); $n!==NULL; $n=$n->previousSibling()) {
                                if ($n->_nodeType === \domo\ELEMENT_NODE) {
                                        \domo\error("HierarchyRequestError");
                                }
                        }
                }
                /*
                 * DOM-LS #6c-3: child is NULL and parent has
                 * an Element child.
                 */
                if ($child === NULL) {
                        for ($n=$parent->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                                if ($n->_nodeType === \domo\ELEMENT_NODE) {
                                        \domo\error("HierarchyRequestError");
                                }
                        }
                }

                break;
        }
}

function ensure_replace_valid(\domo\Node $node, \domo\Node $parent, \domo\Node $child): void
{
        /*
         * DOM-LS: #1: If parent is not a Document, DocumentFragment,
         * or Element node, throw a HierarchyRequestError.
         */
        switch ($parent->nodeType) {
        case \domo\DOCUMENT_NODE:
        case \domo\DOCUMENT_FRAGMENT_NODE:
        case \domo\ELEMENT_NODE:
                break;
        default:
                \domo\error("HierarchyRequestError");
        }

        /*
         * DOM-LS #2: If node is a host-including inclusive ancestor
         * of parent, throw a HierarchyRequestError.
         */
        if ($node === $parent) {
                \domo\error("HierarchyRequestError");
        }
        if ($node->__node_document() === $parent->__node_document() && $node->__is_rooted() === $parent->__is_rooted()) {
                /*
                 * If the conditions didn't figure it out, then check
                 * by traversing parentNode chain.
                 */
                for ($n=$parent; $n!==NULL; $n=$n->parentNode()) {
                        if ($n === $node) {
                                \domo\error("HierarchyRequestError");
                        }
                }
        }

        /*
         * DOM-LS #3: If child's parentNode is not parent
         * throw a NotFoundError
         */
        if ($child->_parentNode !== $parent) {
                \domo\error("NotFoundError");
        }

        /*
         * DOM-LS #4: If node is not a DocumentFragment, DocumentType,
         * Element, Text, ProcessingInstruction, or Comment Node,
         * throw a HierarchyRequestError.
         */
        switch ($node->_nodeType) {
        case \domo\DOCUMENT_FRAGMENT_NODE:
        case \domo\DOCUMENT_TYPE_NODE:
        case \domo\ELEMENT_NODE:
        case \domo\TEXT_NODE:
        case \domo\PROCESSING_INSTRUCTION_NODE:
        case \domo\COMMENT_NODE:
                break;
        default:
                \domo\error("HierarchyRequestError");
        }

        /*
         * DOM-LS #5. If either:
         *      -node is a Text and parent is a Document
         *      -node is a DocumentType and parent is not a Document
         * throw a HierarchyRequestError
         */
        if (($node->_nodeType === \domo\TEXT_NODE          && $parent->_nodeType === \domo\DOCUMENT_NODE)
        ||  ($node->_nodeType === \domo\DOCUMENT_TYPE_NODE && $parent->_nodeType !== \domo\DOCUMENT_NODE)) {
                \domo\error("HierarchyRequestError");
        }

        /*
         * DOM-LS #6: If parent is a Document, and any of the
         * statements below, switched on node, are true, throw a
         * HierarchyRequestError.
         */
        if ($parent->_nodeType !== \domo\DOCUMENT_NODE) {
                return;
        }

        switch ($node->_nodeType) {
        case \domo\DOCUMENT_FRAGMENT_NODE:
                /*
                 * #6a-1: If node has more than one Element child
                 * or has a Text child.
                 */
                $count_text = 0;
                $count_element = 0;

                for ($n=$node->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->_nodeType === \domo\TEXT_NODE) {
                                $count_text++;
                        }
                        if ($n->_nodeType === \domo\ELEMENT_NODE) {
                                $count_element++;
                        }
                        if ($count_text > 0 && $count_element > 1) {
                                \domo\error("HierarchyRequestError");
                        }
                }
                /* #6a-2: If node has one Element child and either: */
                if ($count_element === 1) {
                        /* #6a-2a: parent has an Element child that is not child */
                        for ($n=$parent->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                                if ($n->_nodeType === \domo\ELEMENT_NODE && $n !== $child) {
                                        \domo\error("HierarchyRequestError");
                                }
                        }
                        /* #6a-2b: a DocumentType is following child. */
                        for ($n=$child->nextSibling(); $n!==NULL; $n=$n->nextSibling()) {
                                if ($n->_nodeType === \domo\DOCUMENT_TYPE_NODE) {
                                        \domo\error("HierarchyRequestError");
                                }
                        }
                }
                break;
        case \domo\ELEMENT_NODE:
                /* #6b-1: parent has an Element child that is not child */
                for ($n=$parent->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->_nodeType === \domo\ELEMENT_NODE && $n !== $child) {
                                \domo\error("HierarchyRequestError");
                        }
                }
                /* #6b-2: DocumentType is following child. */
                for ($n=$child->nextSibling(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->nodeType === \domo\DOCUMENT_TYPE_NODE) {
                                \domo\error("HierarchyRequestError");
                        }
                }
                break;
        case \domo\DOCUMENT_TYPE_NODE:
                /* #6c-1: parent has a DocumentType child */
                for ($n=$parent->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->_nodeType === \domo\DOCUMENT_TYPE_NODE) {
                                \domo\error("HierarchyRequestError");
                        }
                }
                /* #6c-2: an Element is preceding child */
                for ($n=$child->previousSibling(); $n!==NULL; $n=$n->previousSibling()) {
                        if ($n->_nodeType === \domo\ELEMENT_NODE) {
                                \domo\error("HierarchyRequestError");
                        }
                }
                break;
        }
}

/******************************************************************************
 * SERIALIZATION
 ******************************************************************************/

/**
 * PORT NOTES
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
/* http://www.whatwg.org/specs/web-apps/current-work/multipage/the-end.html#serializing-html-fragments */
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

function _helper_escape($s)
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

function _helper_escapeAttr($s)
{
        return str_replace(
                array("&", "\"", "\u{00a0}"),
                array("&amp;", "&quot;", "&nbsp;"),
                $s
        );

        /* TODO: Is there still a fast path in PHP? (see NodeUtils.js) */
}

function _helper_attrname(\domo\Attr $a)
{
        $ns = $a->namespaceURI();

        if (!$ns) {
                return $a->localName();
        }

        if ($ns === \domo\NAMESPACE_XML) {
                return 'xml:'.$a->localName();
        }
        if ($ns === \domo\NAMESPACE_XLINK) {
                return 'xlink:'.$a->localName();
        }
        if ($ns === \domo\NAMESPACE_XMLNS) {
                if ($a->localName() === 'xmlns') {
                        return 'xmlns';
                } else {
                        return 'xmlns:' . $a->localName();
                }
        }

        return $a->name();
}

function serialize_node(\domo\Node $child, \domo\Node $parent)
{
        global $hasRawContent;
        global $emptyElements;
        global $extraNewLine;

        $s = "";

        switch ($child->_nodeType) {
        case \domo\ELEMENT_NODE:
                $ns = $child->namespaceURI();
                $html = ($ns === \domo\NAMESPACE_HTML);

                if ($html || $ns === \domo\NAMESPACE_SVG || $ns === \domo\NAMESPACE_MATHML) {
                        $tagname = $child->localName();
                } else {
                        $tagname = $child->tagName();
                }

                $s .= '<' . $tagname;

                foreach ($child->attributes as $a) {
                        $s .= ' ' . _helper_attrname($a);

                        /*
                         * PORT: TODO: Need to ensure this value is NULL
                         * rather than undefined?
                         */
                        if ($a->value() !== NULL) {
                                $s .= '="' . _helper_escapeAttr($a->value()) . '"';
                        }
                }

                $s .= '>';

                if (!($html && isset($emptyElements[$tagname]))) {
                        /* PORT: TODO: Check this serialize function */
                        $ss = $child->__serialize();
                        if ($html && isset($extraNewLine[$tagname]) && $ss[0]==='\n') {
                                $s .= '\n';
                        }
                        /* Serialize children and add end tag for all others */
                        $s .= $ss;
                        $s .= '</' . $tagname . '>';
                }
                break;

        case \domo\TEXT_NODE:
        case \domo\CDATA_SECTION_NODE:
                if ($parent->_nodeType === \domo\ELEMENT_NODE && $parent->namespaceURI() === \domo\NAMESPACE_HTML) {
                        $parenttag = $parent->tagName();
                } else {
                        $parenttag = '';
                }

                if (isset($hasRawContent[$parenttag]) || ($parenttag==='NOSCRIPT' && $parent->ownerDocument()->_scripting_enabled)) {
                        $s .= $child->data();
                } else {
                        $s .= _helper_escape($child->data());
                }
                break;

        case \domo\COMMENT_NODE:
                $s .= '<!--' . $child->data() . '-->';
                break;

        case \domo\PROCESSING_INSTRUCTION_NODE:
                $s .= '<?' . $child->target() . ' ' . $child->data() . '?>';
                break;

        case \domo\DOCUMENT_TYPE_NODE:
                $s .= '<!DOCTYPE ' . $child->name();

                if (false) {
                        // Latest HTML serialization spec omits the public/system ID
                        if ($child->_publicID) {
                                $s .= ' PUBLIC "' . $child->_publicId . '"';
                        }

                        if ($child->_systemId) {
                                $s .= ' "' . $child->_systemId . '"';
                        }
                }

                $s .= '>';
                break;
        default:
                \domo\error("InvalidStateError");
        }

        return $s;
}

/******************************************************************************
 * XML NAMES
 ******************************************************************************/
/******************************************************************************
 * In XML, valid names for Elements or Attributes are governed by a
 * number of overlapping rules, reflecting a gradual standardization
 * process.
 *
 * If terms like 'qualified name,' 'local name', 'namespace', and
 * 'prefix' are unfamiliar to you, consult:
 *
 *      https://www.w3.org/TR/xml/#NT-Name
 *      https://www.w3.org/TR/xml-names/#NT-QName
 *
 * This grammar is from the XML and XML Namespace specs. It specifies whether
 * a string (such as an element or attribute name) is a valid Name or QName.
 *
 * Name           ::= NameStartChar (NameChar)*
 * NameStartChar  ::= ":" | [A-Z] | "_" | [a-z] |
 *                    [#xC0-#xD6] | [#xD8-#xF6] | [#xF8-#x2FF] |
 *                    [#x370-#x37D] | [#x37F-#x1FFF] |
 *                    [#x200C-#x200D] | [#x2070-#x218F] |
 *                    [#x2C00-#x2FEF] | [#x3001-#xD7FF] |
 *                    [#xF900-#xFDCF] | [#xFDF0-#xFFFD] |
 *                    [#x10000-#xEFFFF]
 *
 * NameChar       ::= NameStartChar | "-" | "." | [0-9] |
 *                    #xB7 | [#x0300-#x036F] | [#x203F-#x2040]
 *
 * QName          ::= PrefixedName| UnprefixedName
 * PrefixedName   ::= Prefix ':' LocalPart
 * UnprefixedName ::= LocalPart
 * Prefix         ::= NCName
 * LocalPart      ::= NCName
 * NCName         ::= Name - (Char* ':' Char*)
 *                    # An XML Name, minus the ":"
 *****************************************************************************/
/* TODO: PHP /u unicode matching? */

/*
 * Most names will be ASCII only. Try matching against simple regexps first
 *
 * [HTML-5] Attribute names may be written with any mix of ASCII lowercase
 * and ASCII uppercase alphanumerics.
 *
 * Recall:
 *      \w matches any alphanumeric character A-Za-z0-9
 */
/*
 * TODO: PORT NOTE: in Domino, this pattern was '/^[_:A-Za-z][-.:\w]+$/',
 * which fails for one-letter tagnames (e.g. <p>). This was not a problem
 * because <p> is an HTML element and is thus instantiated differently, but
 * I think one-letter tagnames is still valid, right?
 *
 * Also, in PHP, sending 'p' as the name will not add '\n' to the end of
 * the string, while sending "p" DOES add the newline. The newline is
 * matched by \w and will thus allow a match, but it depends on whether
 * the string was single or double-quoted.
 *
 * To avoid this complication, we switched the '+' to a '*'.
 *
 * Interestingly, in the regex patterns in the next section, it seems that
 * we do indeed use '*' in Domino, so why was '+' being preferred here?
 */
define('pattern_ascii_name', '/^[_:A-Za-z][-.:\w]*$/');
define('pattern_ascii_qname', '/^([_A-Za-z][-.\w]*|[_A-Za-z][-.\w]*:[_A-Za-z][-.\w]*)$/');

/*
 * If the regular expressions above fail, try more complex ones that work
 * for any identifiers using codepoints from the Unicode BMP
 */
define('start', '_A-Za-z\\x{00C0}-\\x{00D6}\\x{00D8}-\\x{00F6}\\x{00F8}-\\x{02ff}\\x{0370}-\\x{037D}\\x{037F}-\\x{1FFF}\\x{200C}-\\x{200D}\\x{2070}-\\x{218F}\\x{2C00}-\\x{2FEF}\\x{3001}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFFD}');
define ('char', '-._A-Za-z0-9\\x{00B7}\\x{00C0}-\\x{00D6}\\x{00D8}-\\x{00F6}\\x{00F8}-\\x{02ff}\\x{0300}-\\x{037D}\\x{037F}-\\x{1FFF}\\x{200C}\\x{200D}\\x{203f}\\x{2040}\\x{2070}-\\x{218F}\\x{2C00}-\\x{2FEF}\\x{3001}-\\x{D7FF}\\{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFFD}');

define('pattern_name',  '/^[' . start . ']' . '[:' . char . ']*$/');
define('pattern_qname', '/^([' . start . '][' . char . ']*|[' . start . '][' . char . ']*:[' . start . '][' . char . ']*)$/');

/*
 * XML says that these characters are also legal:
 * [#x10000-#xEFFFF].  So if the patterns above fail, and the
 * target string includes surrogates, then try the following
 * patterns that allow surrogates and then run an extra validation
 * step to make sure that the surrogates are in valid pairs and in
 * the right range.  Note that since the characters \uf0000 to \u1f0000
 * are not allowed, it means that the high surrogate can only go up to
 * \uDB7f instead of \uDBFF.
 */
define('surrogates', '\\x{D800}-\\x{DB7F}\\x{DC00}-\\x{DFFF}');

define('pattern_has_surrogates', '/[' . surrogates . ']/');
define('pattern_surrogate_chars', '/[' . surrogates . ']/g');
define('pattern_surrogate_pairs', '/[\\x{D800}-\\x{DB7F}][\\x{DC00}-\\x{DFFF}]/g');

define('surrogate_start', start . surrogates);
define('surrogate_char', char . surrogates);

define('pattern_surrogate_name', '/^[' . surrogate_start . ']' . '[:' . surrogate_char . ']*$/');
define('pattern_surrogate_qname', '/^([' . surrogate_start . '][' . surrogate_char . ']*|[' . surrogate_start . '][' . surrogate_char . ']*:[' . surrogate_start . '][' . surrogate_char . ']*)$/');

function is_valid_xml_name($s)
{
  	if (preg_match(pattern_ascii_name, $s)) {
		return true; // Plain ASCII
	}
  	if (preg_match(pattern_name, $s)) {
		return true; // Unicode BMP
	}

  	/*
	 * Maybe the tests above failed because s includes surrogate pairs
  	 * Most likely, though, they failed for some more basic syntax problem
	 */
  	if (!preg_match(pattern_has_surrogates, $s)) {
		return false;
	}

  	/* Is the string a valid name if we allow surrogates? */
  	if (!preg_match(pattern_surrogate_name, $s)) {
		return false;
	}

  	/* Finally, are the surrogates all correctly paired up? */
	$matches_chars = array();
	$matches_pairs = array();

  	$ret0 = preg_match(pattern_surrogate_chars, $s, $matches_chars);
	$ret1 = preg_match(pattern_surrogate_pairs, $s, $matches_pairs);

  	return ($ret0 && $ret1) && ((2*count($matches_pairs)) === count($matches_chars));
}

function is_valid_xml_qname($s)
{
 	if (preg_match(pattern_ascii_qname, $s)) {
		return true; // Plain ASCII
	}
  	if (preg_match(pattern_ascii_qname, $s)) {
		return true; // Unicode BMP
	}

  	/*
	 * Maybe the tests above failed because s includes surrogate pairs
  	 * Most likely, though, they failed for some more basic syntax problem
	 */
  	if (!preg_match(pattern_has_surrogates, $s)) {
		return false;
	}

  	/* Is the string a valid name if we allow surrogates? */
  	if (!preg_match(pattern_surrogate_qname, $s)) {
		return false;
	}

  	/* Finally, are the surrogates all correctly paired up? */
	$matches_chars = array();
	$matches_pairs = array();

  	$ret0 = preg_match(pattern_surrogate_chars, $s, $matches_chars);
	$ret1 = preg_match(pattern_surrogate_pairs, $s, $matches_pairs);

  	return ($ret0 && $ret1) && ((2*count($matches_pairs)) === count($matches_chars));
}

/**
 * Validate and extract a namespace and qualifiedName
 *
 * Used to map (namespace, qualifiedName) => (namespace, prefix, localName)
 *
 * @param ?string $ns
 * @param string $qname
 * @param string &$prefix reference (will be NULL or contain prefix string)
 * @param string &$lname reference (will be qname or contain lname string)
 * @return void
 * @throws DOMException("NamespaceError")
 *
 * @spec https://dom.spec.whatwg.org/#validate-and-extract
 */
function validate_and_extract(?string $ns, string $qname, string &$prefix, string &$lname): void
{
        /*
         * See https://github.com/whatwg/dom/issues/671
         * and https://github.com/whatwg/dom/issues/319
         */
        if (!is_valid_xml_qname($qname)) {
                \domo\error("InvalidCharacterError");
        }

        if ($ns === "") {
                $ns = NULL; /* Per spec */
        }

        if (($pos = strpos($qname, ':')) === false) {
                $prefix = NULL;
                $lname = $qname;
        } else {
                $prefix = substr($qname, 0, $pos);
                $lname  = substr($qname, $pos+1);
        }

        if ($prefix !== NULL && $ns === NULL) {
                \domo\error("NamespaceError");
        }
        if ($prefix === "xml" && $namespace !== \domo\NAMESPACE_XML) {
                \domo\error("NamespaceError");
        }
        if (($prefix==="xmlns" || $qname==="xmlns") && $ns!==\domo\NAMESPACE_XMLNS) {
                \domo\error("NamespaceError");
        }
        if ($ns===\domo\NAMESPACE_XMLNS && !($prefix==="xmlns" || $qname==="xmlns")) {
                \domo\error("NamespaceError");
        }
}

?>
