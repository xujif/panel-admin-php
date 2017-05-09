#json-rpc base on guzzle
a full json-rpc 2.0 [RFC](http://www.jsonrpc.org/specification) implementation

###Usage
####Server
```php
/*
 * in any http framworks
 */
$apiObject = new Api();
$server = new \Xujif\JsonRpc\Server($apiObject);
// return array
// please return to client with json
$ret = $server->handle();
header('Content-Type:application/json')
echo json_encode($ret);
```

####Client
```php
$client = new \Xujif\JsonRpc\Client('API_SERVER_URL');
$client->call('method',[1,2,3]);
// or
$client->method(1,2,3)
// or notification(no result send async)
$client->notify()->method(1,2,3)
// or batch call
$client->batch()->method(1,2,3)->method2(1,2)->exec();
// or batch notification
$client->batch()->notify()->method(1,2,3)->method2(1,2)->exec();
// or batch mixed notification and call
// if all batch call is notify it will send async
$client->batch()
       ->notify()
       ->method(1,2,3)
       ->notify(false)
       ->method2(1,2)
       ->exec();


```

