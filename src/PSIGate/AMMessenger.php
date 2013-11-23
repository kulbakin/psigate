<?php
namespace PSIGate;

/**
 * PSIGate Account Manager API wrapper
 * @link http://psigate.com/pages/techsupport.asp PSIGate API documentation (Account Manager API)
 * 
 * XXX
 * Known isuses with documented v1.1.08 and actual API as of 2013-11-21:
 *  1.  Not all return codes and messages are documented (e.g. "EMR-0099 Immediate email report has been sent successfully.")
 *  2.  Some methods return different success code from what could be assumed from documentation
 *  3.  Summary actions do not support all filters specified in documentation
 *  4.  Summary actions do not have required filetes, though documentation may list some as such
 *  5.  Register actions return generated ID if not specified explicitly except for "CTL01 Register template"
 *      which returns empty value for TemplateID though subsequent requests for details show that TemplateID was generated
 *  6.  In charge template related actions, except for template item add (CTL11), trigger date must be specified with Trigger,
 *      not with RBTrigger as suggested by documentation
 *  7.  Delete, enable, disable template, and delete, enable and disable template item actions (CTL04, CTL08, CTL09,
 *      and CTL14, CTL18, CTL19 respectively) expect template id to be 
 *      supplied with RBCID condition, not with TemplateID as suggested by documentation
 *  8.  Item Add actions are not satisfied with holder id only (RBCID or TemplateID) since PSIGate runs validation
 *      for condition equivalent elements and thus required fields (as defined in holder register action) must be
 *      supplied even though holder id would be enough
 *  9.  Register charge action (RBC01) needs required fields set regardless of the fact that charge is created based on
 *      template with the same ones defined
 *  10. Immediate charge action (RBC99) in Invoice response element returns RBCID value twice
 * 
 * @author Pavel Kulbakin <p.kulbakin@gmail.com>
 */
