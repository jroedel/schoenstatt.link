<?php
namespace Application\Log\Writer;

use Laminas\Log\Writer\AbstractWriter;
use Laminas\Log\Logger;
use Laminas\Json\Json;
use Laminas\Log\Formatter\Simple as SimpleFormatter;

class SlackWebhook extends AbstractWriter
{
    protected $webhookUrl;
    protected $minimumSeverity;

    public function __construct($webhookUrl, $minimumSeverity = Logger::INFO, $options = [])
    {
        parent::__construct($options);
        $this->webhookUrl = $webhookUrl;
        $this->minimumSeverity = $minimumSeverity;
        if ($this->formatter === null) {
            $this->formatter = new SimpleFormatter();
        }
    }

    protected function doWrite(array $event)
    {
        if (! isset($this->webhookUrl)) {
            throw new \Exception('Invalid webhook URL');
        }

        $line = $this->formatter->format($event);

        $json = Json::encode([
            'text' => $line,
        ]);
        // Get cURL resource
        $ch = curl_init();
        // Set some options
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->webhookUrl,
            CURLOPT_HTTPHEADER => [
                'Content-type: application/json',
            ],
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HEADER => false, //don't return headers
            CURLOPT_RETURNTRANSFER => false //don't return response, we won't know what happened
        ]);
        $mh = curl_multi_init();
        curl_multi_add_handle($mh, $ch);
        $stillRunning = false;
        curl_multi_exec($mh, $stillRunning);
        // Close request to clear up some resources
//         curl_close($ch);
        //for now just send and pray
    }
}
