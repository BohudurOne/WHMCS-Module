<?php
if (!defined('WHMCS')) {
    die('Direct Access Not Allowed');
}

function bohudur_MetaData() {
    return [
        'DisplayName' => 'Bohudur Payments',
        'APIVersion' => '1.0',
    ];
}


function bohudur_Config() {
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Bohudur Payments',
        ],
        'apiKey' => [
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '50',
            'Description' => 'Enter your Bohudur API Key.',
        ],
        'webhookSuccessUrl' => [
            'FriendlyName' => 'Webhook Success URL',
            'Type' => 'text',
            'Size' => '100',
            'Description' => 'Optional: URL for successful payments.',
        ],
        'webhookCancelledUrl' => [
            'FriendlyName' => 'Webhook Cancelled URL',
            'Type' => 'text',
            'Size' => '100',
            'Description' => 'Optional: URL for cancelled payments.',
        ],
        'currencyRate' => [
            'FriendlyName' => 'Currency Rate',
            'Type' => 'text',
            'Size' => '10',
            'Description' => '<br>Enter custom conversion rate [1 USD = ? BDT]. (Leave blank to use Bohudur Currency Converter).',
        ],
    ];
}

function bohudur_Link($params) {
    $invoiceId = $params['invoiceid'];
    $amount = (float) $params['amount'];
    $currency = $params['currency'];
    $clientEmail = $params['clientdetails']['email'];
    $clientName = $params['clientdetails']['firstname'] . ' ' . $params['clientdetails']['lastname'];
    $apiKey = $params['apiKey'];
    $webhookSuccessUrl = $params['webhookSuccessUrl'];
    $webhookCancelledUrl = $params['webhookCancelledUrl'];
    $currencyRate = $params['currencyRate'];
    $callbackUrl = $params['systemurl'] . 'modules/gateways/callback/bohudur.php?invoiceid=' . $invoiceId;
    
    $paymentData = [
        'fullname' => $clientName,
        'email' => $clientEmail,
        'amount' => $amount,
        'redirect_url' => $callbackUrl,
        'cancelled_url' => $params['systemurl'] . 'viewinvoice.php?id=' . $invoiceId,
        'return_type' => 'Q',
        'metadata' => [
            'invoice_id' => $invoiceId,
        ],
    ];

    if ($currency != 'BDT') {
        $paymentData['currency'] = $currency;
    }
    if (!empty($currencyRate)) {
        $paymentData['currency_value'] = $currencyRate;
    }
    if (!empty($webhookSuccessUrl)) {
        $paymentData['webhook']['success'] = $webhookSuccessUrl;
    }
    if (!empty($webhookCancelledUrl)) {
        $paymentData['webhook']['cancel'] = $webhookCancelledUrl;
    }
    
    $response = bohudur_SendRequest($apiKey, $paymentData);
    $result = json_decode($response, true);
    
    if (isset($result['payment_url'])) {
        return '<form action="' . $result['payment_url'] . '" method="get">
                    <input type="submit" value="Pay with Bohudur" />
                </form>';
    } else {
        echo '<p>Error: Unable to generate payment link. '.$response.'</p>';
        exit;
    }
}

function bohudur_SendRequest($apiKey, $data) {
    $url = 'https://request.bohudur.one/create/';
    $headers = [
        'AH-BOHUDUR-API-KEY: ' . $apiKey,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}

?>
