<?php
class xftraceroute {
    private $datasize;
    private $timeoutms;
    private $debug = false;
    private $sleep_ms;
    private $ping_ret = [];
    private $callback;
    private $callback_dns_error;
    private $callback_single;
    private $callback_time_out;
    private $host_list = [];
    private $ip_list = [];
    private $ip_done_list = [];
    private $ip_cnt = [];
    private $total_time = 0;
    private $shuffle_ip_list = false;
    /**
     * @var false|resource|Socket
     */
    private $sock;
    private $ping_time_start = [];

    public static function init()
    {
        return new self();
    }

    public function doit($host,$ttl =32,$timeoutms=250,$sleep_ms = 10,$datasize=32){
        $this->total_time = microtime(1);
        $this->timeoutms = $timeoutms;
        $this->datasize = $datasize;
        $this->sleep_ms = $sleep_ms;
        $host = strtr($host,["\n"=>';',","=>';']);
        $host_arr = explode(';',$host);
        foreach ($host_arr as $host){
            $host = trim($host);
            if(preg_match('/(((25[0-5])|(\d{1,2})|(1\d{2})|(2[0-4]\d))\.){3}((25[0-5])|(\d{1,2})|(1\d{2})|(2[0-4]\d))/',$host,$ret)){
                $ip = $host;
            }else{
                (($ip = gethostbyname($host) )== $host) && $ip='';

            }
            if(empty($ip)){
                is_callable($this->callback_dns_error) && call_user_func_array($this->callback_dns_error,[$host]);
                continue;
            }
            $this->ip_cnt[$ip] = $ttl;
            $this->host_list[$ip] = $host;
            $this->ip_list = array_merge($this->ip_list,array_fill(0,$ttl,$ip));
        }

        $this->shuffle_ip_list && shuffle($this->ip_list);
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
        socket_set_option($this->sock, getprotobyname('ip'), 4,$c);
        $this->ping_time_start[$key] = ['t'=>microtime(1),'k'=>$key,'ip'=>$ip,'ttl'=>$c];
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
                if(!in_array($ip,$this->ip_done_list)){
                    empty($ip) || $this->_ping_send($ip);
                    $last = microtime(1);
                }
            }
            if(!empty($this->sock)){
                $read = [$this->sock];
                $select = socket_select($read, $write, $except, 0, 100);
                if ($select!==false && $select>0){
                    $this->_ping_rec($this->sock);
                }
            }

            if(microtime(1)-$t>$this->timeoutms/1000){
                $this->_ping_timeout_clear();
                $t = microtime(1);
            }
        } while (!empty($this->ip_list) || !empty($this->ping_time_start));
        is_callable($this->callback) && call_user_func_array($this->callback,[$this->ping_ret,(microtime(1)-$this->total_time)*1000,$this->host_list]);
    }

    private function _ping_rec($sock){
        $rt = microtime(1);
        while($r = socket_recvfrom($sock, $recvc, 65535, 0, $r_ip, $r_port)){
            $recv = unpack('C*', $recvc);
            empty($this->debug) || var_dump(__FILE__.' line:'.__LINE__,implode(' ',$recv));
            if ($recv[10] === 1 && in_array($recv[21],[0,11])) // ICMP proto = 1 , type = 0 recive success ,type = 11 ttl timeout
            {
                $offest = $recv[21]==0?0:28;
                if($recv[21]==0){
                    $this->ip_done_list[] = $r_ip;
                    $origin_ip = $r_ip;
                }else{
                    $origin_ip = implode('.',[$recv[$offest+17],$recv[$offest+18],$recv[$offest+19],$recv[$offest+20]]);
                }
                $key  = $origin_ip.'_'.$recv[$offest+25].$recv[$offest+26].'_'.$recv[$offest+27].$recv[$offest+28];
                if(isset($this->ping_time_start[$key])){
                    $seq = $recv[$offest+27]*256+$recv[$offest+28];
                    $start_time = $this->ping_time_start[$key]['t'];
                    unset($this->ping_time_start[$key]);
                    $t = $rt - $start_time;
                    $this->ping_ret[$origin_ip][$seq] = ['rip'=>$r_ip,'t'=>$t];
                    is_callable($this->callback_single) && call_user_func_array($this->callback_single,[$r_ip,$seq,$t,$this->host_list[$origin_ip]]);
                    $this->ip_cnt[$origin_ip]--;
                }
            }
        }

    }

    private function _ping_timeout_clear(){
        if(!empty($this->ping_time_start)){
            while(!empty($this->ping_time_start) && (current($this->ping_time_start)['t']+($this->timeoutms/1000))<microtime(1)){
                $arr = array_shift($this->ping_time_start);
                is_callable($this->callback_time_out) && call_user_func_array($this->callback_time_out,[$arr['ip'],$arr['ttl'],$this->host_list[$arr['ip']]]);
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
    public function setDebug(bool $debug): xftraceroute
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * @param bool $shuffle_ip_list
     * @return xftraceroute
     */
    public function setShuffleIpList(bool $shuffle_ip_list): xftraceroute
    {
        $this->shuffle_ip_list = $shuffle_ip_list;

        return $this;
    }


}
function xftracerouteformet($ret){
    $r_text = [];
    foreach ($ret as $ttl=>$info){
       
        if(isset($info['t']) && $info['t']>0){
            $recv_addr = $info['rip'];
            $recv_name = '*';
            $roundtrip_time = $info['t']*1000;
            $r_text []=sprintf("%3d   %-15s  %.3f ms  %s", $ttl, $recv_addr,  $roundtrip_time, $recv_name);

        }else{
            $recv_addr = '*';
            $recv_name = '*';
            $roundtrip_time = 'timeout';
            $r_text []=sprintf("%3d   %-15s  %s  %s", $ttl, $recv_addr,  $roundtrip_time, $recv_name);

        }
    }
    return implode(PHP_EOL,$r_text);
}
//demo
/*
(xftraceroute::init())
    ->setDebug(0)
    ->setShuffleIpList(true)
//    ->setCallbackDnsError(function($host){
//        echo 'DnsError :'.$host.PHP_EOL;
//    })
//    ->setCallbackTimeOut(function($ip,$seq,$host){
//       echo $host.' '.xftracerouteformet([$seq=>['t'=>0,'rip'=>$ip]]).PHP_EOL;
//    })
//    ->setCallbackSingle(function($ip,$seq,$t,$host){
//        echo $host.' '.xftracerouteformet([$seq=>['t'=>$t,'rip'=>$ip]]).PHP_EOL;
//    })
    ->setCallback(function ($ret,$t,$host_list){
        echo 'ret '.PHP_EOL;
        foreach ($ret as $ip=>$r){
            echo '===== '.$host_list[$ip] .' tracerout ret ====='.PHP_EOL;
            echo xftracerouteformet($r).PHP_EOL;
        }
        echo 'total time_ms '.$t.PHP_EOL;
        echo 'memory_get_peak_usage：'.memory_get_peak_usage().PHP_EOL;
        echo 'memory_get_usage：'.memory_get_usage();
})->doit('baidu.com',64,200,10);
