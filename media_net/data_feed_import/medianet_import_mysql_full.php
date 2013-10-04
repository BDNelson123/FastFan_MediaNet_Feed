<?php
require(__DIR__.'/data_import.php');
require($_SERVER['DOCUMENT_ROOT'].'media_net/api/api_request.php');

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
        (bid, AmgId, Name, SortName, ArtistCategory, CreatedDate, LastUpdatedDate)
      values 
        ($Id, '$AmgId', '$Name', '$SortName', '$ArtistCategory', '$CreatedDate', '$LastUpdatedDate')
      ON DUPLICATE KEY UPDATE
        AmgId = '$AmgId',
        Name = '$Name',
        SortName = '$SortName',
        ArtistCategory = '$ArtistCategory',
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
        bid = $ArtistId
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
        (lid, LabelOwnerId, LabelName, ActiveStatusCode, CreatedDate, LastUpdatedDate)
      values 
        ($Id, '$LabelOwnerId', '$LabelName', '$ActiveStatusCode', '$CreatedDate', '$LastUpdatedDate')
      ON DUPLICATE KEY UPDATE
	LabelOwnerId = '$LabelOwnerId',
        LabelName = '$LabelName',
        ActiveStatusCode = '$ActiveStatusCode',
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

    $sql =
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
      INSTR(mnatus.Genre, ':') < 1 
      AND
      mnatus.GenreId = 0
    ";

    $this->db->execute($sql);                    

    $sql2 =
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
      mnatus.SubGenreId = 0
    ";

    $this->db->execute($sql2);                    

    $sql3 =
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
      INSTR(mnatus.Genre, ':') > 0
      AND
      mnatus.GenreId = 0
    ";

    $this->db->execute($sql3);
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
        aid = $ParentCompId
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

    for ($i=1; $i<=25; $i++) {
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
          bid = $ID
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
	aid, 
	bid, 
	album,
	artist,
	releaseDate 
	)
      SELECT 
	mnatus.atid, 
	mnatus.bid, 
	mnatus.title,
	mnaus.Name,
	mnatus.ReleaseDate
      FROM 
        mndigital_albumtrack_us mnatus
      INNER JOIN 
	mndigital_artist_us mnaus on mnatus.bid = mnaus.bid
      WHERE
	mnatus.CompTypeId = 2
      ON DUPLICATE KEY UPDATE 
	bid = mnatus.bid,
	album = mnatus.title,
	artist = mnaus.Name,
	releaseDate = mnatus.ReleaseDate
      ";                
        
      $this->db->execute($sql);  
  }

  private function current_artist() {
    echo "Inserting into artists table - 11th of 12 methods ...\n";

    $sql = 
    "
    INSERT IGNORE artists
    (
      bid, 
      artist,
      ranking,
      highestRanking
    )
    SELECT 
      mnaus.bid,
      mnaus.Name, 
      mnaus.PopularityRanking,
      mnaus.PopularityRanking
    FROM 
      mndigital_artist_us mnaus
    ON DUPLICATE KEY UPDATE 
      artist = mnaus.Name,
      ranking = mnaus.PopularityRanking,
      highestRanking = IF((highestRanking < mnaus.PopularityRanking), highestRanking, mnaus.PopularityRanking)
    ";                
        
    $this->db->execute($sql);  
  }

  private function current_track() {
      echo "Inserting into tracks table - 12th of 12 methods ...\n";

      $sql = 
      "
      INSERT IGNORE tracks
	(
	tid, 
	aid,
	bid,
	trackname,
	genre
	)
      SELECT 
	mnatus.atid, 
	mnatus.aid,
	mnatus.bid,
	mnatus.Title,
	CASE mnatus.GenreId
	  WHEN 1 THEN 1					-- Alternative/Indie
	  WHEN 2 THEN 2					-- Blues
	  WHEN 3 THEN 3					-- Classical/Opera
	  WHEN 22 THEN 3				
	  WHEN mnatus.SubGenreId = 1024 THEN 3
	  WHEN mnatus.SubGenreId = 1025 THEN 3
	  WHEN mnatus.SubGenreId = 1013 THEN 3
	  WHEN mnatus.SubGenreId = 1016 THEN 3
	  WHEN mnatus.SubGenreId = 1017 THEN 3
	  WHEN mnatus.SubGenreId = 1022 THEN 3
	  WHEN mnatus.SubGenreId = 1039 THEN 3
 	  WHEN mnatus.SubGenreId = 1040 THEN 3
	  WHEN mnatus.SubGenreId = 1094 THEN 3
	  WHEN mnatus.SubGenreId = 1090 THEN 3
	  WHEN mnatus.SubGenreId = 1020 THEN 3
 	  WHEN mnatus.SubGenreId = 1073 THEN 3
	  WHEN 4 THEN 4					-- Country
	  WHEN 5 THEN 5					-- Electronica/Dance
	  WHEN mnatus.SubGenreId = 1081 THEN 5
	  WHEN 6 THEN 6					-- Folk
	  WHEN 7 THEN 7					-- Christian/Gospel
	  WHEN mnatus.SubGenreId = 1028 THEN 7	
	  WHEN mnatus.SubGenreId = 1049 THEN 7
	  WHEN mnatus.SubGenreId = 1072 THEN 7
	  WHEN mnatus.SubGenreId = 1099 THEN 7
	  WHEN mnatus.SubGenreId = 1098 THEN 7
	  WHEN mnatus.SubGenreId = 1100 THEN 7
	  WHEN 8 THEN 8					-- Jazz
	  WHEN mnatus.SubGenreId = 1079 THEN 8
	  WHEN mnatus.SubGenreId = 1015 THEN 8
	  WHEN 9 THEN 9					-- New Age
	  WHEN 10 THEN 10				-- Pop
	  WHEN mnatus.SubGenreId = 3862 THEN 10
	  WHEN mnatus.SubGenreId = 1067 THEN 10
	  WHEN mnatus.SubGenreId = 1032 THEN 10
	  WHEN 11 THEN 11				-- Rap/Hip Hop
	  WHEN 12 THEN 12				-- Rock
	  WHEN mnatus.SubGenreId = 1068 THEN 12
	  WHEN mnatus.SubGenreId = 1060 THEN 12
	  WHEN mnatus.SubGenreId = 1002 THEN 12
	  WHEN mnatus.SubGenreId = 1003 THEN 12
	  WHEN mnatus.SubGenreId = 1004 THEN 12
	  WHEN mnatus.SubGenreId = 1005 THEN 12
	  WHEN mnatus.SubGenreId = 1006	THEN 12
	  WHEN mnatus.SubGenreId = 1085 THEN 12
	  WHEN mnatus.SubGenreId = 1086 THEN 12
	  WHEN mnatus.SubGenreId = 1047 THEN 12
	  WHEN mnatus.SubGenreId = 1007 THEN 12
	  WHEN mnatus.SubGenreId = 1075 THEN 12
	  WHEN 13 THEN 13				-- Soul/R&B
	  WHEN mnatus.SubGenreId = 1030 THEN 13
	  WHEN 14 THEN 14				-- Soundtracks
	  WHEN mnatus.SubGenreId = 1043 THEN 14
	  WHEN mnatus.SubGenreId = 1095 THEN 14
	  WHEN mnatus.SubGenreId = 1008 THEN 14
	  WHEN mnatus.SubGenreId = 1009 THEN 14
	  WHEN mnatus.SubGenreId = 1027 THEN 14
	  WHEN mnatus.SubGenreId = 1083 THEN 14
	  WHEN mnatus.SubGenreId = 1078 THEN 14
	  WHEN 20 THEN 14
	  WHEN 15 THEN 17				-- Wolrd
	  WHEN 16 THEN 15				-- Reggae/Ska
	  WHEN 17 THEN 16				-- Latin
	  WHEN mnatus.SubGenreId = 1029 THEN 16
	  WHEN mnatus.SubGenreId = 1050 THEN 16
	  WHEN mnatus.SubGenreId = 1051 THEN 16
	  WHEN mnatus.SubGenreId = 1052 THEN 16
	  WHEN mnatus.SubGenreId = 1053 THEN 16
	  WHEN mnatus.SubGenreId = 1054 THEN 16
	  WHEN mnatus.SubGenreId = 1055 THEN 16
	  WHEN mnatus.SubGenreId = 1058 THEN 16
	  WHEN mnatus.SubGenreId = 1059 THEN 16
	  WHEN mnatus.SubGenreId = 1069 THEN 16
	  WHEN mnatus.SubGenreId = 1070 THEN 16
	  WHEN mnatus.SubGenreId = 1071 THEN 16
	  WHEN mnatus.SubGenreId = 1076 THEN 16
	  WHEN mnatus.SubGenreId = 1077 THEN 16
	  WHEN mnatus.SubGenreId = 1048 THEN 16
	  WHEN mnatus.SubGenreId = 1080 THEN 16
	  WHEN mnatus.SubGenreId = 1082 THEN 16
	  WHEN mnatus.SubGenreId = 1011 THEN 16
	  WHEN 18 THEN 17				-- Other
	  WHEN 19 THEN 17
	  WHEN 21 THEN 17
	  WHEN 23 THEN 17
	  WHEN 25 THEN 17
	  WHEN mnatus.SubGenreId = 1010 THEN 17
	  WHEN mnatus.SubGenreId = 1012 THEN 17
	  WHEN mnatus.SubGenreId = 1014 THEN 17
	  WHEN mnatus.SubGenreId = 1018 THEN 17
	  WHEN mnatus.SubGenreId = 1019 THEN 17
	  WHEN mnatus.SubGenreId = 1021 THEN 17
	  WHEN mnatus.SubGenreId = 1023 THEN 17
	  WHEN mnatus.SubGenreId = 1031 THEN 17
	  WHEN mnatus.SubGenreId = 1033 THEN 17
	  WHEN mnatus.SubGenreId = 1034 THEN 17
	  WHEN mnatus.SubGenreId = 1035 THEN 17
	  WHEN mnatus.SubGenreId = 1036 THEN 17
	  WHEN mnatus.SubGenreId = 1037 THEN 17
	  WHEN mnatus.SubGenreId = 1038 THEN 17
	  WHEN mnatus.SubGenreId = 1041 THEN 17
	  WHEN mnatus.SubGenreId = 1042 THEN 17
	  WHEN mnatus.SubGenreId = 1044 THEN 17
	  WHEN mnatus.SubGenreId = 1045 THEN 17
	  WHEN mnatus.SubGenreId = 1046 THEN 17
	  WHEN mnatus.SubGenreId = 1056 THEN 17
	  WHEN mnatus.SubGenreId = 1057 THEN 17
	  WHEN mnatus.SubGenreId = 1061 THEN 17
	  WHEN mnatus.SubGenreId = 1062 THEN 17
	  WHEN mnatus.SubGenreId = 1064 THEN 17
	  WHEN mnatus.SubGenreId = 1065 THEN 17
	  WHEN mnatus.SubGenreId = 1066 THEN 17
	  WHEN mnatus.SubGenreId = 1074 THEN 17
	  WHEN mnatus.SubGenreId = 1084 THEN 17
	  WHEN mnatus.SubGenreId = 1087 THEN 17
	  WHEN mnatus.SubGenreId = 1088 THEN 17
	  WHEN mnatus.SubGenreId = 1089 THEN 17
	  WHEN mnatus.SubGenreId = 1091 THEN 17
	  WHEN mnatus.SubGenreId = 1092 THEN 17
	  WHEN mnatus.SubGenreId = 1093 THEN 17
	  WHEN mnatus.SubGenreId = 1096 THEN 17
	  WHEN mnatus.SubGenreId = 1097 THEN 17
	  WHEN mnatus.SubGenreId = 1101 THEN 17
	  WHEN mnatus.SubGenreId = 1102 THEN 17
	  WHEN mnatus.SubGenreId = 1103 THEN 17
	  WHEN mnatus.SubGenreId = 1104 THEN 17
	  WHEN mnatus.SubGenreId = 1105 THEN 17
	  WHEN mnatus.SubGenreId = 1106 THEN 17
	  WHEN mnatus.SubGenreId = 1107 THEN 17
	  WHEN mnatus.SubGenreId = 1108 THEN 17
	  WHEN mnatus.SubGenreId = 3863 THEN 17
	ELSE
	  17
	END
      FROM 
        mndigital_albumtrack_us mnatus
      WHERE
	mnatus.CompTypeId = 3
      ON DUPLICATE KEY UPDATE 
	tid = mnatus.atid,
	aid = mnatus.aid,
	bid = mnatus.bid,
	trackname = mnatus.Title,
	genre = 
	CASE
	  WHEN mnatus.GenreId = 1 THEN 1		-- Alternative/Indie
	  WHEN mnatus.GenreId = 2 THEN 2		-- Blues
	  WHEN mnatus.GenreId = 3 THEN 3		-- Classical/Opera
	  WHEN mnatus.GenreId = 22 THEN 3				
	  WHEN mnatus.SubGenreId = 1024 THEN 3
	  WHEN mnatus.SubGenreId = 1025 THEN 3
	  WHEN mnatus.SubGenreId = 1013 THEN 3
	  WHEN mnatus.SubGenreId = 1016 THEN 3
	  WHEN mnatus.SubGenreId = 1017 THEN 3
	  WHEN mnatus.SubGenreId = 1022 THEN 3
	  WHEN mnatus.SubGenreId = 1039 THEN 3
 	  WHEN mnatus.SubGenreId = 1040 THEN 3
	  WHEN mnatus.SubGenreId = 1094 THEN 3
	  WHEN mnatus.SubGenreId = 1090 THEN 3
	  WHEN mnatus.SubGenreId = 1020 THEN 3
 	  WHEN mnatus.SubGenreId = 1073 THEN 3
	  WHEN mnatus.GenreId = 4 THEN 4		-- Country
	  WHEN mnatus.GenreId = 5 THEN 5		-- Electronica/Dance
	  WHEN mnatus.SubGenreId = 1081 THEN 5
	  WHEN mnatus.GenreId = 6 THEN 6		-- Folk
	  WHEN mnatus.GenreId = 7 THEN 7		-- Christian/Gospel
	  WHEN mnatus.SubGenreId = 1028 THEN 7	
	  WHEN mnatus.SubGenreId = 1049 THEN 7
	  WHEN mnatus.SubGenreId = 1072 THEN 7
	  WHEN mnatus.SubGenreId = 1099 THEN 7
	  WHEN mnatus.SubGenreId = 1098 THEN 7
	  WHEN mnatus.SubGenreId = 1100 THEN 7
	  WHEN mnatus.GenreId = 8 THEN 8		-- Jazz
	  WHEN mnatus.SubGenreId = 1079 THEN 8
	  WHEN mnatus.SubGenreId = 1015 THEN 8
	  WHEN mnatus.GenreId = 9 THEN 9		-- New Age
	  WHEN mnatus.GenreId = 10 THEN 10		-- Pop
	  WHEN mnatus.SubGenreId = 3862 THEN 10
	  WHEN mnatus.SubGenreId = 1067 THEN 10
	  WHEN mnatus.SubGenreId = 1032 THEN 10
	  WHEN mnatus.GenreId = 11 THEN 11		-- Rap/Hip Hop
	  WHEN mnatus.GenreId = 12 THEN 12		-- Rock
	  WHEN mnatus.SubGenreId = 1068 THEN 12
	  WHEN mnatus.SubGenreId = 1060 THEN 12
	  WHEN mnatus.SubGenreId = 1002 THEN 12
	  WHEN mnatus.SubGenreId = 1003 THEN 12
	  WHEN mnatus.SubGenreId = 1004 THEN 12
	  WHEN mnatus.SubGenreId = 1005 THEN 12
	  WHEN mnatus.SubGenreId = 1006	THEN 12
	  WHEN mnatus.SubGenreId = 1085 THEN 12
	  WHEN mnatus.SubGenreId = 1086 THEN 12
	  WHEN mnatus.SubGenreId = 1047 THEN 12
	  WHEN mnatus.SubGenreId = 1007 THEN 12
	  WHEN mnatus.SubGenreId = 1075 THEN 12
	  WHEN mnatus.GenreId = 13 THEN 13		-- Soul/R&B
	  WHEN mnatus.SubGenreId = 1030 THEN 13
	  WHEN mnatus.GenreId = 14 THEN 14		-- Soundtracks
	  WHEN mnatus.SubGenreId = 1043 THEN 14
	  WHEN mnatus.SubGenreId = 1095 THEN 14
	  WHEN mnatus.SubGenreId = 1008 THEN 14
	  WHEN mnatus.SubGenreId = 1009 THEN 14
	  WHEN mnatus.SubGenreId = 1027 THEN 14
	  WHEN mnatus.SubGenreId = 1083 THEN 14
	  WHEN mnatus.SubGenreId = 1078 THEN 14
	  WHEN mnatus.GenreId = 20 THEN 14
	  WHEN mnatus.GenreId = 15 THEN 17		-- Wolrd
	  WHEN mnatus.GenreId = 16 THEN 15		-- Reggae/Ska
	  WHEN mnatus.GenreId = 17 THEN 16		-- Latin
	  WHEN mnatus.SubGenreId = 1029 THEN 16
	  WHEN mnatus.SubGenreId = 1050 THEN 16
	  WHEN mnatus.SubGenreId = 1051 THEN 16
	  WHEN mnatus.SubGenreId = 1052 THEN 16
	  WHEN mnatus.SubGenreId = 1053 THEN 16
	  WHEN mnatus.SubGenreId = 1054 THEN 16
	  WHEN mnatus.SubGenreId = 1055 THEN 16
	  WHEN mnatus.SubGenreId = 1058 THEN 16
	  WHEN mnatus.SubGenreId = 1059 THEN 16
	  WHEN mnatus.SubGenreId = 1069 THEN 16
	  WHEN mnatus.SubGenreId = 1070 THEN 16
	  WHEN mnatus.SubGenreId = 1071 THEN 16
	  WHEN mnatus.SubGenreId = 1076 THEN 16
	  WHEN mnatus.SubGenreId = 1077 THEN 16
	  WHEN mnatus.SubGenreId = 1048 THEN 16
	  WHEN mnatus.SubGenreId = 1080 THEN 16
	  WHEN mnatus.SubGenreId = 1082 THEN 16
	  WHEN mnatus.SubGenreId = 1011 THEN 16
	  WHEN mnatus.GenreId = 18 THEN 17		-- Other
	  WHEN mnatus.GenreId = 19 THEN 17
	  WHEN mnatus.GenreId = 21 THEN 17
	  WHEN mnatus.GenreId = 23 THEN 17
	  WHEN mnatus.GenreId = 25 THEN 17
	  WHEN mnatus.SubGenreId = 1010 THEN 17
	  WHEN mnatus.SubGenreId = 1012 THEN 17
	  WHEN mnatus.SubGenreId = 1014 THEN 17
	  WHEN mnatus.SubGenreId = 1018 THEN 17
	  WHEN mnatus.SubGenreId = 1019 THEN 17
	  WHEN mnatus.SubGenreId = 1021 THEN 17
	  WHEN mnatus.SubGenreId = 1023 THEN 17
	  WHEN mnatus.SubGenreId = 1031 THEN 17
	  WHEN mnatus.SubGenreId = 1033 THEN 17
	  WHEN mnatus.SubGenreId = 1034 THEN 17
	  WHEN mnatus.SubGenreId = 1035 THEN 17
	  WHEN mnatus.SubGenreId = 1036 THEN 17
	  WHEN mnatus.SubGenreId = 1037 THEN 17
	  WHEN mnatus.SubGenreId = 1038 THEN 17
	  WHEN mnatus.SubGenreId = 1041 THEN 17
	  WHEN mnatus.SubGenreId = 1042 THEN 17
	  WHEN mnatus.SubGenreId = 1044 THEN 17
	  WHEN mnatus.SubGenreId = 1045 THEN 17
	  WHEN mnatus.SubGenreId = 1046 THEN 17
	  WHEN mnatus.SubGenreId = 1056 THEN 17
	  WHEN mnatus.SubGenreId = 1057 THEN 17
	  WHEN mnatus.SubGenreId = 1061 THEN 17
	  WHEN mnatus.SubGenreId = 1062 THEN 17
	  WHEN mnatus.SubGenreId = 1064 THEN 17
	  WHEN mnatus.SubGenreId = 1065 THEN 17
	  WHEN mnatus.SubGenreId = 1066 THEN 17
	  WHEN mnatus.SubGenreId = 1074 THEN 17
	  WHEN mnatus.SubGenreId = 1084 THEN 17
	  WHEN mnatus.SubGenreId = 1087 THEN 17
	  WHEN mnatus.SubGenreId = 1088 THEN 17
	  WHEN mnatus.SubGenreId = 1089 THEN 17
	  WHEN mnatus.SubGenreId = 1091 THEN 17
	  WHEN mnatus.SubGenreId = 1092 THEN 17
	  WHEN mnatus.SubGenreId = 1093 THEN 17
	  WHEN mnatus.SubGenreId = 1096 THEN 17
	  WHEN mnatus.SubGenreId = 1097 THEN 17
	  WHEN mnatus.SubGenreId = 1101 THEN 17
	  WHEN mnatus.SubGenreId = 1102 THEN 17
	  WHEN mnatus.SubGenreId = 1103 THEN 17
	  WHEN mnatus.SubGenreId = 1104 THEN 17
	  WHEN mnatus.SubGenreId = 1105 THEN 17
	  WHEN mnatus.SubGenreId = 1106 THEN 17
	  WHEN mnatus.SubGenreId = 1107 THEN 17
	  WHEN mnatus.SubGenreId = 1108 THEN 17
	  WHEN mnatus.SubGenreId = 3863 THEN 17
	ELSE
	  17
	END
      ";                
        
      $this->db->execute($sql);
  }  

  // ----------------------- //
  // ----------------------- //
  // ----------------------- //

  public function do_import($full_feed = false, $feed) {
    $this->db = new Database($full_feed ? 'fastfan_full_feeds' : null);            

    // This is the full feed import for radio
    $this->current_file = 'media_net/data_feed_import/daily_incremental_files/us/DMUS-FULL-Fri-Sep-13-07-19-48-PST-2013-A-MV-C-1593.xml'; 
    $this->current_feed_name = 'us';     

    // $this->genreimport($this->current_feed_name);
    // $this->artistimport($this->current_feed_name);
    // $this->albumtrackimport($this->current_feed_name);
    // $this->labelimport($this->current_feed_name);
    $this->rankingupdate($this->current_feed_name);
    $this->albumTrackGenreUpdate($this->current_feed_name);
    $this->albumTrackAidUpdate($this->current_feed_name);
    $this->albumTrackGenreIdUpdate($this->current_feed_name);
    $this->albumTrackUpdate($this->current_feed_name);
    $this->current_album();
    $this->current_artist();
    $this->current_track();
  }
}

$import = new MediaNet_Import;
$import->do_import(!empty($argv[1]) && $argv[1] == 'full', 'ftp_servers_us');
?>
