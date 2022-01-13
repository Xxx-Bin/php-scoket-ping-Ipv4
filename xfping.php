<?php
//demo
// $back = xping::init()->setDebug(true)->setRet($ret)->setError($error)->doit('1.0.0.7');
// var_dump(__FILE__.' line:'.__LINE__,$back,$ret,$error);exit;
class xfping {
    private $datasize;
    private $timeoutms;
    private $debug = false;
    private $sleep_ms;
    private $ping_ret = [];
    private $callback;
    private $callback_dns_error;
    private $callback_single;
    private $callback_time_out;
    private $callback_one_ip;
    private $ip_list = [];
    private $ip_cnt = [];
    private $total_time = 0;
    /**
     * @var false|resource|Socket
     */
    private $sock;
    private $ping_time_start = [];

    public static function init()
    {
        return new self();
    }

    public function doit($host,$i =4,$timeoutms=250,$sleep_ms = 10,$datasize=32){
        $this->total_time = microtime(1);
        $this->timeoutms = $timeoutms;
        $this->datasize = $datasize;
        $this->sleep_ms = $sleep_ms;
        $host = strtr($host,["\n"=>';',","=>';']);
        $host_arr = explode(';',$host);
        foreach ($host_arr as $host){
            if(preg_match('/(((\d{1,2})|(1\d{2})|(2[0-4]\d)|(25[0-5]))\.){3}((\d{1,2})|(1\d{2})|(2[0-4]\d)|(25[0-5]))/',$host,$ret)){
                $ip = $ret[0];
            }else{
                (($ip = gethostbyname($host) )== $host) && $ip='';

            }
            if(empty($ip)){
                is_callable($this->callback_dns_error) && call_user_func_array($this->callback_dns_error,[$host]);
                continue;
            }
            $this->ip_cnt[$ip] = $i;
            $this->ip_list = array_merge($this->ip_list,array_fill(0,$i,$ip));
        }
        shuffle($this->ip_list);
        $this->_ping_loop();

    }

    public function __destruct()
    {
        empty($this->sock) || socket_close($this->sock);
    }

    /**
     * @param mixed $callback
     * @return $this
     */
    public function setCallback($callback)
    {
        $this->callback = $callback;

        return $this;
    }

    /**
     * @param $callback_single
     * @return $this
     */
    public function setCallbackSingle($callback_single)
    {
        $this->callback_single = $callback_single;

        return $this;
    }

    /**
     * @param mixed $callback_one_ip
     * @return $this
     */
    public function setCallbackOneIp($callback_one_ip)
    {
        $this->callback_one_ip = $callback_one_ip;

        return $this;
    }

    /**
     * @param mixed $callback_time_out
     * @return $this
     */
    public function setCallbackTimeOut($callback_time_out)
    {
        $this->callback_time_out = $callback_time_out;

        return $this;
    }

    /**
     * @param mixed $callback_dns_error
     * @return $this
     */
    public function setCallbackDnsError($callback_dns_error)
    {
        $this->callback_dns_error = $callback_dns_error;

        return $this;
    }


    private function _ping_send($ip){
        $port = 0;
        $ident = array(rand(0, 255), rand(0, 255));
        $this->ping_ret[$ip][] = -1;
        $c = count($this->ping_ret[$ip])-1;
        $seq   = array((int)floor($c/256),$c%256);
        $packet = '';
        $packet .= chr(8); // type = 8 : request
        $packet .= chr(0); // code = 0

        $packet .= chr(0); // checksum init
        $packet .= chr(0); // checksum init

        $packet .= chr($ident[0]); // identifier
        $packet .= chr($ident[1]); // identifier

        $packet .= chr($seq[0]); // seq
        $packet .= chr($seq[1]); // seq

        for ($i = 0; $i < $this->datasize; $i++)
            $packet .= chr(0);

        $chk = $this->icmpChecksum($packet);

        $packet[2] = $chk[0]; // checksum init
        $packet[3] = $chk[1]; // checksum init
        $key  = $ip.'_'.implode('',$ident).'_'.implode('',$seq);
        if(empty($this->sock)){
            $this->sock = socket_create(AF_INET, SOCK_RAW,  getprotobyname('icmp'));
            socket_set_nonblock($this->sock);
        }
        $this->ping_time_start[$key] = ['t'=>microtime(1),'k'=>$key,'ip'=>$ip];
        socket_sendto($this->sock, $packet, strlen($packet), 0, $ip, $port);
    }




