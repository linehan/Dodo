<?php
/******************************************************************************
 * Text.php
 * --------
 ******************************************************************************/
namespace Dodo;

require_once('CharacterData.php');

class Text extends CharacterData
{
        public $_nodeType = TEXT_NODE;
        public $_nodeName = '#text';

        public function __construct(Document $doc, $data)
        {
                parent::__construct();
                $this->_ownerDocument = $doc;
                $this->_data = $data;
        }

        /* Overrides Node::nodeValue */
        public function nodeValue(?string $value=NULL)
        {
                /* GET */
                if ($value === NULL) {
                        return $this->_data;
                /* SET */
                } else {
                        $value = ($value === NULL) ? '' : strval($value);

                        if ($value === $this->_data) {
                                return;
                        }

                        $this->_data = $value;

                        if ($this->__is_rooted()) {
                                $this->_ownerDocument->__mutate_value($this);
                        }

                        if ($this->_parentNode && $this->_parentNode->_textchangehook) {
                                $this->_parentNode->_textchangehook($this);
                        }
                }
        }

        public function _subclass_isEqualNode(Node $node): bool
        {
                return ($this->_data === $node->_data);
        }

        public function _subclass_cloneNodeShallow(): ?Node
        {
                return new Text($this->_ownerDocument, $this->_data);
        }


        /* Per spec */
        public function textContent($value = NULL)
        {
                return $this->nodeValue($value);
        }

        public function data($value = NULL)
        {
                return $this->nodeValue($value);
        }

        public function splitText($offset)
        {
                if ($offset > strlen($this->_data) || $offset < 0) {
                        \Dodo\error("IndexSizeError");
                }

                $newdata = substr($this->_data, offset);
                $newnode = $this->_ownerDocument->createTextNode($newdata);
                $this->nodeValue(substr($this->_data, 0, $offset));

                $parent = $this->parentNode();

                if ($parent !== NULL) {
                        $parent->insertBefore($newnode, $this->nextSibling());
                }
                return $newnode;
        }

        public function wholeText()
        {
                $result = $this->textContent();

                for ($n=$this->nextSibling(); $n!==NULL; $n=$n->nextSibling()) {
                        if ($n->_nodeType !== TEXT_NODE) {
                                break;
                        }
                        $result .= $n->textContent();
                }
                return $result;
        }
}

?>
