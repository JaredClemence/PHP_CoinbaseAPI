<?php

/**
 * Description of Request
 * @version 1 Based on API as published on 28 Nov 2015
 * @ref https://docs.exchange.coinbase.com/
 *
 * @author jrc
 */
class CoinbaseExchangeAPI {

    public $CB_ACCESS_KEY;         //CB_ACCESS_KEY: The api key as a string.
    public $CB_ACCESS_SIGN;        //CB-ACCESS-SIGN: The base64-encoded signature (see Signing a Message).
    public $CB_ACCESS_TIMESTAMP;   //CB-ACCESS-TIMESTAMP: A timestamp for your request.
    public $CB_ACCESS_PASSPHRASE;  //CB-ACCESS-PASSPHRASE: The passphrase you specified when creating the API key
    public $uri;
    public $secret;
    public $agentId;

    public function __construct() {
        $ini = parse_ini_file("settings.ini", true);
        $genSettings = 'general';
        $mode = $ini[$genSettings]['mode'];
        $this->CB_ACCESS_KEY = $ini[$mode]['api_key'];
        $this->CB_ACCESS_PASSPHRASE = $ini[$mode]['passphrase'];
        $this->CB_ACCESS_SIGN = null;
        $this->CB_ACCESS_TIMESTAMP = time();
        $this->secret = $ini[$mode]['api_secret'];
        $this->uri = $ini[$mode]['endpoint'];
        $this->agentId = $ini[$genSettings]['agent_identification'];
    }

    public function signature($request_path = '', $body = '', $timestamp = false, $method = 'GET') {
        $body = is_array($body) ? json_encode($body) : $body;
        $timestamp = $timestamp ? $timestamp : time();
        
        $what = $timestamp . $method . $request_path . $body;
        $secret = $this->secret;
        
        return base64_encode(hash_hmac("sha256", $what, base64_decode($secret), true));
    }

    public function sendRequest($relUrl, $method, $data) {
        $cleanedData = $this->cleanData($data);
        if(strtoupper($method)==="GET"){
            return $this->sendGetRequest( $relUrl, $cleanedData );
        }else{
            return $this->sendPostRequest( $relUrl, $method, $cleanedData );
        }
    }
    
