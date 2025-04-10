<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

class Bohudur
{
    private $gatewayParams;
    
    public function __construct() {
        $this->gatewayParams = getGatewayVariables(basename(__FILE__, '.php'));
    }

    public function addTransaction($invoiceId, $transactionId, $amount) {
        $fields = [
            'invoiceid' => $invoiceId,
            'transid' => $transactionId,
            'gateway' => $this->gatewayParams['name'],
            'date' => date("Y-m-d H:i:s"),
            'amount' => $amount,
        ];

        $result = localAPI('AddInvoicePayment', $fields);
        return $result;
    }
    
    public function sendRequest($paymentKey) {
        $url = 'https://request.bohudur.one/execute/';
        $data = json_encode(['paymentkey' => $paymentKey]);

        $headers = [
            'Content-Type: application/json',
            'AH-BOHUDUR-API-KEY: ' . $this->gatewayParams['apiKey']
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    public function verifyPayment($paymentKey, $invoiceId) {
        $response = $this->sendRequest($paymentKey);

        if ($response) {
            $result = json_decode($response, true);

            if (!$result) {
                exit("Invalid JSON response from payment API.");
            }
            
            if (isset($result['Status']) && $result['Status'] === 'EXECUTED') {
                $transactionId = $result['Transaction ID'];
                $amount = $result['Amount'];

                $addTransactionResult = $this->addTransaction($invoiceId, $transactionId, $amount);
                
                if ($addTransactionResult['result'] === 'success') {
                    redirSystemURL("id={$invoiceId}", "viewinvoice.php");
                    exit;
                } else {
                    exit("Failed to add the transaction.");
                }
            } else if ($result['responseCode'] == 807) {
                exit("Oops! Payment already used.");
            } else {
                exit("Something went wrong.");
            }
        } else {
            exit("Payment validation failed.");
        }
    }
}

$bohudur = new Bohudur();

$paymentKey = $_GET['payment_key'];
$invoiceId = (int) $_GET['invoiceid'];

if (preg_match('/^[a-zA-Z0-9]{20}$/', $paymentKey) && $invoiceId > 0) {
    $bohudur->verifyPayment($paymentKey, $invoiceId);
} else {
    http_response_code(404);
    exit;
}

?>
