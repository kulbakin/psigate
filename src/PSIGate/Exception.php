<?php
namespace PSIGate;

/**
 * PSIGate specific exception type
 * Can have code of any type, meant for error codes returned by PSIGate gateways
 * 
 * @author Pavel Kulbakin <p.kulbakin@gmail.com>
 */
class Exception extends \Exception
{
    /**
     * Construct the exception
     *
     * @param string[optional] $message
     * @param mixed[optional] $code
     * @param Exception[optional] $previous
     */
    public function __construct($message = '', $code = 0, $previous = NULL)
    {
        parent::__construct($message, 0, $previous);
        $this->code = $code;
    }
}