    private function sendGetRequest( $relUrl, $data ){
        
        $request_path = $relUrl;
        if( count( $data )==0 ){
            $request_path_adder = "";
        }else{
            $request_path .= '?' . http_build_query($data);
        }
        $timestamp = time();
        $sign = $this->signature($request_path, $output="", $timestamp, $capMethod="GET");
        
        $header = [
            'CB-ACCESS-KEY: ' . $this->CB_ACCESS_KEY,
            'CB-ACCESS-SIGN: ' . $sign,
            'CB-ACCESS-TIMESTAMP: ' . $timestamp,
            'CB-ACCESS-PASSPHRASE: ' . $this->CB_ACCESS_PASSPHRASE,
            'Content-Type: application/json',
            'User-Agent: ' . $this->agentId
        ];
        
        $url = $this->uri . $request_path;
        
        $crl = curl_init($url);
        curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($crl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($crl, CURLOPT_HEADER, false);
        curl_setopt($crl, CURLOPT_HTTPHEADER, $header );
        return curl_exec($crl);
    }
    
    private function sendPostRequest( $relUrl, $method, $data ){
        $request_path = $relUrl;
        $output = null;
        if( is_array( $data ) && count( $data )> 0 ){
            $output = json_encode($data);
        }
        $timestamp = time();
        
        $capMethod = strtoupper($method);
        $sign = $this->signature($request_path, $output, $timestamp, $capMethod);
        
        $header = [
            'CB-ACCESS-KEY: ' . $this->CB_ACCESS_KEY,
            'CB-ACCESS-SIGN: ' . $sign,
            'CB-ACCESS-TIMESTAMP: ' . $timestamp,
            'CB-ACCESS-PASSPHRASE: ' . $this->CB_ACCESS_PASSPHRASE,
            'Content-Type: application/json',
            'User-Agent: ' . $this->agentId
        ];
        
        $targetURL = $this->uri . $request_path;
        
        $crl = curl_init($targetURL);
        curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($crl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($crl, CURLOPT_CUSTOMREQUEST, $capMethod );
        curl_setopt($crl, CURLOPT_POSTFIELDS, $output );
        curl_setopt($crl, CURLOPT_HTTPHEADER, $header );
        $result = curl_exec($crl);
        return $result;
    }

    public function cleanData($dataArray) {
        $temp = [];
        foreach ($dataArray as $key => $value) {
            if ($value !== null ) {
                $temp[$key]= $value;
            }
        }
        return $temp;
    }

    public function getHistory($accountId) {
        //GET
        $relURL = "/accounts/$accountId/ledger";
        $method = "GET";
        $data = [];
        return $this->sendRequest($relURL, $method, $data);
    }

    public function getHolds($accountId) {
        //GET
        $relURL = "/accounts/$accountId/holds";
        $method = "GET";
        $data = [];
        return $this->sendRequest($relURL, $method, $data);
    }

    /**
     * @param type $side
     * @param type $product_id
     * @param type $client_oid
     * @param type $type
     * @param type $stp ["dc"=>"[default] Decrease and cancel", "co"=>"cancel oldest", "cn"=>"Cancel newest", "cb"=>"Cancel both"]
     * @param type $funds
     * @param type $size
     * @param type $time_in_force  ['gtc' =>"[default] Good Till Cancelled", 'ioc' => "Immediate Or Cancel", 'fok' => "Fill Or Kill"]
     * @param type $post_only
     */
    private function newOrder($side, $product_id, $client_oid, $type, $stp, $funds, $size, $time_in_force, $post_only, $price) {
        //POST
        /**
          client_oid	[optional] Order ID selected by you to identify your order
          type	[optional] limit or market (default is limit)
          side	buy or sell
          product_id	A valid product id
          stp	[optional] Self-trade prevention flag

         */
        $data = [
            "side" => $side,
            "product_id" => $product_id,
            "client_oid" => $client_oid,
            "type" => $type,
            "stp" => $stp,
            "funds" => $funds,
            "size" => $size,
            "time_in_force" => $time_in_force,
            "post_only" => $post_only,
            "price"=>$price
        ];
        $method = "POST";
        $relURL = "/orders";
        return $this->sendRequest($relURL, $method, $data);
    }

    public function newMarketOrder($size, $funds, $client_oid, $side, $product_id, $stp) {
        $type = "market";
        $time_in_force = null;
        $post_only = null;
        $price = null;
        /*
          size	[optional]* Desired amount in BTC
          funds	[optional]* Desired amount of fiat funds to use
         * 
         */
        return $this->newOrder($side, $product_id, $client_oid, $type, $stp, $funds, $size, $time_in_force, $post_only, $price);
    }

    public function newLimitOrder($size, $price, $side, $time_in_force, $post_only, $client_oid, $product_id, $stp) {
        $type = "limit";
        $funds = null;
        /**
          price	Price per bitcoin
          size	Amount of BTC to buy or sell
          time_in_force	[optional] GTC, IOC, or FOK (default is GTC)
          post_only	[optional]* Post only flag
         */
        $data = [];
        return $this->newOrder($side, $product_id, $client_oid, $type, $stp, $funds, $size, $time_in_force, $post_only, $price);
    }

    public function cancelOrder($orderId) {
        //DELETE
        $relUrl = "/orders/$orderId";
        $method = "DELETE";
        $data = [];
        return $this->sendRequest($relUrl, $method, $data);
    }

    public function cancelAllOrders() {
        //DELETE
        $relUrl = "/orders";
        $method = "DELETE";
        $data = [];
        return $this->sendRequest( $relUrl, $method, $data );
    }

    /**
     * The API allows for multiple selections to be made simultaniously,
     * to simplify the interface, we are changing this to a single list at 
     * a time.
     * @param array $listOnly ['open','pending','all', 'settled', 'done']
     */
    public function getOrdersByStatus($selection) {
        $relUrl = "/orders";
        $method = "GET";
        $data = ["status"=>$selection];
        return $this->sendRequest($relUrl, $method, $data);
    }

    public function getOrderStatus($orderId) {
        //GET
        $relURL = "/orders/$orderId";
        $method = "GET";
        $data = [];
        return $this->sendRequest($relURL, $method, $data);
    }

    /**
     * 
     * @param string $order_id    [default] all
     * @param string $product_id  [default] all
     */
    public function getFills($order_id, $product_id) {
        //GET
        $relURL = "/fills";
        $method = "GET";
        $data = [ "order_id"=>$order_id, "product_id"=>$product_id ];
        return $this->sendRequest( $relURL, $method, $data );
    }

    public function getAccountsList() {
        //GET
        $method = "GET";
        $data = [];
        $relURL = "/accounts";
        return $this->sendRequest($relURL, $method, $data);
    }

    public function getAccountDetails($accountId) {
        $method = "GET";
        $data = [];
        $relURL = "/accounts/$accountId";
        return $this->sendRequest($relURL, $method, $data);
    }
    
    public function getProductIdentifiers(){
        $method = "GET";
        $relURL = "/products";
        $data = [];
        return $this->sendRequest($relURL, $method, $data);
    }
    
    /**
     * 
     * @param type $productId
     * @param int $level
     *      1 => Only the best bid and ask
     *      2 => Top 50 bids and asks (aggregated)
     *      3 => Full order book (non-aggregated)
     * @return stdClass
     * 
     * OrderBook
     *   -> sequence : string
     *   -> bids : array
     *      -> each : array[ 0=>price:string, 1=>qt:string, 2=>boolean:int ]
     *   -> asks : array
     *      -> each : array[ 0=>price:string, 1=>qt:string, 2=>boolean:int ]
     */
    public function getOrderBook( $productId, $level ){
        $method = "GET";
        $relURL = "/products/$productId/book";
        $data = [
            "level"=>$level
        ];
        return $this->sendRequest($relURL, $method, $data);
    }
    
    /**
     * 
     * @param type $productId
     * @return stdClass
     * 
     * Ticker
     *    -> trade_id : string
     *    -> price : string
     *    -> size : string
     *    -> time : string
     */
    public function getProductTicker( $productId ){
        $method = "GET";
        $relURL = "/products/$productId/ticker";
        $data = [];
        return $this->sendRequest($relURL, $method, $data);
    }
    
    /**
     * Returns 100 of the most recent trades split between buys and sells.
     * 
     * @param string $productId
     * @return array
     * 
     * TradeHistory : array[100]
     *    -> each : stdClass
     *          -> time : string
     *          -> trade_id : string
     *          -> price : string
     *          -> size : string
     *          -> side : string = ["buy", "sell"]
     */
    public function getLatestTrades( $productId ){
        $relURL = "/products/$productId/trades";
        $method = "GET";
        $data = [];
        return $this->sendRequest($relURL, $method, $data);
    }
    
    /**
     * @param string $productId
     * @param string $start       Start time in ISO 8601
     * @param string $end         End time in ISO 8601
     * @param int $granularity    Size of slice in seconds
     * @returns array
     * 
     * ProductHistory : array
     *   -> candleStick : array
     *          0 => time : int
     *          1 => low  : float
     *          2 => hi   : float
     *          3 => open : float
     *          4 => close: float
     *          5 => volume : double
     */
    public function getProductHistory( $productId, $start, $end, $granularity ){
        $relURL = "/products/$productId/candles";
        $method = "GET";
        $data = [
            "start"=>$start,
            "end"  =>$end,
            "granularity" => $granularity
        ];
        return $this->sendRequest($relURL, $method, $data);
    }

}
