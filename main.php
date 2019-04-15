<?php
/* TODO: declare( strict_types = 1 ); in all code files */

/******************************************************************************
 * TOP-LEVEL INTERFACE
 *
 * As far as I can tell, the point of this top-level interface is to
 * allow us to provide a seamless package for the
 *
 *      - HTML parser,
 *      - window,
 *      - DOM implementation,
 *
 * so that the whole thing is nice and seamless.
 ******************************************************************************/

/*
 * Things that get included up here
 *
 *      DOMImplementation TODO: Change this name it's colliding
 *      HTMLParser
 *      Window
 */
use \domo\interfaces\DOMImplementation
use \domo\interfaces\Window
use \domo\parsers\HTMLParser

/*

        You just realized that the best place to start porting
        is here, at the initial external interface, where you
        can make separate sockets for the HTMLParser and the
        selector engine to plug into once you get ahold of that
        code.

        Now, you can see what is provided at the top level, and
        finish up building the test harness for the tests. You're
        almost there.
*/


function createDOMImplementation()
{
        return new DOMImplementation(NULL);
}

function createDocument($html, $force)
{
        /*
         * Previous API couldn't let you pass '' as a document,
         * and that yields a slightly different document than
         * createHTMLDocument('') does. The new 'force' parameter
         * lets your pass '' if you want to.
         */
        if ($html || $force) {
                var $parser = new HTMLParser();
                $parser->parse($html || "", true);
                return $parser->document();
        }

        return new DOMImplementation(NULL)->createHTMLDocument("");
}

function createWindow($html, $address)
{
        $document = $exports->createDocument($html);

        if ($address !== $undefined) {
                $document->_address = $address;
        }

        return new Window($document);
}

                include utils
                export CSSStyleDeclaration
                       CharacterData
                       Comment
                       DOMException
                       DOMImplementation
                       DOMTokenList
                       Document
                       DocumentFragment
                       DocumentType
                       Element
                       HTMLParser
                       NamedNodeMap
                       Node
                       NodeList
                       NodeFilter
                       ProcessingInstruction
                       Text
                       Window

               utils.merge(exports, events);
               utils.merge(exports, htmlelts.elements);
               utils.merge(exports, svg.elements);

/*

   In Node speak, attach all of the following to this module's exports
   under the .impl:
                include utils
                export CSSStyleDeclaration
                       CharacterData
                       Comment
                       DOMException
                       DOMImplementation
                       DOMTokenList
                       Document
                       DocumentFragment
                       DocumentType
                       Element
                       HTMLParser
                       NamedNodeMap
                       Node
                       NodeList
                       NodeFilter
                       ProcessingInstruction
                       Text
                       Window

               utils.merge(exports, events);
               utils.merge(exports, htmlelts.elements);
               utils.merge(exports, svg.elements);

  That's a lot! Will have to figure out how to map exports to PHP-land.

  Will need to let Composer or whatever import these here, I guess?

   exports.impl = require('./impl');
*/

?>
