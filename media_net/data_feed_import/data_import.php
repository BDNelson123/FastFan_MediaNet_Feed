<?php
require(__DIR__.'/../../common/config.php');
require(__DIR__.'/db.php');

// add phpseclib to the include path
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__.'/phpseclib0.3.1');
require('Net/SFTP.php');

class DataImport
{
    protected $sftp;
    protected $base_path = __DIR__;
    protected $db;    
    protected $handle;
    protected $import_id;    
    protected $xml_reader;    
    protected $import_methods = array( );
    protected $current_method;

    protected function sftp_connect($server)
    {        
        $this->sftp = new Net_SFTP($server['host']);
        
        if (!$this->sftp->login($server['username'], $server['password'])) {
            exit('Login Failed');
        }
    }    
            
    protected function do_import_method()
    {
        $this->record_method_start_time();        
        $this->{$this->current_method}();
        $this->record_method_end_time();
    }
    
    protected function record_method_start_time()
    {
        $sql = 
        "
        insert into mn_import_detail (import_id, method, start_time) 
        values ($this->import_id, '$this->current_method', now())
        ";
        
        $this->db->execute($sql);
    }
    
    protected function record_method_end_time()
    {
        $sql = 
        "
        update mn_import_detail 
        set end_time = now(),
        duration_in_seconds = unix_timestamp(now()) - unix_timestamp(start_time)
        where import_id = $this->import_id
        and method = '$this->current_method'
        ";
        
        $this->db->execute($sql);      
    }    
    
    protected function record_start_time()
    {        
        $sql = "insert into mn_imports (feed_name, start_time) values ('$this->current_feed_name', now())";                
        $this->db->execute($sql);
        
        $this->import_id = $this->db->get_insert_id();
    }
    
    protected function record_end_time()
    {
        $sql = 
        "
        update mn_imports 
        set end_time = now(), 
        duration_in_seconds = unix_timestamp(now()) - unix_timestamp(start_time) 
        where id = $this->import_id
        ";       
        
        $this->db->execute($sql);
    }    
    
    protected function clear_table($table_name) 
    {
        $sql = "delete from $table_name";                
        $this->db->execute($sql);
    }
    
    protected function import_data_by_index($index, $table_name, Array $conditions = null)
    {
        $first_node_found = false;
        $data = array();
        $i = 0;
        $invalid_nodes = 0;
        
        if(file_exists($this->current_file))
        {
            $this->xml_reader->open($this->current_file);    
        }
        else
        {
            error_log("File doesn't exist: ".$this->current_file);            
            exit;            
        }
        
        // clear the staging table we're about to import into.  
        $this->clear_table($table_name);    
        
        // a small optimization ... seek to the first index, w/out doing any of the 
        // checks that the next while loop makes.
        while($this->xml_reader->read() && $this->xml_reader->name !== $index);        
        
        // having found the first node we're looking for, start reading each node, 
        // checking after each to see if we've reached a point where we can bail.                
        while($this->xml_reader->read()) 
        {
            if($this->xml_reader->depth == 2  && $this->xml_reader->nodeType == XMLReader::ELEMENT)
            {   
                if($this->xml_reader->name == $index)
                {                    
                    $el = new SimpleXMLElement($this->xml_reader->readOuterXML());                    
                    $valid = true;
                    
                    if(!empty($conditions))
                    {
                        foreach($conditions as $attribute => $value)
                        {
                            if($el->{$attribute} != $value)
                            {
                                $valid = false;       
                                
                                if($first_node_found)
                                {
                                    $invalid_nodes++;
                                    // error_log("invalid node due to $index attribute $attribute (".$el->{$attribute}.") not being equal to $value, is now: ".$invalid_nodes);    
                                }
                                
                                break;
                            }
                        }                            
                    }
                    
                    if($valid)
                    {   
                        $first_node_found = true;            
                        $invalid_nodes = 0;
                        $data[] = $el;
                        $i++;

                        if($i % 1000 ===  0)
                        {
                            // echo 'doing batch insert into table '.$table_name.chr(10);
                            $this->batch_insert($table_name, $data);

                            // re init the array, b/c we just inserted its data, but also b/c 
                            // memory will run away from us if we don't keep it down to size.
                            // W/out this batch style insert, memory will always peg out.
                            $data = array();                        
                        }       
                    }                       
                    else if($invalid_nodes > 10000)
                    {
                        echo "Breaking to next search, too many invalid nodes found ($index)".chr(10);
                        break;
                    }
                }
                else
                {
                    // by this point we've found and inserted all the nodes we care about, 
                    // so we can bail (b/c the parsing has since found a new node, that 
                    // we care nothing about).  Otherwise, we spend tens of minutes, if not over 
                    // an hour in some cases, needlessly reading over the rest of the file.
                    if($first_node_found)
                    {
                        echo 'bailing, found node: '.$this->xml_reader->name.chr(10);
                        break;
                    }
                }                             
            }            
        }        
        
        echo 'found '.$i.' records.'.chr(10);
        
        // insert straggler rows
        if(!empty($data))
        {   
            $this->batch_insert($table_name, $data);            
        }          
    }
    
    protected function download($server, $file_name)
    {   
        echo "Downloading file: $file_name ... ";
        
        $remote_file_name = $server['file_path']."/$file_name";
        $local_file_name = "$this->base_path/daily_incremental_files/$this->current_feed_name/$file_name";
        
        $this->sftp->get($remote_file_name, $local_file_name);        
        $this->uncompress($local_file_name, str_replace('.gz', '', $local_file_name));
        echo "file downloaded and uncompressed\n\n";
    }     
    
    public function uncompress($src_file, $destination_file) 
    {
        $sfp = gzopen($src_file, "rb");
        $fp = fopen($destination_file, "w");

        while ($string = gzread($sfp, 4096)) {
            fwrite($fp, $string, strlen($string));
        }
        gzclose($sfp);
        fclose($fp);
    }    
    
    public function batch_insert($table_name, $data, $additional_columns = array())
    {
        $num_rows = count($data);        
        $values = array();
        
        $column_names = array_merge($this->get_column_names($data), $additional_columns);                

        $sql = 
        "
        insert into $table_name (".implode(',', array_keys($column_names)).")
        values 
        ";

        // this will insert in batches of 1000 rows.  Inserting 1 row at a time
        // was too slow; this speads things up by an order of magnitude.
        foreach($data as $key => $row)
        {               
            $data_values = array();
            
            foreach($column_names as $data_index)
            {
                $data_values[] = $this->db->escape($row->{$data_index});
            }            

            $values[] = "('".implode("','", $data_values)."')";            
        }   
        
        $this->db->execute($sql.implode(', ', $values));
    }  
    
    private function get_column_names($data)
    {
        // we need to get the first object in the $data array, but we can't do that 
        // by simply indexing to the first element.  The keys in the $data array are 
        // all the same value, "Genre".  So, we'll just break out of the loop after 
        // the first iteration.
        $column_keys = array();
        $column_config = array();

        foreach($data as $key => $row)
        {
            $column_keys = array_keys((array)$row);            
            break;
        }
        
        // this just lower cases the string name, and replaces hyphens with underscores.
        foreach($column_keys as $import_column_name)
        {
            $db_column_name = str_replace('-', '_', strToLower($import_column_name));
            $column_config[$db_column_name] = $import_column_name;
        }
        
        return $column_config;
    }
}
?>
