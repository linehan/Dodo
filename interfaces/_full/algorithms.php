<?php

namespace domo\algorithm


function _DOM_insertBeforeOrReplace(Node $node, Node $parent, ?Node $before, boolean $replace): void
{
        /* 
         * TODO: FACTOR: $ref_node is intended to always be non-NULL 
         * if $isReplace is true, but I think that could fail.
         */

        /******************* PRE-FLIGHT CHECKS *******************/

        if ($node === $before) {
                return;
        }

        if ($node instanceof DocumentFragment && $node->rooted()) {
                \domo\error("HierarchyRequestError");
        }

        /******************** COMPUTE AN INDEX *******************/
        /* NOTE: MUST DO HERE BECAUSE STATE WILL CHANGE */

        if ($parent->_childNodes) {
                if ($before !== NULL) {
                        $ref_index = $before->index();
                } else {
                        $ref_index = count($parent->_childNodes);
                }
                if ($node->_parentNode===$parent && $node->index()<$ref_index) {
                        $ref_index--;
                }
        }

        $ref_node = $before ?? $parent->firstChild();

        /************ IF REPLACING, REMOVE OLD CHILD *************/

        if ($replace) {
                if ($before->rooted()) {
                        $before->doc()->mutateRemove($before);
                }
                $before->_parentNode = NULL;
        }

        /************ IF BOTH ROOTED, FIRE MUTATIONS *************/

        $bothWereRooted = $node->rooted() && $parent->rooted();

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

        if ($node instanceof DocumentFragment) {
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
                                LinkedList\replace($ref_node, NULL);
                        }
                        if ($parent->_childNodes === NULL && $parent->_firstChild === $before) {
                                $parent->_firstChild = NULL;
                        }
                }
        } else {
                if ($ref_node !== NULL) {
                        if ($replace) {
                                LinkedList\replace($ref_node, $insert[0]);
                        } else {
                                LinkedList\insertBefore($insert[0], $ref_node);
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

        if ($node instanceof DocumentFragment) {
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

        /************ FIRE MOVE OR INSERT MUTATIONS *************/

        if ($bothWereRooted) {
                $parent->modify();
                $parent->doc()->mutateMove($insert[0]);
        } else {
                if ($parent->rooted()) {
                        $parent->modify();
                        foreach ($insert as $n) {
                                $parent->doc()->mutateInsert($n);
                        }
                }
        }
}



/*
TODO: Look at the way these were implemented in the original;
there are some speedups esp in the way that you implement
things like "node has a doctype child that is not child
*/
static function _DOM_ensureInsertValid(Node $node, Node $parent, ?Node $child)
{
        /*
         * DOM-LS: #1: If parent is not a Document, DocumentFragment,
         * or Element node, throw a HierarchyRequestError.
         */
        switch ($parent->_nodeType) {
        case DOCUMENT_NODE:
        case DOCUMENT_FRAGMENT_NODE:
        case ELEMENT_NODE:
                break;
        default:
                \domo\error("HierarchyRequestError");
        }

        /*
         * DOM-LS #2: If node is a host-including inclusive ancestor
         * of parent, throw a HierarchyRequestError.
         */
        if ($node->isAncestor($parent)) {
                \domo\error("HierarchyRequestError");
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
        case DOCUMENT_FRAGMENT_NODE:
        case DOCUMENT_TYPE_NODE:
        case ELEMENT_NODE:
        case TEXT_NODE:
        case PROCESSING_INSTRUCTION_NODE:
        case COMMENT_NODE:
                break;
        default:
                error("HierarchyRequestError");
        }

        /*
         * DOM-LS #5. If either:
         *      -node is a Text and parent is a Document
         *      -node is a DocumentType and parent is not a Document
         * throw a HierarchyRequestError
         */
        if (($node->_nodeType === TEXT_NODE          && $parent->_nodeType === DOCUMENT_NODE)
        ||  ($node->_nodeType === DOCUMENT_TYPE_NODE && $parent->_nodeType !== DOCUMENT_NODE)) {
                \domo\error("HierarchyRequestError");
        }

        /*
         * DOM-LS #6: If parent is a Document, and any of the
         * statements below, switched on node, are true, throw a
         * HierarchyRequestError.
         */
        if ($parent->_nodeType !== DOCUMENT_NODE) {
                return;
        }

        switch ($node->_nodeType) {
        case DOCUMENT_FRAGMENT_NODE:
                /*
                 * DOM-LS #6a-1: If node has more than one
                 * Element child or has a Text child.
                 */
                $count_text = 0;
                $count_element = 0;

                for ($n=$node->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->_nodeType === TEXT_NODE) {
                                $count_text++;
                        }
                        if ($n->_nodeType === ELEMENT_NODE) {
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
                        if ($child !== NULL && $child->_nodeType === DOCUMENT_TYPE_NODE) {
                               \domo\error("HierarchyRequestError");
                        }
                        /*
                         * DOM-LS #6a-2b: child is not NULL and a
                         * DocumentType is following child.
                         */
                        if ($child !== NULL) {
                                for ($n=$child->nextSibling(); $n!==NULL; $n=$n->nextSibling()) {
                                        if ($n->_nodeType === DOCUMENT_TYPE_NODE) {
                                                \domo\error("HierarchyRequestError");
                                        }
                                }
                        }
                        /* DOM-LS #6a-2c: parent has an Element child */
                        for ($n=$parent->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                                if ($n->_nodeType === ELEMENT_NODE) {
                                        \domo\error("HierarchyRequestError");
                                }
                        }
                }
                break;
        case ELEMENT_NODE:
                /* DOM-LS #6b-1: child is a DocumentType */
                if ($child !== NULL && $child->_nodeType === DOCUMENT_TYPE_NODE) {
                       \domo\error("HierarchyRequestError");
                }
                /* DOM-LS #6b-2: child not NULL and DocumentType is following child. */
                if ($child !== NULL) {
                        for ($n=$child->nextSibling(); $n!==NULL; $n=$n->nextSibling()) {
                                if ($n->_nodeType === DOCUMENT_TYPE_NODE) {
                                        \domo\error("HierarchyRequestError");
                                }
                        }
                }
                /* DOM-LS #6b-3: parent has an Element child */
                for ($n=$parent->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->_nodeType === ELEMENT_NODE) {
                                \domo\error("HierarchyRequestError");
                        }
                }
                break;
        case DOCUMENT_TYPE_NODE:
                /* DOM-LS #6c-1: parent has a DocumentType child */
                for ($n=$parent->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->_nodeType === DOCUMENT_TYPE_NODE) {
                                \domo\error("HierarchyRequestError");
                        }
                }
                /*
                 * DOM-LS #6c-2: child is not NULL and an Element
                 * is preceding child,
                 */
                if ($child !== NULL) {
                        for ($n=$child->previousSibling(); $n!==NULL; $n=$n->previousSibling()) {
                                if ($n->_nodeType === ELEMENT_NODE) {
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
                                if ($n->_nodeType === ELEMENT_NODE) {
                                        \domo\error("HierarchyRequestError");
                                }
                        }
                }

                break;
        }
}

function _DOM_ensureReplaceValid(Node $node, Node $parent, Node $child)
{
        /*
         * DOM-LS: #1: If parent is not a Document, DocumentFragment,
         * or Element node, throw a HierarchyRequestError.
         */
        switch ($parent->nodeType) {
        case DOCUMENT_NODE:
        case DOCUMENT_FRAGMENT_NODE:
        case ELEMENT_NODE:
                break;
        default:
                error("HierarchyRequestError");
        }

        /*
         * DOM-LS #2: If node is a host-including inclusive ancestor
         * of parent, throw a HierarchyRequestError.
         */
        if ($node->isAncestor($parent)) {
                error("HierarchyRequestError");
        }

        /*
         * DOM-LS #3: If child's parentNode is not parent
         * throw a NotFoundError
         */
        if ($child->_parentNode !== $parent) {
                error("NotFoundError");
        }

        /*
         * DOM-LS #4: If node is not a DocumentFragment, DocumentType,
         * Element, Text, ProcessingInstruction, or Comment Node,
         * throw a HierarchyRequestError.
         */
        switch ($node->_nodeType) {
        case DOCUMENT_FRAGMENT_NODE:
        case DOCUMENT_TYPE_NODE:
        case ELEMENT_NODE:
        case TEXT_NODE:
        case PROCESSING_INSTRUCTION_NODE:
        case COMMENT_NODE:
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
        if (($node->_nodeType === TEXT_NODE          && $parent->_nodeType === DOCUMENT_NODE)
        ||  ($node->_nodeType === DOCUMENT_TYPE_NODE && $parent->_nodeType !== DOCUMENT_NODE)) {
                \domo\error("HierarchyRequestError");
        }

        /*
         * DOM-LS #6: If parent is a Document, and any of the
         * statements below, switched on node, are true, throw a
         * HierarchyRequestError.
         */
        if ($parent->_nodeType !== DOCUMENT_NODE) {
                return;
        }

        switch ($node->_nodeType) {
        case DOCUMENT_FRAGMENT_NODE:
                /*
                 * #6a-1: If node has more than one Element child
                 * or has a Text child.
                 */
                $count_text = 0;
                $count_element = 0;

                for ($n=$node->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->_nodeType === TEXT_NODE) {
                                $count_text++;
                        }
                        if ($n->_nodeType === ELEMENT_NODE) {
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
                                if ($n->_nodeType === ELEMENT_NODE && $n !== $child) {
                                        \domo\error("HierarchyRequestError");
                                }
                        }
                        /* #6a-2b: a DocumentType is following child. */
                        for ($n=$child->nextSibling(); $n!==NULL; $n=$n->nextSibling()) {
                                if ($n->_nodeType === DOCUMENT_TYPE_NODE) {
                                        \domo\error("HierarchyRequestError");
                                }
                        }
                }
                break;
        case ELEMENT_NODE:
                /* #6b-1: parent has an Element child that is not child */
                for ($n=$parent->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->_nodeType === ELEMENT_NODE && $n !== $child) {
                                \domo\error("HierarchyRequestError");
                        }
                }
                /* #6b-2: DocumentType is following child. */
                for ($n=$child->nextSibling(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->nodeType === DOCUMENT_TYPE_NODE) {
                                \domo\error("HierarchyRequestError");
                        }
                }
                break;
        case DOCUMENT_TYPE_NODE:
                /* #6c-1: parent has a DocumentType child */
                for ($n=$parent->firstChild(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->_nodeType === DOCUMENT_TYPE_NODE) {
                                \domo\error("HierarchyRequestError");
                        }
                }
                /* #6c-2: an Element is preceding child */
                for ($n=$child->previousSibling(); $n!==NULL; $n=$n->previousSibling()) {
                        if ($n->_nodeType === ELEMENT_NODE) {
                                \domo\error("HierarchyRequestError");
                        }
                }
                break;
        }
}

?>
