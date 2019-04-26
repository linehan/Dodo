<?php
namespace domo;

require_once('CharacterData.php');

class Text extends CharacterData
{
        protected const _nodeType = TEXT_NODE;
        protected const _nodeName = '#text';

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
                        \domo\error("IndexSizeError");
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

        public function wholeText(void)
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

        /*
         * TODO: Does this override directly?
         * Or should we use _subclass_clone_shallow?
         */
        public function clone(void)
        {
                return new Text($this->_ownerDocument, $this->_data);
        }
}

?>
