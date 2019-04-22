<?php
/******************************************************************************
 * DOMException.php
 * ````````````````
 * (Mostly) implements the WebIDL-1 DOMException interface
 * https://www.w3.org/TR/WebIDL-1/#idl-DOMException*
 *****************************************************************************/
namespace domo;

const ERR_CODE_DOES_NOT_EXIST = -1;	/* [DOMO] Errors without Legacy code */
const INDEX_SIZE_ERR = 1;
const DOMSTRING_SIZE_ERR = 2;		/* [WEB-IDL-1] No longer present */
const HIERARCHY_REQUEST_ERR = 3;
const WRONG_DOCUMENT_ERR = 4;
const INVALID_CHARACTER_ERR = 5;
const NO_DATA_ALLOWED_ERR = 6;		/* [WEB-IDL-1] No longer present */
const NO_MODIFICATION_ALLOWED_ERR = 7;
const NOT_FOUND_ERR = 8;
const NOT_SUPPORTED_ERR = 9;
const INUSE_ATTRIBUTE_ERR = 10;
const INVALID_STATE_ERR = 11;
const SYNTAX_ERR = 12;
const INVALID_MODIFICATION_ERR = 13;
const NAMESPACE_ERR = 14;
const INVALID_ACCESS_ERR = 15;
const VALIDATION_ERR = 16;
const TYPE_MISMATCH_ERR = 17;		/* [WEB-IDL-1] No longer present */
const SECURITY_ERR = 18;
const NETWORK_ERR = 19;
const ABORT_ERR = 20;
const URL_MISMATCH_ERR = 21;
const QUOTA_EXCEEDED_ERR = 22;
const TIMEOUT_ERR = 23;
const INVALID_NODE_TYPE_ERR = 24;
const DATA_CLONE_ERR = 25;

const $ERROR_NAME_TO_CODE = array(
	'IndexSizeError' => INDEX_SIZE_ERR,
	'HierarchyRequestError' => HIERARCHY_REQUEST_ERR,
	'WrongDocumentError' => WRONG_DOCUMENT_ERR,
	'InvalidCharacterError' => INVALID_CHARACTER_ERR,
	'NoModificationAllowedError' => NO_MODIFICATION_ALLOWED_ERR,
	'NotFoundError' => NOT_FOUND_ERR,
	'NotSupportedError' => NOT_SUPPORTED_ERR,
	'InUseAttributeError' => INUSE_ATTRIBUTE_ERR,
	'InvalidStateError' => INVALID_STATE_ERR,
	'SyntaxError' => SYNTAX_ERR,
	'InvalidModificationError' => INVALID_MODIFICATION_ERR,
	'NamespaceError' => NAMESPACE_ERR,
	'InvalidAccessError' => INVALID_ACCESS_ERR,
	'SecurityError' => SECURITY_ERR,
	'NetworkError' => NETWORK_ERR,
	'AbortError' => ABORT_ERR,
	'URLMismatchError' => URL_MISMATCH_ERR,
	'QuotaExceededError' => QUOTA_EXCEEDED_ERR,
	'TimeoutError' => TIMEOUT_ERR,
	'InvalidNodeTypeError' => INVALID_NODE_TYPE_ERR,
	'DataCloneError' => DATA_CLONE_ERR,
	'EncodingError' => ERR_CODE_DOES_NOT_EXIST,
	'NotReadableError' => ERR_CODE_DOES_NOT_EXIST,
	'UnknownError' => ERR_CODE_DOES_NOT_EXIST,
	'ConstraintError' => ERR_CODE_DOES_NOT_EXIST,
	'DataError' => ERR_CODE_DOES_NOT_EXIST,
	'TransactionInactiveError' => ERR_CODE_DOES_NOT_EXIST,
	'ReadOnlyError' => ERR_CODE_DOES_NOT_EXIST,
	'VersionError' => ERR_CODE_DOES_NOT_EXIST,
	'OperationError' => ERR_CODE_DOES_NOT_EXIST
);

