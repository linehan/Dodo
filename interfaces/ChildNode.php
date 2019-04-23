<?php

require_once("Node.php");
require_once("../lib/LinkedList.php");

static function _fragment_from_arguments($document, $args)
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
        public function remove(void)
        {
                if ($this->_parentNode === NULL) {
                        return;
                }

                /* Send mutation events if necessary */
                if ($this->doc()) {
                        $this->doc()->_preremoveNodeIterators($this);
                        if ($this->rooted()) {
                                $this->doc()->mutateRemove($this);
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

        /*
         * Remove this node w/o uprooting or sending mutation events
         * (But do update the structure id for all ancestors)
         */
        protected function _remove()
        {
                if ($this->_parentNode === NULL) {
                        return;
                }

                if ($this->_parentNode->_childNodes) {
                        array_splice($this->_parentNode->_childNodes, $this->index(), 1);
                } else if ($this->_parentNode->firstChild() === $this) {
                        if ($this->nextSibling() === $this) {
                                $this->_parentNode->_firstChild = NULL;
                        } else {
                                $this->_parentNode->_firstChild = $this->nextSibling();
                        }
                }

                LinkedList\remove($this);

                $parent->modify();
        }

        /*
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
