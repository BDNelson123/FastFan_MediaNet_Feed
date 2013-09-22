<?
function get_album_image($media_net_id)
{
    // this ensures the id is padded to 9 chars, the implied max in the media net images url
    $padded_media_net_id = str_pad($media_net_id, 9, '0', STR_PAD_LEFT);                
    
    // this inserts backslaches and returns the final url.
    $url_pieces = chunk_split($padded_media_net_id, 3, '/');    
    
    return 'http://images.mndigital.com/albums/'.$url_pieces.'s.jpeg';
}

function get_artist_image_small($media_net_id)
{
    // this ensures the id is padded to 9 chars, the implied max in the media net images url
    $padded_media_net_id = str_pad($media_net_id, 9, '0', STR_PAD_LEFT);                
    
    // this inserts backslaches and returns the final url.
    $url_pieces = chunk_split($padded_media_net_id, 3, '/');    
    
    return 'http://images.mndigital.com/artists/'.$url_pieces.'d.jpeg';
}
?>