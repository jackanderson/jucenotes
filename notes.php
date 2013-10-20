<?php
/* Commments
 *
 * A simple api for fielding the ajax requests from notess.js
 * stores notess in an sqlite3 database
 *
 * @author jack anderson
 */

class NoteApi {
    private $db = NULL;
    private $tableName='notes';
    
    /*
     * Constructor
     * @param $db a PDO instance
     */
    public function __construct($db) {
        $this->db=$db;
        $this->createDatabase();
        $this->createIndexes();
    }
    private function createDatabase() {
         $this->db->exec('CREATE TABLE IF NOT EXISTS `'.$this->tableName.'` (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key VARCHAR(33),
                name TEXT,
                username VARCHAR(200),
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                approved INTEGER,
                value TEXT 
            );
         ');
    }
    private function createIndexes() {
       	 $this->db->exec("CREATE INDEX IF NOT EXISTS `key` on notes('key')");
    }
    public function create($data) {
        $sql="insert into `".$this->tableName."` (key,name,username,value,approved) values (:key,:name,:username,:value,:approved)";
        $values=array(
            'key'=>$data['key'],
            'name'=>$data['name'],
            'username'=>$data['username'],
            'value'=>strip_tags($data['value']),
            'approved'=>1
        );
         if($this->db->prepare($sql)->execute( $values )) {
             $this->respond(200,array('message'=>'inserted new record'));
         } else$this->respond(500);
    }
    public function read($data) {
        if(!empty($data['key'])) {
           $sql="select * from `".$this->tableName."` where key=?";
           if(isset($data['approved'])) $sql.=" and approved!=0";
           $use='key';
        }
        if(!empty($data['id'])) {
            $sql="select * from `".$this->tableName."` where id=?";
            $use='id';
        }
        $stm=$this->db->prepare($sql);
        if($stm->execute(array($data[$use]))!==false) {
            $retArr=$stm->fetchAll();
            if(isset($data['count'])) {
               $retArr=array('count'=>count($retArr)); 
            } 
            $this->respond(200,$retArr);
            return;
        }
        $this->respond(500,'sql error');

    }
    public function update($data) {
        $this->respond(500,'not yet implemented');
    }
    public function delete($data) {

    }
    public function processRequest($method,$data) {
        if(!isset($data['cmd'])) {
            $this->respond(500,'no command issued');
            return;
        }
        switch($data['cmd']) {
            case 'create':
                if($this->validate($method,$data,array('key','name','username','value')))
                {
                    $this->create($data);
                } else $this->respond(500,'failed validation');
            break;
            case 'read':
                if($this->validate($method,$data,array()))
                {
                    $this->read($data);
                }  else $this->respond(500,'failed validation');        
            break;
            case 'update':
                if($this->validate($method,$data,array('id')))
                {
                    $this->update($data);
                } else $this->respond(500,'failed validation');           
            break;
            case 'delete':
                $this->respond(500,'not implemented');
            break;  
            default:
                $this->respond(500,'unknown command');
            break;                          
        }
    }
    private function validate($method,$data,$require) {
        if($data['cmd']=='create' && $method!=='POST') return false;
        foreach($require as $k) {
            if(empty($data[$k])) return false;
        }
        return true;
    }
    private function respond($status, $data='unknown error') {
         header("HTTP/1.1 $status");
         header("Content-type: application/json");
         if(is_array($data)) echo json_encode($data);
         else {
             echo json_encode(array('error'=>$data));
         }
    }
}

$dsn='sqlite:notes.sqlite3';
$pdo=new PDO($dsn);
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

$api = new NoteApi($pdo);
$api->processRequest($_SERVER['REQUEST_METHOD'],($_SERVER['REQUEST_METHOD']=='GET')?$_GET:$_POST);