const $ERROR_NAME_TO_MESSAGE = array(
	'IndexSizeError' => 'INDEX_SIZE_ERR (1): the index is not in the allowed range',
	'HierarchyRequestError' => 'HIERARCHY_REQUEST_ERR (3): the operation would yield an incorrect nodes model',
	'WrongDocumentError' => 'WRONG_DOCUMENT_ERR (4): the object is in the wrong Document, a call to importNode is required',
	'InvalidCharacterError' => 'INVALID_CHARACTER_ERR (5): the string contains invalid characters',
	'NoModificationAllowedError' => 'NO_MODIFICATION_ALLOWED_ERR (7): the object can not be modified',
	'NotFoundError' => 'NOT_FOUND_ERR (8): the object can not be found here',
	'NotSupportedError' => 'NOT_SUPPORTED_ERR (9): this operation is not supported',
	'InUseAttributeError' => 'INUSE_ATTRIBUTE_ERR (10): setAttributeNode called on owned Attribute',
	'InvalidStateError' => 'INVALID_STATE_ERR (11): the object is in an invalid state',
	'SyntaxError' => 'SYNTAX_ERR (12): the string did not match the expected pattern',
  	'InvalidModificationError' => 'INVALID_MODIFICATION_ERR (13): the object can not be modified in this way',
  	'NamespaceError' => 'NAMESPACE_ERR (14): the operation is not allowed by Namespaces in XML',
  	'InvalidAccessError' => 'INVALID_ACCESS_ERR (15): the object does not support the operation or argument',
  	'SecurityError' => 'SECURITY_ERR (18): the operation is insecure',
  	'NetworkError' => 'NETWORK_ERR (19): a network error occurred',
  	'AbortError' => 'ABORT_ERR (20): the user aborted an operation',
  	'URLMismatchError' => 'URL_MISMATCH_ERR (21): the given URL does not match another URL',
  	'QuotaExceededError' => 'QUOTA_EXCEEDED_ERR (22): the quota has been exceeded',
  	'TimeoutError' => 'TIMEOUT_ERR (23): a timeout occurred',
  	'InvalidNodeTypeError' => 'INVALID_NODE_TYPE_ERR (24): the supplied node is invalid or has an invalid ancestor for this operation',
  	'DataCloneError' => 'DATA_CLONE_ERR (25): the object can not be cloned.'
	'EncodingError' => 'The encoding operation (either encoding or decoding) failed.',
	'NotReadableError' => 'The I/O read operation failed.',
	'UnknownError' => 'The operation failed for an unknown transient reason (e.g. out of memory)',
	'ConstraintError' => 'A mutation operation in a transaction failed because a constraint was not satisfied.',
	'DataError' => 'Provided data is inadequate',
	'TransactionInactiveError' => 'A request was placed against a transaction which is currently not active, or which is finished.',
	'ReadOnlyError' => 'The mutating operation was attempted in a readonly transaction.',
	'VersionError' => 'An attempt was made to open a database using a lower version than the existing version.',
	'OperationError' => 'The operation failed for an operation-specific reason.'
);

class DOMException extends Exception
{
	/*
	 * [WEB-IDL-1] This is the actual constructor prototype.
         * I think the invocation is ridiculous, so we wrap it
         * in an error() function (see util.php).
	 */
	public function __construct(?string $message, ?string $name)
	{
		$this->name = $name ?? "";
		$this->err_msg  = $ERROR_NAME_TO_MESSAGE[$this->name] ?? "";
		$this->err_code = $ERROR_NAME_TO_CODE[$this->name] ?? -1;
		$this->usr_msg  = $message ?? $this->err_msg;

		parent::__construct($this->err_msg, $this->err_code);
	}

	public function __toString()
	{
		return __CLASS__ . ': [' . $this->name . '] ' . $this->usr_msg;
	}
}

?>
