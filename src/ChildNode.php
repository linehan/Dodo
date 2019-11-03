<?php
/******************************************************************************
 * ChildNode.php
 * -------------
 ******************************************************************************/
namespace domo;

require_once('Node.php');
require_once('linked_list.php');

function _fragment_from_arguments($document, $args)
{
        $fragment = $document->createDocumentFragment();

        for ($i=0; $i<count($args); $i++) {
                $item = $args[$i];

                if (!($item instanceof Node)) {
                        $item = $document->createTextNode(strval($item));
                }

                $fragment->appendChild($item);
        }

        return $fragment;
}

/*
 * The ChildNode interface contains methods that are particular
 * to 'Node' objects that can have a parent. It is implemented
 * by 'Element', 'DocumentType', and 'CharacterData' objects.
 */
abstract class ChildNode extends Node
{
        public function __construct()
        {
                parent::__construct();
        }

        /**
         * Insert list of Nodes or DOMStrings after this ChildNode
         *
         * @param ... Any number of Nodes or DOMStrings
         * @return void
         *
         * NOTE
         * DOMStrings are inserted as equivalent Text nodes.
         */
        public function after(/* ... */)
        {
                if ($this->_parentNode === NULL) {
                        return;
                }

                $args = func_get_args();

                /* Find next sibling not in $args */
                $after = $this->nextSibling();
                while ($after !== NULL && in_array($after, $args)) {
                        $after = $after->nextSibling();
                }

                $frag = _fragment_from_arguments($this->doc(), $args);
                $this->_parentNode->insertBefore($frag, $after);
        }

        /**
         * Insert list of Nodes or DOMStrings before this ChildNode
         *
         * @param ... Any number of Nodes or DOMStrings
         * @return void
         *
         * NOTE
         * DOMStrings are inserted as equivalent Text nodes.
         */
        public function before(/* ... */)
        {
                if ($this->_parentNode === NULL) {
                        return;
                }

                $args = func_get_args();

                /* Find prev sibling not in $args */
                $before = $this->previousSibling();

                while ($before !== NULL && in_array($before, $args)) {
                        $before = $before->previousSibling();
                }

                $frag = _fragment_from_arguments($this->doc(), $args);

                if ($before) {
                        $before = $before->nextSibling();
                } else {
                        $before = $this->_parentNode->firstChild();
                }

                $this->_parentNode->insertBefore($frag, $before);
        }

        /**
         * Remove this node from its parent
         *
         * @return void
         */
        public function remove()
        {
                if ($this->_parentNode === NULL) {
                        return;
                }

                if (($doc = $this->__node_document())) {
                        //$doc->_preremoveNodeIterators($this);
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

                $parent->__mod_time_update();
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
                \domo\error("NotFoundError");
        }
        public final function replaceChild(Node $node, ?Node $refChild):?Node
        {
                \domo\error("HierarchyRequestError");
        }
        public final function removeChild(ChildNode $node):?Node
        {
                \domo\error("NotFoundError");
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
