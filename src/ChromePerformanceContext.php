<?php namespace EdmondsCommerce\BehatChromePerformance;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\MinkExtension\Context\RawMinkContext;
use Exception;

class ChromePerformanceContext extends RawMinkContext implements Context, SnippetAcceptingContext
{

    protected $_log;

    /**
     * Use this before each Scenario to make sure that the previous log data is discarded
     *
     * @Given I am performance testing
     */
    public function resetLog()
    {
        $this->getSession()->stop();
        $this->_log = null;
    }

    /**
     * @Then There should be no broken requests
     */
    public function thereAreNoBrokenLinks()
    {
        $log = $this->_getLog();
        foreach ($log AS $request) {
            if (isset($request['status']) && $request['status'] != 200) {
                throw new \Exception('Page links to non existent resource' . $request['url']);
            }
        }
    }

    /**
     * @param $number
     * @param $type
     *
     * @throws Exception
     * @Then There should be no more than :number :type files
     */
    public function thereAreNoMoreThanOfType($number, $type)
    {
        switch (strtolower($type)) {
            case 'image':
                $contentType = 'image';
                break;
            case 'css':
                $contentType = 'css';
                break;
            case 'javascript':
                $contentType = 'javascript';
                break;
            case 'html':
                $contentType = 'html';
                break;
            default;
                throw new Exception('Unknown File Type');
        }

        $counter = 0;
        foreach ($this->_getLog() as $request) {
            if (isset($request['type']) && strpos($request['type'], $contentType) !== false) {
                $counter++;
            }
        }

        if ($counter > $number) {
            throw new Exception("Maximun number of $type files is $number, found $counter");
        }
    }

    /**
     * @param $number
     *
     * @throws Exception
     * @Then The page should take no more than :number seconds to load
     */
    public function thePageLoadsInUnder($number)
    {
        $totalTime = 0;
        foreach ($this->_getLog() AS $request) {
            if (isset($request['type']) && $request['type'] == 'text/html') {
                $totalTime = $request['totalTime'];
                break;
            }
        }
        if ($totalTime > $number) {
            throw new Exception("Page took $totalTime seconds to load - Acceptable limit is $number");
        }
    }

    /**
     * @param $number
     *
     * @throws Exception
     * @Then /^The total amount download should be under "([^"]*)"$/
     */
    public function theAmountDownloadedIsUnder($number)
    {
        $totalFileSize = 0;
        foreach ($this->_getLog() AS $request) {
            if (isset($request['fileSize'])) {
                $totalFileSize += $request['fileSize'];
            }
        }
        $maxFileSize = $this->_convertToMachineReadable($number);
        if ($totalFileSize > $maxFileSize) {
            $readable = $this->_convertToHumanReadable($totalFileSize);
            $number   = $this->_convertToHumanReadable($maxFileSize);
            throw new \Exception("The amount downloaded is $readable. Max size is $number");
        }
    }

    protected function _convertToHumanReadable($bytes)
    {
        if ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }

    protected function _convertToMachineReadable($phrase)
    {
        $unit    = strtolower(trim($phrase, " \t\n\r\0\x0B1234567890."));
        $numbers = preg_replace("/[^0-9.]/", "", $phrase);

        switch ($unit) {
            case 'mb':
                $multiplier = 1048576;
                break;
            case 'kb':
                $multiplier = 1024;
                break;
            default:
                $multiplier = 1;
                break;
        }

        return $numbers * $multiplier;
    }

    /**
     * This is used to actually get the logging data. If this throws errors make sure you have the following in your
     * behat.yml file
     *
     * selenium2:
     *  browser: chrome
     *      capabilities:
     *          extra_capabilities: {
     *                  "chromeOptions": {
     *                      perfLoggingPrefs: {
     *                          'traceCategories': 'blink.console,disabled-by-default-devtools.timeline'
     *                      }
     *                  },
     *                  "loggingPrefs": { "performance": "ALL" }
     *              }
     *
     *
     * @return array
     */
    protected function _getLog()
    {
        if (is_null($this->_log)) {
            $driver      = $this->getSession()->getDriver();
            $session     = $driver->getWebDriverSession();
            $performance = $session->log('performance');
            $performance = $this->_handleLog($performance);
            $this->_log  = $performance;
        }

        return $this->_log;
    }

    /**
     * This is used to transform the data that is gathered in the logs into something that is useful. To add more
     * details to this pass through the debug param and this will instead add everything to the log
     *
     * @param      $log   - The log to be processes
     * @param bool $debug - True to gather everything
     *
     * @return array - In the following format
     *   [18.1] => Array # The array key is the request Id
     *       (
     *           [url] => http://www.example.com/
     *           [startTime] => 1431965988.0075
     *           [type] => text/html
     *           [status] => 200
     *           [fileSize] => 18934
     *           [endTime] => 1431965990.2991
     *           [totalTime] => 2.2916398048401
     *       )
     *
     *
     */
    protected function _handleLog($log, $debug = false)
    {
        $logData = array();
        foreach ($log AS $logEntry) {
            $messageData = json_decode($logEntry['message']);
            $message     = $messageData->message;

            $requestId = 'other';
            if (isset($message->params->requestId)) {
                $requestId = $message->params->requestId;
            }
            if ($message->method == 'Network.dataReceived' && $debug == false) {
                continue;
            }
            $params = $message->params;


            if (isset($params->request)) {
                $url                        = $params->request->url;
                $logData[$requestId]['url'] = $url;
            }
            if ($message->method == "Network.requestWillBeSent") {
                $logData[$requestId]['startTime'] = $params->timestamp;
            }
            if ($message->method == "Network.responseReceived") {
                $logData[$requestId]['type']     = $params->response->mimeType;
                $logData[$requestId]['status']   = $params->response->status;
                $logData[$requestId]['fileSize'] = 0;
                if (isset($params->response->headers->{'Content-Length'})) {
                    $logData[$requestId]['fileSize'] = $params->response->headers->{'Content-Length'};
                }
                if (isset($params->response->headers->{'content-length'})) {
                    $logData[$requestId]['fileSize'] = $params->response->headers->{'content-length'};
                }

            }

            if ($message->method == "Network.loadingFinished") {
                $logData[$requestId]['endTime'] = $params->timestamp;
                if (isset($logData[$requestId]['startTime'])) {
                    $logData[$requestId]['totalTime'] = $params->timestamp - $logData[$requestId]['startTime'];
                }
            }
            if ($debug == true) {
                $logData[$requestId][] = $messageData;
            }
        }

        return $logData;
    }

}