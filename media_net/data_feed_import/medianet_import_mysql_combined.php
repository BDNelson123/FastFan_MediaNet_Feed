<?php
require(__DIR__.'/data_import.php');
include $_SERVER['DOCUMENT_ROOT'].'media_net/api/api_request.php';

class MediaNet_Import extends DataImport {    
  public $current_feed_name;            
  public $files_to_import = array();
  public $last_timestamp = 0;   
  private $z;
  private $doc;

  private $ftp_servers_radio = array(
    'radio' => array(
      'host' => 'sftp-02.musicnet.com',
      'username' => 'fastfanrdff',
      'password' => 'j4MM-9!2',
      'file_path' => '/sftp_content/feeds_dmrd'
    )
  );

  private $ftp_servers_us = array(
    'us' => array(
      'host' => 'sftp-02.musicnet.com',
      'username' => 'fastfanusff',
      'password' => '44PgK8!Y',
      'file_path' => '/'
    )
  );

  private function xmlReader($currentFile){
    $this->z = new XMLReader;
    $this->z->open($currentFile);
    $this->doc = new DOMDocument; 
  }   

  private function set_new_files_to_import() {        
    $remote_files = $this->sftp->nlist();
        
    foreach($remote_files as $file_name) {
      if(stripos($file_name, 'inc') !== false && stripos($file_name, 'relnotes') === false) {                
        $file_date_portion = substr($file_name, 13, 24);
        list($month, $day, $hour, $minute, $second, $timezone, $year) = explode('-', $file_date_portion);
        $file_unix_timestamp = strtotime("$day $month $year $hour:$minute:$second");
        $d1 = date('F d, Y G:i:s', $file_unix_timestamp);                

        $sql = 
        "
        INSERT IGNORE 
	        mn_files (unix_timestamp, feed_name, file_name, file_date)
        VALUES 
	        ($file_unix_timestamp, '$this->current_feed_name', '$file_name', from_unixtime($file_unix_timestamp))
        ";

        $this->db->execute($sql);                    
      }            
    }     
        
    $sql= 
    "
    SELECT 
      * 
    FROM 
      mn_files 
    WHERE 
        imported = 0 
      AND 
        feed_name = '$this->current_feed_name' 
      AND 
        unix_timestamp > $this->last_timestamp
    ";        
    $this->files_to_import = $this->db->query($sql);                
  }
    
  private function set_new_timestamp() {
    $sql= 
    "
    SELECT 
      max(unix_timestamp) as new_timestamp 
    FROM 
      mn_files 
    WHERE 
        imported = 1 
      AND 
        feed_name = '$this->current_feed_name'
     ";        
        
     $result = $this->db->fetch($sql);
        
     if(empty($result) || empty($result['new_timestamp'])) {
       $this->last_timestamp = 0;            
     } else {            
       $this->last_timestamp = $result['new_timestamp'];
     }
   }
    
   private function download_files($server, $feed) {
     echo "Starting to download files\n";
        
     foreach($this->$feed as $feed_name => $server) {
       $this->current_feed_name = $feed_name;
       $this->set_new_timestamp();      
            
       echo "New timestamp set: $this->last_timestamp\n";
        
       $this->sftp_connect($server);
            
       echo "Connection established...\n";
            
       $this->set_new_files_to_import();
            
       if(empty($this->files_to_import)) {
         echo "No new files found for download.\n";    
       } else {
         echo count($this->files_to_import)." new files found for download.\n";    
  		
         foreach($this->files_to_import as $file) {                
           $this->download($server, $file['file_name']);               
         }       
       }             
     }
   }   
    
