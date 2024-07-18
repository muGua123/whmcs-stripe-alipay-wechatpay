<?php
/*
 * @ https://EasyToYou.eu - IonCube v11 Decoder Online
 * @ PHP 7.4
 * @ Decoder version: 1.0.2
 * @ Release: 10/08/2022
 */

// Decoded file for php version 71.
$randomSleep = rand(300000, 1500000);
usleep($randomSleep);
require_once __DIR__ . "/../../../init.php";
require_once __DIR__ . "/../../../includes/gatewayfunctions.php";
require_once __DIR__ . "/../../../includes/invoicefunctions.php";
require_once __DIR__ . "/../../../includes/invoicefunctions.php";
require_once __DIR__ . "/../dplstripe_alipay/alipay.php";
$gatewayModuleName = basename(__FILE__, ".php");
$params = getGatewayVariables($gatewayModuleName);
$payload = file_get_contents("php://input");
$stripeSignature = $_SERVER["HTTP_STRIPE_SIGNATURE"];
$webhookSecretKey = $params["webhookSecretKey"];
try {
    $event = Stripe\Webhook::constructEvent($payload, $stripeSignature, $webhookSecretKey);
} catch (Exception $e) {
    logTransaction($params["name"], $e->getMessage(), "error");
    http_response_code(500);
    exit;
}
if ($event->type == "charge.succeeded") {
    $paymentDetails = $event->data->object;
    if ($paymentDetails["payment_method_details"]["type"] !== "alipay" &&
        $paymentDetails["payment_method_details"]["type"] !== "wechat_pay"
    ) {
        exit;
    }
    if ($paymentDetails["paid"] !== true) {
        logTransaction($params["name"], json_encode($paymentDetails), $paymentDetails["paid"]);
        exit;
    }
    if (!empty($paymentDetails["calculated_statement_descriptor"])) {
        $invoiceField = $paymentDetails["calculated_statement_descriptor"];
    } else {
        $invoiceField = $paymentDetails["description"];
    }
    $parseInvoiceNumber = preg_match_all("! #?\\d+!", $invoiceField, $result);
    $invoiceNumber = end($result);
    if (is_array($invoiceNumber)) {
        $invoiceNumber = str_replace("#", "", trim($invoiceNumber[0]));
    }
    if (empty($invoiceNumber) || !filter_var($invoiceNumber, FILTER_VALIDATE_INT)) {
        logTransaction($params["name"], "Can not find invoice number", "error");
        http_response_code(500);
        exit;
    }
    if (!empty(Illuminate\Database\Capsule\Manager::table("tblaccounts")->where("transid", $paymentDetails["id"])->first()->id)) {
        exit;
    }
    $invoiceId = checkCbInvoiceID($invoiceNumber, $params["name"]);
    checkCbTransID($paymentDetails["id"]);
    logTransaction($params["name"], json_encode($paymentDetails), $paymentDetails["paid"]);
    $stripe = new dpl_stripecheckout_alipay($params);
    $getFee = $stripe->getPaymentFee($paymentDetails["balance_transaction"])["fee"];
    $amountOfPayment = $paymentDetails["amount_captured"] / 100;
    if ($params["convertto"]) {
        $getInvoice = Illuminate\Database\Capsule\Manager::table("tblinvoices")->where("id", $invoiceNumber)->first();
        $getWhmcsCurrency = Illuminate\Database\Capsule\Manager::table("tblcurrencies")->where("code", $event->data->object["currency"])->first()->id;
        $getUserCurrency = Illuminate\Database\Capsule\Manager::table("tblclients")->where("id", $getInvoice->userid)->first()->currency;
        if ((int)$getUserCurrency !== (int)$getWhmcsCurrency) {
            $amountOfPayment = convertCurrency($amountOfPayment, $getWhmcsCurrency, $getUserCurrency);
            $getFee = convertCurrency($getFee, $getWhmcsCurrency, $getUserCurrency);
        }
    }
    addInvoicePayment($invoiceId, $paymentDetails["id"], $amountOfPayment, $getFee, $gatewayModuleName);
}
exit;

?>