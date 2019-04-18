<?php

class NodeList extends ArrayObject
{
        function __construct(array $input = NULL)
        {
                parent::__construct(array $input);
        }

        function item($i)
        {
                return $this[$i] ?? NULL;
        }
}

?>