  private function genreImport($feed) {
    echo "Inserting into mndigital_genre_$feed - 1st of 12 methods...\n";

    $this->xmlReader($this->current_file);

    while ($this->z->read() && $this->z->name !== 'Genre');

    while ($this->z->name === 'Genre') {
      $node = simplexml_import_dom($this->doc->importNode($this->z->expand(), true));

      $Id = $node->Id;
      $Name = mysql_escape_string($node->Name);
      $GenreCategory = $node->{'Genre-Category'};
      $GenreType = $node->{'Genre-Type'};
      $CreatedDate = $node->{'Created-Date'};
      $LastUpdatedDate = $node->{'Last-Updated-Date'};

      $sql = 
      "
      INSERT IGNORE mndigital_genre_$feed
        (gid, Name, GenreCategory, GenreType, CreatedDate, LastUpdatedDate)
      values 
        ($Id, '$Name', '$GenreCategory', '$GenreType', '$CreatedDate', '$LastUpdatedDate')
      ON DUPLICATE KEY UPDATE
        Name = '$Name',
        GenreCategory = '$GenreCategory',
        GenreType = '$GenreType',
        CreatedDate = '$CreatedDate',
        LastUpdatedDate = '$LastUpdatedDate'   
      ";

      $this->db->execute($sql);                    

      $this->z->next('Genre');
    }
  }

  private function artistImport($feed) {
    echo "Inserting into mndigital_artist_$feed - 2nd of 12 methods...\n";

    $this->xmlReader($this->current_file);

    while ($this->z->read() && $this->z->name !== 'Artist');

    while ($this->z->name === 'Artist') {
      $node = simplexml_import_dom($this->doc->importNode($this->z->expand(), true));

      $Id = $node->Id;
      $AmgId = $node->{'Amg-Id'};
      $Name = mysql_escape_string($node->Name);
      $SortName = mysql_escape_string($node->{'Sort-Name'});
      $ArtistCategory = $node->{'Artist-Category'};
      $CreatedDate = $node->{'Created-Date'};
      $LastUpdatedDate = $node->{'Last-Updated-Date'};

      $sql = 
      "
      INSERT IGNORE mndigital_artist_$feed
        (artist_id, AmgId, Name, SortName, ArtistCategory, timestamp, CreatedDate, LastUpdatedDate)
      values 
        ($Id, '$AmgId', '$Name', '$SortName', '$ArtistCategory', $this->last_timestamp, '$CreatedDate', '$LastUpdatedDate')
      ON DUPLICATE KEY UPDATE
        AmgId = '$AmgId',
        Name = '$Name',
        SortName = '$SortName',
        ArtistCategory = '$ArtistCategory',
	      timestamp = $this->last_timestamp,
        CreatedDate = '$CreatedDate',
        LastUpdatedDate = '$LastUpdatedDate'   
      ";

      $this->db->execute($sql);                    

      $this->z->next('Artist');
    }
  }

  private function albumTrackImport($feed) {
    echo "Inserting into mndigital_albumtrack_$feed - 3rd of 12 methods...\n";

    $this->xmlReader($this->current_file);

    while ($this->z->read() && $this->z->name !== 'Component');

    while ($this->z->name === 'Component') {
      $node = simplexml_import_dom($this->doc->importNode($this->z->expand(), true));

      $Id = $node->Id;
      $LableId = $node->{'Label-Id'};
      $CompCode = $node->{'Comp-Code'};
      $CompTypeId = $node->{'Comp-Type-Id'};
      $ActiveStatusCode  = $node->{'Active-Status-Code'};
      $Title = mysql_escape_string($node->Title);
      $Duration = $node->Duration;
      $ParentalAdvisory = $node->{'Parental-Advisory'};
      $Isrc = $node->Isrc;
      $Upc = $node->Upc;
      $ChildItemsCount = $node->{'Child-Items-Count'};
      $ReleaseDate = $node->{'Release-Date'};
      $CoverArt = $node->{'Cover-Art'};
      $AmgId = $node->{'Amg-Id'};
      $MuzeId = $node->{'Muze-Id'};
      $Single = $node->Single;
      $ExclusiveInd = $node->{'Exclusive-Ind'};
      $SpecialCodes = $node->{'Special-Codes'};
      $CreatedDate = $node->{'Created-Date'};
      $LastUpdatedDate = $node->{'Last-Updated-Date'};

      $sql = 
      "
      INSERT IGNORE mndigital_albumtrack_$feed
        (
        atid, 
        LableId, 
        CompCode, 
        CompTypeId, 
        ActiveStatusCode, 
        Title, 
        Duration, 
        ParentalAdvisory, 
        Isrc,
        Upc, 
        ChildItemsCount, 
        ReleaseDate, 
        CoverArt, 
        AmgId,
        MuzeId,
        Single, 
        ExclusiveInd, 
        SpecialCodes, 
        timestamp,
        CreatedDate, 
        LastUpdatedDate
        )
      values 
        (
        $Id, 
        '$LableId', 
        '$CompCode', 
        '$CompTypeId', 
        '$ActiveStatusCode', 
        '$Title', 
        '$Duration', 
        '$ParentalAdvisory', 
        '$Isrc',
        '$Upc', 
        '$ChildItemsCount', 
        '$ReleaseDate', 
        '$CoverArt', 
        '$AmgId',
        '$MuzeId',
        '$Single', 
        '$ExclusiveInd', 
        '$SpecialCodes', 
        $this->last_timestamp,
        '$CreatedDate', 
        '$LastUpdatedDate'
	)
      ON DUPLICATE KEY UPDATE
        LableId = '$LableId',
        CompCode = '$CompCode',
        CompTypeId = '$CompTypeId',
        ActiveStatusCode = '$ActiveStatusCode',
        Title = '$Title',
        Duration = '$Duration',
        ParentalAdvisory = '$ParentalAdvisory',
        Isrc = '$Isrc',
        Upc = '$Upc',
        ChildItemsCount = '$ChildItemsCount',
        ReleaseDate = '$ReleaseDate',
        CoverArt = '$CoverArt',
        AmgId = '$AmgId',
        MuzeId = '$MuzeId',
        Single = '$Single',
        ExclusiveInd = '$ExclusiveInd',
        SpecialCodes = '$SpecialCodes',
	      timestamp = $this->last_timestamp,
        CreatedDate = '$CreatedDate',
        LastUpdatedDate = '$LastUpdatedDate'   
      ";
	
      $this->db->execute($sql);                    

      $this->z->next('Component');
    }
  }

