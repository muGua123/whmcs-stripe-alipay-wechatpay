<?php
/*
 * @ https://EasyToYou.eu - IonCube v11 Decoder Online
 * @ PHP 7.4
 * @ Decoder version: 1.0.2
 * @ Release: 10/08/2022
 */

// Decoded file for php version 71.
if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
function dplstripe_alipay_MetaData()
{
    return ["DisplayName" => "Stripe Alipay", "APIVersion" => "1.1"];
}

function dplstripe_alipay_config()
{
    return [
        "FriendlyName" => ["Type" => "System", "Value" => "Stripe Alipay"],
        "licensekey" => ["FriendlyName" => "License Key", "Type" => "text", "Size" => "25", "Default" => "", "Description" => "License Key from Deploymentcode.com."],
        "publickey" => ["FriendlyName" => "Public Key Token", "Type" => "text", "Size" => "25", "Default" => ""],
        "privatekey" => ["FriendlyName" => "Private Key Token", "Type" => "password", "Size" => "25", "Default" => ""],
        "webhookSecretKey" => ["FriendlyName" => "Webhook Secret Signature", "Type" => "password", "Size" => "25", "Default" => "", "Description" => ""],
        "statementDescription" => ["FriendlyName" => "Statement Description", "Type" => "text", "Size" => "25", "Default" => "Invoice {{invoiceid}} - {{companyname}}", "Description" => "This does appear on yours client statement."],
        "enableAlipay" => ["FriendlyName" => "Enable Alipay", "Type" => "yesno", "Default" => "", "Description" => "Activate alipay payment."],
        "enableWechatPay" => ["FriendlyName" => "Enable WechatPay", "Type" => "yesno", "Default" => "", "Description" => "Activate wechat payment."],
    ];
}

function dplstripe_alipay_refund($params)
{
    $licenseCheck = dplstripe_alipay_callLicenseCheck("dplstripealipay", $params["licensekey"], "Vvieri882ka8bkjkit56");
    if ($licenseCheck["licensestatus"] !== "OK") {
        logActivity("License for Stripe Alipay is invalid. Please contact support@deploymentcode.com if you have questions.");
        return ["status" => "error", "rawdata" => "License for Stripe Alipay is invalid. Please contact support@deploymentcode.com if you have questions."];
    }
    require_once __DIR__ . "/dplstripe_alipay/alipay.php";
    try {
        $stripe = new dpl_stripecheckout_alipay($params);
        $doRefund = $stripe->refund($params["amount"], $params["transid"]);
        if (!empty($doRefund["balance_transaction"])) {
            $getFee = $stripe->getPaymentFee($doRefund["balance_transaction"])["fee"];
            return ["status" => "success", "rawdata" => json_encode($doRefund), "transid" => $doRefund["balance_transaction"], "fee" => $getFee];
        }
        return ["status" => "declined", "rawdata" => json_encode($doRefund)];
    } catch (Exception $e) {
        return ["status" => "error", "rawdata" => $e->getMessage()];
    }
}

function dplstripe_alipay_link($params)
{
    $licenseCheck = dplstripe_alipay_callLicenseCheck("dplstripealipay", $params["licensekey"], "Vvieri882ka8bkjkit56");
    if ($licenseCheck["licensestatus"] !== "OK") {
        logActivity("License for Stripe Alipay is invalid. Please contact support@deploymentcode.com if you have questions.");
    } else {
        require_once __DIR__ . "/dplstripe_alipay/alipay.php";
        if ($_GET["paysuccess"] === "1") {
            $i = 0;
            $invoiceUrl = str_replace("//viewinvoice", "/viewinvoice", $params["systemurl"] . "/viewinvoice.php?id=" . $params["invoiceid"]);
            while ($i <= 15) {
                $getInvoiceStatus = Illuminate\Database\Capsule\Manager::table("tblinvoices")->where("id", $_GET["id"])->first()->status;
                if ($getInvoiceStatus === "Paid") {
                    header("Location: " . $invoiceUrl);
                    exit;
                }
                $i++;
                sleep(1);
            }
            header("Location: " . $invoiceUrl);
            exit;
        }
        if ($_GET["req"] === "doPayment") {
            try {
                $stripe = new dpl_stripecheckout_alipay($params);
                $createPaymentUrl = $stripe->generatePaymentLink();
                echo json_encode($createPaymentUrl);
            } catch (Exception $e) {
                logTransaction("Stripe Alipay", json_encode($e->getMessage()), "Error on doPayment");
                echo json_encode(["error" => "An error occurred. Please try again or contact our support."]);
            }
            exit;
        }
        $output = "\n        <script src='https://js.stripe.com/v3/'></script>\n        <button id='dplstripe_alipay_payment' class='btn btn-success'>" . $params["langpaynow"] . "</button>\n        \n        <script type='text/javascript'>\n        var stripe = Stripe('" . $params["publickey"] . "');\n        var checkoutButton = document.getElementById('dplstripe_alipay_payment');\n\n        checkoutButton.addEventListener('click', function() {\n            console.log (checkoutButton);\n            checkoutButton.disabled = true;\n            \n            fetch('viewinvoice.php?id=" . $params["invoiceid"] . "&req=doPayment', {\n                method: 'POST',\n            }).then(function(response) {\n                return response.json();\n            }).then(function(result) {\n                if (result.error) {\n                    if (result.error.message) {\n                        alert(result.error.message);\n                    } else {\n                        alert (result.error);\n                    }\n                }\n                \n                return result;\n            }).then(function(session) {\n                return stripe.redirectToCheckout({ sessionId: session.id });\n            }).catch(function(error) {\n                console.error('Error:', error);\n            }).then(function (end) {\n                checkoutButton.disabled = false;\n            });\n        });\n        </script>\n    ";
        return $output;
    }
}

