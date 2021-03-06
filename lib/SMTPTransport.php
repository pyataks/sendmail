<?php

namespace pyatakss\sendmail;


class SMTPTransport extends Transport implements TransportInterface
{
    /**
     * Send the given Message.
     *
     * Recipient/sender data will be retrieved from the Message API.
     * The return value is the number of recipients who were accepted for delivery.
     *
     * @param MessageInterface $message
     *
     * @return int
     */
    public function send(MessageInterface $message)
    {
        if (!array_key_exists('host', $this->configuration) || !array_key_exists('port', $this->configuration) || !array_key_exists('username', $this->configuration) || !array_key_exists('password', $this->configuration)) {
            return 0;
        }
        $messageStr = $message->toString();

        $toStr = $this->getTo($messageStr, true);
        $to = $this->getTo($messageStr);
        $from = $this->getFrom($messageStr);
        $subject = $message->getSubject();
        $endHeaders = strpos($messageStr, MessageInterface::LINE_SEPARATOR . MessageInterface::LINE_SEPARATOR);
        $headers = substr($messageStr, 0, $endHeaders);
        $body = substr($messageStr, $endHeaders + 4);

        foreach ($from as $address => $name) {
            $from_email = $address;
            $from_name = $name;
        }

        $smtp_host = $this->configuration['host'];
        $smtp_port = $this->configuration['port'];
        $user = $this->configuration['username'];
        $pass = $this->configuration['password'];

        $from_email = (isset($from_email) && !empty($from_email)) ? $from_email : $user;

        if (!($socket = fsockopen($smtp_host, $smtp_port, $errno, $errstr, 15))) {
            return 0;
        }

        $this->serverParse($socket, '220');

        fwrite($socket, 'EHLO smtp.gmail.com' . Message::LINE_SEPARATOR);
        $this->serverParse($socket, '250', __LINE__);

        fwrite($socket, 'AUTH LOGIN' . Message::LINE_SEPARATOR);
        $this->serverParse($socket, '334', __LINE__);

        fwrite($socket, base64_encode($user) . Message::LINE_SEPARATOR);
        $this->serverParse($socket, '334', __LINE__);

        fwrite($socket, base64_encode($pass) . Message::LINE_SEPARATOR);
        $this->serverParse($socket, '235', __LINE__);

        fwrite($socket, 'MAIL FROM: <' . $from_email . '>' . Message::LINE_SEPARATOR);
        $this->serverParse($socket, '250', __LINE__);

        foreach ($to as $email => $name) {
            fwrite($socket, 'RCPT TO: <' . $email . '>' . Message::LINE_SEPARATOR);
            $this->serverParse($socket, '250', __LINE__);
        }

        fwrite($socket, 'DATA' . Message::LINE_SEPARATOR);
        $this->serverParse($socket, '354', __LINE__);

        fwrite($socket, 'Subject: ' . $subject . Message::LINE_SEPARATOR
            . 'To: ' . $toStr . Message::LINE_SEPARATOR
            . $headers . Message::LINE_SEPARATOR . Message::LINE_SEPARATOR
            . $body . Message::LINE_SEPARATOR);

        fwrite($socket, '.' . Message::LINE_SEPARATOR);
        $this->serverParse($socket, '250', __LINE__);

        fwrite($socket, 'QUIT' . Message::LINE_SEPARATOR);
        fclose($socket);

        return count($to);
    }

    /**
     * Parse server responses
     *
     * @param $socket
     * @param $expected_response
     * @param null $line
     *
     * @return bool
     * @throws PSMailException
     */
    private function serverParse($socket, $expected_response, $line = null)
    {
        $server_response = '';
        while (substr($server_response, 3, 1) != ' ') {
            if (!($server_response = fgets($socket, 256))) {
                throw new PSMailException('Error while fetching server response codes');
            }
        }

        if (!(substr($server_response, 0, 3) == $expected_response)) {
            throw new PSMailException($server_response);
        }
    }

    /**
     *  Check connection through the sockets
     */
    public function beforeSend()
    {
        $socket = fsockopen("ssl://smtp.gmail.com", 465, $errno, $errstr, 10);
        if (!$socket) {
            echo "ERROR: smtp.gmail.com 465 - $errstr ($errno)<br>\n";
        } else {
            echo "SUCCESS: smtp.gmail.com 465 - ok<br>\n";
        }

        $socket = fsockopen("smtp.gmail.com", 587, $errno, $errstr, 10);
        if (!$socket) {
            echo "ERROR: smtp.gmail.com 587 - $errstr ($errno)<br>\n";
        } else {
            echo "SUCCESS: smtp.gmail.com 587 - ok<br>\n";
        }
    }
}