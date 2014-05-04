<?php
namespace PSIGate;

/**
 * Abstract messanger to communicate with a gateway
 * 
 * @author Pavel Kulbakin <p.kulbakin@gmail.com>
 */
abstract class Messenger
{
    /**
     * Send request to a gateway
     *
     * @param string $url Gateway URL
     * @param string $data Raw XML post data
     * @param array[optional] $opts Extra cURL options
     * @return string
     */
    protected function _request($url, $data, $opts = array())
    {
        if ( ! extension_loaded('curl')) {
            throw new Exception('The curl extension is required', 'PAPI-0001');
        }
        
        $ch = curl_init($url);
        // default timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        // add POST fields
        curl_setopt($ch, CURLOPT_POST, 1); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        
        // XXX PSIGate gateways have SSLv1
        curl_setopt($ch, CURLOPT_SSLVERSION, 1);
        // XXX SSL Verification does not work for PSIGate gateways
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        
        $opts and curl_setopt_array($ch, $opts);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        $result = curl_exec($ch);
        if (false === $result) {
            throw new Exception(curl_error($ch), sprintf('CURL-%04d', curl_errno($ch)));
        }
        
        return $result;
    }
    
    /**
     * Check result structure and throw exception if it corresponds to error
     * 
     * @param array $result
     * @return bool
     */
    public function analyseTransactionResult($result)
    {
        if ('Y' != $result['ReturnCode']{0}) {
            if ( ! empty($result['ErrMsg'])) {
                list($errCode, $errMsg) = explode(':', $result['ErrMsg'], 2);
            } else {
                $errCode = 'PAPI-0003';
                list(, $errMsg) = explode(':', $result['ReturnCode'], 2);
            }
            throw new Exception($errMsg, $errCode);
        }
        
        return true;
    }
}
