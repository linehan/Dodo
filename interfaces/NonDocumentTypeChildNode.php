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
                if ($this->parentNode === NULL) {
                        return NULL;
                }

                for ($n=$this->nextSibling; $n!==NULL; $n=$n->nextSibling) {
                        if ($n->nodeType === ELEMENT_NODE) {
                                return $n;
                        }
                }
                return NULL;
        }

        public function previousElementSibling(void): ?Element
        {
                if ($this->parentNode === NULL) {
                        return NULL;
                }
                for ($n=$this->previousSibling; $n!==NULL; $n=$n->previousSibling) {
                        if ($n->nodeType === ELEMENT_NODE) {
                                return $n;
                        }
                }
                return NULL;
        }
}

?>
