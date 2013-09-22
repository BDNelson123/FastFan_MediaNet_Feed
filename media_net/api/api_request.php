<?
define('MN_API_KEY', '2hC3uq71bStW02VGUFOa9cup4');
define('MN_SHARED_SECRET', '5ajNzAdCZHb');    

class MN_Api_Request {    
    
    public function __construct()
    {

    }

    public function get_track($mnetId)
    {
        $p = array(
            'method' => 'track.get',
            'mnetId' => $mnetId
        );
        
        return $this->send($p);        
    }

    public function get_tracks($params)
    {
        $p = array(
            'method' => 'search.gettracks',                
            'title' => empty($params['title']) ? '' : $params['title'],
            'page' => empty($params['page']) ? 1 : $params['page'],
            'pageSize' => empty($params['pageSize']) ? 20 : $params['pageSize'],
            'albumMnetId' => empty($params['albumMnetId']) ? null : $params['albumMnetId'],            
            'genre' => empty($params['genre']) ? null : $params['genre']
        );
        
        return $this->send($p);        
    }  

    public function get_track_locations($params)
    {
        $p = array(
            'method' => 'track.getlocations',            
            'mnetIds' => implode(',', $params['mnetIds'])            
        );
        
        return $this->send($p);        
    }       

    public function get_artists($params)
    {
        $p = array(
            'method' => 'search.getartists',                
            'name' => empty($params['name']) ? '' : $params['name'],
            'page' => empty($params['page']) ? 1 : $params['page'],
            'pageSize' => empty($params['pageSize']) ? 20 : $params['pageSize'],
            'genre' => empty($params['genre']) ? null : $params['genre']
        );
        
        return $this->send($p);        
    }

    public function get_albums($params)
    {
        $p = array(
            'method' => 'search.getalbums',                
            'title' => empty($params['title']) ? '' : $params['title'],
            'rights' => 'purchase',
            'includeExplicit' => 'true',
            'page' => empty($params['page']) ? 1 : $params['page'],
            'pageSize' => empty($params['pageSize']) ? 20 : $params['pageSize'],
            'mainArtistOnly' => 'true',
            'genre' => empty($params['genre']) ? null : $params['genre'],
            'artistMnetId' => empty($params['artistMnetId']) ? null : $params['artistMnetId']
        );
        
        return $this->send($p);        
    }

    public function get_genres(&$db, $gids)
    {        
        $genres = array();

        $query = "select gid, provider_name from genres where gid in (" . validateGids( $gids ) . ")";
        $db->query($query);            

        while ($row = $db->fetch_assoc()) {            
            $genres[] = $row['provider_name'];
        }       

        return $genres;
    }    

    public function send(Array $params = array())
    {
        $params['format'] = 'json';           

        $url = 'http://ie-api.mndigital.com?';
        $theQuery = http_build_query($params).'&apiKey='.MN_API_KEY;

        $url.= $theQuery;

        if(!empty($params['include_signature']))
        {
            $signature = hash_hmac('md5', $theQuery, MN_SHARED_SECRET);
            $url.= '&signature='.$signature;
        }                

        // This is standard curl call of PHP
        $curl = curl_init();        
        curl_setopt ($curl, CURLOPT_URL, $url);        
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($curl);        

        $decodedResponse = json_decode($response);        

        if(empty($decodedResponse))
        {

            error_log('Error for url: '.$url);

            $response_error = curl_error($curl);
            error_log('decoded response error:  '.print_R($response_error, true));

            $response_info = curl_getinfo($curl);
            error_log('decoded response error info: '.print_R($response_info, true));
        }
        else
        {
            error_log('decoded response '.print_R($decodedResponse, true));    
        }

        curl_close($curl);

        return $decodedResponse;
    }
}
?>