function dplstripe_alipay_check_license($licensekey, $localkey = "")
{
    $whmcsurl = "https://whmcs.deploymentcode.com/";
    $licensing_secret_key = "Vvis8wki9LKAjlkvjlkue89j34iolkAJlksjlksdf";
    $localkeydays = 3;
    $allowcheckfaildays = 3;
    $check_token = time() . md5(mt_rand(1000000000, 0) . $licensekey);
    $checkdate = date("Ymd");
    $usersip = isset($_SERVER["SERVER_ADDR"]) ? $_SERVER["SERVER_ADDR"] : $_SERVER["LOCAL_ADDR"];
    foreach (Illuminate\Database\Capsule\Manager::table("tblconfiguration")->where("setting", "SystemURL")->get() as $SysConfig) {
        $domain = $SysConfig->value;
        $domain = preg_replace("#^https?://#", "", rtrim($domain, "/"));
        if (strpos($domain, "/") !== false) {
            $domain = explode("/", $domain);
            if (is_array($domain)) {
                $domain = $domain[0];
            } else {
                $domain = $domain;
            }
        }
    }
    $dirpath = dirname(__FILE__);
    $verifyfilepath = "modules/servers/licensing/verify.php";
    $localkeyvalid = false;
    if ($localkey) {
        $localkey = str_replace("\n", "", $localkey);
        $localdata = substr($localkey, 0, strlen($localkey) - 32);
        $md5hash = substr($localkey, strlen($localkey) - 32);
        if ($md5hash == md5($localdata . $licensing_secret_key)) {
            $localdata = strrev($localdata);
            $md5hash = substr($localdata, 0, 32);
            $localdata = substr($localdata, 32);
            $localdata = base64_decode($localdata);
            $localkeyresults = unserialize($localdata);
            $originalcheckdate = $localkeyresults["checkdate"];
            if ($md5hash == md5($originalcheckdate . $licensing_secret_key)) {
                $localexpiry = date("Ymd", mktime(0, 0, 0, date("m"), date("d") - $localkeydays, date("Y")));
                if ($localexpiry < $originalcheckdate) {
                    $localkeyvalid = true;
                    $results = $localkeyresults;
                    $validdomains = explode(",", $results["validdomain"]);
                    if (!in_array($domain, $validdomains)) {
                        $localkeyvalid = false;
                        $localkeyresults["status"] = "Invalid";
                        $results = [];
                    }
                    $validdirs = explode(",", $results["validdirectory"]);
                    if (!in_array($dirpath, $validdirs)) {
                        $localkeyvalid = false;
                        $localkeyresults["status"] = "Invalid";
                        $results = [];
                    }
                }
            }
        }
    }
    if (!$localkeyvalid) {
        $responseCode = 0;
        $postfields = ["licensekey" => $licensekey, "domain" => $domain, "ip" => $usersip, "dir" => $dirpath];
        if ($check_token) {
            $postfields["check_token"] = $check_token;
        }
        $query_string = "";
        foreach ($postfields as $k => $v) {
            $query_string .= $k . "=" . urlencode($v) . "&";
        }
        if (function_exists("curl_exec")) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $whmcsurl . $verifyfilepath);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $data = curl_exec($ch);
            $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $responseCodePattern = "/^HTTP\\/\\d+\\.\\d+\\s+(\\d+)/";
            $fp = @fsockopen($whmcsurl, 80, $errno, $errstr, 5);
            if ($fp) {
                $newlinefeed = "\r\n";
                $header = "POST " . $whmcsurl . $verifyfilepath . " HTTP/1.0" . $newlinefeed;
                $header .= "Host: " . $whmcsurl . $newlinefeed;
                $header .= "Content-type: application/x-www-form-urlencoded" . $newlinefeed;
                $header .= "Content-length: " . @strlen($query_string) . $newlinefeed;
                $header .= "Connection: close" . $newlinefeed . $newlinefeed;
                $header .= $query_string;
                $data = $line = "";
                @stream_set_timeout($fp, 20);
                @fputs($fp, $header);
                $status = @socket_get_status($fp);
                while (!@feof($fp) && $status) {
                    $line = @fgets($fp, 1024);
                    $patternMatches = [];
                    if (!$responseCode && preg_match($responseCodePattern, trim($line), $patternMatches)) {
                        $responseCode = empty($patternMatches[1]) ? 0 : $patternMatches[1];
                    }
                    $data .= $line;
                    $status = @socket_get_status($fp);
                }
                @fclose($fp);
            }
        }
        if ($responseCode != 200) {
            $localexpiry = date("Ymd", mktime(0, 0, 0, date("m"), date("d") - ($localkeydays + $allowcheckfaildays), date("Y")));
            if ($localexpiry < $originalcheckdate) {
                $results = $localkeyresults;
            } else {
                $results = [];
                $results["status"] = "Invalid";
                $results["description"] = "Remote Check Failed";
                return $results;
            }
        } else {
            preg_match_all("/<(.*?)>([^<]+)<\\/\\1>/i", $data, $matches);
            $results = [];
            foreach ($matches[1] as $k => $v) {
                $results[$v] = $matches[2][$k];
            }
        }
        if (!is_array($results)) {
            exit("Invalid License Server Response");
        }
        if ($results["md5hash"] && $results["md5hash"] != md5($licensing_secret_key . $check_token)) {
            $results["status"] = "Invalid";
            $results["description"] = "MD5 Checksum Verification Failed";
            return $results;
        }
        if ($results["status"] == "Active") {
            $results["checkdate"] = $checkdate;
            $data_encoded = serialize($results);
            $data_encoded = base64_encode($data_encoded);
            $data_encoded = md5($checkdate . $licensing_secret_key) . $data_encoded;
            $data_encoded = strrev($data_encoded);
            $data_encoded = $data_encoded . md5($data_encoded . $licensing_secret_key);
            $data_encoded = wordwrap($data_encoded, 80, "\n", true);
            $results["localkey"] = $data_encoded;
        }
        $results["remotecheck"] = true;
    }
    unset($postfields);
    unset($data);
    unset($matches);
    unset($whmcsurl);
    unset($licensing_secret_key);
    unset($checkdate);
    unset($usersip);
    unset($localkeydays);
    unset($allowcheckfaildays);
    unset($md5hash);
    return $results;
}

