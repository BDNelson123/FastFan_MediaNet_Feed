<?php
function debug_var($var){
echo '<pre>';
print_r($var);
echo '</pre>';
}

//==========SANITIZING INPUT==============================//

function sanitize($input) {
      if (is_array($input)) {
	            foreach($input as $var=>$val) 
				{ 
				 $output[$var] = sanitize($val);          
				}      
		}
		else
		 {
		         if (get_magic_quotes_gpc()) 
				 {
				 $input = stripslashes($input);
				 }
				 $input  = cleanInput($input);
				 $output = mysql_real_escape_string($input);
		}
		return $output;
 } 


 function cleanInput($input) {      
 $search = array('@<script[^>]*?>.*?</script>@si',   // Strip out javascript      
 '@<[\/\!]*?[^<>]*?>@si',            // Strip out HTML tags      
 '@<style[^>]*?>.*?</style>@siU',    // Strip style tags properly      
 '@<![\s\S]*?--[ \t\n\r]*>@');         // Strip multi-line comments           
 $output = preg_replace($search, '', $input);
  return $output;
  } 
  

//========================================// 

function send_smtp_email($records){

global $SMTP_CONFIG;

	try{				
			require_once('../PHPMailer_v5.1/class.phpmailer.php');
			$mail = new PHPMailer(true);	
			
			$to = $records["to"];		
			$mail->AddAddress($to);

			$mail->Subject  = $records["subject"];
			$message = $records["message"];
								
			$mail->IsSMTP();                           // tell the class to use SMTP
			
			$mail->SMTPAuth   = $SMTP_CONFIG['auth'] ;                  // enable SMTP authentication
			$mail->Port       = $SMTP_CONFIG['port'];  
			$mail->Host       = $SMTP_CONFIG['host'] ; 
			$mail->Username   = $SMTP_CONFIG['smtp_user'];  // GMAIL username
			$mail->Password   = $SMTP_CONFIG['smtp_password'];   
			
			$mail->AddReplyTo($SMTP_CONFIG['reply_to']);
			$mail->From       = $SMTP_CONFIG['from_email'];
			$mail->AddCC      = $SMTP_CONFIG['ADMIN_EMAIL'];
			$mail->FromName   = $SMTP_CONFIG['from_name'];
			$mail->SMTPSecure=$SMTP_CONFIG['smtp_secure'];
			$mail->MsgHTML($message);
			$mail->IsHTML(true); // send as HTML				
			
			$mail->Send();

		}
		 catch(Error $e) {
			 return false;
	  }
	  
}// end function



//to convert into jason object
function encode($data) {
	return json_encode($data);
}//end of function


//TO handle json p request then show the response to user
function json_p($request_params,$response){
	
if(array_key_exists('callback', $request_params)){
		header('Content-Type: text/javascript; charset=utf8');
		header('Access-Control-Allow-Origin: http://192.168.3.143/fastfans/m');
		header('Access-Control-Max-Age: 3628800');
		header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
	
		$callback = $request_params['callback'];
		echo $callback.'('.$response.');';
	
		}else{
			// normal JSON string
			header('Content-Type: application/json; charset=utf8');
			echo $response;
		}//else	
	
}//end of function	
?>