<?php
/******************************************************************************
 * ProcessingInstruction.php
 * -------------------------
 ******************************************************************************/
namespace domo;

require_once('CharacterData.php');

class ProcessingInstruction extends CharacterData
{
        protected const _nodeType = PROCESSING_INSTRUCTION_NODE;

        public function __construct(Document $doc, string $target, $data)
        {
                parent::__construct();
                $this->_ownerDocument = $doc;
                $this->_nodeName = $target; // spec
                $this->_target = $target;
                $this->_data = $data;
        }

        /* Overrides Node::nodeValue */
        /* $value = '' will unset */
        public function nodeValue($value = NULL)
        {
                if ($value === NULL) {
                        return $this->_data;
                } else {
                        $this->_data = strval($value);
                        if ($this->__is_rooted) {
                                $this->_ownerDocument->__mutate_value($this);
                        }
                }
        }

        public function textContent($value = NULL)
        {
                return $this->nodeValue($value);
        }

        public function data($value = NULL)
        {
                return $this->nodeValue($value);
        }

        /* Delegated methods from Node */
        public function _subclass_cloneNodeShallow(): ?Node
        {
                return new ProcessingInstruction($this->_ownerDocument, $this->_target, $this->_data);
        }
        public function _subclass_isEqualNode(Node $node): bool
        {
                return ($this->_target === $node->_target && $this->_data === $node->_data);
        }
}
