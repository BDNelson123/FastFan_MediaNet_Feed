<?
class Database
{
    private $db;    
    
    public function __construct($db_name = null) {      
        
        $db_name = empty($db_name) ? 'MNDigital_Feed' : $db_name;
        echo 'connecting to: '.$db_name.chr(10);
        
        $this->db = new mysqli(DBASE_HOST, DBASE_USER, DBASE_PWD, DBASE_NAME);
    }
    
    private function connect()
    {
        
    }
    
    private function close()
    {
        
    }    
    
    public function fetch($sql)
    {
        $result = $this->query($sql);
        
        if(!empty($result))
        {
            $r = $result[0];
        }
        else
        {
            $r = array();
        }
        
        return $r;
    }
    
    public function fetch_all($sql)
    {
        return $this->query($sql);
    }    
    
    public function query($sql)
    {        
        $result = $this->db->query($sql);
        
        if($result === false)
        {
            error_log($sql);
            error_log(mysqli_error($this->db));
            
            return false;
        }
        
        $rows = array();
        
        while($row = $result->fetch_array(MYSQL_ASSOC))
        {
            $rows[] = $row;
        }

        return $rows;
    }
    
    public function update($table_name, Array $values = array(), Array $where = array())
    {
        $sql = 
        "
        update $table_name
        set ";
        
        foreach($values as $column_name => $value)
        {
            $update_columns[] = " $column_name = $value";
        }
        
        $sql.= implode(',', $update_columns);
        
        $sql.=
        "
        where 1 = 1
        ";
        
        foreach($where as $column_name => $value)
        {
            $sql.= " and $column_name = '$value'";
        }            
        
        $this->execute($sql);
    }
    
    public function execute($sql)
    {
        $result = false;
        $stmt = $this->db->prepare($sql);        
        
        if(!$stmt)
        {
            error_log('Database::execute() failed:  '.$this->db->error);
        }
        else
        {
            $result = $stmt->execute();
        }        
        
        if(!$result)
        {
            error_log($sql);
            error_log(mysqli_error($this->db));
        }
    }        
    
    public function get_insert_id()
    {
        return $this->db->insert_id;
    }
    
    public function escape($value)
    {
        return mysqli_real_escape_string($this->db, $value);                    
    }
    
}
?>
