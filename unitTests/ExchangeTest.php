<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
set_include_path(get_include_path() . "..");
require_once 'CoinbaseExchangeAPI.php';

class ExchangeTest extends PHPUnit_Framework_TestCase {

    public function testCommunitySuggestion() {
        $secret = 'JorK3Y1f3Z5HWhMfpYeA4h+KUJNNRdq8EjrqPhyy9HHeBAcS1ifto7XkFovOMO+NHag1sH/C+wTCG3Zhn4yZsA==';
        $time = time();
        $what = $time . "GET/orders?status=all";

        $sign = base64_encode(hash_hmac("sha256", $what, base64_decode($secret), true));

        $header = [
            'CB-ACCESS-KEY: 9f9b01e6dcccfff28bf69bf0f7379924',
            'CB-ACCESS-SIGN: ' . $sign,
            'CB-ACCESS-TIMESTAMP: ' . $time,
            'CB-ACCESS-PASSPHRASE: ax9myyavp25ipb9',
            'Content-Type: application/json',
            'User-Agent: tester'
        ];

        $restURL = "https://api-public.sandbox.exchange.coinbase.com/orders?status=all";
        $ch = curl_init($restURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($ch);
        $data = json_decode($result);
        $this->assertNotNull($data, "The JSON parsed successfully resulting in a non-null object.");
    }

    /**
     * @dataProvider cleanDataProvider
     */
    public function testCleanDataMethod($dataArray, $startSize, $endSize) {
        $api = new CoinbaseExchangeAPI();
        $this->assertTrue(is_array($dataArray), "Initial value is an array.");
        $this->assertTrue($startSize >= 0 && $endSize >= 0, "Size values are set correctly.");
        $this->assertEquals($startSize, count($dataArray), "The data array is starting with a size of $startSize.");
        $newData = $api->cleanData($dataArray);
        $this->assertEquals($endSize, count($newData), "The cleaned array has $endSize values.");
        foreach ($newData as $key => $value) {
            $this->assertNotNull($value, "The array value at $key is not null.");
        }
    }

    public function cleanDataProvider() {
        $data = [
            "Test One: Empty Array" => [[], 0, 0],
            "Test Two: Full Array" => [["one" => 1, "two" => 2], 2, 2],
            "Test Three: Null Only" => [["one" => null], 1, 0],
            "Test Four: Null Plus One" => [["one" => "value", "two" => null], 2, 1]
        ];
        return $data;
    }

    public function testGetAccounts() {
        $api = new CoinbaseExchangeAPI();
        $result = $api->getAccountsList();
        $data = json_decode($result);
        $this->assertTrue(is_array($data), "Account List is an Array.");
        $id = null;
        if (is_array($data)) {
            $index = array_rand($data);
            $id = $data[$index]->id;
            $this->assertTrue(count($data) > 0, "Account List is not empty.");
            foreach ($data as $account) {
                $idLength = strlen($account->id);
                $currencyLength = strlen($account->currency);
                $this->assertEquals(36, $idLength, "ID Length is 36 characters.");
                $this->assertEquals(3, $currencyLength, "Currency Length is 3 characters.");
            }
        } else {
            var_dump($data);
        }
        return $id;
    }

    /**
     * @depends testGetAccounts
     */
    public function testGetAccountData($soughtId) {
        $api = new CoinbaseExchangeAPI();
        $result = $api->getAccountsList();
        $data = json_decode($result);
        $singleAccountIndex = array_rand($data);
        $account = $data[$singleAccountIndex];

        $accountResult = $api->getAccountDetails($soughtId);
        $acct = json_decode($accountResult);
        $id = $acct->id;
        $this->assertEquals(36, strlen($id), "Account id is 36 characters long.");
        $attributeList = ["id", "currency", "balance", "hold", "available", "profile_id"];
        $this->checkObjectAttributes($attributeList, $acct);
    }

    /**
     * 
     * @depends testGetAccounts
     */
    public function testGetAccountHistory($id) {
        $api = new CoinbaseExchangeAPI();
        $result = $api->getHistory($id);
        $data = json_decode($result);
        $hasMessage = isset($data->message);
        $this->assertFalse($hasMessage, "No error occured on retrieving the history.");
        $attributeList = ["id", "created_at", "amount", "balance", "type", "details"];
        if (is_array($data)) {
            foreach ($data as $historyElement) {
                $this->checkObjectAttributes($attributeList, $historyElement);
            }
        }
    }

    /**
     * @depends testGetAccounts
     */
    public function testGetHolds($id) {
        $api = new CoinbaseExchangeAPI();
        $result = $api->getHolds($id);
        $data = json_decode($result);
        $attributeList = [ "id", "account_id", "created_at", "updated_at", "amount", "type", "ref"];
        $this->assertTrue(is_array($data));
        if (is_array($data)) {
            foreach ($data as $hold) {
                $this->checkObjectAttributes($attributeList, $hold);
            }
        }
    }

    public function testNewMarketOrderByBTCSize() {
        $api = new CoinbaseExchangeAPI();
        $stp = null;
        $product_id = "BTC-USD";
        $side = "buy";
        $client_oid = null;
        $funds = null;
        $size = "0.01";
        $result = $api->newMarketOrder($size, $funds, $client_oid, $side, $product_id, $stp);
        $data = json_decode($result);
        $expectedFields = [
            "id",
            "size",
            "product_id",
            "side",
            "stp",
            "funds",
            "type",
            "post_only",
            "created_at",
            "fill_fees",
            "filled_size",
            "status",
            "settled"
        ];
        $this->checkObjectAttributes($expectedFields, $data);
    }

    /**
     * @dataProvider marketOrderDataSets
     */
    public function testNewMarketOrders($size, $funds, $client_oid, $side, $product_id, $stp) {
        $api = new CoinbaseExchangeAPI();
        $result = $api->newMarketOrder($size, $funds, $client_oid, $side, $product_id, $stp);
        $dataObj = json_decode($result);

        $expectedFields = [
            "id",
            "product_id",
            "side",
            "stp",
            "type",
            "post_only",
            "created_at",
            "fill_fees",
            "filled_size",
            "status",
            "settled"
        ];

        $this->checkObjectAttributes($expectedFields, $dataObj);
    }

    public function marketOrderDataSets() {
        return [
            "Fixed Buy BTC Order (Good)" => ["0.01", null, null, "buy", "BTC-USD", null],
            "Fixed Buy USD Order (Good)" => [null, "10", null, "buy", "BTC-USD", null],
            "Fixed Sell BTC Order (Good)" => ["0.01", null, null, "sell", "BTC-USD", null],
            "Fixed Sell USD Order (Good)" => [null, "10", null, "sell", "BTC-USD", null]
        ];
    }

    public function checkObjectAttributes($attributeList, $object) {
        foreach ($attributeList as $attrib) {
            $this->assertObjectHasAttribute($attrib, $object, "$attrib exists in account data.");
        }
    }

    /**
     * 
     * @dataProvider limitOrderDataSets
     */
    public function testNewLimitOrdersWithCancel($size, $price, $side, $time_in_force, $post_only, $client_oid, $product_id, $stp) {
        $api = new CoinbaseExchangeAPI();
        $json = $api->newLimitOrder($size, $price, $side, $time_in_force, $post_only, $client_oid, $product_id, $stp);
        $dataObj = json_decode($json);
        $expectedFields = [
            "id",
            "product_id",
            "side",
            "stp",
            "type",
            "post_only",
            "created_at",
            "fill_fees",
            "filled_size",
            "status",
            "settled"
        ];

        $this->checkObjectAttributes($expectedFields, $dataObj);
        $id = $dataObj->id;
        $jsonCancel = $api->cancelOrder($id);
        $cancelObj = json_decode($jsonCancel);

        if (isset($cancelObj->message)) {
            switch ($dataObj->status) {
                case 'pending':
                    $this->assertEquals("Order already done", $cancelObj->message, "When the order is placed, the id is completed immediately in the test system.");
                    break;
                case 'rejected':
                    $this->assertEquals("order not found", $cancelObj->message, "When the order is rejected, the id is not found to cancel.");
                    break;
                default:
                    echo "Unhandled cancelation message: " . $cancelObj->message . "\n";
                    break;
            }
        }
    }

    public function limitOrderDataSets() {
        //size, price, side, time_in_force, post_only, client_oid, product_id, stp
        return [
            "Size-Order" => ["0.01", "315", "buy", null, null, null, "BTC-USD", null],
            "Fill Or Kill" => ["0.01", "315", "buy", "FOK", null, null, "BTC-USD", null],
            "Immediate Or Cancel" => ["0.01", "315", "buy", "IOC", null, null, "BTC-USD", null],
            "Post Only" => ["0.01", "315", "buy", null, true, null, "BTC-USD", null]
        ];
    }

    public function testOrderLists() {
        $api = new CoinbaseExchangeAPI();
        $type = ["open", "pending", "all", "done", "rejected"];
        foreach ($type as $t) {
            $json = $api->getOrdersByStatus($t);
            $dataObj = json_decode($json);
            if (isset($dataObj->message)) {
                echo PHP_EOL . $dataObj->message;
            }

            $this->assertFalse(isset($dataObj->message), "The order status call for transactions of type $t did not result in an error.");
        }
    }

    public function testGetOrder() {
        $api = new CoinbaseExchangeAPI();
        $result = $api->newMarketOrder("0.01", null, null, "buy", "BTC-USD", null);
        $order = json_decode($result);
        $orderResult = $api->getOrderStatus($order->id);
        $order2 = json_decode($orderResult);
        $this->assertEquals($order->id, $order2->id, "The retreived order id is the same as the expected order id.");
        return $order->id;
    }

    /**
     * @depends testGetOrder
     */
    public function testGetFills($id) {
        $api = new CoinbaseExchangeAPI();
        $result = $api->getFills($id, "BTC-USD");
        $dataObj = json_decode($result);
        $this->assertNotNull($dataObj, "The JSON result decodes correctly.");
        $this->assertFalse(isset($dataObj->message), "There is no error message on the query.");
    }

    public function testCancelAll() {
        $api = new CoinbaseExchangeAPI();
        for ($i = 0; $i < 5; $i++) {
            $size = "0.01";
            $price = "0.01";
            $side = "buy";
            $time_in_force = "GTC";
            $post_only = true;
            $product_id = "BTC-USD";
            $client_oid = null;
            $stp = null;
            $response = $api->newLimitOrder($size, $price, $side, $time_in_force, $post_only, $client_oid, $product_id, $stp);
        }
        $selection = "open";
        $status = json_decode($api->getOrdersByStatus($selection));
        $this->assertGreaterThan(0, count($status), "There are orders to cancel.");
        $cancelResult = $api->cancelAllOrders();
        $cancelledIds = json_decode($cancelResult);
        if (is_array($cancelledIds)) {
            foreach ($cancelledIds as $id) {
                $isString = is_string($id);
                $this->assertTrue($isString, "Each id returned by Cancel all is a string in an array.");
            }
        }
        $status2 = $api->getOrdersByStatus($selection);
        $allOpenOrders = json_decode($status2);
        $openOrderCount = count($allOpenOrders);
        $this->assertEquals(0, $openOrderCount, "After calling cancel all, there are zero open orders.");
    }

    public function testGetProductIdentifiers() {
        $api = new CoinbaseExchangeAPI();
        $prodIdString = $api->getProductIdentifiers();
        $prodIds = json_decode($prodIdString);
        /*
         * Check for the following format:
          class stdClass#76 (7) {
          public $id =>
          string(7) "BTC-USD"
          public $base_currency =>
          string(3) "BTC"
          public $quote_currency =>
          string(3) "USD"
          public $base_min_size =>
          string(4) "0.01"
          public $base_max_size =>
          string(5) "10000"
          public $quote_increment =>
          string(4) "0.01"
          public $display_name =>
          string(7) "BTC/USD"
          }
         */
        $fields = ["id", "base_currency", "quote_currency", "base_min_size", "base_max_size", "quote_increment", "display_name"];
        $expectedCurrencies = ["BTC-USD", "BTC-GBP", "BTC-EUR", "BTC-CAD"];

        foreach ($prodIds as $prod) {
            $prodVars = get_object_vars($prod);
            $product = null;
            foreach ($fields as $field) {
                $this->assertTrue(isset($prodVars[$field]), "The returned object has all the expected fields including: $field.");
                if ($field = "id") {
                    $product = $prod->$field;
                }
            }
            if ($product !== null) {
                $index = array_search($product, $expectedCurrencies);
                if ($index !== false) {
                    unset($expectedCurrencies[$index]);
                }
            }
        }

        $this->assertEquals(0, count($expectedCurrencies), "All product ids that are expected have been identified.");
    }
    
    public function testGetOrderBookLevel1(){
        $api = new CoinbaseExchangeAPI();
        $productid = "BTC-USD";
        $level = 1;
        $jsonRes = $api->getOrderBook($productid, $level);
        $orderBook = json_decode($jsonRes);
        $this->_testOrderBookResults($orderBook);
        $this->assertEquals( 1, count( $orderBook->bids ), "The Level 1 Order Book bids list contains only one order." );
        $this->assertEquals( 1, count( $orderBook->asks ), "The Level 1 Order Book ask list contains only one order." );
    }
    
    public function testGetOrderBookLevel2(){
        $api = new CoinbaseExchangeAPI();
        $productid = "BTC-USD";
        $level = 2;
        $jsonRes = $api->getOrderBook($productid, $level);
        $orderBook = json_decode($jsonRes);
        $this->_testOrderBookResults($orderBook);
        $this->assertEquals( 50, count( $orderBook->bids ), "The Level 2 Order Book bids list contains only 50 orders." );
        $this->assertEquals( 50, count( $orderBook->asks ), "The Level 2 Order Book ask list contains only 50 orders." );
        
    }
    
    private function _testOrderBookResults( $orderBook ){
        $this->assertTrue( property_exists($orderBook, "sequence" ), "The order book has a sequence id." );
        $this->assertTrue( property_exists($orderBook, "bids"), "The order book has a bid listing." );
        $this->assertTRue( property_exists($orderBook, "asks"), "The order book has an asks list." );
        foreach( [$orderBook->bids, $orderBook->asks] as $array ){
        $this->assertTrue( is_array( $array ), "The order book is an array." );
        $expectFields = [0,1,2];
        foreach( $array as $order ){
            $this->assertTrue( is_array( $order ), "Each order is a simple array of data." );
            foreach( $expectFields as $field ){
                $this->assertTrue( isset( $order[ $field ] ), "All orders have the expected field identified as '$field'");
            }
        }
        }
    }
    
    public function testGetTicker(){
        $api = new CoinbaseExchangeAPI();
        $productId = "BTC-USD";
        $jsonResult = $api->getProductTicker($productId);
        $ticker = json_decode($jsonResult);
        echo PHP_EOL;
        $properties = ['trade_id','price','size','time'];
        foreach( $properties as $field ){
            $this->assertTrue( property_exists($ticker, $field), "The ticker has the property $field." );
        }
        
    }
    
    public function testGetLatestTrades(){
        $api = new CoinbaseExchangeAPI();
        $productId = "BTC-USD";
        $jsonData = $api->getLatestTrades($productId);
        $trades = json_decode($jsonData);
        $this->assertTrue( is_array( $trades ), "The Trade History is an array." );
        $this->assertEquals( 100, count( $trades ), "The Trade History has 100 trades. " );
        $fields = ['time','trade_id','price','size','side'];
        foreach( $trades as $trade ){
            $this->assertTrue( is_a( $trade, 'stdClass' ) );
            foreach( $fields as $field ){
                $this->assertTrue( property_exists($trade, $field), "The field '$field' exists in each trade." );
            }
        }
    }
    
    public function testProductHistory(){
        $api = new CoinbaseExchangeAPI();
        $now = new DateTime( "now", new DateTimeZone("America/Los_Angeles" ) );
        $before = clone $now;
        $before->sub( new DateInterval("P6M") );
        
        $productId = "BTC-USD";
        $start = $before->format("c");
        $end = $now->format( "c" );
        $granularity = 24 /*hours*/ * 60 /*min_per_hour*/ * 60 /*seconds per minute */;
        $json = $api->getProductHistory($productId, $start, $end, $granularity);
        $data = json_decode($json);
        echo PHP_EOL;
        var_dump( $data );
        
        $this->assertTrue( is_array( $data ), "The product history is returned as an array of data." );
        
        $newKeys = ["time","low","high","open","close","volume"];
        $lastTime = null;
        $sumDiffs = 0;
        $numDiffs = 0;
        usort($data, function($a, $b){
            $newKeys = ["time","low","high","open","close","volume"];
            $keyedDataA = array_combine($newKeys, $a);
            $keyedDataB = array_combine($newKeys, $b);
            return bccomp( $keyedDataA['time'], $keyedDataB['time'], 0 );
        });
        foreach( $data as $dataPoint ){
            $this->assertEquals( 6, count( $dataPoint ), "Each data point, consists of 6 values." );
            $keyedData = array_combine($newKeys, $dataPoint);
            if( $lastTime !== null ){
                $diff = $keyedData['time'] - $lastTime;
                $sumDiffs += $diff;
                $numDiffs++;
            }
            //$this->assertEquals( $granularity, abs( $diff ), "The time between boxes matches the granularity." );
            $dateTime = new DateTime( "now", new DateTimeZone( "America/Los_Angeles" ) );
            $dateTime->setTimestamp($keyedData['time']);
            $this->assertGreaterThanOrEqual( $before, $dateTime, "The box is after the start time." );
            $this->assertLessThanOrEqual( $now, $dateTime, "The box is before the end time.");
            $lastTime = $keyedData['time'];
        }
        $errorInExpectedTimeDiffs = (( $sumDiffs / $numDiffs ) - $granularity )/ $granularity;
        $this->assertLessThan( 0.10, abs( $errorInExpectedTimeDiffs ), "The average time difference is within 10% of the expected bucket widths.");
            
    }

}
