<?php


namespace Diagnolek\Db;


use Psr\Log\AbstractLogger;

class EchoLogger extends AbstractLogger
{

    private $messages = [];
    private $display;

    public function __construct($display=true)
    {
        $this->display = $display;
    }
    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
        $this->messages[] = $message;
        if ($this->display) {
            echo '<div style="width: 100%; word-wrap: normal">'.$message.'</div>';
        }
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function getLastMessage()
    {
        $length = count($this->messages);
        if ($length >= 1) {
            return $this->messages[$length-1];
        }
        return null;
    }

    public function clearMessages()
    {
        $this->messages = [];
    }
}