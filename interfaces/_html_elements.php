<?php
namespace domo;

require_once('_reflected_attributes.php');


const REFERRER = array(
        'type' => array(
                "", 
                "no-referrer", 
                "no-referrer-when-downgrade", 
                "same-origin", 
                "origin", 
                "strict-origin", 
                "origin-when-cross-origin", 
                "strict-origin-when-cross-origin", 
                "unsafe-url"
        ),
        'missing' => ''
);


function build_attributes($owner, $spec_array)
{
        $ret = array();

        foreach ($spec_array as $name => $spec) {
                if (!is_array($spec)) {
                        $spec = array('type'=>$spec, 'name'=>$name);
                }
                if (!isset($spec['name'])) {
                        $spec['name'] = $name;
                }
                $ret[$name] = reflected_attribute($owner, $spec);
        }

        return $ret;
}

class HTMLImgElement extends Element 
{
        private $_prop;
        private $_attr;

        public function __construct ($doc, $lname, $prefix)
        {
                parent::__construct($doc, $lname, $prefix);
                $this->_attr = build_attributes($this, array(
                        'alt' => 'string',
                        'src' => 'URL',
                        'srcset' => 'string',
                        'crossOrigin' => 'CORS',
                        'useMap' => 'string',
                        'isMap' => 'boolean',
                        'height' => array('type'=>'unsigned long', 'default'=>0),
                        'width' => array('type'=>'unsigned long', 'default'=>0),
                        'referrerPolicy' => REFERRER,
                        /* obsolete */
                        'name' => 'string',
                        'lowsrc' => 'URL',
                        'align' => 'string',
                        'hspace' => array('type'=>'unsigned long', 'default'=>0),
                        'vspace' => array('type'=>'unsigned long', 'default'=>0),
                        'longDesc'=> 'URL',
                        'border' => array('type'=>'string', 'is_nullable'=>true)
                ));
        }
        public function __get($name)
        {
                if (isset($this->_attr[$name])) {
                        return $this->_attr[$name]->get();
                }
        }
        public function __set($name, $value)
        {
                if (isset($this->_attr[$name])) {
                        $this->_attr[$name]->set($value);
                }
        }
}

//class HTMLIFrameElement extends HTMLElement
//{
        //private $_attr;

        //public function __construct ($doc, $lname, $prefix)
        //{
                //parent::__construct($doc, $lname, $prefix);
                //$this->_attr = build_attributes(array(
                        //'src' => 'URL',
                        //'srcdoc' => 'string',
                        //'name' => 'string',
                        //'width' => 'string',
                        //'height' => 'string',
                        //'seamless' => 'boolean',
                        //'allowFullscreen' => 'boolean',
                        //'allowUserMedia' => 'boolean',
                        //'allowPaymentRequest' => 'boolean',
                        //'referrerPolicy' => REFERRER,
                        //'align' => 'string',
                        //'scrolling' => 'string',
                        //'frameBorder' => 'string',
                        //'longDesc' => 'URL',
                        //'marginHeight' => array('type'=>'string', 'is_nullable'=>true),
                        //'marginWidth' => array('type'=>'string', 'is_nullable'=>true)
                //));
        //}

        //public function __get($name)
        //{
                //if (isset($this->_attr[$name])) {
                        //return $this->_attr[$name]->get();
                //}
        //}
        //public function __set($name, $value)
        //{
                //if (isset($this->_attr[$name])) {
                        //$this->_attr[$name]->set($value);
                //}
        //}
//}

?>
