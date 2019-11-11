<?php
/******************************************************************************
 * ChildNode.php
 * -------------
 ******************************************************************************/
namespace Dodo;

require_once('Node.php');
require_once('linked_list.php');

function _fragment_from_arguments($document, $args)
{
        $fragment = $document->createDocumentFragment();

        for ($i=0; $i<count($args); $i++) {
                $item = $args[$i];

                if (!($item instanceof Node)) {
                        /* In particular, you can't have NULLs */
                        $item = $document->createTextNode(strval($item));
                }

                $fragment->appendChild($item);
        }

        return $fragment;
}

/**
 * DOM-LS
 * Node objects that can have a parent must
 * implement the ChildNode class. These include:
 *
 *      Element
 *      CharacterData
 *      DocumentType
 *
 * The additional methods defined by this class
 * are practical conveniences, but are not really
 * required to get full DOM functionality.
 *
 * TODO
 * That being the case, perhaps DODO should choose
 * not to implement them.
 */
abstract class ChildNode extends Node
{
        public function __construct()
        {
                parent::__construct();
        }

        /**
         * Insert any number of Nodes or
         * DOMStrings after $this.
         *
         * NOTE
         * DOMStrings are inserted as
         * equivalent Text nodes.
         *
         * TODO: after and before()
         * are very very similar and
         * could probably be factored
         * nicely..
         */
        public function after(/* DOMStrings and/or Nodes */)
        {
                if ($this->_parentNode === NULL) {
                        /*
                         * If $this has no parent,
                         * then it is not actually
                         * part of a document, and
                         * according to DOM-LS,
                         * this method has no effect.
                         */
                        return;
                }

                /*
                 * Arguments are of variable
                 * number and type, although
                 * they should be either Node
                 * or DOMString (with string
                 * standing in for DOMString
                 * in this impelmentation).
                 */
                $args = func_get_args();

                if ($this->nextSibling() === NULL) {
                        /*
                         * If $this is an only
                         * child, then provide
                         * insertBefore with a
                         * NULL $ref. This results
                         * in insertBefore acting
                         * like append.
                         *
                         * TODO: Is this spec?
                         */
                        $ref = NULL;
                } else {
                        $ref = $this->nextSibling();
                        while (in_array($ref, $args)) {
                                /*
                                 * If $this has siblings,
                                 * find the first sibling
                                 * next to $this which
                                 * is not part of the
                                 * arguments. That sibling
                                 * will be the ref.
                                 *
                                 * TODO: Even if one of
                                 * the arguments were
                                 * in the list, does it
                                 * get pruned on insert?
                                 * Doensn't seem so.
                                 *
                                 * TODO: Do we mean this
                                 * equality to be between
                                 * object referenecs, or
                                 * between objects in the
                                 * DOM? Because this does
                                 * not capture the latter.
                                 */
                                $ref = $ref->nextSibling();
                        }
                }

                /*
                 * Turn the arguments into
                 * a DocumentFragment.
                 */
                $frag = _fragment_from_arguments($this->__document_node(), $ref);

                /*
                 * Insert the DocumentFragment
                 * at the determined location.
                 */
                $this->_parentNode->insertBefore($frag, $ref);
        }

        /**
         * Insert any number of Nodes or
         * DOMStrings after $this.
         *
         * NOTE
         * DOMStrings are inserted as
         * equivalent Text nodes.
         */
        public function before(/* DOMStrings and/or Nodes */)
        {
                if ($this->_parentNode === NULL) {
                        /*
                         * If $this has no parent,
                         * then it is not actually
                         * part of a document, and
                         * according to DOM-LS,
                         * this method has no effect.
                         */
                        return;
                }

                /*
                 * Arguments are of variable
                 * number and type, although
                 * they should be either Node
                 * or DOMString (with string
                 * standing in for DOMString
                 * in this impelmentation).
                 */
                $args = func_get_args();

                if ($this->previousSibling() === NULL) {
                        /*
                         * If $this is an only
                         * child, then we need
                         * to provide insertBefore
                         * with $this for the ref.
                         *
                         * TODO
                         * Yet it's written this way.
                         * Why are the two not equiv?
                         */
                        $ref = $this->_parentNode->firstChild();
                } else {
                        $ref = $this->previousSibling();
                        while (in_array($ref, $args)) {
                                /*
                                 * If $this has siblings,
                                 * find the first sibling
                                 * previous to $this which
                                 * is not in the arguments.
                                 * The arguments will be
                                 * inserted before this
                                 * sibling.
                                 *
                                 * TODO: Even if one of
                                 * the arguments were
                                 * in the list, does it
                                 * get pruned on insert?
                                 * Doensn't seem so.
                                 *
                                 * TODO: Do we mean this
                                 * equality to be between
                                 * object referenecs, or
                                 * between objects in the
                                 * DOM? Because this does
                                 * not capture the latter.
                                 */
                                $ref = $ref->previousSibling();
                        }
                        /*
                         * Since we're inserting
                         * before, we need to move
                         * over one.
                         */
                        $ref = $ref->nextSibling();
                }

                /*
                 * Turn the arguments into
                 * a DocumentFragment.
                 */
                $frag = _fragment_from_arguments($this->__document_node(), $args);

                /*
                 * Insert the DocumentFragment
                 * at the determined location.
                 */
                $this->_parentNode->insertBefore($frag, $before);
        }

