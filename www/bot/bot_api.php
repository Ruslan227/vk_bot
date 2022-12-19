<?php

function bot_sendMessageHello($data) {
  $user_id = $data['message']['peer_id'];
  // $users_get_response = vkApi_usersGet($user_id);
  // $user = array_pop($users_get_response);
  $msg = "Привет, !"; // {$user['first_name']}

  vkApi_messagesSend($user_id, $msg); // $attachments
}

function bot_sendMessage($data, $message) {
  $user_id = $data['message']['peer_id'];
  vkApi_messagesSend($user_id, $message); 
}

?>