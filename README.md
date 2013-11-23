# PSIGate PHP API

PHP implementation of PSIGate XML Messenger and Account Manager API.

## General Information

The library is a wrapper around [PSIGate XML API](http://psigate.com/pages/techsupport.asp) making input and result array structures reflect
structure of XML documents PSIGate gateway expects. Refer to original PSIGate documentation to understand what parameters to set and what
result values to expect.

## Installation

The recommended way to install the library is through [Composer](https://getcomposer.org).

```json
{
    "require": {
        "propa/psigate": "*"
    }
}
```

## Handling Exceptions

If an error occurs during request processing, an exception is thrown. Exception code is initialized with corresponding
PSIGate API error code (RPA-0002, RIV-0019 etc.) and exception message with corresponding error message, refer to PSIGate
API documentation for the full list of error codes and associated messages.

There are several PHP API specific errors related to unexpected response format (AMME-0001 and XMLM-0001) and cURL (CURL-0001, CURL-0006 etc).

## XML Messenger (XMLMessenger)

### Basic usage

```php
$xmlm = new \PSIGate\XMLMessenger('dev.psigate.com:7989', 'teststore', 'psigate1234');
try {
    $result = $xmlm->order(array(
        'Subtotal' => '10.00',
        'PaymentType' => 'CC',
        'CardAction' => '0',
        'CardNumber' => '4111111111111111',
        'CardExpMonth' => '02',
        'CardExpYear' => '16',
        'CardIDNumber' => '1234',
        'Item' => array(
            array(
                'ItemID' => 'PSI-BOOK',
                'ItemDescription' => 'XML Interface Doc',
                'ItemQty' => '1',
                'ItemPrice' => '10.00',
                'Option' => array(
                    'Type' => 'Electronic',
                    'File' => 'xml.doc',
                ),
            ),
            array(
                'ItemID' => 'COUPON',
                'ItemDescription' => '10% discount',
                'ItemQty' => '1',
                'ItemPrice' => '-2.00',
            ),
        ),
    ));
    
    // analyze transaction result ...
    
} catch (\PSIGate\Exception $e) {
    // handle transaction error ...
}
```

## Account Manager (AMMessenger)

### Basic usage

```php
$amm = new \PSIGate\AMMessenger('dev.psigate.com:8645', '1000001', 'teststore', 'testpass');
try {
    // register a new account
    $accountResult = $amm->accountRegister(array(
        'Name' => 'John Smith Jr.',
        'Company' => 'PSiGate Inc.',
        'Address1' => '145 King St.',
        'Address2' => '2300',
        'City' => 'Toronto',
        'Province' => 'Ontario',
        'Postalcode' => 'M5H 1J8',
        'Country' => 'Canada',
        'Phone' => '1-905-123-4567',
        'Fax' => '1-905-123-4568',
        'Email' => 'support@psigate.com',
        'Comments' => 'No Comment Today',
        'CardInfo' => array(
            'CardHolder' => 'John Smith',
            'CardNumber' => '4005550000000019',
            'CardExpMonth' => '08',
            'CardExpYear' => '11',
        ),
    ));
    
    // retrieve newly assigned account id
    $accountId = $accountResult['AccountID'];
    $cardSerialNo = $accountResult['CardInfo']['SerialNo'];
    
    // register a charge
    $chargeResult = $amm->chargeRegister(array(
        'StoreID' => 'teststore',
        'AccountID' => $accountId,
        'SerialNo' => $cardSerialNo,
        'RBName' => 'Monthly Payment',
        'Interval' => 'M',
        'RBTrigger' => '12',
        'EndDate' => '2011.12.31',
        'ItemInfo' => array(
            'ProductID' => 'NEWSPAPER',
            'Description' => 'TORONTO STAR',
            'Quantity' => '1',
            'Price' => '25',
            'Tax1' => '2',
            'Tax2' => '1.25',
        ),
    ));
    
    // retrieve newly created charge id
    $chargeId = $chargeResult['RBCID'];
    
    // disable a charge
    $amm->chargeDisable($chargeId);
    
    // ...
    
} catch (\PSIGate\Exception $e) {
    // handle error ...
}
```

### Known Issues (2013-11-21)

There are insonsitensies between Account Manager API documentation v1.1.08 and actual API behavior:

1.  Not all return codes and messages are documented (e.g. "EMR-0099 Immediate email report has been sent successfully.").
2.  Some methods return different success code from what could be assumed from documentation, e.g. account card update
    action (AMA12) returns "RPA-0022 Update Account action completed successfully." though there is more specific
    "RPA-0025 Update Card action completed successfully." listed.
3.  Summary actions do not support all filters specified in documentation.
4.  Summary actions do not have required filters, though documentation may list some as such.
5.  Register actions return generated ID if not specified explicitly except for "CTL01 Register template"
    which returns empty value for TemplateID though subsequent requests for details show that TemplateID was generated.
6.  In charge template related actions, except for template item add (CTL11), trigger date must be specified with Trigger,
    not with RBTrigger as suggested by documentation.
7.  Delete, enable, disable template, and delete, enable and disable template item actions (CTL04, CTL08, CTL09,
    and CTL14, CTL18, CTL19 respectively) expect template id to be 
    supplied with RBCID condition, not with TemplateID as suggested by documentation.
8.  Item Add actions are not satisfied with holder id only (RBCID or TemplateID) since PSIGate runs validation
    for condition equivalent elements and thus required fields (as defined in holder register action) must be
    supplied even though holder id would be enough.
9.  Register charge action (RBC01) needs required fields set regardless of the fact that charge is created based on
    template with the same ones defined.
10. Immediate charge action (RBC99) in Invoice response element returns RBCID value twice.