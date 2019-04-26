<?php
namespace domo;

require_once("ChildNode.php");

/*
 * PORT NOTE: This, per spec, operates less like an inherited
 * class and more like a mixin. It's used by Element and CharacterData.
 */
abstract class NonDocumentTypeChildNode extends ChildNode
{
        public function __construct()
        {
                parent::__construct();
        }

        public function nextElementSibling(void): ?Element
        {
                if ($this->_parentNode === NULL) {
                        return NULL;
                }

                for ($n=$this->nextSibling(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->_nodeType === ELEMENT_NODE) {
                                return $n;
                        }
                }
                return NULL;
        }

        public function previousElementSibling(void): ?Element
        {
                if ($this->_parentNode === NULL) {
                        return NULL;
                }
                for ($n=$this->previousSibling(); $n!==NULL; $n=$n->previousSibling()) {
                        if ($n->_nodeType === ELEMENT_NODE) {
                                return $n;
                        }
                }
                return NULL;
        }
}

/*
 * We have to use this because PHP is single-inheritance, so CharacterData
 * can't inherit from NonDocumentTypeChildNode and Leaf at once.
 *
 * We could use traits...................
 *
*
 * This class selectively overrides Node, providing an alternative
 * (more performant) base class for Node subclasses that can never
 * have children, such as those derived from the abstract CharacterData
 * class.
 */
abstract class NonDocumentTypeChildNodeLeaf extends NonDocumentTypeChildNode
{
        public function __construct()
        {
                parent::__construct();
        }

        public final function hasChildNodes(void): boolean
        {
                return false;
        }
        public final function firstChild(void)
        {
                return NULL;
        }
        public final function lastChild(void)
        {
                return NULL;
        }
        public final function insertBefore(Node $node, ?Node $refChild)
        {
                \domo\error("NotFoundError");
        }
        public final function replaceChild(Node $node, ?Node $refChild)
        {
                \domo\error("HierarchyRequestError");
        }
        public final function removeChild(Node $node)
        {
                \domo\error("NotFoundError");
        }
        public final function __remove_children(void)
        {
                /* no-op */
        }
        public final function childNodes(void)
        {
                if ($this->_childNodes === NULL) {
                        $this->_childNodes = new NodeList();
                }
                return $this->_childNodes;
        }
}

?>
