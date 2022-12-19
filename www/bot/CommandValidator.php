<?php
require_once 'bot/bot_constants.php';

class CommandValidator {
  private function isCommandInAccessibleBounds(int $commandNum, int $l, int $r) {
    return $commandNum >= $l && $commandNum < $r;
  }

  private function validateCommand($actionCommand, $additionalValidatorFunc): bool {
    if (!is_numeric($actionCommand)) {
      return false;
    }
    $actionCommand = intval($actionCommand);

    return $additionalValidatorFunc($actionCommand);
  }

  
  
  private function isActionCommandInAccessibleBounds(int $commandNum): bool {
    return $this->isCommandInAccessibleBounds($commandNum, 1, 4);
  }

  private function isWriteActionCommandInAccessibleBounds(int $commandNum): bool {
    return $this->isCommandInAccessibleBounds($commandNum, 0, 4);
  }

  private function isDayChoiceCommandInAccessibleBounds(int $commandNum): bool {
    return $this->isCommandInAccessibleBounds($commandNum, 0, 8);
  }


  public function validateActionCommand($action): bool {
    return $this->validateCommand($action, array($this, 'isActionCommandInAccessibleBounds'));
  }

  public function validateDayChoiceCommand($commandNum): bool {
    return $this->validateCommand($commandNum, array($this, 'isDayChoiceCommandInAccessibleBounds'));
  }

  public function validateWriteActionCommand($commandNum): bool {
    return $this->validateCommand($commandNum, array($this, 'isWriteActionCommandInAccessibleBounds'));
  }

 
  public function validateChangeIndex($index, $minIndex, $maxIndex) {
    if (!is_numeric($index)) {
      return false;
    }
    $index = intval($index);

    return $this->isCommandInAccessibleBounds($index, $minIndex, $maxIndex + 1);
  }

  public function validateInsertIndex($index, $maxIndex) {
    return $this->validateChangeIndex($index, 0, $maxIndex);
  }

  public function validateRemoveAndUpdateIndex($index, $maxIndex) {
    return $this->validateChangeIndex($index, 1, $maxIndex);
  }

}

?>