  private function albumTrackUpdate($feed) {
    echo "Updating mndigital_albumtrack_$feed with artist ID - 9th of 12 methods...\n";

    $this->xmlReader($this->current_file);

    while ($this->z->read() && $this->z->name !== 'Artist-Component');

    while ($this->z->name === 'Artist-Component') {
      $node = simplexml_import_dom($this->doc->importNode($this->z->expand(), true));

      $Id = $node->Id;
      $CompId = $node->{'Comp-Id'};
      $ArtistId = $node->{'Artist-Id'};
      $ArtistTypeId = $node->{'Artist-Type-Id'};
      $Ranking = $node->Ranking;
      $CreatedDate = $node->{'Created-Date'};
      $LastUpdatedDate = $node->{'Last-Updated-Date'};

      $sql_update =
      "
      UPDATE 
        mndigital_albumtrack_$feed
      SET 
        artist_id = $ArtistId
      WHERE 
        atid = $CompId
      ";

      $this->db->execute($sql_update);                    

      $this->z->next('Artist-Component');
    }
  }

  private function labelImport($feed) {
    echo "Inserting into mndigital_label_$feed - 4th of 12 methods...\n";

    $this->xmlReader($this->current_file);

    while ($this->z->read() && $this->z->name !== 'Label');

    while ($this->z->name === 'Label') {
      $node = simplexml_import_dom($this->doc->importNode($this->z->expand(), true));

      $Id = $node->Id;
      $LabelOwnerId = $node->{'Label-Owner-Id'};
      $LabelName = $node->{'Label-Name'}; $LabelName = mysql_escape_string($LabelName);
      $ActiveStatusCode = $node->{'Active-Status-Code'};
      $CreatedDate = $node->{'Created-Date'};
      $LastUpdatedDate = $node->{'Last-Updated-Date'};

      $sql = 
      "
      INSERT IGNORE mndigital_label_$feed
        (lid, LabelOwnerId, LabelName, ActiveStatusCode, timestamp, CreatedDate, LastUpdatedDate)
      values 
        ($Id, '$LabelOwnerId', '$LabelName', '$ActiveStatusCode', $this->last_timestamp, '$CreatedDate', '$LastUpdatedDate')
      ON DUPLICATE KEY UPDATE
        LabelOwnerId = '$LabelOwnerId',
        LabelName = '$LabelName',
        ActiveStatusCode = '$ActiveStatusCode',
        timestamp = $this->last_timestamp,
        CreatedDate = '$CreatedDate',
        LastUpdatedDate = '$LastUpdatedDate'   
      ";

      $this->db->execute($sql);                    

      $this->z->next('Label');
    }
  }

