<?php

require_once("utils.php");

/*
 * This is a hacky implementation of NamedNodeMap, intended primarily to
 * satisfy clients (like dompurify and the web-platform-tests) which check
 * to ensure that Node#attributes instanceof NamedNodeMap.
 */

/*
 * History: NamedNodeMap has nothing to do with Nodes, it's a collection
 * of Attrs. But once upon a time, an Attr was a type of Node called a
 * NamedNode. But then DOM4 came along and changed that, but it lives on
 * in this interface's name!
 */

class NamedNodeMap
{
        public function __construct($element)
        {
                $this->element = $element;
        }

        public function length(): int
        {
                /* should override */
        }

        public function item(int $index): ?Attr
        {
                /* should override */
        }

        public function getNamedItem(string $qname): ?Attr
        {
                return $this->element->getAttributeNode($qname);
        }

        public function getNamedItemNS(?string $ns, string $lname): ?Attr
        {
                return $this->element->getAttributeNodeNS($ns, $lname);
        }

        public function setNamedItem(Attr $attr): ?Attr
        {
                /* NYI */
        }

        public function setNamedItemNS(Attr $attr): ?Attr
        {
                /* NYI */
        }

        public function removeNamedItem(string $qname): Attr
        {
                $attr = $this->element->getAttributeNode($qname) ?? NULL;
                if ($attr) {
                        $this->element->removeAttribute($qname);
                        return $attr;
                }
                utils\NotFoundError();
        }

        public function removeNamedItemNS($ns, $lname)
        {
                $attr = $this->element->getAttributeNodeNS($ns, $lname) ?? NULL;
                if ($attr) {
                        $this->element->removeAttributeNS($ns, $lname);
                        return $attr;
                }
                utils\NotFoundError();
        }
}

?>
