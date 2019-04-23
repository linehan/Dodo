<?php

        static function set_maybe_duplicate()
        static function get_maybe_duplicate()


        public function _newattr($qname)
        {
                $attr = new Attr($this, $qname, NULL, NULL);
                $key = "|$qname";
                $this->_attrsByQName[$qname] = $attr;
                $this->_attrsByLName[$key] = $attr;

                if ($this->_attributes) {
                        $this->_attributes[count($this->_attrKeys)] = $attr;
                }
                $this->_attrKeys[] = $key;

                return $attr;
        }


        public function _addQName($attr)
        {
                /* TODO: THIS IS HORRIBLE!!!! SO STUPID!!! JUST DO IT OUT IN
                   THE OPEN, IT"S NOT THAT COMPLICATED FAAKK
                 */
                $qname = $attr->name();
                $existing = $this->_attrsByQName[$qname] ?? NULL;

                if (!$existing === NULL) {
                        $this->_attrsByQName[$qname] = $attr;
                } else if (is_array($existing)) {
                        $existing[] = $attr;
                } else {
                        $this->_attrsByQName[$qname] = array($existing, $attr);
                }

                if ($this->_attributes) {
                        $this->_attributes[$qname] = $attr;
                }
        }

        /*
         * TODO: We're factoring and tearing apart the attribute
         * functions in Element to try and understand why there
         * are 4-5 internal storage tables running around, and what's
         * up with all the fucking weird helper functions and what
         * not.
         */

        public
        function _unsafe_setAttribute(string $qname, $value)
        {
                /* #1 SEARCH FOR ATTR IN QNAME ARRAY */
                $attr = $this->_attrsByQName[$qname] ?? NULL;

                if ($attr === NULL) {
                        /* #2 CREATE NEW ATTR */
                        $new = true;

                        /* THIS IS PER SPEC -- no prefix, no namespace, even if the name is foo:bar */
                        $attr = new Attr($this, $qname, NULL, NULL);

                        /* (EXPANDED _newattr inline) */

                        /* #3 STORE ATTR IN 3 INTERNAL ARRAYS */
                        $this->_attrsByQName[$qname] = $attr;
                        $this->_attrsByLName["|$qname"] = $attr;
                        if ($this->_attributes) {
                                $this->_attributes[count($this->_attrKeys)] = $attr;
                        }

                        /* #4 STORE ATTR KEY IN INTERNAL ARRAY */
                        $this->_attrKeys[] = "|$qname";
                } else {
                        /* #5 SELECT ATTR IF IT EXISTS INTERNALLY */
                        $new = false;
                        $attr = (is_array($attr)) ? $attr[0] : $attr;
                        /* TODO: It could be an array since we looked it up
                           in qname
                         */
                }

                /* #6 UPDATE ATTR VALUE */
                $attr->value(strval($value));

                /* #7 FIRE NEW ATTR HOOK IF WE MADE A NEW ATTR */
                if ($new === true && $this->_newattrhook) {
                        $this->_newattrhook($qname, $value);
                }
        }

        /* SO THE ONLY DIFFERENCE IS WE CALL _addQName() rather than
           just adding it directly!! */

        public
        function _unsafe_setAttributeNS(?string $ns, string $qname, $value)
        {
                /* #1 SEARCH FOR ATTR IN LNAME ARRAY TODO: WHY NOT QNAME??? */
                $pos    = strpos($qname, ":");
                $prefix = ($pos === false) ? NULL   : substr($qname, 0, $pos);
                $lname  = ($pos === false) ? $qname : substr($qname, $pos+1);

                $ns   = ($ns === "") ? NULL : $ns;
                $key  = $this->_helper_lname_key($ns, $lname);
                $attr = $this->_attrsByLName[$key] ?? NULL;

                if ($attr === NULL) {
                        /* #2 CREATE NEW ATTR */
                        $new = true;
                        $attr = new Attr($this, $lname, $prefix, $ns);

                        /* #3 STORE ATTR IN 2 INTERNAL ARRAYS */
                        $this->_attrsByLName[$key] = $attr;
                        if ($this->_attributes) {
                                $this->_attributes[] = $attr;
                        }
                        /* #4 STORE ATTR KEY IN INTERNAL ARRAY */
                        $this->_attrKeys[] = $key;

                        /* #5 DO FANCY PANTSY ADD TO QNAME? WHY? */
                        $this->_addQName($attr);
                } else {
                        $new = false;
                        /* TODO: Won't be an array since we looked it up
                           in lname -- I guess lname doesn't do that shit?
                                <ns>|<lname> always unique
                                <qname>      is not
                         */
                }

                /* #6 UPDATE ATTR VALUE */
                $attr->value(strval($value));

                /* #7 FIRE NEW ATTR HOOK IF WE MADE NEW ATTR */
                if ($new === true && $this->_newattrhook) {
                        $this->_newattrhook($qname, $value);
                }
        }

?>