class AMMessenger extends Messenger
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
     * @param string $cid Client ID
     * @param string $userid User Id
     * @param string $pass Password
     */
    public function __construct($host, $cid, $userid, $pass)
    {
        $this->_url = 'https://'.$host.'/Messenger/AMMessenger';
        $this->_id = array(
            'CID'      => $cid,
            'UserID'   => $userid,
            'Password' => $pass,
        );
    }
    
    /**
     * Execute PSIGate action
     * 
     * Generic method to send action to PSIGate gateway.
     * In most cases thourgh specific action methods should
     * be used (accountSummary, accountRegister, chargeRegister etc.)
     * 
     * @param string $action PSIGate action code
     * @param array $data Data to submit
     * @param string|array[optional] $successCode Success codes to expect, unexpected codes rise Exceptions
     * @param string[optional] $returnNode Name of the node to pick from xml and present as result
     * @return mixed
     */
    public function action($action, $data, $successCode = null, $returnNode = null)
    {
        $data = array_merge($this->_id, array('Action' => $action), $data);
        $result = Helper::xmlToArray($this->_request($this->_url, Helper::arrayToXml($data, 'Request')->saveXML()));
        
        if ( ! isset($result['Response']) or ! isset($result['Response']['ReturnCode']) or ! isset($result['Response']['ReturnMessage'])) { // received response is not of expected format
            throw new Exception('Unexpected response from gateway', 'AMME-0001');
        }
        
        if ( ! is_null($successCode) and ! in_array($result['Response']['ReturnCode'], (array)$successCode)) {
            throw new Exception($result['Response']['ReturnMessage'], $result['Response']['ReturnCode']);
        }
        
        $result = $result['Response'];
        if ( ! is_null($returnNode)) {
            if (isset($result[$returnNode])) {
                $result = $result[$returnNode];
            } else {
                $result = null;
            }
        }
        
        return $result;
    }
    
    /**
     * Retrieve account summary
     * 
     * @param array[optional] $condition
     * @return array
     */
    public function accountSummary($condition = array())
    {
        // AMA00 Condition(...) : RPA-0020 RPA-0021 Condition(...) Account*(...) 
        return $this->action('AMA00', array(
            'Condition' => $condition,
        ), array('RPA-0020', 'RPA-0021'), 'Account');
    }
    
    /**
     * Register a new account
     * 
     * @param array $account
     * @return array
     */
    public function accountRegister($account)
    {
        // AMA01 Account+(...) : RPA-0000 Account+(ReturnCode ReturnMessage ...)
        return $this->action('AMA01', array(
            'Account' => $account,
        ), 'RPA-0000', 'Account');
    }
    
    /**
     * Update an account
     * 
     * @param string $accountId
     * @param array $update
     * @return array
     */
    public function accountUpdate($accountId, $update)
    {
        // AMA02 Condition(AccountID) Update(...) : RPA-0022 Condition(AccountID) Update(...) 
        return $this->action('AMA02', array(
            'Condition' => array('AccountID' => $accountId),
            'Update' => $update,
        ), 'RPA-0022', 'ReturnCode');
    }
    
    /**
     * Retrieve account details
     * 
     * @param string $accountId
     * @return array
     */
    public function accountDetails($accountId)
    {
        // AMA05 Condition(AccountID) : RPA-0020 RPA-0021 Condition(AccountID) Account(...)
        return $this->action('AMA05', array(
            'Condition' => array('AccountID' => $accountId),
        ), array('RPA-0020', 'RPA-0021'), 'Account');
    }
    
    /**
     * Enable account(s)
     * 
     * @param string $accountId
     * @return array
     */
    public function accountEnable($accountId)
    {
        // AMA08 Condition(AccountID+) : RPA-0046 Condition(AccountID+)
        return $this->action('AMA08', array(
            'Condition' => array('AccountID' => $accountId),
        ), 'RPA-0046', 'ReturnCode');
    }
    
    /**
     * Disable account(s)
     * 
     * @param string $accountId
     * @return array
     */
    public function accountDisable($accountId)
    {
        // AMA09 Condition(AccountID+) : RPA-0040 Condition(AccountID+)
        return $this->action('AMA09', array(
            'Condition' => array('AccountID' => $accountId),
        ), 'RPA-0040', 'ReturnCode');
    }
    
    /**
     * Add new credit cards to an account
     * 
     * NOTE:
     * result will only contain info about card added during the request
     * as opposed to account details returning all cards registered
     * 
     * @param string $accountId
     * @param array $cardInfo
     * @return array
     */
    public function accountCardAdd($accountId, $cardInfo)
    {
        // AMA11 Account(AccountID CardInfo+(...)) : Account(ReturnCode ReturnMessage CardInfo+)
        return $this->action('AMA11', array(
            'Account' => array('AccountID' => $accountId, 'CardInfo' => $cardInfo),
        ), 'RPA-0015', 'Account');
    }
    
    /**
     * Update a credit card expiration date
     * 
     * XXX
     * According to documentation one might assume successful code to be 
     * "RPA-0025 Update Card action completed successfully."
     * but actual code returned by gateway is
     * "RPA-0022 Update Account action completed successfully."
     * 
     * @param string $accountId
     * @param string $serialNo
     * @param array $update
     * @return array
     */
    public function accountCardUpdate($accountId, $serialNo, $update)
    {
        // AMA12 Condition(AccountID SerialNo) Update(...) : RPA-0022 Condition(AccountID SerialNo) Update(...)
        return $this->action('AMA12', array(
            'Condition' => array('AccountID' => $accountId, 'SerialNo' => $serialNo),
            'Update' => $update,
        ), 'RPA-0022', 'ReturnCode');
    }
    
    /**
     * Delete a credit card
     * 
     * @param string $accountId
     * @param string $serialNo
     * @return array
     */
    public function accountCardDelete($accountId, $serialNo)
    {
        // AMA14 Condition(AccountID SerialNo) : RPA-0058 Condition(AccountID SerialNo)
        return $this->action('AMA14', array(
            'Condition' => array('AccountID' => $accountId, 'SerialNo' => $serialNo),
        ), 'RPA-0058', 'ReturnCode');
    }
    
    /**
     * Enable a credit card
     * 
     * @param string $accountId
     * @param string $serialNo
     * @return array
     */
    public function accountCardEnable($accountId, $serialNo)
    {
        // AMA18 Condition(AccountID SerialNo) : RPA-0048 Condition(AccountID SerialNo)
        return $this->action('AMA18', array(
            'Condition' => array('AccountID' => $accountId, 'SerialNo' => $serialNo),
        ), 'RPA-0048', 'ReturnCode');
    }
    
    /**
     * Disable a credit card
     * 
     * @param string $accountId
     * @param string $serialNo
     * @return array
     */
    public function accountCardDisable($accountId, $serialNo)
    {
        // AMA19 Condition(AccountID SerialNo) : RPA-0042 Condition(AccountID SerialNo)
        return $this->action('AMA19', array(
            'Condition' => array('AccountID' => $accountId, 'SerialNo' => $serialNo),
        ), 'RPA-0042', 'ReturnCode');
    }
    
    /**
     * Create an account from an existing real-time order
     * 
     * @param string $orderId
     * @param string $storeId
     * @param string[optional] $accountId
     * @return array
     */
    public function accountRegisterFromOrder($orderId, $storeId, $accountId = null)
    {
        // AMA20 Condition(StoreID OrderID) : RPA-0150 Condition(...) Account(...)
        return $this->action('AMA20', array(
            'Condition' => array('AccountID' => $accountId, 'OrderID' => $orderId, 'StoreID' => $storeId),
        ), 'RPA-0150', 'Account');
    }
    
    /**
     * Create an account from an existing real-time order
     * 
     * NOTE:
     * result will only contain info about card added during the request
     * as opposed to account details returning all cards registered
     * 
     * @param string $orderId
     * @param string $storeId
     * @param string[optional] $accountId
     * @return array
     */
    public function accountCardAddFromOrder($orderId, $storeId, $accountId = null)
    {
        // AMA21 Condition(AccountID StoreID OrderID) : RPA-0015 Condition(...) Account(...)
        return $this->action('AMA21', array(
            'Condition' => array('AccountID' => $accountId, 'OrderID' => $orderId, 'StoreID' => $storeId),
        ), 'RPA-0015', 'Account');
    }
    
    /**
     * Retrieve charge summary
     * 
     * @param array[optional] $condition
     * @return array
     */
    public function chargeSummary($condition = array())
    {
        // RBC00 Condition(...) : RRC-0060 RRC-0061 Condition Charge*(...)
        return $this->action('RBC00', array(
            'Condition' => $condition,
        ), array('RRC-0060', 'RRC-0061'), 'Charge');
    }
    
    /**
     * Register a new charge
     * 
     * XXX
     * When registering charge based on template, required field must be set
     * explicitly even though already defined by it
     * 
     * @param array $charge
     * @return array
     */
    public function chargeRegister($charge)
    {
        // RBC01 Charge+(AccountID SerialNo StoreID ... ItemInfo+(...)) : RRC-0000 Charge+(ReturnCode ReturnMessage ...)
        return $this->action('RBC01', array(
            'Charge' => $charge,
        ), 'RRC-0000', 'Charge');
    }
    
    /**
     * Update a charge
     * 
     * @param string $rbcid
     * @param array $update
     * @return array
     */
    public function chargeUpdate($rbcid, $update)
    {
        // RBC02 Condition(RBCID) : RRC-0072 Condition(RBCID) Update(...)
        return $this->action('RBC02', array(
            'Condition' => array('RBCID' => $rbcid),
            'Update' => $update,
        ), 'RRC-0072', 'ReturnCode');
    }
    
    /**
     * Delete a charge
     * 
     * @param string $rbcid
     * @return array
     */
    public function chargeDelete($rbcid)
    {
        // RBC04 Condition(RBCID) : RRC-0082 Condition(RBCID)
        return $this->action('RBC04', array(
            'Condition' => array('RBCID' => $rbcid),
        ), 'RRC-0082', 'ReturnCode');
    }
    
    /**
     * Retrieve charge details
     * 
     * @param string $rbcid
     * @return array
     */
    public function chargeDetails($rbcid)
    {
        // RBC05 Condition(RBCID) : RRC-0060 RRC-0061 Condition(RBCID) Charge(...)
        return $this->action('RBC05', array(
            'Condition' => array('RBCID' => $rbcid),
        ), array('RRC-0060', 'RRC-0061'), 'Charge');
    }
    
    /**
     * Enable charge(s) – Changes the charge status to "Active"
     * 
     * @param string $rbcid
     * @return array
     */
    public function chargeEnable($rbcid)
    {
        // RBC08 Condition(RBCID+) : RRC-0190 Condition(RBCID+)
        return $this->action('RBC08', array(
            'Condition' => array('RBCID' => $rbcid),
        ),'RRC-0190', 'ReturnCode');
    }
    
    /**
     * Disable charge(s) – Changes the charge status to "Inactive"
     * 
     * @param string $rbcid
     * @return array
     */
    public function chargeDisable($rbcid)
    {
        // RBC09 chargeDisable Condition(RBCID) : RRC-0090 Condition(RBCID)
        return $this->action('RBC09', array(
            'Condition' => array('RBCID' => $rbcid),
        ),'RRC-0090', 'ReturnCode');
    }
    
    /**
     * Register a new charge
     * 
     * XXX
     * Invoice result group contains charge id (RBCID) twice which
     * makes return value having RBCID value inside Invoice as array
     * having two same elements.
     * 
     * @param array $charge
     * @return array
     */
    public function chargeImmediate($charge)
    {
        // RBC99 Charge(AccountID SerialNo StoreID ... ItemInfo+(...)) : Invoce(...) Result(...)
        return $this->action('RBC99', array(
            'Charge' => $charge,
        ), 'PSI-0000', null);
    }
    
    /**
     * Add new charge item(s)
     * 
     * @param array $charge
     * @return array
     */
    public function chargeItemAdd($charge)
    {
        // RBC11 Charge(AccountID SerialNo StoreID RBCID ... ItemInfo+(...)) : RRC-0065 Charge(RBCID ItemInfo+)
        return $this->action('RBC11', array(
            'Charge' => $charge,
        ), 'RRC-0065', 'Charge');
    }
    
    /**
     * Delete a charge item
     * 
     * @param string $rbcid
     * @param string $itemSerialNo
     * @return array
     */
    public function chargeItemDelete($rbcid, $itemSerialNo)
    {
        // RBC14 Condition(RBCID ItemSerialNo) : RRC-0098 -
        return $this->action('RBC14', array(
            'Condition' => array('RBCID' => $rbcid, 'ItemSerialNo' => $itemSerialNo),
        ), 'RRC-0098', 'ReturnCode');
    }
    
    /**
     * Enable a charge item - Changes the charge item status returned via RBC05 to "Active"
     * 
     * @param string $rbcid
     * @param string $itemSerialNo
     * @return array
     */
    public function chargeItemEnable($rbcid, $itemSerialNo)
    {
        // RBC18 Condition(RBCID ItemSerialNo) : RRC-0095 -
        return $this->action('RBC18', array(
            'Condition' => array('RBCID' => $rbcid, 'ItemSerialNo' => $itemSerialNo),
        ), 'RRC-0095', 'ReturnCode');
    }
    
    /**
     * Disable a charge item - Changes the charge item status returned via RBC05 to "Inactive"
     * 
     * @param string $rbcid
     * @param string $itemSerialNo
     * @return array
     */
    public function chargeItemDisable($rbcid, $itemSerialNo)
    {
        // RBC19 Condition(RBCID ItemSerialNo) : RRC-0092 -
        return $this->action('RBC19', array(
            'Condition' => array('RBCID' => $rbcid, 'ItemSerialNo' => $itemSerialNo),
        ), 'RRC-0092', 'ReturnCode');
    }
    
    /**
     * Mass update of Charge Information derived from Template Information
     * 
     * @param string $templateId
     * @return array
     */
    public function chargeUpdateFromTemplate($templateId)
    {
        // RBC52 Condition(TemplateID) : RRC-0072 -
        return $this->action('RBC52', array(
            'Condition' => array('TemplateID' => $templateId),
        ), 'RRC-0072', 'ReturnCode');
    }
    
    /**
     * Retrieve Charge Template Summary
     * 
     * XXX
     * Not all filters defined by documentation work,
     * the working ones are TemplateID, RBName, Status
     * 
     * @param array[optional] $condition
     * @return array
     */
    public function templateSummary($condition = array())
    {
        // CTL00 Condition(...) : CTL-0060 CTL-0061 Condition(...) ChargeTemplate+(...)
        return $this->action('CTL00', array(
            'Condition' => $condition,
        ), array('CTL-0060', 'CTL-0061'), 'ChargeTemplate');
    }
    
    /**
     * Register a new charge template
     * 
     * XXX
     * Charge trigger date must be specified with Trigger, not RBTrigger
     * Response does not contain generated TemplateID (returned empty)
     * 
     * @param array $chargeTemplate
     * @return array
     */
    public function templateRegister($chargeTemplate)
    {
        // CTL01 ChargeTemplate(... ItemInfo+(...)) : CTL-0000 ChargeTemplate(...)
        return $this->action('CTL01', array(
            'ChargeTemplate' => $chargeTemplate,
        ), 'CTL-0000', 'ChargeTemplate');
    }
    
    /**
     * Update a charge template
     * 
     * XXX
     * Charge trigger date must be specified with Trigger, not RBTrigger
     * 
     * @param string $templateId
     * @param array $update
     * @return array
     */
    public function templateUpdate($templateId, $update)
    {
        // CTL02 Condition(TemplateID) Update(...) : CTL-0072 Condition(TemplateID) Update(...)
        return $this->action('CTL02', array(
            'Condition' => array('TemplateID' => $templateId),
            'Update' => $update,
        ), 'CTL-0072', 'ReturnCode');
    }
    
    /**
     * Delete a charge template
     * 
     * XXX
     * Template id must be specified with RBCID, not with TemplateID
     * 
     * @param string $templateId
     * @return array
     */
    public function templateDelete($templateId)
    {
        // CTL04 Condition(RBCID) : CTL-0082 Condition(RBCID)
        return $this->action('CTL04', array(
            'Condition' => array('RBCID' => $templateId),
        ), 'CTL-0082', 'ReturnCode');
    }
    
    /**
     * Retrieve charge template details
     * 
     * @param string $templateId
     * @return array
     */
    public function templateDetails($templateId)
    {
        // CTL05 Condition(TemplateID) : CTL-0060 CTL-0061 Condition(TemplateID) ChargeTemplate(...)
        return $this->action('CTL05', array(
            'Condition' => array('TemplateID' => $templateId),
        ), array('CTL-0060', 'CTL-0061'), 'ChargeTemplate');
    }
    
    /**
     * Enable charge template
     * 
     * XXX
     * Template id must be specified with RBCID, not with TemplateID
     * 
     * @param string $templateId
     * @return array
     */
    public function templateEnable($templateId)
    {
        // CTL08 Condition(RBCID) : CTL-0190 Condition(RBCID)
        return $this->action('CTL08', array(
            'Condition' => array('RBCID' => $templateId),
        ), 'CTL-0190', 'ReturnCode');
    }
    
    /**
     * Disable charge template
     * 
     * XXX
     * Template id must be specified with RBCID, not with TemplateID
     * 
     * @param string $templateId
     * @return array
     */
    public function templateDisable($templateId)
    {
        // CTL09 Condition(RBCID) : CTL-0090 Condition(RBCID)
        return $this->action('CTL09', array(
            'Condition' => array('RBCID' => $templateId),
        ), 'CTL-0090', 'ReturnCode');
    }
    
    /**
     * Add new charge template item(s)
     * 
     * @param array $charge
     * @return array
     */
    public function templateItemAdd($chargeTemplate)
    {
        // CTL11 ChargeTemplate(TemplateID ItemInfo+(...)) : CTL-0065 -
        return $this->action('CTL11', array(
            'ChargeTemplate' => $chargeTemplate,
        ), 'CTL-0065', 'ReturnCode');
    }
    
    /**
     * Delete charge template item
     * 
     * @param string $templateId
     * @param string $itemSerialNo
     * @return array
     */
    public function templateItemDelete($templateId, $itemSerialNo)
    {
        // CTL14 Condition(RBCID ItemSerialNo) : CTL-0098 Condition(RBCID SerialNo)
        return $this->action('CTL14', array(
            'Condition' => array('RBCID' => $templateId, 'ItemSerialNo' => $itemSerialNo),
        ), 'CTL-0098', 'ReturnCode');
    }
    
    /**
     * Enable charge template item
     * 
     * @param string $templateId
     * @param string $itemSerialNo
     * @return array
     */
    public function templateItemEnable($templateId, $itemSerialNo)
    {
        // CTL18 Condition(TemplateID ItemSerialNo) : CTL-0192 Condition(TemplateID SerialNo)
        return $this->action('CTL18', array(
            'Condition' => array('RBCID' => $templateId, 'ItemSerialNo' => $itemSerialNo),
        ), 'CTL-0192', 'ReturnCode');
    }
    
    /**
     * Disable charge template item
     * 
     * @param string $templateId
     * @param string $itemSerialNo
     * @return array
     */
    public function templateItemDisable($templateId, $itemSerialNo)
    {
        // CTL19 Condition(TemplateID ItemSerialNo) : CTL-0092 Condition(TemplateID SerialNo)
        return $this->action('CTL19', array(
            'Condition' => array('RBCID' => $templateId, 'ItemSerialNo' => $itemSerialNo),
        ), 'CTL-0092', 'ReturnCode');
    }
    
    /**
     * Retrieve invoice summary
     * 
     * @param array[optional] $condition
     * @return array
     */
    public function invoiceSummary($condition = array())
    {
        // INV00 Condition(...) : RIV-0060 RIV-0061 Condition(...) Invoice*(...)
        return $this->action('INV00', array(
            'Condition' => $condition,
        ), array('RIV-0060', 'RIV-0061'), 'Invoice');
    }
    
    /**
     * Update an invoice
     * 
     * @param string $invoiceNo
     * @param array $update
     * @return array
     */
    public function invoiceUpdate($invoiceNo, $update)
    {
        // INV02 invoiceUpdate Condition(InvoiceNo) Update(...) : RIV-0072 Condition(InvoiceNo) Update(...)
        return $this->action('INV02', array(
            'Condition' => array('InvoiceNo' => $invoiceNo),
            'Update' => $update,
        ), 'RIV-0072', 'ReturnCode');
    }
    
    /**
     * Retrieve invoice details
     * 
     * @param string $invoiceNo
     * @return array
     */
    public function invoiceDetails($invoiceNo)
    {
        // INV05 Condition(InvoiceNo) : RIV-0060 RIV-0060 Condition(InvoiceNo) Invoice(...)
        return $this->action('INV05', array(
            'Condition' => array('InvoiceNo' => $invoiceNo),
        ), array('RIV-0060', 'RIV-0061'), 'Invoice');
    }
    
    /**
     * Change Invoice Status to Paid
     * 
     * @param string $invoiceNo
     * @return array
     */
    public function invoicePaid($invoiceNo)
    {
        // INV08 Condition(InvoiceNo) : RIV-0190 Condition(InvoiceNo)
        return $this->action('INV08', array(
            'Condition' => array('InvoiceNo' => $invoiceNo),
        ), 'RIV-0190', 'ReturnCode');
    }
    
    /**
     * Change Invoice Status to Outstanding
     * 
     * @param string $invoiceNo
     * @return array
     */
    public function invoiceOutstanding($invoiceNo)
    {
        // INV09 Condition(InvoiceNo) : RIV-0090 Condition(InvoiceNo)
        return $this->action('INV09', array(
            'Condition' => array('InvoiceNo' => $invoiceNo),
        ), 'RIV-0090', 'ReturnCode');
    }
    
    /**
     * Change Invoice Status to Rebill
     * 
     * @param string $invoiceNo
     * @return array
     */
    public function invoiceRebill($invoiceNo)
    {
        // INV99 Condition(InvoiceNo) : RIV-0198 Condition(InvoiceNo) Invoice(...)
        return $this->action('INV99', array(
            'Condition' => array('InvoiceNo' => $invoiceNo),
        ), 'RIV-0198', 'Invoice');
    }
    
    /**
     * Retrieve e-mail report(s)
     * 
     * XXX
     * Only Type filter works, others though defined 
     * by documentation (Interval, Period, Address, Status) do not
     * 
     * @param array[optional] $condition
     * @return array
     */
    public function emailReportSummary($condition = array())
    {
        // EMR00 Condition~(...) : EMR-0060 EMR-0061 Condition(...) EmailReportSetting+(...)
        return $this->action('EMR00', array(
            'Condition' => $condition,
        ), array('EMR-0060', 'EMR-0061'), 'EmailReportSetting');
    }
    
    /**
     * Register an e-mail report
     * 
     * @param array $emailReportSetting
     * @return array
     */
    public function emailReportRegister($emailReportSetting)
    {
        // EMR01 EmailReportSetting(...) : EMR-0000 EmailReportSetting(...)
        return $this->action('EMR01', array(
            'EmailReportSetting' => $emailReportSetting,
        ), 'EMR-0000', 'EmailReportSetting');
    }
    
    /**
     * Update an e-mail report
     * 
     * @param string $type
     * @param array $update
     * @return array
     */
    public function emailReportUpdate($type, $update)
    {
        // EMR02 Condition(Type) Update(...) : EMR-0072 Condition(Type) Update(...)
        return $this->action('EMR02', array(
            'Condition' => array('Type' => $type),
            'Update' => $update,
        ), 'EMR-0072', 'ReturnCode');
    }
    
    /**
     * Delete an e-mail report
     * 
     * @param string $type
     * @return array
     */
    public function emailReportDelete($type)
    {
        // EMR04 Condition(Type) : EMR-0082 Condition(Type)
        return $this->action('EMR04', array(
            'Condition' => array('Type' => $type),
        ), 'EMR-0082', 'ReturnCode');
    }
    
    /**
     * Retrieve e-mail report details
     * 
     * @param string $type
     * @return array
     */
    public function emailReportDetails($type)
    {
        // EMR05 Condition(Type) : EMR-0060 EMR-0061 Condition(Type) EmailReportSetting(...)
        return $this->action('EMR05', array(
            'Condition' => array('Type' => $type),
        ), array('EMR-0060', 'EMR-0061'), 'EmailReportSetting');
    }
    
    /**
     * Enable e-mail report(s)
     * 
     * @param string $type
     * @return array
     */
    public function emailReportEnable($type)
    {
        // EMR08 Condition(Type+) : EMR-0190 Condition(Type+)
        return $this->action('EMR08', array(
            'Condition' => array('Type' => $type),
        ), 'EMR-0190', 'ReturnCode');
    }
    
    /**
     * Disable e-mail report(s)
     * 
     * @param string $type
     * @return array
     */
    public function emailReportDisable($type)
    {
        // EMR09 Condition(Type+) : EMR-0090 Condition(Type+)
        return $this->action('EMR09', array(
            'Condition' => array('Type' => $type),
        ), 'EMR-0090', 'ReturnCode');
    }
    
    /**
     * Immediate report generation
     * 
     * @param string $type
     * @return array
     */
    public function emailReportImmediate($type)
    {
        // EMR99 emailReportImmediate Condition(Type) : EMR-0099 Condition(Type)
        return $this->action('EMR99', array(
            'Condition' => array('Type' => $type),
        ), 'EMR-0099', 'ReturnCode');
    }
}
