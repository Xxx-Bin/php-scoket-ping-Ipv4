<?php
//demo
// $back = xping::init()->setDebug(true)->setRet($ret)->setError($error)->doit('baidu.com',5,2000,1000,32);
// var_dump(__FILE__.' line:'.__LINE__,$back,$ret,$error);exit;
class xping {
    private $icmp_error = "No Error";
    private $datasize;
    private $ret;
    private $timeoutms;
    private $ident_arr = [];
    private $debug = false;
    private $sleep_ms;

    public static function init()
    {
        return new self();
    }

    public function doit($host,$i =4,$timeoutms=250,$sleep_ms = 10,$datasize=32){
        $this->timeoutms = $timeoutms;
        $this->datasize = $datasize;
        $this->sleep_ms = $sleep_ms;
        $this->ret['host'] = $host;
        if(preg_match('/(((\d{1,2})|(1\d{2})|(2[0-4]\d)|(25[0-5]))\.){3}((\d{1,2})|(1\d{2})|(2[0-4]\d)|(25[0-5]))/',$host,$ret)){
            $ip = $ret[0];
        }else{
            $avg = microtime(1);
            $ip = gethostbyname($host);
            $this->ret['dns_ms'] = microtime(1) - $avg;
        }
        if(empty($ip)){
            $this->icmp_error = 'ip empty';
            return false;
        }
        $this->ret['ip'] = $ip;

        $t_arr  = [];
        $sc = $i;
        do{
            if(($ms = $this->_ping($ip))>0){
                $this->debug && print($ms.PHP_EOL);
                $t_arr[] = $ms;
            }else{
                $this->debug && print($ip.' '.$this->icmp_error.PHP_EOL);
            }
            usleep($this->sleep_ms);
            $i--;
        }while($i>0);
        $rc = count($t_arr);

        if(empty($rc)){
            $this->icmp_error = $ip.' no recive';
            return false;
        }else{
            $avg = round(array_sum($t_arr)/$rc,2);
            $min = call_user_func_array('min',$t_arr);
            $max = call_user_func_array('max',$t_arr);
            $loss = round(100-($rc*100/$sc));
        }

        $this->ret['ping_ret']= [
            'avg'=>$avg,
            'min'=>$min,
            'max'=>$max,
            'loss'=>$loss,
            'sc'=>$sc,
            'rc'=>$rc,
            'str'=>sprintf('send=%d recive=%d loss=%.2f %%  min=%.2fms max=%.2fms avg=%.2fms ',$sc,$rc,$loss,$min,$max,$avg)
        ];
        return true;
    }


    private function _ping($host)
    {
        $port = 0;

        $this->setIcmpError("No Error ");
        $ident = array(rand(0, 255), rand(0, 255));
        $seq   = array(rand(0, 255), rand(0, 255));
        $this->ident_arr[implode('',$ident)] = $seq;
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
        $sock = socket_create(AF_INET, SOCK_RAW,  getprotobyname('icmp'));
        $time_start = microtime(1);
        socket_sendto($sock, $packet, strlen($packet), 0, $host, $port);

        $read = [$sock];
        $write = null;
        $except = null;
        $success = 0;
        $time_stop = $time_start;
        do {
            //
            $t = max($this->timeoutms - round(microtime(1) - $time_start,0),1);
            $select = socket_select($read, $write, $except, 0, $t * 1000);
            if ($select === null) {
                $this->setIcmpError("Select Error ");
                break;

            } elseif ($select === 0) {
                $this->setIcmpError("select Timeout ");
                break;
            }
            $recv = '';
            $time_stop = microtime(1);
            socket_recvfrom($sock, $recv, 64 + $this->datasize, 0, $host, $port);
            $recv = unpack('C*', $recv);
            if ($recv[10] !== 1) // ICMP proto = 1
            {
                $this->setIcmpError("Not ICMP packet ".implode(' ',$recv));
                continue;
            }

            if ($recv[21] !== 0) // ICMP response = 0
            {
                $this->setIcmpError("Not ICMP response ".implode(' ',$recv));
                continue;
            }

            if ($ident[0] !== $recv[25] || $ident[1] !== $recv[26]) {
                $this->setIcmpError("Bad identification number ".implode(' ',$recv));
                continue;
            }

            if ($seq[0] !== $recv[27] || $seq[1] !== $recv[28]) {
                $this->setIcmpError("Bad sequence number ".implode(' ',$recv));
                continue;
            }
            $success = 1;
        } while (microtime(1) - $time_start < $this->timeoutms && $success == 0);
        socket_close($sock);
        $ms = ($time_stop - $time_start) * 1000;

        if ($ms <= 0) {
            $ms = -1;
        }

        return $ms;
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
     * @param string $icmp_error
     */
    private function setIcmpError(string $icmp_error): void
    {
        $this->icmp_error = $icmp_error;
    }

    /**
     * @param mixed $debug
     * @return xping
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;

        return $this;
    }


    public function setRet(&$ret)
    {
        $this->ret = &$ret ;
        return $this;
    }

    public function setError(&$error)
    {
        $error = $this->icmp_error;
        return $this;
    }
}
