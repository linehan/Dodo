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

        /*
         * Inserts a list of Node or DOMString objects in the children
         * list of this ChildNode's parent, just after this ChildNode.
         * DOMString objects are inserted as equivalent Text nodes.
         */
        public function after(/* Nodes or DOMStrings */)
        {
                if ($this->parentNode === NULL) {
                        return;
                }

                /* Get the argument array */
                $arguments = func_get_args();

                /*
                 * Find "viable next sibling"; that is, the next
                 * sibling not in the arguments array
                 */
                $node = $this->nextSibling;

                while ($node !== NULL && in_array($arguments, $node)) {
                        $node = $node->nextSibling;
                }

                /*
                 * ok, parent and sibling are saved away since this node
                 * could itself appear in $arguments and we're about to
                 * move $arguments to a document fragment.
                 */
                $frag = _fragment_from_arguments($this->doc(), $arguments);
                $this->parentNode->insertBefore($frag, $node);
        }

        /*
         * Inserts a set of Node or DOMString objects in the children
         * list of this ChildNode's parent, just before this ChildNode.
         * DOMString objects are inserted as equivalent Text nodes.
         */
        public function before(/* Nodes or DOMStrings */)
        {
                if ($this->parentNode === NULL) {
                        return;
                }

                /* Get the argument array */
                $arguments = func_get_args();

                /*
                 * Find "viable prev sibling"; that is, prev
                 * one not in $arguments
                 */
                $node = $this->previousSibling;

                while ($node !== NULL && in_array($arguments, $node)) {
                        $node = $node->previousSibling;
                }

                /*
                 * ok, parent and sibling are saved away since this node
                 * could itself appear in $arguments and we're about to
                 * move $arguments to a document fragment.
                 */
                $frag = _fragment_from_arguments($this->doc(), $arguments);

                if ($node) {
                        $node = $node->nextSibling;
                } else {
                        $this->parentNode->firstChild;
                }

                $this->parentNode->insertBefore($frag, $node);
        }

        /* Remove this node from its parent */
        public function remove()
        {
                if ($this->parentNode() === NULL) {
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
                $this->parentNode = NULL;
        }

        /*
         * Remove this node w/o uprooting or sending mutation events
         * (But do update the structure id for all ancestors)
         */
        protected function _remove()
        {
                if ($this->parentNode === NULL) {
                        return;
                }

                if ($this->parentNode->_childNodes) {
                        array_splice($this->parentNode->_childNodes, $this->index(), 1);
                } else if ($this->parentNode->_firstChild === $this) {
                        if ($this->_nextSibling === $this) {
                                $this->parentNode->_firstChild = NULL;
                        } else {
                                $this->parentNode->_firstChild = $this->_nextSibling;
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
                $arguments = func_get_args();

                $parent = $this->parentNode();
                $node = $this->nextSibling();

                /*
                 * Find "viable next sibling"; that is, next one
                 * not in $arguments
                 */
                while ($node !== NULL && in_array($arguments, $node)) {
                        $node = $node.nextSibling();
                }

                /*
                 * ok, parent and sibling are saved away since this node
                 * could itself appear in $arguments and we're about to
                 * move $arguments to a document fragment.
                 */
                $frag = _fragment_from_arguments($this->doc(), $arguments);

                /* TODO PORT: Huh? What did they mean to say here? */
                if ($this->parentNode === $parent) {
                        $parent->replaceChild($fragment, $this);
                } else {
                        /* `this` was inserted into docFrag */
                        $parent->insertBefore($fragment, $node);
                }
        }
}
