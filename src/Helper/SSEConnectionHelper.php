<?php
namespace APPNAME\Helper;


/**
 * Class SSEConnectionHelper
 */
class SSEConnectionHelper {

    public function isSSEConnectionRequest($request)
    {
        if (in_array('text/event-stream', $request->getHeader('Accept'))) {
            return true;
        }
        return false;
    }
    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     */
    public function handleIncommingConnection($request, $loop, $browser, $broadcastStream) {
        if ($this->isSSEConnectionRequest($request)) {
            echo "incomming sse: ".$request->getHeaderLine('Last-Event-ID').PHP_EOL;
            return $this->getStreamingResponse($loop, $broadcastStream, $browser);
        }
    }

    /**
     * @return \React\Http\Response
     */
    public function getStreamingResponse($loop, $broadcastStream, $browser) {
        $stream = new \React\Stream\ThroughStream(function ($data) {
            if (is_string($data)) {
                return 'data: ' . $data . "\n\n";
            } else if (is_array($data)) {
                $str = '';
                foreach($data as $key => $value) {
                    $str .= "$key: $value\n";
                }
                return $str. "\n\n";
            }
        });

        $broadcastStream->on('data', function($data) use ($loop,$browser, $stream){
            $domain = current($data['data']['leaf_cert']['all_domains']);
            $ip = $this->getAddrByHost(str_replace('*.','',$domain));

            echo "$domain".PHP_EOL;

            if ($ip) {
                $uri = 'https://www.iplocate.io/api/lookup/'.$ip;

                $res = file_get_contents($uri);
                $res = json_decode($res);

                echo "GTO CERT $domain - $ip".PHP_EOL;


                $data['location'] = $res;
                $stream->write(array(
                    'event' => 'certificate',
                    'data' => json_encode($data),
                ));


            }

        });

        return new \React\Http\Response(
            200,
            array(
                'Content-Type' => 'text/event-stream'
            ),
            $stream
        );
    }


    private function getAddrByHost($host, $timeout = 1) {
        $query = exec("nslookup -timeout=$timeout -retry=1 $host", $result);
        if(preg_match('/\nAddress: (.*)\n/', implode("\n",$result), $matches)) {
            return trim($matches[1]);
        }
        return false;
    }

}