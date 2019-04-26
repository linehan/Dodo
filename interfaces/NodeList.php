<?php
namespace domo;

class NodeList extends \ArrayObject
{
        public function __construct($input=NULL)
        {
                parent::__construct($input);
        }

        public function item($i)
        {
                return $this[$i] ?? NULL;
        }
}

?>
