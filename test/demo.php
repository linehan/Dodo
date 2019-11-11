<?php
/******************************************************************************
 * This is just a demo to show basic invocation.
 * It is not intended to provide test coverage of any kind.
 *
 * Run with: 'php ./demo.php' 
 ******************************************************************************/
require_once('../src/dodo.php');
require_once('../src/html_elements.php');

/* Instantiate the nodes */
$doc = new Dodo\Document('html');
$html = $doc->createElement('html');
$body = $doc->createElement('body');
$comment = $doc->createComment('Hello, world!');
$p = $doc->createElement("p");
$img = new Dodo\HTMLImgElement($doc, "img", ""); /* using createElement soon */

/* Construct the tree */
$p->appendChild($doc->createTextNode('Lorem ipsum'));
$body->appendChild($comment);
$body->appendChild($img);
$body->appendChild($p);
$html->appendChild($body);
$doc->appendChild($html);

/* Print the tree */
echo $doc->__serialize() . "\n\n";

/* Update the attributes on the <img> node */
$img->alt = "Incredible Vision";
$img->width = "1337px"; // NOTE: width stored as a string

/* Print the tree again (<img> should have attributes now) */
echo $doc->__serialize() . "\n\n";

/* Print the width, the value should be an integer */
echo "IMG width: " . $img->width . "\n";

?>
