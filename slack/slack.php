<?php

require_once(INCLUDE_DIR.'class.signal.php');
require_once(INCLUDE_DIR.'class.plugin.php');
require_once('config.php');

class SlackPlugin extends Plugin {
    var $config_class = "SlackPluginConfig";

    function bootstrap() {
        Signal::connect('model.created', array($this, 'onTicketCreated'), 'Ticket');
    }

    function onTicketCreated($ticket){
        try {
            global $ost;
            $subject = $ticket->getSubject();
            $payload = array(
                'attachments' =>
                array (
                    array (
                        'pretext' => "Создана новая заявка <" . $ost->getConfig()->getUrl() . "scp/tickets.php?id="
                        . $ticket->getId() . "|#" . $ticket->getNumber() . "> в отделе ". Format::htmlchars($ticket->getDept() instanceof Dept ? $ticket->getDept()->getName() : '')
                        ." через " . $ticket->getSource() . " (" . Format::db_datetime($ticket->getCreateDate()) . " MSK)",
                        'fallback' => "Создана новая заявка <" . $ost->getConfig()->getUrl() . "scp/tickets.php?id="
                        . $ticket->getId() . "|#" . $ticket->getNumber() . "> в отделе ". Format::htmlchars($ticket->getDept() instanceof Dept ? $ticket->getDept()->getName() : '')
                        ." через " . $ticket->getSource() . " (" . Format::db_datetime($ticket->getCreateDate()) . " MSK)",
                        'color' => "#D00000",
                        'fields' =>
                        array(
                            array (
                                'title' => mb_convert_case(Format::htmlchars($ticket->getName()), MB_CASE_TITLE) . " (".$ticket->getEmail().")",
                                'value' => $subject,
                                'short' => false
                            ),
                        ),
                    ),
                ),
            );

            $regex = NULL;
            if ($ticket->getDept() instanceof Dept) {
                $id = $ticket->getDept()->getId();
                $channel = self::getConfig()->get("slack_department_id_".$id);
                $regex = self::getConfig()->get("slack_department_regex_id_".$id);
                if ($channel) {
                    $payload["channel"] = $channel;
                }
            }

            if ($regex) {
                $message = $ticket->getLastMessage()->getBody()->getClean();
                if (!preg_match($regex, $subject) || !preg_match($regex, $message)) {
                    error_log('The message was ignored due to regex mismatch.');
                    return;
                }
            }

            $url = $this->getConfig()->get('slack-webhook-url');
            $data_string = utf8_encode(json_encode($payload));

            if (!function_exists('curl_init')){
                error_log('osticket slackplugin error: cURL is not installed!');
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($ch);

            if($result === false){
                error_log($url . ' - ' . curl_error($ch));
            }
            else{
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if($statusCode != '200'){
                    error_log($url . ' Http code: ' . $statusCode);
                }
            }
            curl_close($ch);
        }
        catch(Exception $e) {
            error_log('Error posting to Slack. '. $e->getMessage());
        }
    }
}

?>
