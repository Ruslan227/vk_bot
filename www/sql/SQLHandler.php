<?php
require_once 'bot/BotState.php';
require_once 'sqlconfig.php';

class SQLHandler {

  private $link;

  public function __construct() {
    $this->connect();
  }

  public function __destruct() {
    $this->link->close();
  }

  private function connect() {
    $this->link = mysqli_connect(
      SERVER_ADDRESS,
      USER_NAME,
      PASSWORD,
      DATA_BASE_NAME
    );

    if ($this->link == false) {
      throw new Exception("Ошибка: Невозможно подключиться к MySQL " . mysqli_connect_error());
    } else {
      mysqli_set_charset($this->link, "utf8");
    }
  }

  private function insertUser($userId) { 
    $sql = 'INSERT INTO db2.user_state (id) VALUES (' . $userId . ')';
    $res = mysqli_query($this->link, $sql);
    if ($res == false) {
      throw new Exception('sql error: can not insert user id ' . mysqli_error($this->link));
    }
  }

  public function readRequest($requestData, int $dayNum, $dayName = null): string {
    $userId = $this->getUserId($requestData);
    $day = ($dayName == null)? DAYS[$dayNum] : $dayName;
    
    $sql = 'SELECT * FROM db2.schedule WHERE day_of_week="' . $day . '" AND user_id=' . $userId . ' ORDER BY row_num';
    $notes = mysqli_query($this->link, $sql);
    $result = '';
    $ind = 1;

    while ($row = mysqli_fetch_array($notes)) {
      $result .= $ind . ". " . $row['message'] . "\n";
      $ind++;
    }

    return $result;
  }

  public function getUserState($requestData) { 
    $userId = $this->getUserId($requestData);
    $sql = 'SELECT * FROM db2.user_state WHERE id=' . $userId;
    $result = mysqli_query($this->link, $sql);
    $stateObj = 'not found';

    if ($row = mysqli_fetch_array($result)) {
      $stateObj = $row['state'];
    }

    $defaultVal = array('stateObj' => serialize(BotState::START));

    if ($stateObj == 'not found') {
      $this->insertUser($userId);
      $stateObj = $defaultVal; 
    } else if ($stateObj == null) {
      $stateObj = $defaultVal;
    } else {
      $stateObj = json_decode($stateObj, true, 512, JSON_UNESCAPED_UNICODE); 
    }

    return $stateObj;
  }

  public function saveState($state, $requestData) {
    $userId = $this->getUserId($requestData);
    $encodedState = json_encode($state, JSON_UNESCAPED_UNICODE);

    if ($encodedState == false) {
      throw new Exception("json error in save");
    }

    $sql = "UPDATE db2.user_state SET state = '" .  str_replace("\"", "\\\"", $encodedState) . "' WHERE id = " . $userId; 

    if (!mysqli_query($this->link, $sql)) {
      throw new Exception("sql error: can not update state");
    }
  }

  private function getUserId($requestData) {
    return $requestData['message']['peer_id'];
  }

  // remove one task, after that row_num-- for tasks which row_num > deleted_row_num; 
  public function removeTask($state, $requestData, int $taskNum) {
    $userId = $this->getUserId($requestData); 

    $sql = "DELETE FROM db2.schedule WHERE user_id = " . $userId . " AND day_of_week = '" . $state['day_of_week'] . "' AND row_num = " . $taskNum;

    if(!mysqli_query($this->link, $sql)) {
      throw new Exception("sql error: can not remove task");
    }

    $this->updateRowNums($state, $userId, $taskNum, false);
  }

  private function updateRowNums($state, $userId, $taskNum, $isIncOnUpdate) {
    $sql = "SELECT * FROM db2.schedule WHERE user_id = " . $userId . " AND day_of_week = '" . $state['day_of_week'] . "' AND row_num >= " . $taskNum;

    $tasksToUpdate = mysqli_query($this->link, $sql);
    $idNewRowNum = array();

    while ($row = mysqli_fetch_array($tasksToUpdate)) {
      $idNewRowNum[$row['id']] = $isIncOnUpdate? ($row['row_num'] + 1) : (max($row['row_num'] - 1, 1));
    }

    foreach($idNewRowNum as $id => $newRowNum) {
      $sql = "UPDATE db2.schedule SET row_num = " . $newRowNum . " WHERE id = " . $id;

      if (!mysqli_query($this->link, $sql)) {
        throw new Exception("sql error: can not update row_num");
      }
    }
  }

  public function getAmountOfTasks($state, $requestData): int {
    $userId = $this->getUserId($requestData); 
    
    $sql = 'SELECT COUNT(*) FROM db2.schedule WHERE user_id = ' . $userId . ' AND day_of_week = "' . $state['day_of_week'] . '"';

    $tasksAmount = mysqli_query($this->link, $sql);
    
    if ($row = mysqli_fetch_array($tasksAmount)) {
      $tasksAmount = $row['COUNT(*)'];
    } else {
      throw new Exception("sql error: can not count amount of tasks");
    }
    
    return $tasksAmount;
  }

  public function insertTask($state, $requestData, $taskName) {
    $userId = $this->getUserId($requestData); 

    $this->updateRowNums($state, $userId, $state['insertInd'], true);

    $sql = 'INSERT INTO db2.schedule (user_id, message, day_of_week, row_num) VALUES (' . $userId . ', "' . $taskName . '", "' . $state['day_of_week'] . '", ' . $state['insertInd'] . ')';


    if(!mysqli_query($this->link, $sql)) {
      throw new Exception("sql error: can not insert task");
    }
  }

  public function updateTask($state, $requestData, $newMessage) {
    $userId = $this->getUserId($requestData); 
    $sql = 'UPDATE db2.schedule SET message = "' . $newMessage . '" WHERE user_id = ' . $userId . ' AND day_of_week = "' . $state['day_of_week'] . '" AND row_num = ' . $state['changeInd']; 

    if(!mysqli_query($this->link, $sql)) {
      throw new Exception("sql error: can not update task");
    }
  }



  /*
+-------+--------------+------+-----+---------+-------+
| Field | Type         | Null | Key | Default | Extra |
+-------+--------------+------+-----+---------+-------+
| id    | int unsigned | NO   | PRI | NULL    |       |
| state | varchar(400) | YES  |     |         |       |
+-------+--------------+------+-----+---------+-------+
2 rows in set (0.01 sec)

mysql> DESCRIBE db2.schedule;
+-------------+--------------+------+-----+---------+----------------+
| Field       | Type         | Null | Key | Default | Extra          |
+-------------+--------------+------+-----+---------+----------------+
| id          | int unsigned | NO   | PRI | NULL    | auto_increment |
| user_id     | int unsigned | NO   | MUL | NULL    |                |
| message     | varchar(250) | NO   |     | NULL    |                |
| day_of_week | varchar(250) | NO   | MUL | NULL    |                |
| row_num     | int unsigned | NO   | MUL | NULL    |                |
+-------------+--------------+------+-----+---------+----------------+

  */
  
}

?>