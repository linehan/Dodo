<?php
namespace domo;

require_once('CharacterData.php');

class Comment extends CharacterData
{
        protected const _nodeType = COMMENT_NODE:
        protected const _nodeName = '#comment';

        public function __construct(Document $doc, $data)
        {
                parent::__construct();
                $this->_ownerDocument = $doc;
                $this->_data = $data;
        }

        public function nodeValue($value = NULL)
        {
                if ($value === NULL) {
                        return $this->_data;
                } else {
                        $value = ($value === NULL) ? '' : strval($value);

                        $this->_data = $value;

                        if ($this->__is_rooted()) {
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

        /*
         * TODO: Does this override directly?
         * Or should we use _subclass_clone_shallow?
         */
        public function clone(void): Comment
        {
                return new Comment($this->_ownerDocument, $this->_data);
        }
}
