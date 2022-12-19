<?php
define('TUTORIAL_MESSAGE', "Бот умеет изменять и читать ваше расписание на неделю. Вы вводите номер команды, выбранная команда выполняется.");
define('ACTIONS_LIST', "Доступные действия:\n1. чтение расписания\n2. запись дел\n3. как работает бот?");
define('CHOOSE_DAY', "Выберите день недели:\n0. отмена");
define('ILLEGAL_COMMAND', "Недопустимый формат ввода. Введите еще раз.");
define('ERROR', 'что-то пошло не так');
define('FAILED_COMMAND', 'не удалось выполнить команду');
define('ILLEGAL_USER_COMMAND', "Недопустимый формат ввода. Вводить можно только целые числа, которые входят в диапазон допустимых команд. Введите еще раз.");
define('NO_PLANS', 'у вас нет дел');
define('WRITE_ACTIONS', "что вы хотите сделать с данным списком?\n0. в главное меню\n1. удалить дело\n2. вставить дело после i-ой позиции в списке\n3. изменить дело");
define('CHOOSE_TASK_NUMBER', 'введите номер дела.');
define('ENTER_INSERT_INDEX', 'введите номер записи, после которой будет вставлено новое');
define('ENTER_TASK_NAME', 'введите название дела');
define('ENTER_UPDATE_TASK_INDEX', 'введите номер дела, которое хотите изменить');
define('ENTER_UPDATE_NEW_TASK', 'введите название нового задания');
define('DAYS', array(
  1 => 'Понедельник',
  2 => 'Вторник',
  3 => 'Среда',
  4 => 'Четверг',
  5 => 'Пятница',
  6 => 'Суббота',
  7 => 'Воскресенье'
));

define('DAYS_LIST_MESSAGE', getDaysMessage());

function getDaysMessage() {
  $res = '';

  foreach (DAYS as $num => $dayName) {
    $res .= "\n" . $num . " " . $dayName;
  }

  return $res;
}
?>