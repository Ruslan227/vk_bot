<?php
define('CALLBACK_API_EVENT_CONFIRMATION', 'confirmation');
define('CALLBACK_API_EVENT_MESSAGE_NEW', 'message_new');

require_once 'config.php';
require_once 'global.php';

require_once 'api/vk_api.php';

require_once 'bot/bot_api.php';
require_once 'bot/Bot.php';
require_once 'sql/SQLHandler.php';

if (!isset($_REQUEST)) {
  exit;
}

try {
  $sqlHandler = new SQLHandler(); 
} catch (Exception $e) {
  echo 'Caught exception: ', $e->getMessage(), '\n';
  exit();
}

callback_handleEvent($sqlHandler);

function callback_handleEvent($sqlHandler) {
  $event = _callback_getEvent();

  try {
    switch ($event['type']) {
      //Подтверждение сервера
      case CALLBACK_API_EVENT_CONFIRMATION:
        _callback_handleConfirmation();
        break;

      //Получение нового сообщения
      case CALLBACK_API_EVENT_MESSAGE_NEW:
        _callback_handleMessageNew($event['object'], $sqlHandler);
        break;

      default:
        _callback_response('Unsupported event');
        break;
    }
  } catch (Exception $e) {
    log_error($e);
  }

  _callback_okResponse();
}

function _callback_getEvent() {
  return json_decode(file_get_contents('php://input'), true);
}

function _callback_handleConfirmation() {
  _callback_response(CALLBACK_API_CONFIRMATION_TOKEN);
}

function _callback_handleMessageNew($data, $sqlHandler) {
  // bot_sendMessageHello($data);

  $bot = new Bot();
  $userState = $sqlHandler->getUserState($data);
  $bot->setBotState($userState);
  $bot->setSqlHandler($sqlHandler);
  
  try {
    $bot->doCommand($data);
  } catch (Exception $e) {
    bot_sendMessage($data, $e->getMessage());
  }
  
  _callback_okResponse();
}

function _callback_okResponse() {
  _callback_response('ok');
}

function _callback_response($data) {
  echo $data;
  exit();
}


