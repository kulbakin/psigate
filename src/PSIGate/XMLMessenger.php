<?php
namespace PSIGate;

/**
 * PSIGate XML Interface API wrapper
 * @link http://psigate.com/pages/techsupport.asp PSIGate API documentation (Real-time XML API)
 * 
 * @author Pavel Kulbakin <p.kulbakin@gmail.com>
 */
class XMLMessenger extends Messenger
{
    /**
     * Gateway URL
     * 
     * @var string
     */
    protected $_url;
    
    /**
     * Verification data
     * 
     * @var arrray
     */
    protected $_id;

    /**
     * Constructor
     *
     * @param string $host Gateway host with port
     * @param string $storeId Store ID
     * @param string $pass Password
     */
    public function __construct($host, $storeId, $pass)
    {
        $this->_url = 'https://'.$host.'/Messenger/XMLMessenger';
        $this->_id = array(
            'StoreID'   => $storeId,
            'Passphrase' => $pass,
        );
    }
    
    /**
     * Execute PSIGate action
     * 
     * XXX
     * Documentation specifies fraud transactions to return ReturnCode=N:FRAUD, but
     * actual value corresponds to successful transaction and order is processed,
     * though ErrMsg is filled,
     * discovered on test transactions, very likely same true for live ones as well,
     * 
     * 
     * @param array $data Data to submit
     * @param string[optional] $returnNode Name of the node to pick from xml and present as result
     * @return mixed
     */
    public function order($data, $returnNode = null)
    {
        $data = array_merge($this->_id, $data);
        $result = Helper::xmlToArray($this->_request($this->_url, Helper::arrayToXml($data, 'Order')->saveXML()));
        
        if ( ! isset($result['Result']) or ! isset($result['Result']['ReturnCode']) or ! isset($result['Result']['Approved'])) { // received response is not of expected format
            throw new Exception('Unexpected response from gateway', 'XMLM-0001');
        }
        
        if ('Y' != $result['Result']['ReturnCode']{0}) {
            list($errCode, $errMsg) = explode(':', $result['Result']['ErrMsg'], 2);
            throw new Exception($errMsg, $errCode);
        }
        
        $result = $result['Result'];
        if ( ! is_null($returnNode)) {
            if (isset($result[$returnNode])) {
                $result = $result[$returnNode];
            } else {
                $result = null;
            }
        }
        
        return $result;
    }
}