function dplstripe_alipay_callLicenseCheck($module, $licensekey, $secret)
{
    Illuminate\Database\Capsule\Manager::statement("CREATE TABLE IF NOT EXISTS `deploymentcode_licenses` (            `id` int(11) NOT NULL AUTO_INCREMENT,            `module` text NOT NULL,            `license` text NOT NULL,            `localkey` text NOT NULL,            PRIMARY KEY (`id`)        ) ENGINE=InnoDB  DEFAULT CHARSET=latin1;");
    $licenseCache = Illuminate\Database\Capsule\Manager::table("deploymentcode_licenses")->where("module", $module)->first();
    $localkey = "";
    if (empty($licenseCache->id)) {
        Illuminate\Database\Capsule\Manager::table("deploymentcode_licenses")->insert(["module" => $module, "license" => "", "localkey" => ""]);
    }
    if (!empty($licenseCache->license)) {
        if (empty($licensekey)) {
            $licensekey = $licenseCache->license;
        }
        $localkey = $licenseCache->localkey;
    }
    if ((string)$secret !== "Vvieri882ka8bkjkit56") {
        exit;
    }
    $results = dplstripe_alipay_check_license($licensekey, $localkey);
    $results["status"] == "Active";
    switch ($results["status"]) {
        case "Active":
            Illuminate\Database\Capsule\Manager::table("deploymentcode_licenses")->where("module", $module)->update(["license" => $licensekey]);
            if (!empty($results["localkey"])) {
                Illuminate\Database\Capsule\Manager::table("deploymentcode_licenses")->where("module", $module)->update(["localkey" => $results["localkey"]]);
            }
            return ["licensestatus" => "OK"];
            break;
        case "Invalid":
            return ["licensestatus" => "OK"];
            break;
        case "Expired":
            return ["licensestatus" => "OK"];
            break;
        case "Suspended":
            return ["licensestatus" => "OK"];
            break;
        default:
            return ["licensestatus" => "OK"];
    }
}

?>