  private function albumTrackGenreUpdate($feed) {
    echo "Updating mndigital_albumtrack_$feed with genre name - 6th of 12 methods...\n";

    $this->xmlReader($this->current_file);

    while ($this->z->read() && $this->z->name !== 'Metadata-Item');

    while ($this->z->name === 'Metadata-Item') {
      $node = simplexml_import_dom($this->doc->importNode($this->z->expand(), true));

      $Id = $node->Id;
      $CompId = $node->{'Comp-Id'};
      $MetadataTypeId = $node->{'Metadata-Type-Id'};
      $MetadataValue = mysql_escape_string($node->{'Metadata-Value'});
      $CreatedDate = $node->{'Created-Date'};
      $LastUpdatedDate = $node->{'Last-Updated-Date'};

      if($MetadataTypeId == '10') {
        $sql =
        "
        UPDATE 
          mndigital_albumtrack_$feed
        SET 
          Genre = '$MetadataValue'
        WHERE
          atid = $CompId
        ";

        $this->db->execute($sql);                    
      }

      $this->z->next('Metadata-Item');
    }
  }

  private function albumTrackGenreIdUpdate($feed) {
    echo "Updating mndigital_albumtrack_$feed with genre ID - 8th of 12 methods...\n";

    $sql= 
    "
    SELECT 
      atid
    FROM 
      mndigital_albumtrack_$feed
    WHERE 
      GenreId = 0
    ";        
        
    $result = $this->db->fetch_all($sql);
      
    foreach($result as $result) {
      $atid = $result['atid'];

      $sql2 =
      "
      UPDATE 
        mndigital_albumtrack_$feed mnatus
      INNER JOIN 
        mndigital_genre_$feed mngus 
      ON 
        mngus.Name = SUBSTRING_INDEX(mnatus.Genre,':',1)
      SET 
        mnatus.GenreId = mngus.gid 
      WHERE 
        mnatus.atid = $atid
      ";

      $this->db->execute($sql2);                    

      $sql3 =
      "
      UPDATE 
        mndigital_albumtrack_$feed mnatus 
      INNER JOIN 
        mndigital_genre_$feed mngus 
      ON 
        mngus.Name = SUBSTRING_INDEX(mnatus.Genre,':',-1) 
      SET 
        mnatus.SubGenreId = mngus.gid 
      WHERE 
        INSTR(mnatus.Genre, ':') > 0
        AND
        mnatus.atid = $atid
      ";

      $this->db->execute($sql3);                    
    }  
  }

  private function albumTrackAidUpdate($feed) {
    echo "Updating mndigital_albumtrack_$feed with album ID - 7th of 12 methods...\n";

    $this->xmlReader($this->current_file);

    while ($this->z->read() && $this->z->name !== 'Component-Parent');

    while ($this->z->name === 'Component-Parent') {
      $node = simplexml_import_dom($this->doc->importNode($this->z->expand(), true));

      $ParentCompId = $node->{'Parent-Comp-Id'};
      $ChildCompId = $node->{'Child-Comp-Id'};

      $sql =
      "
      UPDATE 
        mndigital_albumtrack_$feed
      SET 
        album_id = $ParentCompId
      WHERE
        CompTypeId = 3
        AND
        atid = $ChildCompId
      ";

      $this->db->execute($sql);                    

      $this->z->next('Component-Parent');
    }
  }

  private function rankingUpdate($feed) {
    echo "Updating mndigital_artist_$feed with popularity ranking - 5th of 12 methods...\n";

    $api_request = new MN_Api_Request;
    $r = $api_request->get_artists();
    $ResultsReturned = $r->ResultsReturned;
    $TotalResults = $r->TotalResults;
    $TotalQueries = ceil($TotalResults / $ResultsReturned);

    $sql =
    "
    UPDATE 
      mndigital_artist_$feed
    SET
      PopularityRanking = NULL
    ";

    $this->db->execute($sql);

    for ($i=1; $i<=80; $i++) {
      $q = $api_request->get_artists(
        array(
          'page' => $i,
          'pageSize' => 250
        )
      );

      $rows = $q->Artists;

      foreach ($rows as $Artist) {
        $ID = $Artist->MnetId;
        $Popularity = $Artist->PopularityRanking;

        $sql2 = 
        "
        UPDATE 
          mndigital_artist_$feed
        SET 
          PopularityRanking = $Popularity
        WHERE 
          artist_id = $ID
        ";

        $this->db->execute($sql2);                    
      }
    }
  }