    private function _ping_loop()
    {
        $write = null;
        $except = null;
        $t = microtime(1);
        $last = 0;
        do {
            if(microtime(1)-$last>(($this->sleep_ms)/1000)){
                $ip = array_shift($this->ip_list);
                empty($ip) || $this->_ping_send($ip);
                $last = microtime(1);
            }
            if(!empty($this->sock)){
                $read = [$this->sock];
                $select = socket_select($read, $write, $except, 0, 100);
                if ($select!==false && $select>0){
                    $this->_ping_rec($this->sock);
                }
            }

            if(microtime(1)-$t>0.5){
                $this->_ping_timeout_clear();
                $t = microtime(1);
            }
        } while (!empty($this->ip_list) || !empty($this->ping_time_start));
        is_callable($this->callback) && call_user_func_array($this->callback,[$this->ping_ret,(microtime(1)-$this->total_time)*1000]);
    }

    private function _ping_rec($sock){
        $rt = microtime(1);
        while($r = socket_recvfrom($sock, $recvc, 65535, 0, $r_ip, $r_port)){
            $recv = unpack('C*', $recvc);
            empty($this->debug) || var_dump(__FILE__.' line:'.__LINE__,implode(' ',$recv));
            if ($recv[10] === 1 && $recv[21] === 0) // ICMP proto = 1
            {
                $key  = $r_ip.'_'.$recv[25].$recv[26].'_'.$recv[27].$recv[28];
                if(isset($this->ping_time_start[$key])){
                    $seq = $recv[27]*256+$recv[28];
                    $start_time = $this->ping_time_start[$key]['t'];
                    unset($this->ping_time_start[$key]);
                    $t = $rt - $start_time;
                    $this->ping_ret[$r_ip][$seq] = $t;
                    is_callable($this->callback_single) && call_user_func_array($this->callback_single,[$r_ip,$seq,$t]);
                    $this->ip_cnt[$r_ip]--;
                    if($this->ip_cnt[$r_ip]<=0){
                        is_callable($this->callback_one_ip) && call_user_func_array($this->callback_one_ip,[$r_ip,$this->ping_ret[$r_ip]]);
                    }
                }
            }
        }

    }

    private function _ping_timeout_clear(){
        if(!empty($this->ping_time_start)){
            while(!empty($this->ping_time_start) && (current($this->ping_time_start)['t']+($this->timeoutms/1000))<microtime(1)){
                $arr = array_shift($this->ping_time_start);
                is_callable($this->callback_time_out) && call_user_func_array($this->callback_time_out,[$arr['ip']]);
                $this->ip_cnt[$arr['ip']]--;
            }
        }

    }
  


    private function icmpChecksum($data)
    {
        $bit = unpack('n*', $data);
        $sum = array_sum($bit);

        if (strlen($data) % 2) {
            $temp = unpack('C*', $data[strlen($data) - 1]);
            $sum += $temp[1];
        }

        $sum = ($sum >> 16) + ($sum & 0xffff);
        $sum += ($sum >> 16);

        return pack('n*', ~$sum);
    }

    /**
     * @param bool $debug
     * @return $this
     */
    public function setDebug(bool $debug): xfping
    {
        $this->debug = $debug;

        return $this;
    }


}
function formet($ip,$ret){
    $sc = $rc = $lc = $min = $max = $avg = 0;
    foreach ($ret as $r){
        $sc++;
        if($r>0){
            $r = $r*1000;
            $rc++;
            $min = empty($min)?$r:min($r,$min);
            $max = empty($max)?$r:max($r,$max);
            $avg = empty($avg)?$r:(($r+$avg)/2);
        }else{
            $lc++;
        }
    }
    $loss = $lc*100/$sc;
    return sprintf('ip=%s send=%d recive=%d loss=%.2f%%  min=%.2fms max=%.2fms avg=%.2fms ',$ip,$sc,$rc,$loss,$min,$max,$avg);
}
//demo
/*
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
*/
