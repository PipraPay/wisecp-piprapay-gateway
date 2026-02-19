<?php
    class piprapay extends PaymentGatewayModule
    {
        function __construct()
        {
            $this->name             = __CLASS__;

            parent::__construct();
        }

        public function config_fields()
        {
            return [
                'api_key'              => [
                    'name'              => "Api Key",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["api_key"] ?? '',
                ],
                'base_url'        => [
                    'name'              => "Base url",
                    'type'              => "text",
                    'value'             => $this->config["settings"]["base_url"] ?? '',
                ],
                'piprapay_version'          => [
                    'name'              => "PipraPay V3+",
                    'description'       => "",
                    'type'              => "approval",
                    'value'             => 1,
                    'checked'           => (int) ($this->config["settings"]["piprapay_version"] ?? 0),
                ],
            ];
        }

        public function area($params=[])
        {

            $gatewayapi_key        = trim($this->config['settings']['api_key']);
            $gatewaybase_url  = trim($this->config['settings']['base_url']);
            $gatewaybutton_text     = $this->lang["pay-button"];
            
            $invoiceid              = $this->checkout_id;
            $description            = 'Invoice Payment';
            $amount                 = $params['amount']; # Format: ##.##

            $firstname              = $this->clientInfo->name;
            $lastname               = $this->clientInfo->surname;
            $email                  = $this->clientInfo->email;
            $phone                  = $this->clientInfo->phone;

            $systemurl              = APP_URI;
            
            if($this->config['settings']['piprapay_version'] == 0){
                $success_url            = $this->links["successful"];
                $cancel_url             = $this->links["failed"];
                $ipn_url                = $this->links["callback"];
                
                
                $url = $gatewaybase_url . '/api/create-charge';
                
                $data = [
                    'full_name' => $firstname . ' ' . $lastname,
                    'email_mobile' => $email,
                    'amount' => $amount,
                    'metadata' => [
                        'invoiceid' => $invoiceid
                    ],
                    'redirect_url' => $success_url,
                    'return_type' => 'GET',
                    'cancel_url' => $cancel_url,
                    'webhook_url' => $ipn_url,
                    'currency' => $params['currency']
                ];
                
                
                $headers = [
                    'accept: application/json',
                    'content-type: application/json',
                    'mh-piprapay-api-key: '.$gatewayapi_key
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                
                // Force TLS 1.2
                curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
                
                // Optional (debug)
                curl_setopt($ch, CURLOPT_VERBOSE, true);
                
                $response = curl_exec($ch);
    
                curl_close($ch);
                
                $urlData = json_decode($response, true);
                
                if (isset($urlData['status']) && $urlData['status'] === true && isset($urlData['pp_url'])) {
                    echo '<script>location.href="'.$urlData['pp_url'].'";</script>';
                } else {
                    echo "Initialization Error: " . $response;
                }
            }else{
                $success_url            = preg_replace('/(https?:\/\/)www\./i', '$1', str_replace('?status=success', '', $this->links["successful"]));
                $ipn_url                = preg_replace('/(https?:\/\/)www\./i', '$1', $this->links["callback"]);
                
                $url = $gatewaybase_url . '/checkout/redirect';
                
                $data = [
                    'full_name' => $firstname . ' ' . $lastname,
                    'email_address' => $email,
                    'mobile_number' => $phone,
                    'amount' => $amount,
                    'metadata' => [
                        'invoiceid' => $invoiceid
                    ],
                    'return_url' => $success_url,
                    'webhook_url' => $ipn_url,
                    'currency' => $params['currency']
                ];
                
                
                $headers = [
                    'accept: application/json',
                    'content-type: application/json',
                    'MHS-PIPRAPAY-API-KEY: '.$gatewayapi_key
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                
                // Force TLS 1.2
                curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
                
                // Optional (debug)
                curl_setopt($ch, CURLOPT_VERBOSE, true);
                
                $response = curl_exec($ch);
    
                curl_close($ch);
                
                $urlData = json_decode($response, true);
                
                if (isset($urlData['pp_id']) && isset($urlData['pp_url'])) {
                    echo '<script>location.href="'.$urlData['pp_url'].'";</script>';
                } else {
                    echo "Initialization Error: " . $response;
                } 
            }
        }

        public function callback()
        {
            $api_key           = $this->config["settings"]["api_key"];
            $base_url       = $this->config["settings"]["base_url"];
            
            $rawData = file_get_contents("php://input");
            
            if($this->config['settings']['piprapay_version'] == 0){
    
                $headers = getallheaders();
        
                $received_api_key = '';
                
                if (isset($headers['mh-piprapay-api-key'])) {
                    $received_api_key = $headers['mh-piprapay-api-key'];
                } elseif (isset($headers['Mh-Piprapay-Api-Key'])) {
                    $received_api_key = $headers['Mh-Piprapay-Api-Key'];
                } elseif (isset($_SERVER['HTTP_MH_PIPRAPAY_API_KEY'])) {
                    $received_api_key = $_SERVER['HTTP_MH_PIPRAPAY_API_KEY']; // fallback if needed
                }
                
                if ($received_api_key !== $api_key) {
                    $this->error = "Payment status failed";
                    return false;
                }
        
                $data = json_decode($rawData, true);
        
                // Step 3: Check pp_id exists
                if (!isset($data['pp_id'])) {
                    $this->error = "Payment status failed";
                    return false;
                }
                
                $pp_id = $data['pp_id'];
                
                // Step 4: Call PipraPay Verify API
                $verify_url = $base_url . '/api/verify-payments';
        
                $payload = json_encode(["pp_id" => $pp_id]);
                
                $ch = curl_init($verify_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'accept: application/json',
                    'content-type: application/json',
                    'mh-piprapay-api-key: ' . $api_key
                ]);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                
                $verifyData = json_decode($response, true);
                
                // Optional: log $verifyData for debugging
                $invoiceId = $data['metadata']['invoiceid'] ?? null;
                
                $price = $verifyData['amount'] . " " . $verifyData['currency'];
            
                $checkout       = $this->get_checkout($invoiceId);
                
                $this->set_checkout($checkout);
            
                if ($verifyData['status'] == 'completed') {
                    return [
                        'status'            => 'successful',
                    ];
                }else{
                    $this->error = "Payment status failed";
                    return false;
                }
                
            }else{
                $data = json_decode($rawData, true);
                
                // Extract required fields
                $invoiceId = $data['metadata']['invoiceid'] ?? null;
                $ppId      = $data['pp_id'] ?? null;
                $status    = $data['status'] ?? null;
                
                if (!$invoiceId || !$ppId || !$status) {
                    $this->error = 'Invalid Data';
                    return false;
                }else{
                
                    $transactionId  = $ppId;
                    $checkout       = $this->get_checkout($invoiceId);
                    
                    if($checkout){
        
                        $this->set_checkout($checkout);
                        
                        // Step 1: Verify the payment with PipraPay
                        $verifyPayload = json_encode(['pp_id' => $ppId]);
                        
                        $ch = curl_init($base_url . '/verify-payment');
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Content-Type: application/json',
                            'Accept: application/json',
                            'MHS-PIPRAPAY-API-KEY: ' . $api_key,
                        ]);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $verifyPayload);
                        
                        $verifyResponse = curl_exec($ch);
                        $verifyResult   = json_decode($verifyResponse, true);
                        
                        // Step 2: Confirm status from verification
                        if (isset($verifyResult['status']) && strtolower($verifyResult['status']) === 'completed') {
                            return [
                                'status'            => 'successful',
                            ];
                        } else {
                            $this->error = "Payment status failed";
                            return false;
                        }
                        
                    }else{
                        $this->error = 'Checkout ID unknown';
                        return false;
                    }
                }
            }
        }
    }