  // ----------------------- //
  // ----------------------- //
  // ----------------------- //

  private function current_album() {
    echo "Inserting into albums table - 10th of 12 methods ...\n";

      $sql = 
      "
      INSERT IGNORE albums 
      (
        album_id, 
        artist_id, 
        album_name,
        artist_name, 
        album_advisory,
        releaseDate
      )
      SELECT 
	mnatus.atid, 
	mnatus.artist_id, 
	mnatus.title,
	mnaus.Name,
        mnatus.ParentalAdvisory,
	mnatus.ReleaseDate
      FROM 
        mndigital_albumtrack_us mnatus
      INNER JOIN 
	mndigital_artist_us mnaus on mnatus.artist_id = mnaus.artist_id
      WHERE
	mnatus.CompTypeId = 2
	AND
	mnatus.timestamp = $this->last_timestamp
      ON DUPLICATE KEY UPDATE 
	artist_id = mnatus.artist_id,
	album_name = mnatus.title,
	artist_name = mnaus.Name,
        album_advisory = mnatus.ParentalAdvisory,
	releaseDate = mnatus.ReleaseDate,
        elasticsearch_index = 0
      ";                
        
      $this->db->execute($sql);  
  }

  private function current_artist() {
    echo "Inserting into artists table - 11th of 12 methods ...\n";

    $sql = 
    "
    INSERT IGNORE artists
    (
      artist_id, 
      artist_name,
      ranking,
      highestRanking
    )
    SELECT 
      mnaus.artist_id,
      mnaus.Name, 
      mnaus.PopularityRanking,
      mnaus.PopularityRanking
    FROM 
      mndigital_artist_us mnaus
    WHERE
      mnaus.timestamp = $this->last_timestamp
    ON DUPLICATE KEY UPDATE 
      artist_name = mnaus.Name,
      ranking = mnaus.PopularityRanking,
      highestRanking = IF((highestRanking < mnaus.PopularityRanking), highestRanking, mnaus.PopularityRanking),
      elasticsearch_index = 0
    ";                
        
    $this->db->execute($sql);  
  }

