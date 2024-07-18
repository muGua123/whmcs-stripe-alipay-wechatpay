<?php
/*
 * @ https://EasyToYou.eu - IonCube v11 Decoder Online
 * @ PHP 7.4
 * @ Decoder version: 1.0.2
 * @ Release: 10/08/2022
 */

// Decoded file for php version 71.
if (!class_exists("ComposerAutoloaderInit2e7937fe969b19ab9e3b1291eb13be5f")) {
    require_once __DIR__ . "/vendor/autoload.php";
}
if (!class_exists("dpl_stripecheckout_alipay")) {
    class dpl_stripecheckout_alipay
    {
        public function __construct($params)
        {
            $this->params = $params;
            $this->username = $params["username"];
            $this->password = $params["password"];
            $this->url = $params["apiUrl"];
            $this->clientDetails = $this->getClientInformationByInvoiceId();
            $this->invoiceUrl = str_replace("//viewinvoice", "/viewinvoice", $params["systemurl"] . "/viewinvoice.php?id=" . $params["invoiceid"]);
            Stripe\Stripe::setApiKey($this->params["privatekey"]);
        }

        private function getClientInformationByInvoiceId()
        {
            $return = [];
            $getClientId = $this->getInvoiceDetails()->userid;
            foreach (Illuminate\Database\Capsule\Manager::table("tblclients")->where("id", $getClientId)->get() as $client) {
                foreach ($client as $key => $val) {
                    $return[$key] = $val;
                }
            }
            return $return;
        }

        private function getInvoiceDetails()
        {
            return Illuminate\Database\Capsule\Manager::table("tblinvoices")->where("id", $this->params["invoiceid"])->first();
        }

        private function convertAmount($amount)
        {
            return (int)str_replace(".", "", $amount * 100);
        }

        private function getInvoicesLines()
        {
            $return = [];
            foreach (Illuminate\Database\Capsule\Manager::table("tblinvoiceitems")->where("invoiceid", $this->params["invoiceid"])->get() as $items) {
                $return[] = ["quantity" => 1, "price_data" => ["currency" => $this->params["currency"], "unit_amount" => $this->convertAmount($items->amount), "product_data" => ["name" => $items->description]]];
            }
            return $return;
        }

        private function getInvoiceLine()
        {
            $return[] = [
                "quantity" => 1,
                "price_data" => [
                    "currency" => $this->params["currency"],
                    "unit_amount" => $this->convertAmount($this->params["amount"]),
                    "product_data" => ["name" => $this->makeStatement()]
                ]
            ];
            return $return;
        }

        public function refund($amount, $transactionId)
        {
            $refund = Stripe\Refund::create(["amount" => $this->convertAmount($amount), "charge" => $transactionId]);
            return $refund;
        }

        public function getPaymentFee($transactionId)
        {
            try {
                $stripe = new Stripe\StripeClient($this->params["privatekey"]);
                $getTransaction = $stripe->balanceTransactions->retrieve($transactionId);
                return ["fee" => $getTransaction["fee"] / 100];
            } catch (Exception $e) {
                return ["fee" => 0];
            }
        }

        public function makeStatement()
        {
            $makeStatement = str_replace("{{invoiceid}}", $this->params["invoiceid"], $this->params["statementDescription"]);
            $makeStatement = str_replace("{{companyname}}", $this->params["companyname"], $makeStatement);
            return $makeStatement;
        }

        public function generatePaymentLink()
        {
            $payment_method_types = [];
            $payment_method_options = [];

            if (!empty($this->params['enableAlipay']) && $this->params['enableAlipay'] == 'on') {
                $payment_method_types[] = 'alipay';
            }
            if (!empty($this->params['enableWechatPay']) && $this->params['enableWechatPay'] == 'on') {
                $payment_method_types[] = 'wechat_pay';
                $payment_method_options['wechat_pay'] = ['client' => 'web'];
            }

            if (empty($payment_method_types)) {
                throw new Exception("No payment method enabled");
            }

            $postData = [
                "payment_method_types" => $payment_method_types,
                "payment_method_options" => $payment_method_options,
                "line_items" => [$this->getInvoiceLine()],
                "mode" => "payment",
                "success_url" => $this->invoiceUrl . "&paysuccess=1",
                "cancel_url" => $this->invoiceUrl . "&paymentfailed=1",
                "client_reference_id" => $this->params["invoiceid"],
                "payment_intent_data" => ["statement_descriptor" => $this->makeStatement(), "description" => $this->makeStatement()]
            ];

            $stripeClientInstance = new Stripe\StripeClient($this->params["privatekey"]);
            if (!empty($this->clientDetails["email"])) {
                $stripeClient = $stripeClientInstance->customers->all(["email" => $this->clientDetails["email"], 'limit' => 1]);
                if ($stripeClient) {
                    $postData['customer'] = $stripeClient['data'][0]->id;
                } else {
                    $createClient = $stripeClientInstance->customers->create([
                        "address" => [
                            "line1" => $this->clientDetails["address1"],
                            "line2" => $this->clientDetails["address2"],
                            "country" => $this->clientDetails["country"],
                            "postal_code" => $this->clientDetails["postcode"],
                            "state" => $this->clientDetails["state"]
                        ],
                        "name" => $this->clientDetails["firstname"] . " " . $this->clientDetails["lastname"],
                        "phone" => $this->clientDetails["phonenumber"],
                        "email" => $this->clientDetails["email"]
                    ]);
                    if (empty($createClient["id"])) {
                        throw new Exception("Error during creating stripe client");
                    }
                    $postData["customer"] = $createClient["id"];
                }
            }

//            if (!empty($this->clientDetails["email"])) {
//                $postData['email'] = $this->clientDetails["email"];
//            }

            $session = Stripe\Checkout\Session::create($postData);

            return $session;
        }
    }
}

?>