        /*
         * Remove $this from its parent.
         */
        public function remove()
        {
                if ($this->_parentNode === NULL) {
                        /*
                         * If $this has no parent,
                         * according to DOM-LS,
                         * this method has no effect.
                         */
                        return;
                }

                if (($doc = $this->__node_document())) {
                        /*
                         * Un-associate $this
                         * with its document,
                         * if it has one.
                         */
                        if ($this->__is_rooted()) {
                                $doc->__mutate_remove($this);
                                $doc->__uproot();
                        }
                }

                /*
                 * Remove this node from its parents array of children
                 * and update the structure id for all ancestors
                 */
                $this->_remove();

                /* Forget this node's parent */
                $this->_parentNode = NULL;
        }

        /**
         * Remove this node w/o uprooting or sending mutation events
         * This is like a 'soft remove' - it's used in whatwg stuff.
         */
        protected function _remove()
        {
                if ($this->_parentNode === NULL) {
                        return;
                }

                $parent = $this->_parentNode;

                if ($parent->_childNodes !== NULL) {
                        array_splice($parent->_childNodes, $this->__sibling_index(), 1);
                } else if ($parent->_firstChild === $this) {
                        $parent->_firstChild = $this->nextSibling();
                }

                ll_remove($this);
        }

        /**
         * Replace this node with the nodes or strings provided as arguments.
         */
        public function replaceWith(/* Nodes or DOMStrings */)
        {
                if ($this->parentNode() === NULL) {
                        return;
                }

                /* Get the argument array */
                $args = func_get_args();

                $parent = $this->parentNode();
                $node = $this->nextSibling();

                /*
                 * Find "viable next sibling"; that is, next one
                 * not in $arguments
                 */
                while ($node !== NULL && in_array($node, $args)) {
                        $node = $node->nextSibling();
                }

                /*
                 * ok, parent and sibling are saved away since this node
                 * could itself appear in $arguments and we're about to
                 * move $arguments to a document fragment.
                 */
                $frag = _fragment_from_arguments($this->doc(), $arguments);

                if ($this->_parentNode === $parent) {
                        $parent->replaceChild($frag, $this);
                } else {
                        /* `this` was inserted into docFrag */
                        $parent->insertBefore($frag, $node);
                }
        }
}

/*
 * We have to use this because PHP is single-inheritance, so DocumentType
 * can't inherit from ChildNode and Leaf at once.
 *
 * We could use traits...................nah
 *
 * This class selectively overrides Node, providing an alternative
 * (more performant) base class for Node subclasses that can never
 * have children, such as those derived from the abstract CharacterData
 * class.
 */
abstract class ChildNodeLeaf extends ChildNode
{
        public function __construct()
        {
                parent::__construct();
        }

        public final function hasChildNodes(): bool
        {
                return false;
        }
        public final function firstChild(): ?Node
        {
                return NULL;
        }
        public final function lastChild(): ?Node
        {
                return NULL;
        }
        public final function insertBefore(Node $node, ?Node $refChild):?Node
        {
                \Dodo\error("NotFoundError");
        }
        public final function replaceChild(Node $node, ?Node $refChild):?Node
        {
                \Dodo\error("HierarchyRequestError");
        }
        public final function removeChild(ChildNode $node):?Node
        {
                \Dodo\error("NotFoundError");
        }
        public final function __remove_children()
        {
                /* no-op */
        }
        public final function childNodes(): ?NodeList
        {
                if ($this->_childNodes === NULL) {
                        $this->_childNodes = new NodeList();
                }
                return $this->_childNodes;
        }
}

?>
