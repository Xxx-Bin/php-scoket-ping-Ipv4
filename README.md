# php-scoket-ping-Ipv4
ping Ipv4 by php socket


# dependent 
php-socket

# usage
## xfping
### desc
faster and multiple ip
### demo
```php
require_once 'xfping.php';
(xfping::init())
    ->setDebug(1)
    ->setCallbackDnsError(function($host){
        echo 'DnsError :'.$host.PHP_EOL;
    })
    ->setCallbackTimeOut(function($ip){
        echo 'timeout :'.$ip.PHP_EOL;
    })
    ->setCallbackOneIp(function($ip,$ret){
       echo formet($ip,$ret).PHP_EOL;
    })
    ->setCallbackSingle(function($ip,$seq,$t){
        echo 'from ip='.$ip.' seq='.$seq.' t_ms='.($t*1000).PHP_EOL;
    })
    ->setCallback(function ($ret,$t){
        echo 'ret '.PHP_EOL;
        foreach ($ret as $ip=>$r){
            echo formet($ip,$r).PHP_EOL;
        }
        echo 'total time_ms '.$t;
})->doit('127.0.0.2;baidu.com;127.0.0.1',4,1250,50);
```
### out
```bash
//debug info
string(67) ".\fping.socket.php line:187"
string(148) "69 0 0 60 32 215 0 0 52 1 159 33 220 181 38 148 192 168 2 215 0 0 130 37 125 215 0 3 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0"
// Single ping  Callback
from ip=220.181.38.148 seq=3 t_ms=56.200981140137
// Singe ip ping callback
ip=220.181.38.148 send=4 recive=4 loss=0.00%  min=56.20ms max=57.78ms avg=56.57ms
// all ip ping callback
ret
ip=127.0.0.1 send=4 recive=4 loss=0.00%  min=0.26ms max=0.29ms avg=0.27ms
ip=127.0.0.2 send=4 recive=4 loss=0.00%  min=0.14ms max=0.25ms avg=0.17ms
ip=220.181.38.148 send=4 recive=4 loss=0.00%  min=56.20ms max=57.78ms avg=56.57ms
// all ping spend time
total time_ms 720.81995010376

```
### function
#### setDebug
true or false ,defualt false.
if true ,print ip packet content
#### setCallbackDnsError
##### desc 
like name
##### argv
- host 
ping host

#### setCallbackTimeOut
##### desc
when timeout callback
##### argv
- $ip
timeout ip
#### setCallbackOneIp
##### desc
ip ping job was finished callback
##### argv
- $ip 
ping ip
- $ret 
ping ret
```
 // icmp_seq as key,ttl  as value
array(4) {
  [0]=>
  float(0.0002930164337158203)
  [1]=>
  float(0.0002079010009765625)
  [2]=>
  float(0.0002319812774658203)
  [3]=>
  float(0.0002110004425048828)
}
```
#### setCallbackSingle
##### desc
ip single ping job was finished callback
##### argv
- $ip
ping ip
- $seq
icmp_seq
- $t
ttl

#### setCallback
##### desc
all ip ping job was finished callback
##### argv
- $ret
```
array(1) {
    // ip as key
  ["127.0.0.1"]=>
  array(4) {
  // icmp_seq as key,ttl  as value
    [0]=>
    float(0.00030112266540527344)
    [1]=>
    float(0.00023412704467773438)
    [2]=>
    float(0.00021004676818847656)
    [3]=>
    float(0.00026702880859375)
  }
}

```
- total_time_ms
all ping spedn time , unit is ms

## xping
### demo
```php
require_once 'xping.php';
$back = xping::init()
    ->setDebug(false)
    ->setRet($ret)
    ->setError($error)
    ->doit('baidu.com',5,2000,1000,32);
var_dump(__FILE__.' line:'.__LINE__,$back,$ret,$error);exit;

```

### out
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

### $ret
### host
ping domain 
#### ip
ping domain 's ip
### dns_ms
Domain name resolution time
#### ping_ret
##### rc
recive count
##### sc
send count


## change from 
[php-doc-note](https://www.php.net/manual/zh/function.socket-create.php#80775)

## other
[[update]php ping ipv4 by socket](https://bjun.tech/blog/xphp/120)

