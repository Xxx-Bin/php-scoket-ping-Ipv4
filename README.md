# php-scoket-ping-Ipv4
ping Ipv4 by php socket


# dependent 
php-socket

# usage
## demo
```php
require_once 'xping.php';
$back = xping::init()
    ->setDebug(false)
    ->setRet($ret)
    ->setError($error)
    ->doit('baidu.com',5,2000,1000,32);
var_dump(__FILE__.' line:'.__LINE__,$back,$ret,$error);exit;

```

## out
```bash
//debug info
68.974018096924
63.398838043213
62.793016433716
62.896966934204
62.525987625122
string(64) "\xping.php line:*"
// back
bool(true)
// $ret
array(4) {
  ["host"]=>
  string(9) "baidu.com"
  ["dns_ms"]=>
  float(0.0179290771484375)
  ["ip"]=>
  string(14) "220.181.38.251"
  ["ping_ret"]=>
  array(7) {
    ["avg"]=>
    float(64.12)
    ["min"]=>
    float(62.52598762512207)
    ["max"]=>
    float(68.97401809692383)
    ["loss"]=>
    float(0)
    ["sc"]=>
    int(5)
    ["rc"]=>
    int(5)
    ["str"]=>
    string(65) "send=5 recive=5 loss=0.00 %  min=62.53ms max=68.97ms avg=64.12ms "
  }
}
//$error
string(8) "No Error"

```

