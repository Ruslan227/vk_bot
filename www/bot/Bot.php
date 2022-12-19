<?php
require_once 'bot/bot_constants.php';
require_once 'bot/BotState.php';
require_once 'bot/bot_api.php';
require_once 'bot/CommandValidator.php';

class Bot {
  private $state; // json
  private CommandValidator $commandValidator; 
  private SQLHandler $sqlHandler;
  private SplObjectStorage $stateFunc; 

  
  public function __construct() {
    $this->commandValidator = new CommandValidator();
    $this->initStateFunc();
  }

  public function setSqlHandler($sqlHandler) {
    $this->sqlHandler = $sqlHandler;
  }

  public function setBotState($botState) {
    $this->state = $botState;
  }

  private function initStateFunc() {
    $this->stateFunc = new SplObjectStorage();

    $this->stateFunc[BotState::START]                            = 'start';
    $this->stateFunc[BotState::CHOOSE_ACTION]                    = 'getAction';
    $this->stateFunc[BotState::READ_DAYS_WERE_LISTED]            = 'readRequestHandler';
    $this->stateFunc[BotState::WRITE_DAYS_WERE_LISTED ]          = 'writeRequestHandler';
    $this->stateFunc[BotState::WRITE_ACTIONS_WERE_LISTED]        = 'chooseWriteAction';
    $this->stateFunc[BotState::TASK_NUMBER_TO_REMOVE_WAS_CHOSEN] = 'removeTaskEnd';
    $this->stateFunc[BotState::WRITE_INSERT_INDEX_WAS_CHOSEN]    = 'insertTaskIndexWasChosen';
    $this->stateFunc[BotState::WRITE_INSERT_TASK_NAME_WAS_GIVEN] = 'isertTaskEnd';
    $this->stateFunc[BotState::WRITE_UPDATE_INDEX_WAS_CHOSEN]    = 'changeTaskEnterTaskName';
    $this->stateFunc[BotState::WRITE_UPDATE_TASK_NAME_WAS_GIVEN] = 'changeTaskEnd';
  }

  public function doCommand($requestData) {
    $state = unserialize($this->state['stateObj']);

    if ($this->stateFunc->contains($state)) {
      array($this, $this->stateFunc[$state])($requestData);
    } else {
      throw new Exception("unexpected state: " . $this->state['stateObj']);
    }
  }

  private function saveStateAndMessage(BotState $state, string $message, $requestData) {
    $this->state['stateObj'] = serialize($state);
    $this->sqlHandler->saveState($this->state, $requestData);
    bot_sendMessage($requestData, $message);
  }

  private function start($requestData) { 
    $this->saveStateAndMessage(BotState::CHOOSE_ACTION, ACTIONS_LIST, $requestData);
  }

  private function getAction($requestData) {
    $action = $this->getValidatedAction($requestData, array($this->commandValidator, 'validateActionCommand'));

    switch($action) {
      case 1:
        $this->readSchedule($requestData);
        break;
      case 2:
        $this->writeSchedule($requestData);
        break;
      case 3:
        $this->getTutorial($requestData);
        break;
    }
  }

  private function readSchedule($requestData) {
    $this->saveStateAndMessage(BotState::READ_DAYS_WERE_LISTED, CHOOSE_DAY . DAYS_LIST_MESSAGE, $requestData);
  }

  private function writeSchedule($requestData) {
    $this->saveStateAndMessage(BotState::WRITE_DAYS_WERE_LISTED, CHOOSE_DAY . DAYS_LIST_MESSAGE, $requestData);
  }

  private function getTutorial($requestData) {
    $this->saveStateAndMessage(BotState::CHOOSE_ACTION, TUTORIAL_MESSAGE . "\n" . ACTIONS_LIST, $requestData);
  }

  private function readRequestHandler($requestData) {
    $action = $this->getValidatedAction($requestData, array($this->commandValidator, 'validateDayChoiceCommand'));

    if ($action == 0) {
      $this->start($requestData);
      return;
    } 

    $this->sendPlans($requestData, $action);
  }

  private function writeRequestHandler($requestData) {
    $action = $this->getValidatedAction($requestData, array($this->commandValidator, 'validateDayChoiceCommand'));

    if ($action == 0) {
      $this->start($requestData);
      return;
    }

    $this->state['day_of_week'] = DAYS[$action]; 

    $this->sendPlans($requestData, $action);
    $this->saveStateAndMessage(BotState::WRITE_ACTIONS_WERE_LISTED, WRITE_ACTIONS, $requestData);
  }