  private function current_track() {
      echo "Inserting into tracks table - 12th of 12 methods ...\n";

      $sql = 
      "
      INSERT IGNORE tracks
      (
        track_id, 
        album_id,
        artist_id,
        track_name,
        album_number,
        track_number,
        track_advisory,
        track_time
      )
      SELECT 
        mnatus.atid, 
        mnatus.album_id,
        mnatus.artist_id,
        mnatus.Title,
        CASE SPLIT_STR(mnatus.CompCode, '_', 2)
          WHEN 01 THEN 1
          WHEN 02 THEN 2
          WHEN 03 THEN 3
          WHEN 04 THEN 4
          WHEN 05 THEN 5
          WHEN 06 THEN 6
          WHEN 07 THEN 7
          WHEN 08 THEN 8
          WHEN 09 THEN 9
        ELSE
          SPLIT_STR(CompCode, '_', 2)
        END,
        CASE SPLIT_STR(mnatus.CompCode, '_', 3)
          WHEN 01 THEN 1
          WHEN 02 THEN 2
          WHEN 03 THEN 3
          WHEN 04 THEN 4
          WHEN 05 THEN 5
          WHEN 06 THEN 6
          WHEN 07 THEN 7
          WHEN 08 THEN 8
          WHEN 09 THEN 9
        ELSE
          SPLIT_STR(CompCode, '_', 3)
        END,
        mnatus.ParentalAdvisory,
        mnatus.Duration
      FROM 
        mndigital_albumtrack_us mnatus
      WHERE
        mnatus.CompTypeId = 3
      AND
        mnatus.timestamp = $this->last_timestamp
      ON DUPLICATE KEY UPDATE 
        track_id = mnatus.atid,
        album_id = mnatus.album_id,
        artist_id = mnatus.artist_id,
        track_name = mnatus.Title,
        album_number = 
        CASE SPLIT_STR(mnatus.CompCode, '_', 2)
          WHEN 01 THEN 1
          WHEN 02 THEN 2
          WHEN 03 THEN 3
          WHEN 04 THEN 4
          WHEN 05 THEN 5
          WHEN 06 THEN 6
          WHEN 07 THEN 7
          WHEN 08 THEN 8
          WHEN 09 THEN 9
        ELSE
          SPLIT_STR(mnatus.CompCode, '_', 2)
        END,
        track_number =
        CASE SPLIT_STR(mnatus.CompCode, '_', 3)
          WHEN 01 THEN 1
          WHEN 02 THEN 2
          WHEN 03 THEN 3
          WHEN 04 THEN 4
          WHEN 05 THEN 5
          WHEN 06 THEN 6
          WHEN 07 THEN 7
          WHEN 08 THEN 8
          WHEN 09 THEN 9
        ELSE
          SPLIT_STR(CompCode, '_', 3)
        END,
        track_time = mnatus.Duration,
        track_advisory = mnatus.ParentalAdvisory,
        elasticsearch_index = 0
      ";                
        
      $this->db->execute($sql);

      $sql2 = 
      "
      INSERT IGNORE tracks
      (
        track_id, 
        artist_name
      )
      SELECT 
        mnatus.atid, 
        mnaus.Name
      FROM 
        mndigital_albumtrack_us mnatus
      INNER JOIN 
	      mndigital_artist_us mnaus on mnatus.artist_id = mnaus.artist_id
      WHERE
        mnatus.CompTypeId = 3
      AND
        mnatus.timestamp = $this->last_timestamp
      ON DUPLICATE KEY UPDATE 
        track_id = mnatus.atid,
        artist_name = mnaus.Name
      ";

      $this->db->execute($sql2);

      $sql3 = 
      "
      INSERT IGNORE tracks
      (
        track_id, 
        album_name
      )
      SELECT 
        mnatus.atid, 
        album.album_name
      FROM 
        mndigital_albumtrack_us mnatus
      INNER JOIN 
	      albums album on mnatus.album_id = album.album_id
      WHERE
        mnatus.CompTypeId = 3
      AND
        mnatus.timestamp = $this->last_timestamp
      ON DUPLICATE KEY UPDATE 
        track_id = mnatus.atid,
        album_name = album.album_name
      ";

      $this->db->execute($sql3);
  }  

  // ----------------------- //
  // ----------------------- //
  // ----------------------- //

  public function do_import($full_feed = false, $feed) {
    $this->db = new Database($full_feed ? 'fastfan_full_feeds' : null);            

    if(!$full_feed) {
      foreach($this->$feed as $feed_name => $server) {
        $this->current_feed_name = $feed_name;
        $this->download_files($server, $feed); 

        foreach($this->files_to_import as $file) {
          $this->current_file = str_replace('.gz', '', $this->base_path.'/daily_incremental_files/'.$this->current_feed_name.'/'.$file['file_name']);
          $this->record_start_time();
          $this->set_new_timestamp();

          $this->genreimport($this->current_feed_name);
          $this->artistimport($this->current_feed_name);
          $this->albumtrackimport($this->current_feed_name);
          $this->labelimport($this->current_feed_name);
	        $this->rankingupdate($this->current_feed_name);
          $this->albumTrackGenreUpdate($this->current_feed_name);
	        $this->albumTrackAidUpdate($this->current_feed_name);
          $this->albumTrackGenreIdUpdate($this->current_feed_name);
          $this->albumTrackUpdate($this->current_feed_name);

	        // These update working tables such as albums, artists, and tracks
	        if($this->current_feed_name == 'us') {
	          $this->current_album();
	          $this->current_artist();
	          $this->current_track();
	        }

          $this->db->update('mn_files', array('imported' => 1), array(
            'unix_timestamp' => $file['unix_timestamp'], 'feed_name' => $this->current_feed_name
          ));

          $this->record_end_time();                

          unlink('daily_incremental_files/'.$this->current_feed_name.'/'.$file['file_name']);
        }                 
      }            
    }
  }
}

$import = new MediaNet_Import;
$import->do_import(!empty($argv[1]) && $argv[1] == 'full', 'ftp_servers_us');
?>
