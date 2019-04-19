<?php
namespace domo\util;

/******************************************************************************
 * In XML, valid names for Elements or Attributes are governed by a
 * number of overlapping rules, reflecting a gradual standardization
 * process.
 *
 * If terms like 'qualified name,' 'local name', 'namespace', and
 * 'prefix' are unfamiliar to you, consult:
 *
 *      https://www.w3.org/TR/xml/#NT-Name
 *      https://www.w3.org/TR/xml-names/#NT-QName
 *
 * This grammar is from the XML and XML Namespace specs. It specifies whether
 * a string (such as an element or attribute name) is a valid Name or QName.
 *
 * Name           ::= NameStartChar (NameChar)*
 * NameStartChar  ::= ":" | [A-Z] | "_" | [a-z] |
 *                    [#xC0-#xD6] | [#xD8-#xF6] | [#xF8-#x2FF] |
 *                    [#x370-#x37D] | [#x37F-#x1FFF] |
 *                    [#x200C-#x200D] | [#x2070-#x218F] |
 *                    [#x2C00-#x2FEF] | [#x3001-#xD7FF] |
 *                    [#xF900-#xFDCF] | [#xFDF0-#xFFFD] |
 *                    [#x10000-#xEFFFF]
 *
 * NameChar       ::= NameStartChar | "-" | "." | [0-9] |
 *                    #xB7 | [#x0300-#x036F] | [#x203F-#x2040]
 *
 * QName          ::= PrefixedName| UnprefixedName
 * PrefixedName   ::= Prefix ':' LocalPart
 * UnprefixedName ::= LocalPart
 * Prefix         ::= NCName
 * LocalPart      ::= NCName
 * NCName         ::= Name - (Char* ':' Char*)
 *                    # An XML Name, minus the ":"
 *****************************************************************************/


/*
 * Most names will be ASCII only. Try matching against simple regexps first
 *
 * Recall:
 *      \w matches any alphanumeric character A-Za-z0-9
 */
define('pattern_ascii_name', '/^[_:A-Za-z][-.:\w]+$/');
define('pattern_ascii_qname', '/^([_A-Za-z][-.\w]+|[_A-Za-z][-.\w]+:[_A-Za-z][-.\w]+)$/');

/*
 * If the regular expressions above fail, try more complex ones that work
 * for any identifiers using codepoints from the Unicode BMP
 */
define('start', '_A-Za-z\\x{00C0}-\\x{00D6}\\x{00D8}-\\x{00F6}\\x{00F8}-\\x{02ff}\\x{0370}-\\x{037D}\\x{037F}-\\x{1FFF}\\x{200C}-\\x{200D}\\x{2070}-\\x{218F}\\x{2C00}-\\x{2FEF}\\x{3001}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFFD}');
define ('char', '-._A-Za-z0-9\\x{00B7}\\x{00C0}-\\x{00D6}\\x{00D8}-\\x{00F6}\\x{00F8}-\\x{02ff}\\x{0300}-\\x{037D}\\x{037F}-\\x{1FFF}\\x{200C}\\x{200D}\\x{203f}\\x{2040}\\x{2070}-\\x{218F}\\x{2C00}-\\x{2FEF}\\x{3001}-\\x{D7FF}\\{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFFD}');

define('pattern_name',  '/^[' . start . ']' . '[:' . char . ']*$/');
define('pattern_qname', '/^([' . start . '][' . char . ']*|[' . start . '][' . char . ']*:[' . start . '][' . char . ']*)$/');

//$regex_qname = '^(' . $ncname . '|' . $ncname . ':' . $ncname . ')$';

/* TODO: PHP /u unicode matching? */

/*
 * XML says that these characters are also legal:
 * [#x10000-#xEFFFF].  So if the patterns above fail, and the
 * target string includes surrogates, then try the following
 * patterns that allow surrogates and then run an extra validation
 * step to make sure that the surrogates are in valid pairs and in
 * the right range.  Note that since the characters \uf0000 to \u1f0000
 * are not allowed, it means that the high surrogate can only go up to
 * \uDB7f instead of \uDBFF.
 */
define('surrogates', '\\x{D800}-\\x{DB7F}\\x{DC00}-\\x{DFFF}');

define('pattern_has_surrogates', '/[' . surrogates . ']/');
define('pattern_surrogate_chars', '/[' . surrogates . ']/g');
define('pattern_surrogate_pairs', '/[\\x{D800}-\\x{DB7F}][\\x{DC00}-\\x{DFFF}]/g');

define('surrogate_start', start . surrogates);
define('surrogate_char', char . surrogates);

define('pattern_surrogate_name', '/^[' . surrogate_start . ']' . '[:' . surrogate_char . ']*$/');
define('pattern_surrogate_qname', '/^([' . surrogate_start . '][' . surrogate_char . ']*|[' . surrogate_start . '][' . surrogate_char . ']*:[' . surrogate_start . '][' . surrogate_char . ']*)$/');


function isValidName($s)
{
  	if (preg_match(pattern_ascii_name, $s)) {
		return true; // Plain ASCII
	}
  	if (preg_match(pattern_name, $s)) {
		return true; // Unicode BMP
	}

  	/*
	 * Maybe the tests above failed because s includes surrogate pairs
  	 * Most likely, though, they failed for some more basic syntax problem
	 */
  	if (!preg_match(pattern_has_surrogates, $s)) {
		return false;
	}

  	/* Is the string a valid name if we allow surrogates? */
  	if (!preg_match(pattern_surrogate_name, $s)) {
		return false;
	}

  	/* Finally, are the surrogates all correctly paired up? */
	$matches_chars = array();
	$matches_pairs = array();

  	$ret0 = preg_match(pattern_surrogate_chars, $s, $matches_chars);
	$ret1 = preg_match(pattern_surrogate_pairs, $s, $matches_pairs);

  	return ($ret0 && $ret1) && ((2*count($matches_pairs)) === count($matches_chars));
}

function isValidQName($s)
{
 	if (preg_match(pattern_ascii_qname, $s)) {
		return true; // Plain ASCII
	}
  	if (preg_match(pattern_ascii_qname, $s)) {
		return true; // Unicode BMP
	}

  	/*
	 * Maybe the tests above failed because s includes surrogate pairs
  	 * Most likely, though, they failed for some more basic syntax problem
	 */
  	if (!preg_match(pattern_has_surrogates, $s)) {
		return false;
	}

  	/* Is the string a valid name if we allow surrogates? */
  	if (!preg_match(pattern_surrogate_qname, $s)) {
		return false;
	}

  	/* Finally, are the surrogates all correctly paired up? */
	$matches_chars = array();
	$matches_pairs = array();

  	$ret0 = preg_match(pattern_surrogate_chars, $s, $matches_chars);
	$ret1 = preg_match(pattern_surrogate_pairs, $s, $matches_pairs);

  	return ($ret0 && $ret1) && ((2*count($matches_pairs)) === count($matches_chars));
}

?>