  private function chooseWriteAction($requestData) {
    $action = $this->getValidatedAction($requestData, array($this->commandValidator, 'validateWriteActionCommand'));

    if ($action == 0) {
      $this->start($requestData);
      return;
    }

    switch ($action) {
      case 1:
        $this->removeTaskStart($requestData);
       break;
      case 2:
        $this->insertTask($requestData);
       break;
      case 3:
        $this->changeTask($requestData);
        break;
    }
  }

  private function removeTaskStart($requestData) {
    $this->saveStateAndMessage(BotState::TASK_NUMBER_TO_REMOVE_WAS_CHOSEN, CHOOSE_TASK_NUMBER, $requestData);
  }

  private function removeTaskEnd($requestData) {
    $action = $this->getActionAndCheckTasksAmount($requestData, array($this->commandValidator, 'validateRemoveAndUpdateIndex'));

    $this->sqlHandler->removeTask($this->state, $requestData, $action);

    $this->sendPlans($requestData, -1, $this->state['day_of_week']);
    $this->saveStateAndMessage(BotState::WRITE_ACTIONS_WERE_LISTED, WRITE_ACTIONS, $requestData);
  }

  private function getValidatedAction($requestData, $validatorFunc): int {
    $action = $this->getUserMessage($requestData); 

    if (!$validatorFunc($action)) { 
      throw new Exception(ILLEGAL_USER_COMMAND);
    }

    return (int) $action;
  }

  private function insertTask($requestData) {
    $this->saveStateAndMessage(BotState::WRITE_INSERT_INDEX_WAS_CHOSEN, ENTER_INSERT_INDEX, $requestData);
  }

  private function insertTaskIndexWasChosen($requestData) {
    $action = $this->getActionAndCheckTasksAmount($requestData, array($this->commandValidator, 'validateInsertIndex'));
    $action += 1;

    $this->state['insertInd'] = $action;
    $this->saveStateAndMessage(BotState::WRITE_INSERT_TASK_NAME_WAS_GIVEN, ENTER_TASK_NAME, $requestData);
  }

  private function isertTaskEnd($requestData) {
    $taskName = $this->getUserMessage($requestData);
    $this->sqlHandler->insertTask($this->state, $requestData, $taskName);

    unset($this->state['insertInd']);
    $this->sendPlans($requestData, -1, $this->state['day_of_week']);
    $this->saveStateAndMessage(BotState::WRITE_ACTIONS_WERE_LISTED, WRITE_ACTIONS, $requestData);
  }

  private function changeTask($requestData) {
    $this->saveStateAndMessage(BotState::WRITE_UPDATE_INDEX_WAS_CHOSEN, ENTER_UPDATE_TASK_INDEX, $requestData);
  }

  private function changeTaskEnterTaskName($requestData) {
    $action = $this->getActionAndCheckTasksAmount($requestData, array($this->commandValidator, 'validateRemoveAndUpdateIndex'));

    $this->state['changeInd'] = $action;
    $this->saveStateAndMessage(BotState::WRITE_UPDATE_TASK_NAME_WAS_GIVEN, ENTER_UPDATE_NEW_TASK, $requestData);
  }

  private function getActionAndCheckTasksAmount($requestData, $validatorFunc): int {
    $action = $this->getUserMessage($requestData); 
    $tasksAmount = $this->sqlHandler->getAmountOfTasks($this->state, $requestData);
 
    if (!$validatorFunc($action, $tasksAmount)) { 
      throw new Exception(ILLEGAL_USER_COMMAND);
    }

    return (int) $action;
  }

  private function changeTaskEnd($requestData) {
    $newTaskName = $this->getUserMessage($requestData); 
    $this->sqlHandler->updateTask($this->state, $requestData, $newTaskName);

    unset($this->state['changeInd']);
    $this->sendPlans($requestData, -1, $this->state['day_of_week']);
    $this->saveStateAndMessage(BotState::WRITE_ACTIONS_WERE_LISTED, WRITE_ACTIONS, $requestData);
  }

  private function getUserMessage($requestData) {
    return $requestData['message']['text'];
  }

  private function sendPlans($requestData, $dayNum = -1, $dayName = null) {
    $plans = $this->sqlHandler->readRequest($requestData, $dayNum, $dayName);  

    if ($plans == null) {
      $plans = NO_PLANS;
    }

    bot_sendMessage($requestData, $plans);
  }

}

?>


