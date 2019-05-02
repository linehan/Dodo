<?php
/******************************************************************************
 * CharacterData.php
 * -----------------
 ******************************************************************************/
namespace domo;

require_once("NonDocumentTypeChildNode.php");

abstract class CharacterData extends NonDocumentTypeChildNodeLeaf
{
        public function __construct()
        {
                parent::__construct();
        }

        // DOMString substringData(unsigned long offset,
        //               unsigned long count);
        // The substringData(offset, count) method must run these steps:
        //
        //     If offset is greater than the context object's
        //     length, throw an INDEX_SIZE_ERR exception and
        //     terminate these steps.
        //
        //     If offset+count is greater than the context
        //     object's length, return a DOMString whose value is
        //     the UTF-16 code units from the offsetth UTF-16 code
        //     unit to the end of data.
        //
        //     Return a DOMString whose value is the UTF-16 code
        //     units from the offsetth UTF-16 code unit to the
        //     offset+countth UTF-16 code unit in data.
        /*
         * PORT NOTES:
         * In Domino.js, checking was done to ensure $offset and $count
         * were integers and not-undefined. Here we just use type hints.
         */
        public function substringData(int $offset, int $count)
        {
                if ($offset > strlen($this->_data) || $offset < 0 || count < 0) {
                        \domo\error("IndexSizeError");
                }

                return substr($this->_data, $offset, $offset+$count);
        }

        // void appendData(DOMString data);
        // The appendData(data) method must append data to the context
        // object's data.
        /* PORT NOTES: Again, for the number of arguments, we can just
         * use the function prototype to check.
         */
        public function appendData($data)
        {
                $this->_data .= strval($data);
        }

        // void insertData(unsigned long offset, DOMString data);
        // The insertData(offset, data) method must run these steps:
        //
        //     If offset is greater than the context object's
        //     length, throw an INDEX_SIZE_ERR exception and
        //     terminate these steps.
        //
        //     Insert data into the context object's data after
        //     offset UTF-16 code units.
        //
        public function insertData(int $offset, $data)
        {
                return $this->replaceData($offset, 0, $data);
        }


        // void deleteData(unsigned long offset, unsigned long count);
        // The deleteData(offset, count) method must run these steps:
        //
        //     If offset is greater than the context object's
        //     length, throw an INDEX_SIZE_ERR exception and
        //     terminate these steps.
        //
        //     If offset+count is greater than the context
        //     object's length var count be length-offset.
        //
        //     Starting from offset UTF-16 code units remove count
        //     UTF-16 code units from the context object's data.
        public function deleteData(int $offset, int $count)
        {
                return $this->replaceData($offset, $count, '');
        }


        // void replaceData(unsigned long offset, unsigned long count,
        //          DOMString data);
        //
        // The replaceData(offset, count, data) method must act as
        // if the deleteData() method is invoked with offset and
        // count as arguments followed by the insertData() method
        // with offset and data as arguments and re-throw any
        // exceptions these methods might have thrown.
        public function replaceData(int $offset, int $count, $data)
        {
                $curtext = $this->_data;
                $len = strlen($curtext);

                $data = strval($data);

                if ($offset > $len || $offset < 0) {
                        \domo\error("IndexSizeError");
                }

                if ($offset+$count > $len) {
                        $count = $len - $offset;
                }

                $prefix = substr($curtext, 0, $offset);
                $suffix = substr($curtext, $offset+$count);

                $this->_data = $prefix + $data + $suffix;
        }

        public function length(): int
        {
                strlen($this->_data);
        }
}

?>
