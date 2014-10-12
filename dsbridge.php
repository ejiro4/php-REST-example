<?php
    //phpinfo();
	//var_dump($_SERVER);
	session_start();
	
	
	class curlBridge{
		protected $_url = "https://api.test.sabre.com/";
		//Go to https://developer.sabre.com/docs/read/rest_basics/authentication
		//to Read more on building your APP Key.
		protected $_dsAppKey = "your APP key here";
		protected $_lastToken = null;
		protected $_expireAt = null;
		protected $_lastInfo = null;
		protected $_debugMode = false;
		protected $_numretries = 0;
		
		private function checkExpDate(){
			$retVal = false;
			$dtToken = strtotime($_SESSION['expireAt']);
			$dtNow = time();
			$subTime = $dtToken - $dtNow;
			
			if($this->_debugMode){
				var_dump($subTime);
				var_dump($_SESSION);
			}
			if($subTime>0){
				$retVal = true;
			}else{
				$retVal = false;
			}
			
			
			return $retVal;
		}
		
		private function getAuthToken()
		{
			if(isset($_SESSION['lastToken']) and $this->checkExpDate()){
				$this->_lastToken = $_SESSION['lastToken'];
			}else{
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__)."/cacert.pem");
				curl_setopt($ch, CURLOPT_URL, $this->_url . 'v1/auth/token');
				curl_setopt($ch, CURLOPT_POST, true);
				//curl_setopt($ch, CURLOPT_HEADER, true);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded;charset=UTF-8','Authorization:Basic ' . $this->_dsAppKey));
				curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				$retVal = curl_exec($ch);
				
				//var_dump(curl_error($ch));
				
				curl_close($ch);

				//parse token result
				$js = json_decode($retVal,true);
				//var_dump($js);
				//echo 'token'. $js['access_token'];
			
				
				$now = time();//date("Y-m-d h:i:sa");
				$expIn = $js['expires_in'];
			
				$nDt = date("Y-m-d h:i:sa",$now);
				//var_dump($nDt);
				//calc expire time
				$expTime = date("Y-m-d h:i:sa",$now+$expIn);
				
				//date_add($expTime,"P".$expIn."s");
				//var_dump($expTime);
				$this->_lastToken = $js['access_token'];
				$_SESSION['lastToken'] = $js['access_token'];
				$_SESSION['expireAt'] = $expTime;
				$_SESSION['initAt'] = $nDt;
			}
		}
		
		public function sendRequest($payload='')
		{
			$retVal = 'null';
			if($this->_lastToken==null){
				//try to get authentication token
				$this->getAuthToken();	
			}
			if($this->_lastToken!=null){
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__)."/cacert.pem");
				curl_setopt($ch, CURLOPT_URL, $this->_url . $payload);
				//curl_setopt($ch, CURLOPT_HTTPGET, true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				//curl_setopt($ch, CURLOPT_HEADER, true);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded;charset=UTF-8','Authorization:Bearer ' . $this->_lastToken));
				$retVal = curl_exec($ch);
				
				$this->_lastInfo = curl_getInfo($ch);
				
				
				
				curl_close($ch);
				
				$st = $this->_lastInfo['http_code'];
				if($st==401 && $this->_numretries==0){
					//auto retry 1 time
					$this->_numretries++;
					return $this->sendRequest($payload);
				}
				
				
			}
			return $retVal;
		}
		
		public function sendResponse($status=200,$body='',$content_type='text/html'){
			$status_header = 'HTTP/1.1 ' . $status . ' ';
			// set the status
			header($status_header);
			// set the content type
			header('Content-type: ' . $content_type);
			header('Access-Control-Allow-Origin: *');

			// pages with body are easy
			if($body != '')
			{
				// send the body
				//
				//if($this->_debugMode){
					//var_dump($_SESSION);
				//}
				echo $body;
				exit;
			}else{
				echo "no content";
				exit;
			}
	
		}
		
		public function handleRequest($_SRV,$G,$P){
			$location = $_SRV['REQUEST_URI'];
			$qs = $_SRV['QUERY_STRING'];
			
			$response = null;
			$this->_numretries=0;
			if($qs){
				$location = substr($location,0,strpos($location,$qs)-1);
				if(isset($G['debug'])){
					$this->_debugMode = true;
					
				}
				
			}

			if(isset($_SESSION['appInUse'])==false){
				$this->sendResponse($status=401,$body='forbidden by access control');
				
			}
			
			if($location && strpos($location,"v1/",0)>=0){
				$location = substr($location, strpos($location,"v1/",0));
				//var_dump($location);
				//var_dump($qs);
				$response = $this->sendRequest($payload=$location.(strlen($qs)>0?'?'.$qs:''));
			}
			//var_dump($this->_lastInfo);
			$st = $this->_lastInfo['http_code'];
			$ct = $this->_lastInfo['content_type'];

			if($response!=null && $st==200){
				$this->sendResponse($status=$st,$body=$response,$content_type=$ct);
			}else{
				if($st==401){
					$this->_lastToken = null;
					unset($_SESSION['lastToken']);
					unset($_SESSION['expireAt']);
					unset($_SESSION['initAt']);

					$this->sendResponse($status=$st, $body="Internal Bridge Error");
				}else{
					$this->sendResponse($status=$st, $body=$response);
				}
			}
		}
		
		
	}

	$db = new curlBridge();
	$db->handleRequest($_SERVER,$_GET,$_POST);
	
	

?>